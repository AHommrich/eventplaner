<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoReport extends Model
{
    protected $fillable = [
        'event_id',
        'photo_id',
        'reporter_guest_id',
        'reported_guest_id',
        'reason',
        'message',
        'status',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Model-side default so `create()` returns a hydrated status without a
     * refresh — the DB-level default only kicks in after INSERT, but the
     * response is built from the in-memory instance.
     */
    protected $attributes = [
        'status' => 'open',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'reporter_guest_id');
    }

    public function reportedGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'reported_guest_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
