<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrinkLog extends Model
{
    protected $fillable = ['guest_id', 'drink_id', 'size_id', 'amount_liter', 'base_points', 'final_points'];

    protected $casts = [
        'amount_liter' => 'float',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function drink()
    {
        return $this->belongsTo(Drink::class);
    }

    public function size()
    {
        return $this->belongsTo(DrinkCatalogSize::class, 'size_id');
    }
}
