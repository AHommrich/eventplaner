<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PhotoReportedMail;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoHide;
use App\Models\PhotoReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Guest-initiated photo report (App Store Review Guideline 1.2).
 *
 * The report is created, the reporter is added to `photo_hides` so the flagged
 * photo disappears from their gallery immediately, and the event owner gets a
 * mail — anonymised, so the reported uploader can never learn who flagged them.
 */
class PhotoReportController extends Controller
{
    public function store(Request $request, Photo $photo): JsonResponse
    {
        $guest = $request->user();

        // scope guard: photo must belong to the guest's event
        abort_if($photo->event_id !== $guest->event_id, 404);

        // only app-gallery photos are visible in the mobile app right now, so
        // reports on other albums are treated as unknown resources
        $album = PhotoAlbum::find($photo->album_id);
        abort_if($album && $album->slug !== PhotoAlbum::APP_GALLERY, 404);

        $data = $request->validate([
            'reason' => 'required|in:inappropriate_content,privacy,other',
            'message' => 'nullable|string|max:1000',
        ]);

        $report = PhotoReport::create([
            'event_id' => $photo->event_id,
            'photo_id' => $photo->id,
            'reporter_guest_id' => $guest->id,
            'reported_guest_id' => $photo->guest_id, // null for owner uploads
            'reason' => $data['reason'],
            'message' => $data['message'] ?? null,
        ]);

        // auto-hide: the reported photo disappears from the reporter's own
        // gallery without waiting for moderator resolution (App Store 1.2)
        PhotoHide::firstOrCreate([
            'viewer_guest_id' => $guest->id,
            'photo_id' => $photo->id,
        ]);

        // notify the event owner. Anonymous — the mail contains no reporter
        // identity so guests are not deterred from flagging content.
        if ($photo->event && $photo->event->owner) {
            Mail::to($photo->event->owner->email)
                ->send(new PhotoReportedMail($report, $photo->event));
        }

        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'auto_hidden' => true,
        ], 201);
    }
}
