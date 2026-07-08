<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoReport;
use App\Models\User;

/*
 * /requests hub — photo report card.
 *
 * Event owners see their own event's open reports; sysadmin sees them across
 * all events. Resolving is guarded to sysadmin OR the event owner.
 */

function makePhotoReport(Event $event): PhotoReport
{
    $album = PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => PhotoAlbum::APP_GALLERY,
        'name' => 'App-Galerie',
    ]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $uploader->id,
        'url' => 'https://example.test/'.uniqid().'.jpg',
        'r2_key' => 'photos/'.uniqid().'.jpg',
    ]);

    return PhotoReport::create([
        'event_id' => $event->id,
        'photo_id' => $photo->id,
        'reporter_guest_id' => $reporter->id,
        'reported_guest_id' => $uploader->id,
        'reason' => 'inappropriate_content',
        'message' => 'test',
    ]);
}

it('shows the event owner their own photo reports on /requests', function () {
    $owner = actingAsOwner();
    $event = $owner->ownedEvents->first();
    $report = makePhotoReport($event);

    $this->get('/requests')
        ->assertOk()
        ->assertInertia(function ($assert) use ($report) {
            $reports = $assert->toArray()['props']['photo_reports'];
            $this->assertCount(1, $reports);
            $this->assertSame($report->id, $reports[0]['id']);
            $this->assertSame('photo_report', $reports[0]['type']);
        });
});

it('does not show photo reports of another owners event', function () {
    $owner = actingAsOwner();
    $strangerEvent = Event::factory()->create();
    makePhotoReport($strangerEvent);

    $this->get('/requests')
        ->assertOk()
        ->assertInertia(function ($assert) {
            $reports = $assert->toArray()['props']['photo_reports'];
            $this->assertCount(0, $reports);
        });
});

it('shows sysadmin photo reports across all events with event_name', function () {
    actingAsAdmin();
    $event = Event::factory()->create(['name' => 'Sample Wedding']);
    makePhotoReport($event);

    $this->get('/requests')
        ->assertOk()
        ->assertInertia(function ($assert) {
            $reports = $assert->toArray()['props']['photo_reports'];
            $this->assertCount(1, $reports);
            $this->assertSame('Sample Wedding', $reports[0]['event_name']);
        });
});

it('exposes reported_uploader as null for owner uploads', function () {
    $owner = actingAsOwner();
    $event = $owner->ownedEvents->first();
    $album = PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => PhotoAlbum::APP_GALLERY,
        'name' => 'App-Galerie',
    ]);
    $reporter = Guest::factory()->create(['event_id' => $event->id]);
    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => null,
        'uploader_user_id' => $owner->id,
        'url' => 'https://example.test/owner.jpg',
        'r2_key' => 'photos/owner.jpg',
    ]);
    PhotoReport::create([
        'event_id' => $event->id,
        'photo_id' => $photo->id,
        'reporter_guest_id' => $reporter->id,
        'reported_guest_id' => null,
        'reason' => 'privacy',
    ]);

    $this->get('/requests')
        ->assertOk()
        ->assertInertia(function ($assert) {
            $reports = $assert->toArray()['props']['photo_reports'];
            $this->assertNull($reports[0]['reported_uploader']);
        });
});

it('lets the event owner resolve a report', function () {
    $owner = actingAsOwner();
    $event = $owner->ownedEvents->first();
    $report = makePhotoReport($event);

    $this->post(route('requests.photo-reports.resolve', $report->id))
        ->assertRedirect('/requests');

    $report->refresh();
    expect($report->status)->toBe('resolved')
        ->and($report->resolved_at)->not->toBeNull()
        ->and($report->resolved_by_user_id)->toBe($owner->id);
});

it('lets sysadmin resolve any report', function () {
    $admin = actingAsAdmin();
    $event = Event::factory()->create();
    $report = makePhotoReport($event);

    $this->post(route('requests.photo-reports.resolve', $report->id))
        ->assertRedirect('/requests');

    expect($report->fresh()->resolved_by_user_id)->toBe($admin->id);
});

it('forbids resolving a report from another owners event', function () {
    actingAsOwner();
    $strangerEvent = Event::factory()->create();
    $report = makePhotoReport($strangerEvent);

    $this->post(route('requests.photo-reports.resolve', $report->id))
        ->assertStatus(403);
});

it('rejects double-resolving with 422', function () {
    $owner = actingAsOwner();
    $event = $owner->ownedEvents->first();
    $report = makePhotoReport($event);
    $report->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by_user_id' => $owner->id]);

    $this->post(route('requests.photo-reports.resolve', $report->id))
        ->assertStatus(422);
});
