# Tag 3b — Alle API-Endpunkte (React-Native-Kontrakt)

**Aufwand**: ~4h
**Outcome**: 7 Feature-Test-Files decken alle 16 API-Endpoints + die 2 Guest-Middlewares ab. Der API-Vertrag zur React-Native-App ist explizit getestet.

## Übersicht der API-Endpoints

| Methode | Route | Controller | Middleware |
|---|---|---|---|
| GET | `/auth/qr/{token}` | `QrAuthController@login` | — |
| POST | `/auth/qr/{token}/select` | `QrAuthController@select` | — |
| DELETE | `/auth/logout` | inline | `auth:sanctum` |
| GET | `/photos` | `PhotoController@index` | `auth:sanctum` + `app_access` |
| POST | `/photos` | `PhotoController@store` | `auth:sanctum` + `app_access` |
| GET | `/event/info` | `EventInfoController@show` | `auth:sanctum` + `app_access` |
| GET | `/guest/me` | `GuestApiController@me` | `auth:sanctum` + `app_access` |
| POST | `/guest/rsvp` | `GuestApiController@rsvp` | `auth:sanctum` + `app_access` |
| POST | `/guest/{id}/rsvp` | `GuestApiController@rsvpForMember` | `auth:sanctum` + `app_access` |
| POST | `/guest/rsvp/revoke` | `GuestApiController@revoke` | `auth:sanctum` + `app_access` |
| GET | `/game/photo/status` | `PhotoGameController@status` | `auth:sanctum` + `app_access` |
| POST | `/game/photo/assign` | `PhotoGameController@assign` | `auth:sanctum` + `app_access` |
| POST | `/game/photo/submit` | `PhotoGameController@submit` | `auth:sanctum` + `app_access` |
| GET | `/drinks` | `DrinkLogController@index` | + `drinks_access` |
| POST | `/drinks/log` | `DrinkLogController@log` | + `drinks_access` |
| GET | `/drinks/stats` | `DrinkLogController@stats` | + `drinks_access` |

## Schritte

### 1. `tests/Feature/Api/QrAuthTest.php` (7 Cases)

```php
it('returns a token immediately for solo guests')
it('returns the member list without tokens for family guests')
it('returns a token only for the chosen family member after select')
it('returns 409 when the chosen family member is already logged in')
it('logs out and deletes the current token')
it('returns 404 for an invalid token')
it('returns 404 for an expired/deleted token')
```

**Wichtig**: Token-Aktivität via `Laravel\Sanctum\PersonalAccessToken::where(...)` prüfen, NICHT über `$guest->tokens()` auf eager-geladenem Objekt (MorphMany scoped dort nicht korrekt — siehe CLAUDE.md).

### 2. `tests/Feature/Api/EventInfoTest.php` (5 Cases)

```php
it('returns palette and resolved role colors as hex')
it('returns null cover overlay fields when no cover is set')
it('returns set cover overlay fields when cover is uploaded')
it('falls back to legacy venue_address when no structured address exists')
it('localizes drink names via Accept-Language header')
```

### 3. `tests/Feature/Api/GuestApiTest.php` (7 Cases)

```php
it('returns the current guest profile via /me')
it('accepts a yes-rsvp for the current guest')
it('accepts a no-rsvp for the current guest')
it('accepts a rsvp for a family member')
it('rejects rsvp for a guest from another family/group')
it('creates a revocation request when /revoke is called')
it('rejects revoke when no rsvp exists')
```

### 4. `tests/Feature/Api/PhotoApiTest.php` (6 Cases)

```php
it('lists all event photos for the guest')
it('uploads a jpeg photo to R2', function () {
    Storage::fake('r2');
    /* ... */
})
it('converts HEIC to JPEG before storing', function () {
    // Imagick muss verfügbar sein, sonst markTestSkipped
})
it('rejects upload without app_access middleware')
it('rejects invalid file formats')
it('returns 401 without bearer token')
```

`Storage::fake('r2')` für R2-Mock. HEIC-Konvertierung via Imagick — wenn nicht im Test-Container verfügbar, `markTestSkipped`.

### 5. `tests/Feature/Api/PhotoGameApiTest.php` (7 Cases)

```php
it('returns status with active game and no current assignment')
it('returns status with active game and current open assignment')
it('returns status when no active game exists')
it('assigns a new task from the pool')
it('uses an override task when added override is selected')
it('submits a photo for an open assignment')
it('allows re-submission for the same assignment')
```

### 6. `tests/Feature/Api/DrinkLogApiTest.php` (6 Cases)

```php
it('returns drinks grouped by catalog with selected sizes only')
it('logs a drink with size_id and amount_liter')
it('returns stats including total points using DrinkScoreService')
it('rejects access without drinks_access middleware')
it('localizes drink names via Accept-Language header')
it('returns 401 without bearer token')
```

### 7. `tests/Feature/Middleware/GuestAccessTest.php` (4 Cases)

```php
it('allows guest with app_access to access photos endpoint')
it('rejects guest without app_access from photos endpoint')
it('allows guest with drinks_access to access drinks endpoint')
it('rejects guest without drinks_access from drinks endpoint')
```

## Test-Helper

Falls noch nicht da, in `tests/Pest.php` oder `tests/TestCase.php` einen `actingAsGuest(Guest $guest)` Helper hinzufügen:

```php
function actingAsGuest(Guest $guest): TestCase
{
    $token = $guest->createToken('test')->plainTextToken;
    return test()->withHeader('Authorization', 'Bearer ' . $token);
}
```

Plus eine Factory für `Event`, `Guest`, `DrinkCatalog`, `EventPhotoGame` falls noch nicht vorhanden:
- `database/factories/EventFactory.php`
- `database/factories/GuestFactory.php`
- `database/factories/DrinkCatalogFactory.php`
- usw.

## Datei-Liste

| Datei | Aktion |
|---|---|
| `tests/Feature/Api/QrAuthTest.php` | neu (7) |
| `tests/Feature/Api/EventInfoTest.php` | neu (5) |
| `tests/Feature/Api/GuestApiTest.php` | neu (7) |
| `tests/Feature/Api/PhotoApiTest.php` | neu (6) |
| `tests/Feature/Api/PhotoGameApiTest.php` | neu (7) |
| `tests/Feature/Api/DrinkLogApiTest.php` | neu (6) |
| `tests/Feature/Middleware/GuestAccessTest.php` | neu (4) |
| `tests/Pest.php` | `actingAsGuest()` Helper |
| `database/factories/*` | neu wo noch nicht vorhanden |

→ **7 Test-Files, 42 Cases** (4 davon Middleware)

## Akzeptanzkriterien

- [ ] Alle 16 API-Endpoints haben mindestens 1 Happy-Path-Case
- [ ] Beide Guest-Middlewares (`app_access`, `drinks_access`) sind explizit auf Erfolg + Reject getestet
- [ ] QR-Login deckt die 409-Konflikt-Edge-Case (besetzter Familien-Token) ab
- [ ] R2 wird via `Storage::fake('r2')` gemockt — keine echten R2-Calls in Tests
- [ ] HEIC-Test ist robust (skipped wenn Imagick fehlt, statt zu failen)
- [ ] i18n-Tests für 2 Endpoints (event/info, drinks) decken Accept-Language ab
- [ ] `composer test` läuft alle 42 neuen Cases grün
- [ ] **Docblock-Sweep**: alle 6 `app/Http/Controllers/Api/*.php` haben Klassen-Docblock; `PhotoGameController::buildAssignPool()` hat zusätzlich Methoden-Docblock; getestete Middlewares (`EnsureGuestHasAppAccess`, `EnsureGuestHasDrinksAccess`) haben Klassen-Docblock — siehe [tag-2-architecture-docs.md](tag-2-architecture-docs.md) §2.2 + §2.3

## Commit-Vorschlag

```
test: Feature-Tests für alle API-Endpoints (React-Native-Kontrakt) + Guest-Middlewares
```
