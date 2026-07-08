<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoHide extends Model
{
    protected $fillable = [
        'viewer_guest_id',
        'photo_id',
    ];

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'viewer_guest_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
