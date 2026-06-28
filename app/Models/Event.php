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
        'venue_name', 'venue_address', 'venue_lat', 'venue_lng',
        'venue_street', 'venue_house_number', 'venue_postal_code',
        'venue_city', 'venue_state', 'venue_country',
        'venue_display_mode',
        'dresscode', 'schedule',
        'color_primary', 'color_secondary', 'color_tertiary', 'color_home_text',
        'color_home_shadow', 'home_shadow_opacity',
        'role_screen_bg', 'role_card_bg', 'role_card_text',
        'role_card_button', 'role_card_button_text',
        'role_tab_tint', 'role_border', 'role_fab', 'role_fab_icon',
        'font_heading',
        'drink_game_enabled',
        'drink_game_end_time',
        'photo_game_enabled',
        'projector_token',
        'projector_album_id',
        'projector_name_mode',
    ];

    protected $casts = [
        'drink_game_enabled'   => 'boolean',
        'drink_game_end_time'  => 'datetime',
        'photo_game_enabled'   => 'boolean',
        'venue_lat'            => 'float',
        'venue_lng'            => 'float',
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

    public function stylePresets()
    {
        return $this->hasMany(EventStylePreset::class);
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

    public function photoAlbums()
    {
        return $this->hasMany(PhotoAlbum::class)->orderBy('sort_order');
    }

    public function projectorAlbum()
    {
        return $this->belongsTo(PhotoAlbum::class, 'projector_album_id');
    }

    public function drinks()
    {
        return $this->hasMany(Drink::class);
    }

    public function photoGame()
    {
        return $this->hasOne(EventPhotoGame::class);
    }
}
