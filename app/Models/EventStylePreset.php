<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStylePreset extends Model
{
    protected $fillable = [
        'event_id', 'name',
        'color_primary', 'color_secondary', 'color_tertiary',
        'color_home_text', 'color_home_shadow', 'home_shadow_opacity',
        'role_screen_bg', 'role_card_bg', 'role_card_text',
        'role_card_button', 'role_card_button_text',
        'role_tab_tint', 'role_border', 'role_fab', 'role_fab_icon',
        'role_nav_bg',
        'font_heading',
        'design_preset',
        'role_config_version',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
