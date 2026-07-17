<?php

use App\Models\User;

/*
 * §8.1 split — /admin/users is purely global user administration now.
 * Per-event access + tiers live in /event/access (EventAccessController).
 */

it('renders /admin/users with only the global user list', function () {
    actingAsAdmin();

    test()->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('Admin/Users')
            ->has('users')
            ->missing('event_access')
            ->missing('events')
        );
});

it('no longer exposes per-event access routes on the admin group', function () {
    expect(collect(app('router')->getRoutes())->map->getName()->filter())
        ->not->toContain('admin.users.addToEvent')
        ->not->toContain('admin.users.removeFromEvent');
});

it('still lets an admin change a global role', function () {
    actingAsAdmin();
    $user = User::factory()->create(['role' => 'user']);

    test()->put(route('admin.users.update', $user), ['role' => 'admin'])->assertRedirect();

    expect($user->fresh()->role)->toBe('admin');
});
