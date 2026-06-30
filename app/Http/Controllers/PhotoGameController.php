<?php

namespace App\Http\Controllers;

use App\Models\EventPhotoGame;
use App\Models\EventTaskOverride;
use App\Models\PhotoGameAssignment;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Organizer view on the photo game (admin endpoints, Inertia).
 *
 *  - `index()` → game status, pool preview (see {@see \App\Services\PhotoGameTaskPool}),
 *                submissions, override management
 *  - `start()` / `end()` → set status to active / ended (lifecycle)
 *  - `updateCatalog()` → select the event-type catalog (wedding / birthday / ...)
 *  - `upsertOverride()` / `destroyOverride()` → maintain hidden / modified / added deltas
 *  - `destroyAssignment()` → delete submission incl. R2 photo (cleanup side effect)
 */
class PhotoGameController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $game = $event->photoGame;

        // global event-type catalogs (is_base=false, event_id=null) for the dropdown
        $catalogs = PhotoGameTaskCatalog::whereNull('event_id')
            ->where('is_base', false)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'event_type']);

        // build task pool: base + selected type catalog + overrides of the event
        $taskPool = $this->buildTaskPool($event->id, $game?->catalog_id);

        // overrides of the event for the UI (so the frontend knows the status of each task)
        $overrides = EventTaskOverride::where('event_id', $event->id)
            ->get(['id', 'task_id', 'action', 'custom_text']);

        $submissions = [];
        if ($game) {
            $submissions = $game->assignments()
                ->with(['guest:id,firstname,lastname', 'task:id,description', 'override:id,custom_text', 'photo:id,url'])
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'guest_name' => trim(($a->guest?->firstname ?? '').' '.($a->guest?->lastname ?? '')),
                    'task' => $a->override?->custom_text ?? $a->task?->description,
                    'photo_url' => $a->photo?->url,
                    'submitted_at' => $a->submitted_at,
                ]);
        }

        return Inertia::render('PhotoGame/Index', [
            'game' => $game ? [
                'id' => $game->id,
                'status' => $game->status,
                'catalog_id' => $game->catalog_id,
            ] : null,
            'catalogs' => $catalogs,
            'task_pool' => $taskPool,
            'overrides' => $overrides,
            'submissions' => $submissions,
        ]);
    }

    /** Builds the merged task pool for the admin view + API */
    public function buildTaskPool(int $eventId, ?int $typeCatalogId): array
    {
        // 1. base tasks (is_base=true)
        $baseCatalog = PhotoGameTaskCatalog::base()->first();
        $baseTasks = $baseCatalog
            ? $baseCatalog->tasks()->active()->get()->map(fn ($t) => [
                'id' => $t->id,
                'description' => $t->description,
                'translation_key' => $t->translation_key,
                'override_id' => null,
                'state' => 'normal',
                'original_text' => null,
            ])->all()
            : [];

        // 2. event-type tasks
        $typeTasks = [];
        if ($typeCatalogId) {
            $typeCatalog = PhotoGameTaskCatalog::find($typeCatalogId);
            $typeTasks = $typeCatalog
                ? $typeCatalog->tasks()->active()->get()->map(fn ($t) => [
                    'id' => $t->id,
                    'description' => $t->description,
                    'translation_key' => $t->translation_key,
                    'override_id' => null,
                    'state' => 'normal',
                    'original_text' => null,
                ])->all()
                : [];
        }

        $pool = array_merge($baseTasks, $typeTasks);

        // 3. apply overrides
        $overrides = EventTaskOverride::where('event_id', $eventId)->get();
        foreach ($overrides as $ov) {
            if ($ov->action === 'hidden') {
                $pool = array_map(fn ($t) => $t['id'] === $ov->task_id
                    ? array_merge($t, ['state' => 'hidden', 'override_id' => $ov->id])
                    : $t, $pool);
            } elseif ($ov->action === 'modified') {
                $pool = array_map(fn ($t) => $t['id'] === $ov->task_id
                    ? array_merge($t, [
                        'state' => 'modified',
                        'override_id' => $ov->id,
                        'original_text' => $t['description'],
                        'description' => $ov->custom_text,
                        // translation_key is preserved (for original display)
                    ])
                    : $t, $pool);
            } elseif ($ov->action === 'added') {
                $pool[] = [
                    'id' => null,
                    'description' => $ov->custom_text,
                    'translation_key' => null,
                    'override_id' => $ov->id,
                    'state' => 'added',
                    'original_text' => null,
                ];
            }
        }

        return array_values($pool);
    }

    public function start()
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $game = $event->photoGame ?? EventPhotoGame::create([
            'event_id' => $event->id,
            'status' => EventPhotoGame::STATUS_DRAFT,
        ]);

        $game->update(['status' => EventPhotoGame::STATUS_ACTIVE]);

        return redirect()->back()->with('success', 'Spiel gestartet.');
    }

    public function end()
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $game = $event->photoGame;
        abort_if(! $game, 404);

        $game->update(['status' => EventPhotoGame::STATUS_ENDED]);

        return redirect()->back()->with('success', 'Spiel beendet.');
    }

    public function updateCatalog(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'catalog_id' => 'nullable|exists:photo_game_task_catalogs,id',
        ]);

        $game = $event->photoGame ?? EventPhotoGame::create([
            'event_id' => $event->id,
            'status' => EventPhotoGame::STATUS_DRAFT,
        ]);

        $game->update(['catalog_id' => $data['catalog_id'] ?? null]);

        return redirect()->back()->with('success', 'Event-Typ aktualisiert.');
    }

    public function upsertOverride(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'task_id' => 'nullable|exists:photo_game_tasks,id',
            'action' => 'required|in:hidden,modified,added',
            'custom_text' => 'required_if:action,modified|required_if:action,added|nullable|string|max:500',
        ]);

        // 'added' has no task_id — no upsert via task_id, always create new
        if ($data['action'] === 'added') {
            $override = EventTaskOverride::create([
                'event_id' => $event->id,
                'task_id' => null,
                'action' => 'added',
                'custom_text' => $data['custom_text'],
            ]);
        } else {
            $override = EventTaskOverride::updateOrCreate(
                ['event_id' => $event->id, 'task_id' => $data['task_id']],
                ['action' => $data['action'], 'custom_text' => $data['custom_text'] ?? null]
            );
        }

        return redirect()->back()->with('success', 'Aufgabe angepasst.');
    }

    public function destroyOverride(EventTaskOverride $override)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);
        abort_if($override->event_id !== $event->id, 403);

        $override->delete();

        return redirect()->back()->with('success', 'Override zurückgesetzt.');
    }

    public function destroyAssignment(PhotoGameAssignment $assignment)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);
        abort_if($assignment->game->event_id !== $event->id, 403);

        if ($assignment->photo_id) {
            $photo = $assignment->photo;
            if ($photo) {
                Storage::disk('s3')->delete($photo->r2_key);
                $photo->delete();
            }
        }

        $assignment->delete();

        return redirect()->back()->with('success', 'Einreichung gelöscht.');
    }
}
