<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoGameTask extends Model
{
    protected $fillable = ['catalog_id', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function catalog()
    {
        return $this->belongsTo(PhotoGameTaskCatalog::class, 'catalog_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
