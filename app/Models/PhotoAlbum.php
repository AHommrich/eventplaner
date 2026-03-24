<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoAlbum extends Model
{
    const APP_GALLERY   = 'app_gallery';
    const PRESENTATION  = 'presentation';
    const PHOTO_GAME    = 'photo_game';

    protected $fillable = ['event_id', 'slug', 'name', 'sort_order'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class, 'album_id');
    }
}
