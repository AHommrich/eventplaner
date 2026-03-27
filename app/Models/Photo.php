<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['event_id', 'album_id', 'guest_id', 'uploaded_by', 'uploader_user_id', 'url', 'r2_key', 'description'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function album()
    {
        return $this->belongsTo(PhotoAlbum::class, 'album_id');
    }

    public function gameAssignment()
    {
        return $this->hasOne(PhotoGameAssignment::class, 'photo_id');
    }
}
