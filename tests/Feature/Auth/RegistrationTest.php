<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_accepted' => true,
        ]);

        $this->assertAuthenticated();
        // fresh users must verify email first, then onboarding (no event).
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_records_privacy_acceptance_timestamp()
    {
        $this->post('/register', [
            'name' => 'Privacy Aware',
            'email' => 'privacy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_accepted' => true,
        ]);

        $user = \App\Models\User::where('email', 'privacy@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->privacy_accepted_at);
    }

    public function test_register_route_is_rate_limited()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => "User $i",
                'email' => "u$i@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
                'privacy_accepted' => true,
            ]);
            \Illuminate\Support\Facades\Auth::logout();
        }

        $this->post('/register', [
            'name' => 'Overflow',
            'email' => 'overflow@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_accepted' => true,
        ])->assertStatus(429);
    }

    public function test_registration_is_blocked_without_privacy_consent()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'No Consent',
            'email' => 'noconsent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // privacy_accepted intentionally omitted
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('privacy_accepted');
        $this->assertDatabaseMissing('users', ['email' => 'noconsent@example.com']);
    }
}
