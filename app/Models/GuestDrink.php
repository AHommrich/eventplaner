<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestDrink extends Model
{
    use HasFactory;

    protected $fillable = ['guest_id', 'drink_type', 'thirst_level'];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
