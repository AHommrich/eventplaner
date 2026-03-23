<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRequest extends Model
{
    protected $fillable = ['user_id', 'event_name', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
