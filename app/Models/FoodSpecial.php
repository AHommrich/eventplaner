<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodSpecial extends Model
{
    protected $fillable = ['name'];

    public function guests()
    {
        return $this->belongsToMany(Guest::class, 'guest_food_special');
    }
}
