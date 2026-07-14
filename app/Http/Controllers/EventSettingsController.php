<?php

namespace App\Http\Controllers;

use App\Services\PhotoSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Editing of event master data, split across two Inertia pages:
 *
 *  - `show()` / `update()`             → slim "Event settings": core data (name, date,
 *                                        RSVP deadline, dresscode) + feature toggles
 *                                        (drink game, photo game) + projector token.
 *  - `design()` / `updateDesign()`     → "App → Design": cover (incl. HEIC→JPEG via
 *                                        Imagick), home text/shadow, colors (palette + 9
 *                                        roles), heading font, with style presets + preview.
 *  - `uploadCover()` / `deleteCover()` → standalone cover endpoints (object-storage cleanup).
 *
 * The venue/location lives on the schedule page now (the main venue is the first
 * station) — see {@see ScheduleController}.
 *
 * Color roles store palette keys (`primary` / `secondary` / `tertiary`), not hex —
 * resolution is handled by {@see \App\Services\ColorRoleResolver} for the API response.
 */
class EventSettingsController extends Controller
{
    public function show()
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        return Inertia::render('Event/Settings', [
            'event' => $event->only([
                'id', 'name', 'date', 'rsvp_deadline',
                'dresscode',
                'drink_game_enabled',
                'drink_game_end_time',
                'photo_game_enabled',
                'projector_token',
            ]),
        ]);
    }

    public function update(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
            'rsvp_deadline' => 'nullable|date',
            'dresscode' => 'nullable|string|max:1000',
            'drink_game_enabled' => 'boolean',
            'drink_game_end_time' => 'nullable|date',
            'photo_game_enabled' => 'boolean',
        ]);

        $event->update($data);

        return redirect()->route('event.settings')->with('success', 'Einstellungen gespeichert.');
    }

    public function design()
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        return Inertia::render('App/Design', [
            'event' => $event->only([
                'id', 'name', 'date', 'dresscode',
                'cover_image_url',
                // Read-only preview context (edited on other pages):
                'venue_display_mode',
                'venue_name', 'venue_street', 'venue_house_number',
                'venue_postal_code', 'venue_city', 'venue_country',
                // Design fields:
                'color_primary', 'color_secondary', 'color_tertiary', 'color_home_text',
                'color_home_shadow', 'home_shadow_opacity',
                'role_screen_bg', 'role_card_bg', 'role_card_text',
                'role_card_button', 'role_card_button_text',
                'role_tab_tint', 'role_border', 'role_fab', 'role_fab_icon',
                'role_nav_bg',
                'font_heading',
                'design_preset',
            ]),
            'stylePresets' => $event->stylePresets()
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name',
                    'color_primary', 'color_secondary', 'color_tertiary',
                    'color_home_text', 'color_home_shadow', 'home_shadow_opacity',
                    'role_screen_bg', 'role_card_bg', 'role_card_text',
                    'role_card_button', 'role_card_button_text',
                    'role_tab_tint', 'role_border', 'role_fab', 'role_fab_icon',
                    'role_nav_bg',
                    'font_heading',
                ]),
        ]);
    }

    public function updateDesign(Request $request, PhotoSanitizer $sanitizer)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'color_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_tertiary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_text' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_shadow' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'home_shadow_opacity' => 'nullable|integer|min:0|max:100',
            'role_screen_bg' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_bg' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_text' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button_text' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_tab_tint' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_border' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab_icon' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_nav_bg' => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'font_heading' => ['nullable', \Illuminate\Validation\Rule::in([
                'playfair', 'cormorant', 'cinzel', 'dancing',
                'great_vibes', 'raleway', 'lora', 'josefin',
            ])],
            'design_preset' => ['nullable', \Illuminate\Validation\Rule::in(['classic', 'soft-luxury'])],
            'cover' => 'nullable|file|mimes:jpeg,jpg,png,heic,heif|max:10240',
        ]);

        $event->update(collect($data)->except('cover')->all());

        if ($request->hasFile('cover')) {
            $this->storeCover($event, $request->file('cover'), $sanitizer);
        }

        return redirect()->route('app.design')->with('success', 'Einstellungen gespeichert.');
    }

    /**
     * Re-encode an uploaded cover to EXIF-free JPEG, replace any existing blob
     * in object storage and persist the new URL/key on the event.
     */
    private function storeCover($event, \Illuminate\Http\UploadedFile $file, PhotoSanitizer $sanitizer): void
    {
        // Re-encode to JPEG without EXIF — see PhotoSanitizer.
        $contents = $sanitizer->toJpegWithoutExif($file->getPathname());

        if ($event->cover_image_r2_key) {
            Storage::disk('s3')->delete($event->cover_image_r2_key);
        }

        $key = 'covers/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($key, $contents, 'public');

        $event->update([
            'cover_image_url' => Storage::disk('s3')->url($key),
            'cover_image_r2_key' => $key,
        ]);
    }

    public function uploadCover(Request $request, PhotoSanitizer $sanitizer)
    {
        $request->validate([
            'cover' => 'required|file|mimes:jpeg,jpg,png,heic,heif|max:10240',
        ]);

        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $this->storeCover($event, $request->file('cover'), $sanitizer);

        return response()->json(['cover_image_url' => $event->cover_image_url]);
    }

    /** WCAG contrast ratio between two hex colors (#rrggbb) */
    private function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = $this->relativeLuminance($hex1);
        $l2 = $this->relativeLuminance($hex2);
        [$lighter, $darker] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $linearize = fn (float $c) => $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linearize($r)
             + 0.7152 * $linearize($g)
             + 0.0722 * $linearize($b);
    }

    public function deleteCover(): \Illuminate\Http\JsonResponse
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        if ($event->cover_image_r2_key) {
            Storage::disk('s3')->delete($event->cover_image_r2_key);
        }

        $event->update(['cover_image_url' => null, 'cover_image_r2_key' => null]);

        return response()->json(['ok' => true]);
    }
}
