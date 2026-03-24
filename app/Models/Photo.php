<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['event_id', 'album_id', 'guest_id', 'uploaded_by', 'url', 'r2_key'];

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
}
