<?php

use App\Mail\PhotoReportedMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestContentHide;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoHide;
use App\Models\PhotoReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Photo moderation API tests (App Store Review Guideline 1.2).
 *
 * Cover reporting a visible photo, hiding a guest's content, hidden-list
 * retrieval, and that GET /api/photos filters both hide dimensions server
 * side. Owner uploads (photos.guest_id null) are structurally out of scope
 * for hide-content and stay visible regardless.
 */

function makeGalleryAlbum(Event $event): PhotoAlbum
{
    return PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => PhotoAlbum::APP_GALLERY,
        'name' => 'App-Galerie',
    ]);
}

function makeGuestPhoto(Event $event, PhotoAlbum $album, Guest $guest, string $url): Photo
{
    return Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => $url,
        'r2_key' => 'photos/'.uniqid().'.jpg',
    ]);
}

function makeOwnerPhoto(Event $event, PhotoAlbum $album, User $user, string $url): Photo
{
    return Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => null,
        'uploader_user_id' => $user->id,
        'url' => $url,
        'r2_key' => 'photos/'.uniqid().'.jpg',
    ]);
}

it('lets a guest report a visible photo from their event', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = makeGalleryAlbum($event);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $photo = makeGuestPhoto($event, $album, $uploader, 'https://example.test/p.jpg');

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", [
            'reason' => 'inappropriate_content',
            'message' => 'looks off to me',
        ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'status', 'auto_hidden'])
        ->assertJson([
            'status' => 'open',
            'auto_hidden' => true,
        ]);

    expect(PhotoReport::where('photo_id', $photo->id)->exists())->toBeTrue();

    $report = PhotoReport::first();
    expect($report->reporter_guest_id)->toBe($reporter->id)
        ->and($report->reported_guest_id)->toBe($uploader->id)
        ->and($report->event_id)->toBe($event->id);

    expect(PhotoHide::where('viewer_guest_id', $reporter->id)
        ->where('photo_id', $photo->id)
        ->exists())->toBeTrue();

    Mail::assertQueued(PhotoReportedMail::class, function (PhotoReportedMail $mail) use ($owner) {
        return $mail->hasTo($owner->email);
    });
});

it('still reports the photo when the owner notification fails', function () {
    // Simulate a mail/queue failure at dispatch time (e.g. a sync queue driver
    // hitting a transient transport error). The report + auto-hide are already
    // persisted, so the endpoint must still return 201 — never a 500 that makes
    // the guest think their report failed when it actually went through.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mail transport down'));

    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = makeGalleryAlbum($event);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $photo = makeGuestPhoto($event, $album, $uploader, 'https://example.test/p.jpg');

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'other'])
        ->assertCreated()
        ->assertJson(['auto_hidden' => true]);

    expect(PhotoReport::where('photo_id', $photo->id)->exists())->toBeTrue();
    expect(PhotoHide::where('viewer_guest_id', $reporter->id)
        ->where('photo_id', $photo->id)
        ->exists())->toBeTrue();
});

it('allows reports on owner uploads and stores reported_guest_id as null', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = makeGalleryAlbum($event);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $photo = makeOwnerPhoto($event, $album, $owner, 'https://example.test/o.jpg');

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'privacy'])
        ->assertCreated();

    $report = PhotoReport::first();
    expect($report->reported_guest_id)->toBeNull()
        ->and($report->reporter_guest_id)->toBe($reporter->id);
});

it('rejects a report for a photo from another event', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $album = makeGalleryAlbum($otherEvent);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $otherEvent->id]);
    $photo = makeGuestPhoto($otherEvent, $album, $uploader, 'https://example.test/x.jpg');

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'other'])
        ->assertNotFound();
});

it('validates required and enum fields on report', function () {
    $event = Event::factory()->create();
    $album = makeGalleryAlbum($event);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $photo = makeGuestPhoto($event, $album, $uploader, 'https://example.test/p.jpg');

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'nonsense'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", [
            'reason' => 'other',
            'message' => str_repeat('x', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('rate-limits report submissions per guest', function () {
    RateLimiter::clear('api');
    $event = Event::factory()->create();
    $album = makeGalleryAlbum($event);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $photo = makeGuestPhoto($event, $album, $uploader, 'https://example.test/rl.jpg');

    for ($i = 0; $i < 10; $i++) {
        actingAsGuest($reporter)
            ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'other'])
            ->assertCreated();
    }

    actingAsGuest($reporter)
        ->postJson("/api/photos/{$photo->id}/report", ['reason' => 'other'])
        ->assertStatus(429);
});

it('lets a guest hide another guest of the same event', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $target = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($viewer)
        ->postJson("/api/guests/{$target->id}/hide-content")
        ->assertCreated()
        ->assertJson(['hidden_guest_id' => $target->id]);

    expect(GuestContentHide::where('viewer_guest_id', $viewer->id)
        ->where('hidden_guest_id', $target->id)
        ->exists())->toBeTrue();
});

it('rejects hiding yourself', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($viewer)
        ->postJson("/api/guests/{$viewer->id}/hide-content")
        ->assertStatus(422);
});

it('rejects hiding a guest from another event', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $target = Guest::factory()->create(['event_id' => $otherEvent->id]);

    actingAsGuest($viewer)
        ->postJson("/api/guests/{$target->id}/hide-content")
        ->assertStatus(422);
});

it('is idempotent when the same guest is hidden twice', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $target = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($viewer)->postJson("/api/guests/{$target->id}/hide-content")->assertCreated();
    actingAsGuest($viewer)->postJson("/api/guests/{$target->id}/hide-content")->assertOk();

    expect(GuestContentHide::where('viewer_guest_id', $viewer->id)->count())->toBe(1);
});

it('deletes a hide idempotently', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $target = Guest::factory()->create(['event_id' => $event->id]);
    GuestContentHide::create([
        'event_id' => $event->id,
        'viewer_guest_id' => $viewer->id,
        'hidden_guest_id' => $target->id,
    ]);

    actingAsGuest($viewer)->deleteJson("/api/guests/{$target->id}/hide-content")->assertNoContent();
    actingAsGuest($viewer)->deleteJson("/api/guests/{$target->id}/hide-content")->assertNoContent();

    expect(GuestContentHide::where('viewer_guest_id', $viewer->id)->count())->toBe(0);
});

it('returns the current viewers hidden guests', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $a = Guest::factory()->create(['event_id' => $event->id, 'firstname' => 'Anna', 'lastname' => 'M.']);
    $b = Guest::factory()->create(['event_id' => $event->id, 'firstname' => 'Bob', 'lastname' => 'K.']);
    GuestContentHide::create(['event_id' => $event->id, 'viewer_guest_id' => $viewer->id, 'hidden_guest_id' => $a->id]);
    GuestContentHide::create(['event_id' => $event->id, 'viewer_guest_id' => $viewer->id, 'hidden_guest_id' => $b->id]);

    $response = actingAsGuest($viewer)->getJson('/api/guests/hidden-content')->assertOk();
    $ids = collect($response->json('hidden_guests'))->pluck('id')->all();

    expect($ids)->toContain($a->id, $b->id);
});

it('filters photos of a hidden guest out of GET /api/photos', function () {
    $event = Event::factory()->create();
    $album = makeGalleryAlbum($event);
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $bad = Guest::factory()->create(['event_id' => $event->id]);
    $good = Guest::factory()->create(['event_id' => $event->id]);
    $photoBad = makeGuestPhoto($event, $album, $bad, 'https://example.test/bad.jpg');
    $photoGood = makeGuestPhoto($event, $album, $good, 'https://example.test/good.jpg');
    GuestContentHide::create([
        'event_id' => $event->id,
        'viewer_guest_id' => $viewer->id,
        'hidden_guest_id' => $bad->id,
    ]);

    $response = actingAsGuest($viewer)->getJson('/api/photos')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($photoGood->id)
        ->and($ids)->not->toContain($photoBad->id);
});

it('still shows owner uploads even when they are on a hide list conceptually', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = makeGalleryAlbum($event);
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $other = Guest::factory()->create(['event_id' => $event->id]);
    $ownerPhoto = makeOwnerPhoto($event, $album, $owner, 'https://example.test/owner.jpg');
    GuestContentHide::create([
        'event_id' => $event->id,
        'viewer_guest_id' => $viewer->id,
        'hidden_guest_id' => $other->id, // hides a guest, but owner uploads must stay visible
    ]);

    $response = actingAsGuest($viewer)->getJson('/api/photos')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($ownerPhoto->id);
});

it('filters individually hidden photos out of GET /api/photos', function () {
    $event = Event::factory()->create();
    $album = makeGalleryAlbum($event);
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $shown = makeGuestPhoto($event, $album, $uploader, 'https://example.test/shown.jpg');
    $hidden = makeGuestPhoto($event, $album, $uploader, 'https://example.test/hidden.jpg');
    PhotoHide::create(['viewer_guest_id' => $viewer->id, 'photo_id' => $hidden->id]);

    $response = actingAsGuest($viewer)->getJson('/api/photos')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($shown->id)
        ->and($ids)->not->toContain($hidden->id);
});

it('exposes guest_id on GET /api/photos so the app can hide the block button for owner uploads', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = makeGalleryAlbum($event);
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    makeGuestPhoto($event, $album, $uploader, 'https://example.test/g.jpg');
    makeOwnerPhoto($event, $album, $owner, 'https://example.test/o.jpg');

    $response = actingAsGuest($viewer)->getJson('/api/photos')->assertOk();
    $shapes = collect($response->json('data'))->map(fn ($row) => array_key_exists('guest_id', $row));

    expect($shapes->every(fn ($ok) => $ok))->toBeTrue();
    expect(collect($response->json('data'))->pluck('guest_id')->contains(null))->toBeTrue();
});
