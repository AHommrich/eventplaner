<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'firstname',
        'lastname',
        'badge_id',
        'family_id',
        'beer',
        'wine',
    ];

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function drinks()
{
    return $this->hasMany(GuestDrink::class);
}
}
