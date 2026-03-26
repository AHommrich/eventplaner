<?php

namespace App\Http\Controllers;

use App\Models\PhotoGameTask;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoGameTaskController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        // Globale Kataloge (event_id = null) + eigene Kataloge
        $catalogs = PhotoGameTaskCatalog::with(['tasks' => fn($q) => $q->orderBy('sort_order')])
            ->where(fn($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->orderByRaw('event_id IS NULL DESC')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'is_global' => is_null($c->event_id),
                'is_active' => $c->is_active,
                'tasks'     => $c->tasks->map(fn($t) => [
                    'id'          => $t->id,
                    'description' => $t->description,
                    'sort_order'  => $t->sort_order,
                    'is_active'   => $t->is_active,
                ]),
            ]);

        return Inertia::render('PhotoGame/Tasks', [
            'catalogs' => $catalogs,
        ]);
    }

    public function storeCatalog(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $exists = PhotoGameTaskCatalog::where('event_id', $event->id)->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'Dieses Event hat bereits einen eigenen Katalog.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        PhotoGameTaskCatalog::create([
            'event_id' => $event->id,
            'name'     => $data['name'],
        ]);

        return redirect()->back()->with('success', 'Katalog erstellt.');
    }

    public function storeTask(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'catalog_id'  => 'required|exists:photo_game_task_catalogs,id',
            'description' => 'required|string|max:1000',
        ]);

        // Sicherstellen, dass der Katalog diesem Event gehört (nicht global)
        $catalog = PhotoGameTaskCatalog::findOrFail($data['catalog_id']);
        abort_if($catalog->event_id !== $event->id, 403);

        $maxOrder = $catalog->tasks()->max('sort_order') ?? -1;

        PhotoGameTask::create([
            'catalog_id'  => $data['catalog_id'],
            'description' => $data['description'],
            'sort_order'  => $maxOrder + 1,
        ]);

        return redirect()->back()->with('success', 'Aufgabe erstellt.');
    }

    public function updateTask(PhotoGameTask $task, Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);
        abort_if($task->catalog->event_id !== $event->id, 403);

        $data = $request->validate([
            'description' => 'sometimes|required|string|max:1000',
            'is_active'   => 'sometimes|boolean',
        ]);

        $task->update($data);

        return redirect()->back()->with('success', 'Aufgabe aktualisiert.');
    }

    public function destroyTask(PhotoGameTask $task)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);
        abort_if($task->catalog->event_id !== $event->id, 403);

        $task->delete();

        return redirect()->back()->with('success', 'Aufgabe gelöscht.');
    }
}
