<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestContentHide extends Model
{
    protected $fillable = [
        'event_id',
        'viewer_guest_id',
        'hidden_guest_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'viewer_guest_id');
    }

    public function hiddenGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'hidden_guest_id');
    }
}
