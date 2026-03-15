<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Guest extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'firstname',
        'lastname',
        'badge_id',
        'family_id',
        'beer',
        'wine',
        'likelihood',
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

public function foodSpecials()
{
    return $this->belongsToMany(FoodSpecial::class, 'guest_food_special');
}

public function invitationToken()
{
    return $this->hasOne(InvitationToken::class);
}

public function photos()
{
    return $this->hasMany(Photo::class);
}

}
