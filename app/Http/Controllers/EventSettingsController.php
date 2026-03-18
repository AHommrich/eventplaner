<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
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
                'cover_image_url', 'venue_name', 'venue_address',
                'dresscode', 'schedule',
                'color_primary', 'color_secondary', 'color_home_text',
                'color_accent', 'color_background', 'color_card',
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
            'venue_name'      => 'nullable|string|max:255',
            'venue_address'   => 'nullable|string|max:500',
            'dresscode'       => 'nullable|string|max:1000',
            'schedule'        => 'nullable|string|max:5000',
            'color_primary'    => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_home_text'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_accent'     => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_background' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_card'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cover' => 'nullable|file|mimes:jpeg,jpg,png,heic,heif|max:10240',
        ]);

        // WCAG AA Kontrast-Check: color_accent muss auf background und card ≥ 4.5:1 sein
        $accent     = $data['color_accent']     ?? $event->color_accent     ?? '#7c2d3e';
        $background = $data['color_background'] ?? $event->color_background ?? '#e8e3de';
        $card       = $data['color_card']       ?? $event->color_card       ?? '#ffffff';

        if ($this->contrastRatio($accent, $background) < 4.5) {
            throw ValidationException::withMessages([
                'color_accent' => 'color_accent hat zu wenig Kontrast auf color_background (WCAG AA: min. 4.5:1).',
            ]);
        }
        if ($this->contrastRatio($accent, $card) < 4.5) {
            throw ValidationException::withMessages([
                'color_accent' => 'color_accent hat zu wenig Kontrast auf color_card (WCAG AA: min. 4.5:1).',
            ]);
        }

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
