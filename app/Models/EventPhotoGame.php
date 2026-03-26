<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhotoGame extends Model
{
    const STATUS_DRAFT  = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_ENDED  = 'ended';

    protected $fillable = ['event_id', 'status', 'catalog_id'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function catalog()
    {
        return $this->belongsTo(PhotoGameTaskCatalog::class, 'catalog_id');
    }

    public function assignments()
    {
        return $this->hasMany(PhotoGameAssignment::class, 'game_id');
    }
}
