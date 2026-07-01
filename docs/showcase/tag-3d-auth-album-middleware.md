# Tag 3d — Auth-Flows + Album-Routing + Permission-Middlewares

**Aufwand**: ~2h
**Outcome**: 3 Test-Files schließen die letzten kritischen Lücken — Login-Pfade, Foto-Album-Routing und Sicherheits-Middlewares sind explizit getestet.

## Warum diese 3 Lücken

| Lücke | Warum kritisch |
|---|---|
| **Auth-Flows** (Google OAuth, Email-Verification) | Login-Pfade — fallen die aus, kommt keiner in die App. Aktuell ungetestet. |
| **Album-Routing + Standard-Alben-Anlage** | Photo-Flow ist Kern. Routing per Slug ist tricky und Bug-anfällig (Commit 9d2cadf hatte Bug bei fehlenden Alben). |
| **Permission-Middlewares** (`EnsureHasEventAccess`, `EnsureUserIsAdmin`) | Sicherheitsherzstück. Werden implizit über Feature-Tests gestreift, aber dedizierter Test macht den Sicherheitspunkt explizit. |

## Schritte

### 1. `tests/Feature/Auth/SocialLoginTest.php` (4 Cases)

Vorab prüfen, wie der Controller heißt:
```bash
find /Users/andrehommrich/Repos/eventplaner/app/Http/Controllers/Auth -name "Social*"
```

Tests:
```php
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

it('redirects to google oauth provider')
it('creates a new user after successful google callback with email_verified_at set')
it('logs in an existing user matched by email')
it('returns to login when google callback fails')
```

Socialite via `Socialite::shouldReceive('driver->stateless->user')->andReturn(...)` mocken.

**Wichtig** (CLAUDE.md): Google OAuth markiert Email automatisch als verifiziert — der Test muss bestätigen dass `email_verified_at` nach dem Callback NICHT null ist.

### 2. `tests/Feature/Photo/AlbumRoutingTest.php` (4 Cases)

```php
use App\Models\Event;
use App\Models\PhotoAlbum;

it('creates the three standard albums when a new event is created', function () {
    $event = Event::factory()->create();
    // Slugs: 'app_gallery', 'presentation', 'photo_game'
    expect($event->albums()->pluck('slug')->sort()->values()->toArray())
        ->toEqual(['app_gallery', 'photo_game', 'presentation']);
});

it('backfills missing standard albums on photo upload', function () {
    $event = Event::factory()->create();
    $event->albums()->where('slug', 'presentation')->delete();
    // Foto-Upload triggert Backfill
    // expect: presentation-Album existiert wieder nach Upload
});

it('routes photo upload to the correct album by slug', function () {
    // Upload mit album=presentation → Foto landet in presentation-Album
});

it('rejects photo upload with unknown album slug', function () {
    // Upload mit album=foobar → 422 oder 404
});
```

**Hinweis**: Wie genau der Backfill triggert, in der aktuellen `PhotoController@store` (web) oder `Api/PhotoController@store` schauen. Test passt sich an.

### 3. `tests/Feature/Middleware/PermissionMiddlewareTest.php` (4 Cases)

```php
use App\Models\Event;
use App\Models\User;

it('blocks user without event access from has_event routes', function () {
    $user = User::factory()->create();
    actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/no-event');
});

it('allows user with event access to has_event routes', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $user->events()->attach($event);
    actingAs($user)
        ->withSession(['active_event_id' => $event->id])
        ->get('/dashboard')
        ->assertOk();
});

it('blocks non-admin user from admin routes', function () {
    $user = User::factory()->create();  // role=null
    actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

it('allows admin user to access admin routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});
```

Genaue Route-Pfade in `routes/web.php` checken — Redirect-Ziel kann auch `/onboarding` sein statt `/no-event`.

## Datei-Liste

| Datei | Cases |
|---|---|
| `tests/Feature/Auth/SocialLoginTest.php` | 4 |
| `tests/Feature/Photo/AlbumRoutingTest.php` | 4 |
| `tests/Feature/Middleware/PermissionMiddlewareTest.php` | 4 |

→ **3 Test-Files, 12 Cases**

## Akzeptanzkriterien

- [ ] Google-OAuth-Callback ist mit Socialite-Mock getestet, inkl. `email_verified_at` Auto-Verifizierung
- [ ] Album-Routing-Test bestätigt: 3 Standard-Alben werden bei Event-Erstellung angelegt
- [ ] Album-Routing-Test bestätigt: fehlende Standard-Alben werden nachgeholt (Backfill)
- [ ] `EnsureHasEventAccess` ist auf Pass + Reject getestet
- [ ] `EnsureUserIsAdmin` ist auf Pass + Reject getestet
- [ ] `composer test` läuft alle 12 neuen Cases grün
- [ ] Coverage-Stand nach Tag 3d: Backend ~65% Line-Coverage
- [ ] **Docblock-Sweep**: `EnsureHasEventAccess` + `EnsureUserIsAdmin` haben Klassen-Docblock gemäß [tag-2-architecture-docs.md](tag-2-architecture-docs.md) §2.3 — damit ist der Docblock-Sweep aus Tag 2 vollständig

## Commit-Vorschlag

```
test: Auth-Flows (Google OAuth) + Album-Routing + Permission-Middlewares
```
