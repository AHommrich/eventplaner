<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_frame_options_nosniff_and_referrer_policy_are_always_set(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_locks_down_microphone_and_geolocation(): void
    {
        $response = $this->get('/');

        $policy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('camera=(self)', $policy);
    }

    public function test_content_security_policy_allows_osm_and_blocks_frames(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString('nominatim.openstreetmap.org', $csp);
    }

    public function test_content_security_policy_permits_heic_worker_and_sentry_ingest(): void
    {
        // Guard against silent regressions: heic2any needs `worker-src blob:`,
        // and Sentry-JS needs `connect-src *.ingest.de.sentry.io`. Dropping
        // either kills a feature (HEIC upload) or the frontend error monitor.
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("worker-src 'self' blob:", $csp);
        $this->assertStringContainsString('*.ingest.de.sentry.io', $csp);
    }

    public function test_hsts_is_absent_in_test_environment(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_present_in_production_like_env(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_is_omitted_in_local_env_so_vite_dev_server_works(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $this->get('/')->assertHeaderMissing('Content-Security-Policy');
    }
}
