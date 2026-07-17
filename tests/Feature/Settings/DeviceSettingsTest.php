<?php

use App\Models\DevicePairing;
use App\Models\Event;
use App\Models\User;

function pairedDevice(User $user, Event $event, string $label): DevicePairing
{
    $token = $user->createToken($label, ['management:event:'.$event->id])->accessToken;
    $token->forceFill(['expires_at' => now()->addDay(), 'last_used_at' => now()])->save();

    return DevicePairing::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'token_hash' => hash('sha256', str_repeat((string) (($user->id % 9) + 1), 64)),
        'device_label' => $label,
        'expires_at' => now(),
        'redeemed_at' => now(),
        'personal_access_token_id' => $token->id,
    ]);
}

it('shows members only their own event devices and administrators the event inventory', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $manager = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $ownerDevice = pairedDevice($owner, $event, 'Owner phone');
    $managerDevice = pairedDevice($manager, $event, 'Manager phone');

    $this->actingAs($manager)->withSession(['active_event_id' => $event->id])
        ->get(route('event.access'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Event/Access')
            ->where('can_manage_access', false)
            ->has('my_devices', 1)
            ->where('my_devices.0.id', $managerDevice->id)
            ->has('event_devices', 0)
            ->has('members', 0)
            ->where('owner', null));

    $this->actingAs($owner)->withSession(['active_event_id' => $event->id])
        ->get(route('event.access'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_manage_access', true)
            ->has('my_devices', 1)
            ->where('my_devices.0.id', $ownerDevice->id)
            ->has('event_devices', 2)
            ->has('event_devices.0', fn ($device) => $device
                ->hasAll(['user.name', 'user.email', 'last_used_at', 'expires_at', 'is_expired'])
                ->etc())
            ->where('event_devices', fn ($devices) => collect($devices)
                ->contains(fn ($device) => $device['user']['name'] === $manager->name)));
});

it('creates an event-bound pairing secret from the event access page', function () {
    $manager = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);

    $response = $this->actingAs($manager)->withSession(['active_event_id' => $event->id])
        ->postJson(route('event.access.pairings'), ['device_label' => 'New phone'])
        ->assertCreated();

    $plainToken = $response->json('pairing_token');
    expect(DevicePairing::first()->token_hash)->toBe(hash('sha256', $plainToken))
        ->and(DevicePairing::first()->event_id)->toBe($event->id)
        ->and(DevicePairing::first()->user_id)->toBe($manager->id);
});

it('lets members revoke their own device and administrators revoke any device in the event', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $manager = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);

    $ownDevice = pairedDevice($manager, $event, 'Manager phone');
    $this->actingAs($manager)->withSession(['active_event_id' => $event->id])
        ->deleteJson(route('event.access.devices.destroy', $ownDevice))
        ->assertNoContent();
    expect(DevicePairing::find($ownDevice->id))->toBeNull();

    $managedDevice = pairedDevice($manager, $event, 'Second phone');
    $this->actingAs($owner)->withSession(['active_event_id' => $event->id])
        ->deleteJson(route('event.access.devices.destroy', $managedDevice))
        ->assertNoContent();
    expect(DevicePairing::find($managedDevice->id))->toBeNull();
});

it('rejects foreign-event device revocation and removes the user settings device surface', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $foreignOwner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $foreignEvent = Event::factory()->for($foreignOwner, 'owner')->create();
    $foreignDevice = pairedDevice($foreignOwner, $foreignEvent, 'Foreign phone');

    $this->actingAs($owner)->withSession(['active_event_id' => $event->id])
        ->deleteJson(route('event.access.devices.destroy', $foreignDevice))
        ->assertForbidden();

    $this->get('/settings/devices')->assertNotFound();
});
