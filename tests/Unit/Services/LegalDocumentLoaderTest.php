<?php

use App\Services\LegalDocumentLoader;

it('loads the German privacy policy and returns the expected shape', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', 'de');

    expect($doc)->toHaveKeys(['locale', 'updated_at', 'sections'])
        ->and($doc['locale'])->toBe('de')
        ->and($doc['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and($doc['sections'])->toBeArray()->not->toBeEmpty();

    foreach ($doc['sections'] as $section) {
        expect($section)->toHaveKeys(['id', 'heading', 'body_markdown']);
    }
});

it('exposes all seven required section ids in the German version', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', 'de');
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
    $doc = (new LegalDocumentLoader())->load('privacy', 'en');
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

it('falls back to German when locale is null', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', null);

    expect($doc['locale'])->toBe('de');
});

it('falls back to German for unknown locales', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', 'fr');

    expect($doc['locale'])->toBe('de');
});

it('interpolates retention placeholders from config', function () {
    config()->set('retention.invitation_tokens_after_event_days', 42);
    config()->set('retention.declined_guests_after_event_days', 365);

    $doc = (new LegalDocumentLoader())->load('privacy', 'de');
    $speicherdauer = collect($doc['sections'])->firstWhere('id', 'speicherdauer');

    expect($speicherdauer['body_markdown'])
        ->toContain('42 Tage')
        ->and($speicherdauer['body_markdown'])->toContain('365 Tage');
});

it('leaves no placeholder tokens in the rendered markdown', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', 'de');

    foreach ($doc['sections'] as $section) {
        expect($section['body_markdown'])->not->toContain('{{retention.');
    }
});

it('preserves the {#id} anchor from the markdown source', function () {
    $doc = (new LegalDocumentLoader())->load('privacy', 'de');
    $ids = array_column($doc['sections'], 'id');

    expect($ids)->each->toMatch('/^[a-z0-9-]+$/');
});

it('throws when the document is missing', function () {
    (new LegalDocumentLoader())->load('does-not-exist', 'de');
})->throws(RuntimeException::class);
