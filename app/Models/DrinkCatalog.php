<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrinkCatalog extends Model
{
    protected $table = 'drink_catalog';

    protected $fillable = [
        'category',
        'type',
        'display_name',
        'display_name_en',
        'alcohol_percent',
        'is_alcoholic',
        'negative_points',
        'is_active',
        'sort_order',
        'search_terms',
    ];

    protected $casts = [
        'alcohol_percent' => 'float',
        'is_alcoholic'    => 'boolean',
        'is_active'       => 'boolean',
        'negative_points' => 'integer',
        'search_terms'    => 'array',
    ];

    public function sizes()
    {
        return $this->hasMany(DrinkCatalogSize::class, 'catalog_id')->orderBy('sort_order');
    }

    public function defaultSize(): ?DrinkCatalogSize
    {
        return $this->sizes->firstWhere('is_default', true) ?? $this->sizes->first();
    }

    public function eventDrinks()
    {
        return $this->hasMany(Drink::class, 'drink_catalog_id');
    }
}
