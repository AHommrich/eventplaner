<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyAssignedNote;
use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Per-event notes & todos (manage-level).
 *
 * Personal notes (`assignee_user_id` null) are private to their author. Assigned
 * todos are created by an owner/event_admin (`assignNote`) for an *active*
 * event_manager; the assignee may read + mark done but not edit/reassign/delete —
 * that stays with the assigning tier. Every mutation is cross-event guarded.
 */
class NoteController extends Controller
{
    public function index(Request $request)
    {
        $event = $this->eventFor($request);
        if (! $event) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $this->authorize('manage', $event);

        $canAssign = $user->can('assignNote', $event);

        $personal = $event->notes()
            ->whereNull('assignee_user_id')
            ->where('author_user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Note $n) => $this->serialize($n));

        $assignedToMe = $event->notes()
            ->where('assignee_user_id', $user->id)
            ->with('author')
            ->latest()
            ->get()
            ->map(fn (Note $n) => $this->serialize($n));

        $assignedTeam = collect();
        if ($canAssign) {
            $assignedTeam = $event->notes()
                ->whereNotNull('assignee_user_id')
                ->with('assignee')
                ->latest()
                ->get()
                ->map(fn (Note $n) => $this->serialize($n));
        }

        // Managers available as assignees (active event_managers of this event).
        $managers = $canAssign
            ? $event->users()
                ->wherePivot('role', 'event_manager')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            : collect();

        $payload = [
            'personal' => $personal,
            'assigned_to_me' => $assignedToMe,
            'assigned_team' => $assignedTeam,
            'can_assign' => $canAssign,
            'managers' => $managers,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Notes/Index', $payload);
    }

    public function store(Request $request)
    {
        $event = $this->eventFor($request);
        abort_unless($event !== null, 404);
        $user = $request->user();
        $this->authorize('manage', $event);

        $data = $request->validate([
            'type' => 'required|in:note,todo',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'assignee_user_id' => 'nullable|integer',
        ]);

        $assigneeId = $data['assignee_user_id'] ?? null;
        if ($assigneeId !== null) {
            abort_unless($user->can('assignNote', $event), 403);
            $this->assertValidAssignee($event, $assigneeId);
        }

        $note = $event->notes()->create([
            'author_user_id' => $user->id,
            'author_name' => $user->name,
            'assignee_user_id' => $assigneeId,
            'type' => $assigneeId !== null ? 'todo' : $data['type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
        ]);

        if ($assigneeId !== null) {
            NotifyAssignedNote::dispatch($note->id, $assigneeId);
        }

        if ($request->expectsJson()) {
            return response()->json(['note' => $this->serialize($note)], 201);
        }

        return redirect()->back()->with('success', 'Notiz gespeichert.');
    }

    public function update(Request $request, Note $note)
    {
        $event = $this->eventFor($request);
        abort_if($note->event_id !== $event?->id, 403);
        $user = $request->user();
        $this->authorize('manage', $event);

        // Assignee-only path: mark an assigned todo done/undone. Nothing else —
        // any other field in the payload is a forbidden edit attempt (403).
        if (! $this->canEditNote($user, $event, $note)) {
            abort_unless($note->assignee_user_id === $user->id && $note->type === 'todo', 403);

            $extraFields = array_diff(array_keys($request->except(['_method', '_token'])), ['is_done']);
            abort_unless(empty($extraFields), 403);

            $done = $request->validate(['is_done' => 'required|boolean'])['is_done'];
            $note->update(['is_done' => $done, 'done_at' => $done ? now() : null]);

            if ($request->expectsJson()) {
                return response()->json(['note' => $this->serialize($note->fresh())]);
            }

            return redirect()->back()->with('success', 'Aktualisiert.');
        }

        $data = $request->validate([
            'type' => 'sometimes|required|in:note,todo',
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|nullable|string|max:5000',
            'is_done' => 'sometimes|boolean',
            'assignee_user_id' => 'sometimes|nullable|integer',
        ]);

        $previousAssigneeId = $note->assignee_user_id;

        // Reassignment goes through the same guard as creation.
        if (array_key_exists('assignee_user_id', $data) && $data['assignee_user_id'] !== $note->assignee_user_id) {
            if ($data['assignee_user_id'] !== null) {
                abort_unless($user->can('assignNote', $event), 403);
                $this->assertValidAssignee($event, $data['assignee_user_id']);
            }
            $note->assignee_user_id = $data['assignee_user_id'];
        }

        // Assigned entries are always completable todos, regardless of a
        // stale or malicious client sending type=note.
        $effectiveType = $note->assignee_user_id !== null ? 'todo' : ($data['type'] ?? $note->type);
        if (array_key_exists('is_done', $data)) {
            if ($effectiveType !== 'todo') {
                throw ValidationException::withMessages(['is_done' => 'Nur ToDos können abgehakt werden.']);
            }
            $note->is_done = $data['is_done'];
            $note->done_at = $data['is_done'] ? now() : null;
        }

        if (isset($data['type']) || $note->assignee_user_id !== null) {
            $note->type = $effectiveType;
            // A note is never "done"; clear the flag when switching away from todo.
            if ($effectiveType !== 'todo') {
                $note->is_done = false;
                $note->done_at = null;
            }
        }
        if (array_key_exists('title', $data)) {
            $note->title = $data['title'];
        }
        if (array_key_exists('body', $data)) {
            $note->body = $data['body'];
        }

        $note->save();

        if ($note->assignee_user_id !== null && $note->assignee_user_id !== $previousAssigneeId) {
            NotifyAssignedNote::dispatch($note->id, $note->assignee_user_id);
        }

        if ($request->expectsJson()) {
            return response()->json(['note' => $this->serialize($note)]);
        }

        return redirect()->back()->with('success', 'Notiz aktualisiert.');
    }

    public function destroy(Request $request, Note $note)
    {
        $event = $this->eventFor($request);
        abort_if($note->event_id !== $event?->id, 403);
        $user = $request->user();
        $this->authorize('manage', $event);
        abort_unless($this->canEditNote($user, $event, $note), 403);

        $note->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->back()->with('success', 'Notiz gelöscht.');
    }

    /** Resolve the API header event when present, otherwise the web session event. */
    private function eventFor(Request $request): ?Event
    {
        return $request->attributes->get('management_event') ?? $this->activeEvent();
    }

    /**
     * Full edit rights: the author of a personal note, or any assign-tier user
     * (event_admin/owner/superadmin) for an assigned note.
     */
    private function canEditNote(User $user, Event $event, Note $note): bool
    {
        if ($note->isAssigned()) {
            return $user->can('assignNote', $event);
        }

        return $note->author_user_id === $user->id;
    }

    /** The assignee must be an active event_manager of this event. */
    private function assertValidAssignee(Event $event, int $assigneeId): void
    {
        $isManager = $event->users()
            ->where('users.id', $assigneeId)
            ->wherePivot('role', 'event_manager')
            ->exists();

        if (! $isManager) {
            throw ValidationException::withMessages([
                'assignee_user_id' => 'Aufgaben können nur aktiven Event-Managern dieses Events zugewiesen werden.',
            ]);
        }
    }

    private function serialize(Note $note): array
    {
        return [
            'id' => $note->id,
            'type' => $note->type,
            'title' => $note->title,
            'body' => $note->body,
            'is_done' => $note->is_done,
            'done_at' => $note->done_at?->toIso8601String(),
            'author_name' => $note->author_name,
            'assignee_user_id' => $note->assignee_user_id,
            'assignee_name' => $note->relationLoaded('assignee') ? $note->assignee?->name : null,
            'created_at' => $note->created_at->toIso8601String(),
        ];
    }
}
