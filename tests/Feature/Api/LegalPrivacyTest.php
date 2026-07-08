<?php

use Illuminate\Support\Facades\RateLimiter;

$expectedSectionIds = [
    'verantwortlich',
    'datenerhebung',
    'rechtsgrundlage',
    'speicherdauer',
    'rechte',
    'drittanbieter',
    'kontakt',
];

it('returns the German privacy policy by default', function () use ($expectedSectionIds) {
    $response = $this->getJson('/api/legal/privacy');

    $response->assertOk()
        ->assertJsonPath('locale', 'de')
        ->assertJsonStructure([
            'locale',
            'updated_at',
            'sections' => [
                ['id', 'heading', 'body_markdown'],
            ],
        ]);

    $sections = $response->json('sections');
    $ids = array_column($sections, 'id');
    expect($ids)->toContain(...$expectedSectionIds);
});

it('returns the English privacy policy when locale=en', function () use ($expectedSectionIds) {
    $response = $this->getJson('/api/legal/privacy?locale=en');

    $response->assertOk()
        ->assertJsonPath('locale', 'en');

    $ids = array_column($response->json('sections'), 'id');
    expect($ids)->toContain(...$expectedSectionIds);

    $verantwortlich = collect($response->json('sections'))->firstWhere('id', 'verantwortlich');
    expect($verantwortlich['heading'])->toBe('Data Controller');
});

it('falls back to German for unknown locales', function () {
    $this->getJson('/api/legal/privacy?locale=fr')
        ->assertOk()
        ->assertJsonPath('locale', 'de');
});

it('falls back to German when locale is missing', function () {
    $this->getJson('/api/legal/privacy')
        ->assertOk()
        ->assertJsonPath('locale', 'de');
});

it('interpolates retention placeholders from config', function () {
    config()->set('retention.invitation_tokens_after_event_days', 42);
    config()->set('retention.declined_guests_after_event_days', 365);
    config()->set('retention.guest_erasure_grace_days', 14);

    $response = $this->getJson('/api/legal/privacy?locale=de');

    $speicherdauer = collect($response->json('sections'))->firstWhere('id', 'speicherdauer');

    expect($speicherdauer['body_markdown'])
        ->toContain('42 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('365 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('14 Tage');
});

it('returns updated_at as an ISO-8601 UTC string', function () {
    $updatedAt = $this->getJson('/api/legal/privacy')->json('updated_at');

    // ISO-8601 with Z suffix, e.g. 2026-07-03T00:00:00Z
    expect($updatedAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('is publicly reachable without authentication', function () {
    $this->getJson('/api/legal/privacy')->assertOk();
});

it('returns the German imprint document', function () {
    $response = $this->getJson('/api/legal/imprint?locale=de');

    $response->assertOk()
        ->assertJsonPath('locale', 'de')
        ->assertJsonStructure([
            'locale',
            'updated_at',
            'sections' => [
                ['id', 'heading', 'body_markdown'],
            ],
        ]);

    $sections = $response->json('sections');
    $ids = array_column($sections, 'id');
    expect($ids)->toContain('provider', 'contact', 'mstv');

    $provider = collect($sections)->firstWhere('id', 'provider');
    expect($provider['body_markdown'])
        ->toContain('André Hommrich')
        ->and($provider['body_markdown'])->toContain('Dernbacher Str. 26');
});

it('returns the English imprint document', function () {
    $response = $this->getJson('/api/legal/imprint?locale=en');

    $response->assertOk()
        ->assertJsonPath('locale', 'en');

    $provider = collect($response->json('sections'))->firstWhere('id', 'provider');
    expect($provider['heading'])->toBe('Provider')
        ->and($provider['body_markdown'])->toContain('Germany');
});

it('falls back to German for unknown imprint locales', function () {
    $this->getJson('/api/legal/imprint?locale=fr')
        ->assertOk()
        ->assertJsonPath('locale', 'de');
});

it('exposes imprint publicly without authentication', function () {
    $this->getJson('/api/legal/imprint')->assertOk();
});

it('rate-limits after 30 requests per minute', function () {
    RateLimiter::clear('api');

    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/legal/privacy')->assertOk();
    }

    $this->getJson('/api/legal/privacy')->assertStatus(429);
});
