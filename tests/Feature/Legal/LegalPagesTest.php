<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_imprint_page_is_public(): void
    {
        $this->get('/impressum')->assertOk();
    }

    public function test_privacy_policy_page_is_public(): void
    {
        $this->get('/datenschutz')->assertOk();
    }

    public function test_imprint_route_is_named_legal_imprint(): void
    {
        $this->assertSame(url('/impressum'), route('legal.imprint'));
    }

    public function test_privacy_route_is_named_legal_privacy(): void
    {
        $this->assertSame(url('/datenschutz'), route('legal.privacy'));
    }

    public function test_privacy_page_receives_retention_windows_from_config(): void
    {
        config()->set('retention.invitation_tokens_after_event_days', 42);
        config()->set('retention.declined_guests_after_event_days', 365);

        $this->get('/datenschutz')
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert
                ->component('Legal/Privacy')
                ->where('retention.invitation_tokens_days', 42)
                ->where('retention.declined_guests_days', 365)
            );
    }
}
