<?php

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoReport;
use App\Models\User;

/*
 * P1 authorization backbone — per-event tier gating.
 *
 * Manager = manage tier (guests/photos/drinks/games/revocations). Event-Admin +
 * Owner = administer tier (deep settings, design, schedule, access, guest delete,
 * projector config, photo-report adjudication).
 */

/** Owner + their event. */
function ownerEvent(): array
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $event = Event::factory()->for($owner, 'owner')->create();

    return [$owner, $event];
}

/** Authenticate a pivot member of $event at $role with it as the active event. */
function actingAsMember(Event $event, string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $event->users()->attach($user->id, ['role' => $role]);
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);

    return $user;
}

// ---------------------------------------------------------------------------
// Route gating: administer-only GET pages
// ---------------------------------------------------------------------------

dataset('administer_pages', [
    'event settings' => ['event.settings'],
    'app design' => ['app.design'],
    'schedule' => ['schedule.index'],
    'event access' => ['event.access'],
]);

it('blocks a manager from administer pages', function (string $routeName) {
    [$owner, $event] = ownerEvent();
    actingAsMember($event, 'event_manager');

    test()->get(route($routeName))->assertForbidden();
})->with('administer_pages');

it('allows an event_admin on administer pages', function (string $routeName) {
    [$owner, $event] = ownerEvent();
    actingAsMember($event, 'event_admin');

    test()->get(route($routeName))->assertOk();
})->with('administer_pages');

// ---------------------------------------------------------------------------
// Route gating: manager keeps manage-level pages
// ---------------------------------------------------------------------------

it('allows a manager on manage-level pages', function () {
    [$owner, $event] = ownerEvent();
    actingAsMember($event, 'event_manager');

    test()->get(route('guests.index'))->assertOk();
    test()->get(route('photos'))->assertOk();
    test()->get(route('requests.index'))->assertOk();
});

// ---------------------------------------------------------------------------
// Route gating: per-route administer mutations
// ---------------------------------------------------------------------------

it('blocks a manager from deleting a guest but allows the owner', function () {
    [$owner, $event] = ownerEvent();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsMember($event, 'event_manager');
    test()->delete(route('guests.destroy', $guest))->assertForbidden();
    expect(Guest::find($guest->id))->not->toBeNull();

    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);
    test()->delete(route('guests.destroy', $guest))->assertRedirect();
    expect(Guest::find($guest->id))->toBeNull();
});

it('blocks a manager from projector config and schedule visibility', function () {
    [$owner, $event] = ownerEvent();
    $group = Group::create(['event_id' => $event->id, 'name' => 'Family']);
    actingAsMember($event, 'event_manager');

    test()->post(route('photos.projector-token.regenerate'))->assertForbidden();
    test()->patch(route('groups.schedule-visibility', $group))->assertForbidden();
});

// ---------------------------------------------------------------------------
// EventAccessController — owner acting
// ---------------------------------------------------------------------------

it('lets an owner invite a member as event_admin', function () {
    [$owner, $event] = ownerEvent();
    $invitee = User::factory()->create(['email' => 'admin@test.de']);
    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);

    test()->post(route('event.access.invite'), ['email' => 'admin@test.de', 'role' => 'event_admin'])
        ->assertRedirect();

    expect($event->fresh()->users()->where('users.id', $invitee->id)->value('event_user.role'))->toBe('event_admin');
});

it('lets an owner promote a member to co-owner', function () {
    [$owner, $event] = ownerEvent();
    $member = actingAsMember($event, 'event_manager');
    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);

    test()->patch(route('event.access.role', $member), ['role' => 'owner'])->assertRedirect();

    expect($event->fresh()->isOwnedBy($member))->toBeTrue();
});

// ---------------------------------------------------------------------------
// EventAccessController — event_admin acting (bounded powers)
// ---------------------------------------------------------------------------

it('forbids an event_admin from inviting another event_admin', function () {
    [$owner, $event] = ownerEvent();
    actingAsMember($event, 'event_admin');
    User::factory()->create(['email' => 'target@test.de']);

    test()->post(route('event.access.invite'), ['email' => 'target@test.de', 'role' => 'event_admin'])
        ->assertForbidden();
});

it('lets an event_admin invite a manager but not remove a co-owner', function () {
    [$owner, $event] = ownerEvent();
    actingAsMember($event, 'event_admin');
    User::factory()->create(['email' => 'mgr@test.de']);
    $coOwner = User::factory()->create();
    $event->users()->attach($coOwner->id, ['role' => 'owner']);

    test()->post(route('event.access.invite'), ['email' => 'mgr@test.de', 'role' => 'event_manager'])
        ->assertRedirect();

    test()->delete(route('event.access.remove', $coOwner))->assertForbidden();
    expect($event->fresh()->users()->where('users.id', $coOwner->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// RequestController — photo reports are administer-only
// ---------------------------------------------------------------------------

function makeReport(Event $event): PhotoReport
{
    $album = PhotoAlbum::firstOrCreate(
        ['event_id' => $event->id, 'slug' => PhotoAlbum::APP_GALLERY],
        ['name' => 'App-Galerie'],
    );
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
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
        'reporter_guest_id' => $uploader->id,
        'reported_guest_id' => $uploader->id,
        'reason' => 'inappropriate_content',
        'message' => 'test',
    ]);
}

it('hides photo reports from a manager on /requests', function () {
    [$owner, $event] = ownerEvent();
    makeReport($event);
    actingAsMember($event, 'event_manager');

    test()->get('/requests')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert->where('photo_reports', []));
});

it('shows photo reports to an event_admin on /requests', function () {
    [$owner, $event] = ownerEvent();
    makeReport($event);
    actingAsMember($event, 'event_admin');

    test()->get('/requests')
        ->assertOk()
        ->assertInertia(function ($assert) {
            expect($assert->toArray()['props']['photo_reports'])->toHaveCount(1);
        });
});

it('forbids a manager from resolving a photo report', function () {
    [$owner, $event] = ownerEvent();
    $report = makeReport($event);
    actingAsMember($event, 'event_manager');

    test()->post(route('requests.photo-reports.resolve', $report))->assertForbidden();
    expect($report->fresh()->status)->toBe('open');
});
