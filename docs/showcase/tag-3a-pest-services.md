# Tag 3a — Pest-Setup + Critical Services (Unit-Tests)

**Aufwand**: ~3h
**Outcome**: Pest läuft parallel, 3 Unit-Test-Files decken die Kern-Services ab (DrinkScore, PhotoGameTaskPool, ColorRoleResolver).

## Warum Pest statt PHPUnit-only

- **Out-of-the-box Parallel-Runner** (`pest --parallel`) — schnelles lokales Feedback ohne separates paratest-Setup.
- **Modernere Test-Syntax** (`it(...)->expect(...)`) — passt zur Lesbarkeit-Leitlinie.
- **Nicht-destruktiv**: Pest baut auf PHPUnit auf, die existing `tests/Feature/Auth/*` Starter-Kit-Tests laufen weiter.

## Schritte

### 1. Pest installieren

```bash
docker exec laravel-app composer require --dev pestphp/pest pestphp/pest-plugin-laravel
docker exec laravel-app ./vendor/bin/pest --init
```

Das legt `tests/Pest.php` an und registriert Base-TestCases.

### 1b. `tests/Pest.php` Setup-Details

Wichtig — sonst Test-DB-Chaos:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// RefreshDatabase global für alle Feature-Tests → frische DB pro Test, keine Migration-Hänger
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
```

`phpunit.xml` Check: `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` für schnelle Tests (Standard bei Laravel-Starter — prüfen ob noch drin).

**Sanctum-Hinweis**: Für API-Feature-Tests in Tag 3b reicht `Sanctum::actingAs($guest, ['*'])` oder unser eigener `actingAsGuest()`-Helper (kommt in Tag 3b). Keine globale Konfig nötig.

**`Mail::fake()` global setzen** — sonst feuern Tests echte Resend-Calls (Email-Verification, Event-Access-Invite, etc.):

```php
// tests/Pest.php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('r2');
})->in('Feature');
```

Spart in jedem Feature-Test die Wiederholung — und verhindert, dass ein vergessenes `Mail::fake()` eine echte Mail verschickt.

### 2. `composer.json` Test-Script umstellen

```json
"scripts": {
    "test": [
        "@php artisan config:clear --ansi",
        "./vendor/bin/pest --parallel"
    ],
    "test:filter": "./vendor/bin/pest --filter"
}
```

### 3. Existing Starter-Kit-Tests laufen lassen

```bash
docker exec laravel-app composer test
```

→ Alle bisherigen Tests (Auth, Settings, Dashboard, ExampleTest) müssen weiter grün sein.

### 4. (Optional) Refactor: Pool-Logik in Service extrahieren

Falls `buildAssignPool()` in `app/Http/Controllers/Api/PhotoGameController.php` privat und schwer testbar ist: in `app/Services/PhotoGameTaskPool.php` extrahieren.

Signatur:
```php
class PhotoGameTaskPool
{
    /**
     * @return Collection<int, array{id: ?int, override_id: ?int, description: string}>
     */
    public function build(Event $event): Collection
    { /* ... */ }
}
```

Controller delegiert dann nur noch. Refactor nur wenn die Testbarkeit ernsthaft besser wird — sonst Test direkt am Controller mit reflection-loser Public-Method.

### 5. (Optional) Refactor: Farb-Resolver extrahieren

Falls die Palette→Hex-Auflösung in `EventInfoController` inline ist: in `app/Services/ColorRoleResolver.php` extrahieren.

```php
class ColorRoleResolver
{
    /** @return array<string, string|null> Mapping der 9 Rollen → Hex, plus Cover-Overlay-Felder */
    public function resolve(Event $event): array { /* ... */ }
}
```

### 6. Drei Unit-Test-Files schreiben

#### `tests/Unit/Services/DrinkScoreServiceTest.php` (8 Cases)

```php
use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkLog;
use App\Services\DrinkScoreService;

it('scores a beer by volume and abv', function () { /* 0.5l × 5% × 10 = 25 */ });
it('scores wine higher than beer per volume', function () { /* 0.2l × 12% × 10 = 24 */ });
it('applies shot multiplier to spirits', function () { /* 0.04l × 40% × 10 × 2.0 = 32 */ });
it('does not apply shot multiplier to non-spirit alcoholic drinks', function () { /* Longdrink kein × 2 */ });
it('applies binge penalty after three consecutive alcoholic drinks', function () { /* 4. Drink → 50% */ });
it('does not apply binge penalty when interleaved with nonalcoholic drinks', function () { /* Wasser dazwischen unterbricht Streak */ });
it('returns flat negative points for water', function () { /* −5 */ });
it('returns flat negative points for softdrinks', function () { /* −3 */ });
```

#### `tests/Unit/Services/PhotoGameTaskPoolTest.php` (6 Cases)

```php
it('contains base tasks when no type catalog is selected', function () { /* nur Base */ });
it('contains base + type tasks when catalog_id is set', function () { /* Base + Hochzeit */ });
it('removes a task when hidden override exists', function () { /* hidden → weg */ });
it('replaces description when modified override exists', function () { /* description ersetzt */ });
it('adds custom tasks for added overrides', function () { /* added → drin */ });
it('combines all override types correctly', function () { /* Mix-Test */ });
```

#### `tests/Unit/Services/ColorRoleResolverTest.php` (4 Cases)

```php
it('resolves all nine roles to hex colors from palette', function () { /* alle 9 mapping korrekt */ });
it('falls back to primary when role key is invalid', function () { /* defensiv */ });
it('returns null cover overlay fields when no cover is set', function () { /* color_home_text/shadow null */ });
it('returns set cover overlay fields when cover exists', function () { /* alle drei gesetzt */ });
```

### 7. Lokal verifizieren

```bash
docker exec laravel-app composer test
```

→ Alle Tests grün (Starter-Kit + 18 neue Cases).

```bash
docker exec laravel-app ./vendor/bin/pest --filter=DrinkScore
```

→ Einzelne Service-Tests filterbar.

## Datei-Liste

| Datei | Aktion |
|---|---|
| `composer.json` | Pest-Deps + Test-Script |
| `composer.lock` | regen (composer install) |
| `tests/Pest.php` | neu (von `pest --init`) + `uses(RefreshDatabase::class)->in('Feature')` |
| `phpunit.xml` | Check: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| `app/Services/PhotoGameTaskPool.php` | (optional) neu, Logik aus Controller |
| `app/Services/ColorRoleResolver.php` | (optional) neu, Logik aus EventInfoController |
| `app/Http/Controllers/Api/PhotoGameController.php` | (wenn extrahiert) Delegation |
| `app/Http/Controllers/Api/EventInfoController.php` | (wenn extrahiert) Delegation |
| `tests/Unit/Services/DrinkScoreServiceTest.php` | neu (8 Cases) |
| `tests/Unit/Services/PhotoGameTaskPoolTest.php` | neu (6 Cases) |
| `tests/Unit/Services/ColorRoleResolverTest.php` | neu (4 Cases) |

## Akzeptanzkriterien

- [ ] `composer test` läuft Pest mit `--parallel`, alle Tests grün (Starter-Kit + 18 neue)
- [ ] `./vendor/bin/pest --filter=DrinkScore` filtert korrekt
- [ ] Jede der 3 Test-Files folgt Pest-Syntax (`it(...)`, kein PHPUnit-Style)
- [ ] Existing Starter-Kit-Tests sind NICHT umgeschrieben (bleiben PHPUnit, kein Aufwand)
- [ ] `tests/Pest.php` hat `uses(RefreshDatabase::class)->in('Feature')` (sonst zerlegt sich die Test-DB zwischen Tests)
- [ ] `phpunit.xml` nutzt `sqlite :memory:` für Tests (schneller als MariaDB-Hit pro Test)
- [ ] Wenn Refactor: Controller-Method ist jetzt schmaler, Service-Klasse hat Single Responsibility
- [ ] **Docblock-Sweep**: `app/Services/DrinkScoreService.php` (und ggf. `PhotoGameTaskPool` / `ColorRoleResolver` falls extrahiert) hat Klassen-Docblock gemäß [tag-2-architecture-docs.md](tag-2-architecture-docs.md) §2.1

## Commit-Vorschlag

```
test: Pest + Parallel-Runner + Unit-Tests für DrinkScore, Fotospiel-Pool, Farb-Resolver
```
