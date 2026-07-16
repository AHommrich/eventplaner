<?php

namespace App\Http\Controllers;

use App\Models\EventStylePreset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventStylePresetController extends Controller
{
    public function store(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate($this->rules());

        $event->stylePresets()->create([...$data, 'role_config_version' => 2]);

        return back()->with('success', 'Stil gespeichert.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'color_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_tertiary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_text' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_shadow' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'home_shadow_opacity' => 'nullable|integer|min:0|max:100',
            'role_screen_bg' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_bg' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_text' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button_text' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_tab_tint' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_border' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab_icon' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_nav_bg' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary'])],
            'font_heading' => ['nullable', Rule::in([
                'playfair', 'cormorant', 'cinzel', 'dancing',
                'great_vibes', 'raleway', 'lora', 'josefin',
            ])],
            'design_preset' => ['nullable', Rule::in(['classic', 'soft-luxury'])],
        ];
    }

    public function update(Request $request, EventStylePreset $preset)
    {
        $event = $this->activeEvent();
        abort_if(! $event || $preset->event_id !== $event->id, 403);

        $data = $request->validate($this->rules());
        $preset->update([...$data, 'role_config_version' => 2]);

        return back()->with('success', 'Stil aktualisiert.');
    }

    public function destroy(EventStylePreset $preset)
    {
        $event = $this->activeEvent();
        abort_if(! $event || $preset->event_id !== $event->id, 403);

        $preset->delete();

        return back()->with('success', 'Stil gelöscht.');
    }
}
