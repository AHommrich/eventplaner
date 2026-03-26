<?php

namespace App\Http\Controllers;

use App\Models\EventPhotoGame;
use App\Models\PhotoGameAssignment;
use App\Models\PhotoGameTask;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoGameController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $game = $event->photoGame;

        // Alle Kataloge: global + eigene für dieses Event
        $catalogs = PhotoGameTaskCatalog::active()
            ->where(fn($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->orderByRaw('event_id IS NULL DESC')
            ->orderBy('name')
            ->get(['id', 'name', 'event_id']);

        // Tasks des aktuell gewählten Katalogs
        $selectedCatalog = null;
        if ($game?->catalog_id) {
            $cat = PhotoGameTaskCatalog::with(['tasks' => fn($q) => $q->orderBy('sort_order')])
                ->find($game->catalog_id);
            if ($cat) {
                $selectedCatalog = [
                    'id'        => $cat->id,
                    'name'      => $cat->name,
                    'is_global' => is_null($cat->event_id),
                    'tasks'     => $cat->tasks->map(fn($t) => [
                        'id'          => $t->id,
                        'description' => $t->description,
                        'is_active'   => $t->is_active,
                        'sort_order'  => $t->sort_order,
                    ]),
                ];
            }
        }

        $submissions = [];
        if ($game) {
            $submissions = $game->assignments()
                ->with(['guest:id,firstname,lastname', 'task:id,description', 'photo:id,url'])
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->get()
                ->map(fn($a) => [
                    'id'          => $a->id,
                    'guest_name'  => trim(($a->guest->firstname ?? '') . ' ' . ($a->guest->lastname ?? '')),
                    'task'        => $a->task?->description,
                    'photo_url'   => $a->photo?->url,
                    'submitted_at'=> $a->submitted_at,
                ]);
        }

        return Inertia::render('PhotoGame/Index', [
            'game'             => $game ? [
                'id'         => $game->id,
                'status'     => $game->status,
                'catalog_id' => $game->catalog_id,
            ] : null,
            'catalogs'         => $catalogs,
            'selected_catalog' => $selectedCatalog,
            'submissions'      => $submissions,
        ]);
    }

    /**
     * Kopiert einen globalen Katalog als event-spezifische Vorlage.
     * Existiert schon ein eigener Katalog, werden dessen Tasks durch die
     * Tasks des gewählten globalen Katalogs ersetzt.
     */
    public function forkCatalog(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'catalog_id' => 'required|exists:photo_game_task_catalogs,id',
        ]);

        $source = PhotoGameTaskCatalog::with('tasks')->findOrFail($data['catalog_id']);
        abort_if(!is_null($source->event_id), 422, 'Nur globale Kataloge können kopiert werden.');

        // Vorhandenen eigenen Katalog wiederverwenden oder neu anlegen
        $own = PhotoGameTaskCatalog::where('event_id', $event->id)->first();

        if ($own) {
            // Tasks ersetzen (Bestehende löschen, neue aus Vorlage kopieren)
            $own->tasks()->delete();
            $own->update(['name' => $source->name . ' (angepasst)']);
            $target = $own;
            $message = 'Katalog wurde durch die neue Vorlage ersetzt — du kannst ihn jetzt anpassen.';
        } else {
            $target = PhotoGameTaskCatalog::create([
                'event_id'  => $event->id,
                'name'      => $source->name . ' (angepasst)',
                'is_active' => true,
            ]);
            $message = 'Vorlage kopiert — du kannst den Katalog jetzt anpassen.';
        }

        foreach ($source->tasks as $task) {
            PhotoGameTask::create([
                'catalog_id'  => $target->id,
                'description' => $task->description,
                'sort_order'  => $task->sort_order,
                'is_active'   => $task->is_active,
            ]);
        }

        // Spiel auf Katalog setzen
        $game = $event->photoGame ?? EventPhotoGame::create([
            'event_id'   => $event->id,
            'status'     => EventPhotoGame::STATUS_DRAFT,
            'catalog_id' => null,
        ]);

        $game->update(['catalog_id' => $target->id]);

        return redirect()->back()->with('success', $message);
    }

    public function start(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $game = $event->photoGame ?? EventPhotoGame::create([
            'event_id'   => $event->id,
            'status'     => EventPhotoGame::STATUS_DRAFT,
            'catalog_id' => null,
        ]);

        $game->update(['status' => EventPhotoGame::STATUS_ACTIVE]);

        return redirect()->back()->with('success', 'Spiel gestartet.');
    }

    public function end(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $game = $event->photoGame;
        abort_if(!$game, 404);

        $game->update(['status' => EventPhotoGame::STATUS_ENDED]);

        return redirect()->back()->with('success', 'Spiel beendet.');
    }

    public function updateCatalog(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'catalog_id' => 'nullable|exists:photo_game_task_catalogs,id',
        ]);

        $game = $event->photoGame ?? EventPhotoGame::create([
            'event_id'   => $event->id,
            'status'     => EventPhotoGame::STATUS_DRAFT,
            'catalog_id' => null,
        ]);

        $game->update(['catalog_id' => $data['catalog_id']]);

        return redirect()->back()->with('success', 'Katalog aktualisiert.');
    }

    public function destroyAssignment(PhotoGameAssignment $assignment)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);
        abort_if($assignment->game->event_id !== $event->id, 403);

        if ($assignment->photo_id) {
            $photo = $assignment->photo;
            if ($photo) {
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($photo->r2_key);
                $photo->delete();
            }
        }

        $assignment->delete();

        return redirect()->back()->with('success', 'Einreichung gelöscht.');
    }
}
