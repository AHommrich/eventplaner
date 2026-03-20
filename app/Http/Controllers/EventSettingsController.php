<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;

class EventSettingsController extends Controller
{
    public function show()
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        return Inertia::render('Event/Settings', [
            'event' => $event->only([
                'id', 'name', 'date', 'rsvp_deadline',
                'cover_image_url',
                'venue_name', 'venue_lat', 'venue_lng',
                'venue_street', 'venue_house_number',
                'venue_postal_code', 'venue_city', 'venue_state', 'venue_country',
                'dresscode', 'schedule',
                'color_primary', 'color_secondary', 'color_tertiary', 'color_home_text',
                'role_screen_bg', 'role_card_bg', 'role_card_text',
                'role_card_button', 'role_card_button_text',
                'role_tab_tint', 'role_border', 'role_fab', 'role_fab_icon',
                'font_heading',
            ]),
        ]);
    }

    public function update(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'date'            => 'nullable|date',
            'rsvp_deadline'   => 'nullable|date',
            'venue_name'         => 'nullable|string|max:255',
            'venue_lat'          => 'nullable|numeric|between:-90,90',
            'venue_lng'          => 'nullable|numeric|between:-180,180',
            'venue_street'       => 'nullable|string|max:255',
            'venue_house_number' => 'nullable|string|max:20',
            'venue_postal_code'  => 'nullable|string|max:20',
            'venue_city'         => 'nullable|string|max:255',
            'venue_state'        => 'nullable|string|max:255',
            'venue_country'      => 'nullable|string|max:100',
            'dresscode'       => 'nullable|string|max:1000',
            'schedule'        => 'nullable|string|max:5000',
            'color_primary'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_tertiary'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_text' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'role_screen_bg'       => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_bg'         => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_text'       => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button'     => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_card_button_text'=> ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_tab_tint'        => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_border'          => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab'             => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'role_fab_icon'        => ['nullable', \Illuminate\Validation\Rule::in(['primary', 'secondary', 'tertiary'])],
            'font_heading'         => ['nullable', \Illuminate\Validation\Rule::in([
                'playfair', 'cormorant', 'cinzel', 'dancing',
                'great_vibes', 'raleway', 'lora', 'josefin',
            ])],
            'cover' => 'nullable|file|mimes:jpeg,jpg,png,heic,heif|max:10240',
        ]);

        $event->update(collect($data)->except('cover')->all());

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $mime = strtolower($file->getMimeType() ?? '');

            if (in_array($mime, ['image/heic', 'image/heif'])) {
                $manager  = ImageManager::imagick();
                $image    = $manager->read($file->getPathname());
                $encoded  = $image->toJpeg(90);
                $contents = (string) $encoded;
            } else {
                $contents = file_get_contents($file->getPathname());
            }

            if ($event->cover_image_r2_key) {
                Storage::disk('s3')->delete($event->cover_image_r2_key);
            }

            $key = 'covers/' . Str::uuid() . '.jpg';
            Storage::disk('s3')->put($key, $contents, 'public');
            $url = Storage::disk('s3')->url($key);

            $event->update([
                'cover_image_url'    => $url,
                'cover_image_r2_key' => $key,
            ]);
        }

        return redirect()->route('event.settings')->with('success', 'Einstellungen gespeichert.');
    }

    public function uploadCover(Request $request)
    {
        $request->validate([
            'cover' => 'required|file|mimes:jpeg,jpg,png,heic,heif|max:10240',
        ]);

        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $file = $request->file('cover');
        $mime = strtolower($file->getMimeType() ?? '');

        if (in_array($mime, ['image/heic', 'image/heif'])) {
            $manager = ImageManager::imagick();
            $image   = $manager->read($file->getPathname());
            $encoded = $image->toJpeg(90);
            $contents = (string) $encoded;
        } else {
            $contents = file_get_contents($file->getPathname());
        }

        // Altes Cover löschen
        if ($event->cover_image_r2_key) {
            Storage::disk('s3')->delete($event->cover_image_r2_key);
        }

        $key = 'covers/' . Str::uuid() . '.jpg';
        Storage::disk('s3')->put($key, $contents, 'public');
        $url = Storage::disk('s3')->url($key);

        $event->update([
            'cover_image_url'   => $url,
            'cover_image_r2_key' => $key,
        ]);

        return response()->json(['cover_image_url' => $url]);
    }

    /** WCAG-Kontrastverhältnis zwischen zwei Hex-Farben (#rrggbb) */
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
        $r   = hexdec(substr($hex, 0, 2)) / 255;
        $g   = hexdec(substr($hex, 2, 2)) / 255;
        $b   = hexdec(substr($hex, 4, 2)) / 255;

        $linearize = fn(float $c) => $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linearize($r)
             + 0.7152 * $linearize($g)
             + 0.0722 * $linearize($b);
    }

    public function deleteCover(): \Illuminate\Http\JsonResponse
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        if ($event->cover_image_r2_key) {
            Storage::disk('s3')->delete($event->cover_image_r2_key);
        }

        $event->update(['cover_image_url' => null, 'cover_image_r2_key' => null]);

        return response()->json(['ok' => true]);
    }
}
