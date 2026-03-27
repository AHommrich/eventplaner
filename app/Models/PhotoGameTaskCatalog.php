<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoGameTaskCatalog extends Model
{
    protected $fillable = ['event_id', 'name', 'is_active', 'is_base', 'event_type'];

    protected $casts = ['is_active' => 'boolean', 'is_base' => 'boolean'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tasks()
    {
        return $this->hasMany(PhotoGameTask::class, 'catalog_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
