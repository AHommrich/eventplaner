# Tag 3c — Web-Controller (Kernfeatures Inertia)

**Aufwand**: ~5h
**Outcome**: 13 Test-Files decken alle Kernfeature-Web-Controller ab — Gäste, Drinks, Fotospiel, Event-Settings, Projektor, Invitations, Requests, Photos.

## Schritte

### Gäste-Verwaltung (Hauptpriorität)

#### `tests/Feature/Guest/GuestCrudTest.php` (7 Cases)

```php
it('lists guests for the active event')
it('creates a new guest with group and category')
it('updates an existing guest')
it('deletes a guest')
it('lets admin set rsvp for a guest')
it('toggles app_access for a guest')
it('toggles drinks_access for a guest')
```

Routes: `guests.*`, `guests.app-access`, `guests.drinks-access`, `guests.admin-rsvp`

#### `tests/Feature/Guest/GroupCategoryTest.php` (4 Cases)

```php
it('creates a new group')
it('deletes a group with its guests')
it('creates a new category')
it('rejects creating a group for another event')
```

Routes: `groups.*`, `categories.store`

#### `tests/Feature/Guest/RevocationTest.php` (3 Cases)

```php
it('approves a guest revocation request')
it('declines a guest revocation request')
it('resets drink logs for a guest')
```

Routes: `requests.revocations.*`, `guests.drink-logs.reset`

#### `tests/Feature/Guest/FoodSpecialTest.php` (2 Cases)

```php
it('creates a new food special with a name')
it('returns JSON response when called with wantsJson()')
```

Routes: `foodspecials.store` (POST `/foodspecials`)

`FoodSpecialController` ist sehr klein (1 Methode `store`), aber für Menüauswahl relevant — daher Vollständigkeit.

### QR / Invitations

#### `tests/Feature/Invitation/InvitationTokenTest.php` (5 Cases)

```php
it('shows the invitations index page with all groups and solo guests')
it('generates invitation tokens for all groups and solo guests')
it('regenerates a token for a specific group')
it('regenerates a token for a solo guest')
it('produces unique tokens with 32 chars')
```

Routes: `invitations` (Index), `invitations.generate`, `invitations.generate.group`, `invitations.generate.guest`

### Getränke + Trinkspiel

#### `tests/Feature/Drinks/DrinkCatalogTest.php` (5 Cases)

```php
it('lists the drink catalog with size selection state for the active event')
it('adds a single size for an event drink')
it('batch-toggles multiple sizes')
it('removes a drink size from an event')
it('only shows drinks for the active event')
```

Routes: `drinks.index`, `drinks.store`, `drinks.batch`, `drinks.destroy`

#### `tests/Feature/Drinks/DrinkGameTest.php` (4 Cases)

```php
it('shows the drink game page with leaderboard sorted by score')
it('updates drink_game_enabled flag')
it('updates drink_game_end_time')
it('hides game page from guests without drinks_access')  // wenn applicable
```

Routes: `drinks.game`, `drinks.game.update`

### Fotospiel

#### `tests/Feature/PhotoGame/PhotoGameAdminTest.php` (7 Cases)

```php
it('shows photo game admin page with current state')
it('starts a new game')
it('ends an active game')
it('updates the catalog (type) for the active game')
it('deletes an assignment and removes its photo from R2')
it('upserts a task override (hidden / modified / added)')
it('deletes a task override')
```

Routes: `photo-game.index`, `.start`, `.end`, `.catalog`, `.assignments.destroy`, `.overrides.upsert`, `.overrides.destroy`

`Storage::fake('r2')` für den Cleanup-Test.

### Event-Einstellungen + Layout

#### `tests/Feature/Event/EventSettingsTest.php` (7 Cases)

```php
it('shows the event settings page with current values')
it('updates basic event fields (name, date, dresscode, schedule)')
it('updates all color palette and role fields')
it('uploads a cover image to R2')
it('deletes the cover image')
it('converts HEIC cover upload to JPEG')  // wenn Imagick verfügbar
it('rejects update without event access')
```

Routes: `event.settings`, `event.settings.update`, `event.settings.cover`, `event.settings.cover.delete`

#### `tests/Feature/Event/StylePresetTest.php` (3 Cases)

```php
it('saves a new style preset from current event colors')
it('deletes a style preset')
it('only lists presets owned by the current user/event')
```

Routes: `event.style-presets.store`, `event.style-presets.destroy`

#### `tests/Feature/Event/EventAccessTest.php` (3 Cases)

```php
it('lists users with access to the event')
it('invites another user to the event')
it('removes a user from the event')
```

Routes: `event.access`, `event.access.invite`, `event.access.remove`

#### `tests/Feature/Event/EventManagementTest.php` (4 Cases)

```php
it('creates a new event via onboarding')
it('switches the active event in session')
it('lists own events on onboarding for users without an event')
it('creates an event-request for joining an existing event')
```

Routes: `events.store`, `events.switch`, `onboarding`, `events.request`

### Projektor / Beamer

#### `tests/Feature/Projector/ProjectorTest.php` (6 Cases)

```php
it('shows the projector page for a valid token')
it('returns photos for the configured album via /projector/{token}/photos')
it('returns 404 for an invalid projector token')
it('regenerates the projector token')
it('updates projector_name_mode (first / full / none)')
it('updates projector_album_id')
```

Routes: `projector.show`, `projector.photos`, `photos.projector-album`, `photos.projector-name-mode`, `photos.projector-token.regenerate`

### Photo-Web

#### `tests/Feature/Photo/PhotoWebTest.php` (4 Cases)

```php
it('lists photos grouped by album')
it('deletes a single photo and removes it from R2')
it('batch-deletes multiple photos')
it('updates the projector album selection')
```

Routes: `photos`, `photos.destroy`, `photos.destroy-batch`, `photos.projector-album`

### Requests

#### `tests/Feature/Request/EventRequestTest.php` (2 Cases)

```php
it('approves an event-join request and adds the user to the event')
it('declines an event-join request')
```

Routes: `requests.event-requests.approve`, `requests.event-requests.decline`

## Test-Helper

In `tests/Pest.php`:

```php
function actingAsOwner(?Event $event = null): User
{
    $user = User::factory()->create();
    $event ??= Event::factory()->for($user)->create();
    $user->events()->attach($event);
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);
    return $user;
}

function actingAsAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    test()->actingAs($admin);
    return $admin;
}
```

## Datei-Liste

| Datei | Cases |
|---|---|
| `tests/Feature/Guest/GuestCrudTest.php` | 7 |
| `tests/Feature/Guest/GroupCategoryTest.php` | 4 |
| `tests/Feature/Guest/RevocationTest.php` | 3 |
| `tests/Feature/Guest/FoodSpecialTest.php` | 2 |
| `tests/Feature/Invitation/InvitationTokenTest.php` | 5 |
| `tests/Feature/Drinks/DrinkCatalogTest.php` | 5 |
| `tests/Feature/Drinks/DrinkGameTest.php` | 4 |
| `tests/Feature/PhotoGame/PhotoGameAdminTest.php` | 7 |
| `tests/Feature/Event/EventSettingsTest.php` | 7 |
| `tests/Feature/Event/StylePresetTest.php` | 3 |
| `tests/Feature/Event/EventAccessTest.php` | 3 |
| `tests/Feature/Event/EventManagementTest.php` | 4 |
| `tests/Feature/Projector/ProjectorTest.php` | 6 |
| `tests/Feature/Photo/PhotoWebTest.php` | 4 |
| `tests/Feature/Request/EventRequestTest.php` | 2 |
| `tests/Pest.php` | Helper-Funktionen ergänzen |

→ **14 Test-Files, 66 Cases**

## Akzeptanzkriterien

- [ ] Alle Web-Routes für die 7 Kernfeatures sind getestet (Gäste, Groups/Categories, Invitations, Drinks, Drink-Game, Fotospiel, Event-Settings, Style-Presets, Event-Access, Projektor, Photos, Requests, FoodSpecials)
- [ ] `Storage::fake('r2')` für jeden Test mit R2-Interaktion (Photos, Cover, Assignment-Delete)
- [ ] HEIC-Cover-Test ist defensive (skipped wenn Imagick fehlt)
- [ ] `actingAsOwner()` / `actingAsAdmin()` Helper sind in `tests/Pest.php`
- [ ] `composer test` läuft alle 66 neuen Cases grün
- [ ] Coverage-Stand nach Tag 3c: Backend ~50% (DrinkScore/Pool/Resolver aus 3a + alle API aus 3b + Web-Kern aus 3c)
- [ ] **Docblock-Sweep**: alle getesteten Web-Controller (`Guest`, `Group`, `Drink`, `PhotoGame`, `Event`, `Projector`, `Invitation`, `InvitationToken`, `Photo`) haben Klassen-Docblock gemäß [tag-2-architecture-docs.md](tag-2-architecture-docs.md) §2.4

## Commit-Vorschlag

```
test: Feature-Tests für Web-Kernfeatures (Gäste, Drinks, Fotospiel, Event-Settings, Projektor)
```
