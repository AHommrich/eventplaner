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

    public function test_privacy_page_interpolates_retention_windows_from_config(): void
    {
        config()->set('retention.invitation_tokens_after_event_days', 42);
        config()->set('retention.declined_guests_after_event_days', 365);

        $this->get('/datenschutz')
            ->assertOk()
            ->assertInertia(function ($assert) {
                $assert->component('Legal/Privacy');

                $sections = $assert->toArray()['props']['sections'];
                $speicherdauer = collect($sections)->firstWhere('id', 'speicherdauer');

                $this->assertNotNull($speicherdauer, 'speicherdauer section missing');
                $this->assertStringContainsString('42 Tage', $speicherdauer['body_html']);
                $this->assertStringContainsString('365 Tage', $speicherdauer['body_html']);
            });
    }
}
