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
                'cover_image_url', 'venue_name', 'venue_address',
                'dresscode', 'schedule', 'color_primary', 'color_secondary',
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
            'color_primary'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $event->update($data);

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
}
