<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Guest extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'event_id',
        'group_id',
        'firstname',
        'lastname',
        'rsvp_status',
        'rsvp_set_by_guest_id',
        'rsvp_set_by_user_id',
        'rsvp_set_at',
        'app_access',
        'drinks_access',
    ];

    protected $casts = [
        'rsvp_set_at' => 'datetime',
        'app_access' => 'boolean',
        'drinks_access' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
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

    public function rsvpSetByGuest()
    {
        return $this->belongsTo(Guest::class, 'rsvp_set_by_guest_id');
    }

    public function rsvpSetByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'rsvp_set_by_user_id');
    }

    /** QR token: group token if in a group, otherwise own token */
    public function getQrToken(): ?InvitationToken
    {
        if ($this->group_id && $this->group?->invitationToken) {
            return $this->group->invitationToken;
        }

        return $this->invitationToken;
    }
}
