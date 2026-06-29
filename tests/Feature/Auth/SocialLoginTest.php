<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function mockSocialiteWith(string $email, string $name = 'Test User', string $googleId = 'google-123'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($googleId);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/oauth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redirects to google oauth provider', function () {
    mockSocialiteWith('whoever@example.com');

    $this->get(route('oauth.google.redirect'))->assertRedirect();
});

it('creates a new user with email_verified_at set after successful google callback', function () {
    mockSocialiteWith('newbie@gmail.com', 'Neu Mensch');

    $this->get(route('oauth.google.callback'));

    $user = User::where('email', 'newbie@gmail.com')->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->name)->toBe('Neu Mensch');
    $this->assertAuthenticatedAs($user);
});

it('logs in an existing user matched by email', function () {
    $existing = User::factory()->create(['email' => 'existing@gmail.com']);
    mockSocialiteWith('existing@gmail.com');

    $this->get(route('oauth.google.callback'));

    $this->assertAuthenticatedAs($existing->fresh());
    expect(User::where('email', 'existing@gmail.com')->count())->toBe(1);
});

it('marks an existing unverified user as verified after google callback', function () {
    $existing = User::factory()->unverified()->create(['email' => 'unverified@gmail.com']);
    mockSocialiteWith('unverified@gmail.com');

    $this->get(route('oauth.google.callback'));

    expect($existing->fresh()->email_verified_at)->not->toBeNull();
});
