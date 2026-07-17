<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A per-event note or todo.
 *
 * Personal note: `assignee_user_id` null (owned by the author). Assigned todo:
 * an owner/event_admin created it for an active event_manager — the assignee may
 * read + mark done, but not edit/reassign/delete (that stays with the assigning
 * tier). Soft-deleted; the retention pruner (`app:prune-notes`) hard-purges later.
 */
class Note extends Model
{
    /** @use HasFactory<\Database\Factories\NoteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'author_user_id',
        'author_name',
        'assignee_user_id',
        'type',
        'title',
        'body',
        'is_done',
        'done_at',
    ];

    protected $casts = [
        'is_done' => 'boolean',
        'done_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /** True for owner/event_admin-assigned todos (non-null assignee). */
    public function isAssigned(): bool
    {
        return $this->assignee_user_id !== null;
    }
}
