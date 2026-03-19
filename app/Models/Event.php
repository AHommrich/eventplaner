<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'date', 'slug', 'rsvp_deadline',
        'cover_image_url', 'cover_image_r2_key',
        'venue_name', 'venue_address',
        'dresscode', 'schedule',
        'color_primary', 'color_secondary', 'color_home_text',
        'color_accent', 'color_background', 'color_card',
        'color_card_text', 'color_card_button', 'color_card_button_text', 'color_tab_tint',
        'drink_game_enabled',
        'drink_game_end_time',
    ];

    protected $casts = [
        'drink_game_enabled'   => 'boolean',
        'drink_game_end_time'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name) . '-' . Str::random(6);
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function drinks()
    {
        return $this->hasMany(Drink::class);
    }
}
