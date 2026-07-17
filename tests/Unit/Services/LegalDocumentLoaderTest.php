<?php

use App\Services\LegalDocumentLoader;

it('loads the German privacy policy and returns the expected shape', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'de');

    expect($doc)->toHaveKeys(['locale', 'updated_at', 'sections'])
        ->and($doc['locale'])->toBe('de')
        ->and($doc['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and($doc['sections'])->toBeArray()->not->toBeEmpty();

    foreach ($doc['sections'] as $section) {
        expect($section)->toHaveKeys(['id', 'heading', 'body_markdown']);
    }
});

it('exposes all seven required section ids in the German version', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'de');
    $ids = array_column($doc['sections'], 'id');

    expect($ids)->toContain(
        'verantwortlich',
        'datenerhebung',
        'rechtsgrundlage',
        'speicherdauer',
        'rechte',
        'drittanbieter',
        'kontakt',
    );
});

it('exposes all seven required section ids in the English version', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'en');
    $ids = array_column($doc['sections'], 'id');

    expect($doc['locale'])->toBe('en');
    expect($ids)->toContain(
        'verantwortlich',
        'datenerhebung',
        'rechtsgrundlage',
        'speicherdauer',
        'rechte',
        'drittanbieter',
        'kontakt',
    );
});

it('loads the German imprint document', function () {
    $doc = (new LegalDocumentLoader)->load('imprint', 'de');
    $ids = array_column($doc['sections'], 'id');

    expect($doc['locale'])->toBe('de')
        ->and($doc['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and($ids)->toContain('provider', 'contact', 'mstv');

    $provider = collect($doc['sections'])->firstWhere('id', 'provider');
    expect($provider['body_markdown'])->toContain('André Hommrich');
});

it('loads the English imprint document', function () {
    $doc = (new LegalDocumentLoader)->load('imprint', 'en');
    $provider = collect($doc['sections'])->firstWhere('id', 'provider');

    expect($doc['locale'])->toBe('en')
        ->and($provider['heading'])->toBe('Provider')
        ->and($provider['body_markdown'])->toContain('Germany');
});

it('falls back to German when locale is null', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', null);

    expect($doc['locale'])->toBe('de');
});

it('falls back to German for unknown locales', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'fr');

    expect($doc['locale'])->toBe('de');
});

it('interpolates retention placeholders from config', function () {
    config()->set('retention.invitation_tokens_after_event_days', 42);
    config()->set('retention.declined_guests_after_event_days', 365);
    config()->set('retention.guest_erasure_grace_days', 14);
    config()->set('retention.photo_reports_after_event_days', 90);
    config()->set('sanctum.management_token_ttl_days', 60);
    config()->set('retention.expired_device_pairings_hours', 12);
    config()->set('retention.failed_jobs_days', 5);

    $doc = (new LegalDocumentLoader)->load('privacy', 'de');
    $speicherdauer = collect($doc['sections'])->firstWhere('id', 'speicherdauer');

    expect($speicherdauer['body_markdown'])
        ->toContain('42 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('365 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('14 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('90 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('60 Tagen')
        ->and($speicherdauer['body_markdown'])->toContain('12 Stunden')
        ->and($speicherdauer['body_markdown'])->toContain('5 Tagen');
});

it('leaves no placeholder tokens in the rendered markdown', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'de');

    foreach ($doc['sections'] as $section) {
        expect($section['body_markdown'])->not->toContain('{{retention.');
        expect($section['body_markdown'])->not->toContain('{{auth.');
    }
});

it('preserves the {#id} anchor from the markdown source', function () {
    $doc = (new LegalDocumentLoader)->load('privacy', 'de');
    $ids = array_column($doc['sections'], 'id');

    expect($ids)->each->toMatch('/^[a-z0-9-]+$/');
});

it('throws when the document is missing', function () {
    (new LegalDocumentLoader)->load('does-not-exist', 'de');
})->throws(RuntimeException::class);
