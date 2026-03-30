<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrinkCatalogSize extends Model
{
    protected $table = 'drink_catalog_sizes';

    protected $fillable = ['catalog_id', 'amount_liter', 'is_default', 'sort_order'];

    protected $casts = [
        'amount_liter' => 'float',
        'is_default'   => 'boolean',
    ];

    public function catalog()
    {
        return $this->belongsTo(DrinkCatalog::class, 'catalog_id');
    }
}
