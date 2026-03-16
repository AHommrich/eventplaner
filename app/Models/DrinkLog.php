<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrinkLog extends Model
{
    protected $fillable = ['guest_id', 'drink_id'];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function drink()
    {
        return $this->belongsTo(Drink::class);
    }
}
