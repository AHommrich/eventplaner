<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drink extends Model
{
    protected $fillable = ['event_id', 'drink_catalog_id', 'size_id'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function catalog()
    {
        return $this->belongsTo(DrinkCatalog::class, 'drink_catalog_id');
    }

    public function size()
    {
        return $this->belongsTo(DrinkCatalogSize::class, 'size_id');
    }

    public function logs()
    {
        return $this->hasMany(DrinkLog::class);
    }
}
