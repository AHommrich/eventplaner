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
        'amount_liter',
        'alcohol_percent',
        'is_alcoholic',
        'negative_points',
        'is_active',
        'sort_order',
        'search_terms',
    ];

    protected $casts = [
        'amount_liter'    => 'float',
        'alcohol_percent' => 'float',
        'is_alcoholic'    => 'boolean',
        'is_active'       => 'boolean',
        'negative_points' => 'integer',
        'search_terms'    => 'array',
    ];

    public function eventDrinks()
    {
        return $this->hasMany(Drink::class, 'drink_catalog_id');
    }
}
