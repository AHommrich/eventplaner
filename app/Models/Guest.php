<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Guest extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'event_id',
        'category_id',
        'group_id',
        'firstname',
        'lastname',
        'beer',
        'wine',
        'likelihood',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
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

    /** QR-Token: Gruppen-Token wenn in Gruppe, sonst eigener Token */
    public function getQrToken(): ?InvitationToken
    {
        if ($this->group_id && $this->group?->invitationToken) {
            return $this->group->invitationToken;
        }

        return $this->invitationToken;
    }
}
