<?php

use App\Http\Middleware\ResolveManagementEvent;
use App\Models\DevicePairing;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

function runManagementEventMiddleware(
    ?User $user,
    ?Event $event,
    string $tier = 'manage',
    ?array $abilities = null,
) {
    $request = Request::create('/api/management/testing', 'GET');

    if ($event) {
        $request->headers->set('X-Event-ID', (string) $event->id);
    }

    if ($user) {
        $abilities ??= $event ? ['management:event:'.$event->id] : ['management:event:0'];
        $token = $user->createToken('management-test', $abilities)->accessToken;
        if ($event) {
            DevicePairing::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'expires_at' => now()->addDays(90),
                'redeemed_at' => now(),
                'personal_access_token_id' => $token->id,
            ]);
        }
        $user->withAccessToken($token);
        $request->setUserResolver(fn () => $user);
    }

    return app(ResolveManagementEvent::class)->handle(
        $request,
        fn (Request $resolved) => response()->json([
            'event_id' => $resolved->attributes->get('management_event')?->id,
        ]),
        $tier,
    );
}

it('resolves a primary owners event without requiring a pivot row', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();

    $response = runManagementEventMiddleware($owner, $event);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['event_id'])->toBe($event->id);
});

it('resolves an event for a pivot manager and a foreign event for a superadmin', function () {
    $primary = User::factory()->create();
    $event = Event::factory()->for($primary, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $admin = User::factory()->create(['role' => 'admin']);
    $event->users()->attach($manager, ['role' => 'event_manager']);

    expect(runManagementEventMiddleware($manager, $event)->getStatusCode())->toBe(200)
        ->and(runManagementEventMiddleware($admin, $event)->getStatusCode())->toBe(200);
});

it('rejects a missing event header or an event without current membership', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $foreignUser = User::factory()->create(['is_approved' => true]);

    expect(runManagementEventMiddleware($owner, null)->getStatusCode())->toBe(403)
        ->and(runManagementEventMiddleware($foreignUser, $event)->getStatusCode())->toBe(403);
});

it('rechecks token ability email verification and approval', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();

    expect(runManagementEventMiddleware($owner, $event, abilities: ['role:guest'])->getStatusCode())->toBe(403);

    $owner->forceFill(['email_verified_at' => null])->save();
    expect(runManagementEventMiddleware($owner->fresh(), $event)->getStatusCode())->toBe(403);

    $owner->forceFill(['email_verified_at' => now(), 'is_approved' => false])->save();
    expect(runManagementEventMiddleware($owner->fresh(), $event)->getStatusCode())->toBe(403);
});

it('enforces the requested event policy tier', function () {
    $primary = User::factory()->create();
    $event = Event::factory()->for($primary, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $eventAdmin = User::factory()->create(['is_approved' => true]);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $event->users()->attach($eventAdmin, ['role' => 'event_admin']);

    expect(runManagementEventMiddleware($manager, $event, 'administer')->getStatusCode())->toBe(403)
        ->and(runManagementEventMiddleware($eventAdmin, $event, 'administer')->getStatusCode())->toBe(200);
});

it('rejects a valid bound token when the event header names another event', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $bound = Event::factory()->for($owner, 'owner')->create();
    $foreign = Event::factory()->for($owner, 'owner')->create();
    $request = Request::create('/api/management/testing', 'GET');
    $request->headers->set('X-Event-ID', (string) $foreign->id);
    $token = $owner->createToken('management-test', ['management:event:'.$bound->id])->accessToken;
    DevicePairing::create([
        'user_id' => $owner->id,
        'event_id' => $bound->id,
        'expires_at' => now()->addDays(90),
        'redeemed_at' => now(),
        'personal_access_token_id' => $token->id,
    ]);
    $owner->withAccessToken($token);
    $request->setUserResolver(fn () => $owner);

    $response = app(ResolveManagementEvent::class)->handle(
        $request,
        fn () => response()->noContent(),
    );

    expect($response->getStatusCode())->toBe(403);
});
