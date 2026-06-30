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
}
