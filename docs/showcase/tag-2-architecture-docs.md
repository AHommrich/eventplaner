# Tag 2 — ARCHITECTURE.md + Code-Lesbarkeit

**Aufwand**: ~3h
**Outcome**: Ein Reviewer kann in 20-30 Minuten Lesen die Architektur verstehen und findet sich im Code zurecht.

## Schritte

### 1. `docs/ARCHITECTURE.md` neu schreiben (~200-250 Zeilen, eine Datei, kein Doku-Wald)

Sektionen in dieser Reihenfolge:

**Sektion 1 — Domänenmodell**
- Event als Root: Event ↔ Guest ↔ Group ↔ Category
- Foto-Subsystem: Photo, PhotoAlbum (Slugs: `app_gallery`, `presentation`, `photo_game`)
- Drink-Subsystem: DrinkCatalog → DrinkCatalogSize → Drink (Event-Auswahl) → DrinkLog (Tracking)
- Fotospiel: EventPhotoGame → PhotoGameAssignment → PhotoGameTask (global) / EventTaskOverride
- Eine Mermaid-ER-Skizze ODER ASCII-Diagramm (Mermaid besser, GitHub rendert es)

**Sektion 2 — Auth-Schichten**
- User (Owner/Admin via `role`-Spalte) vs. Guest (Sanctum-Token via QR-Login, `tokens()`-Relation)
- Middleware-Aliase: `admin` = `EnsureUserIsAdmin`, `has_event` = `EnsureHasEventAccess`
- Guest-Middlewares: `EnsureGuestHasAppAccess`, `EnsureGuestHasDrinksAccess`
- Verweise auf `app/Http/Controllers/Api/QrAuthController.php` und `app/Http/Middleware/*`

**Sektion 3 — Aktives Event (Session-Pattern)**
- `activeEvent()` in `app/Http/Controllers/Controller.php` liest `session('active_event_id')`
- `HandleInertiaRequests` teilt `active_event` + `accessible_events` global an alle Vue-Seiten
- Event-Switcher in Sidebar (Dropdown wenn >1 Event)

**Sektion 4 — Fotospiel Delta-Modell**
- Warum globaler Katalog + Overrides statt pro-Event Tasks (Wartbarkeit: 1 Stelle für Standard-Aufgaben)
- 3 Override-Typen: `hidden` | `modified` | `added`
- `buildTaskPool()` in `app/Http/Controllers/Api/PhotoGameController.php` (oder `app/Services/PhotoGameTaskPool.php` nach Tag-3a-Extraction)
- Re-Submission erlaubt; S3-Cleanup beim Assignment-Delete

**Sektion 5 — Trinkspiel-Score** (Showcase-Sektion, ausführlich)
- Formel: `Liter × % × 10`, gerundet
- **Shot-Multiplier 2.0**: schnellere Absorption von Spirits (4cl pur ≠ 4cl im Longdrink)
- **Binge-Penalty 50%**: ab 3 alkoholische Getränke in Folge → halbe Basispunkte
- **Negative Punkte alkoholfrei**: Wasser −5, Softdrinks −3 (flat, nicht volume-based)
- Datei-Pointer: `app/Services/DrinkScoreService.php`

**Sektion 6 — Farbsystem (Palette + Rollen)**
- 3 Palette-Slots (`color_primary` / `color_secondary` / `color_tertiary`)
- 9 Rollen-Felder die Keys (`primary` | `secondary` | `tertiary`) speichern, nicht Hex
- Begründung: Konsistenz + Re-Skin in einem Schritt (ändere Palette → alle Rollen folgen)
- Cover-Overlay: `color_home_text` / `color_home_shadow` / `home_shadow_opacity` nur relevant wenn Cover gesetzt
- `EventInfoController` löst Rollen → Hex auf für die API-Response

**Sektion 7 — Projektor-Subsystem**
- `projector_token` pro Event (auto-generiert, regenerierbar)
- Public Route `/projector/{token}` ohne Auth — nur Token-Besitzer kann die Diashow zeigen
- Auto-Poll alle 10s für neue Fotos, Crossfade 5s
- Context-Label je Album-Slug:
  - `app_gallery` → Gastname (Modi: `first` / `full` / `none` via `projector_name_mode`)
  - `presentation` → Beschreibung
  - `photo_game` → Aufgabentext aus Assignment
- Datei-Pointer: `resources/js/pages/Projector/Show.vue`, `app/Http/Controllers/ProjectorController.php`

**Sektion 8 — i18n-Schichten**
- Frontend: vue-i18n v11, Locale-Files `resources/js/locales/de.json` + `en.json`, Sprache in `localStorage`
- Backend: Accept-Language-Header für API-Responses (Drinks-Namen, Fotospiel-Aufgaben)
- Datei-Pointer: `resources/js/plugins/i18n.ts`, `app/Http/Middleware/SetLocaleFromHeader.php` (falls so heißt)

**Sektion 9 — Drink-Catalog-Datenmodell** (Showcase: Refactor-Highlight)
- `drink_catalog`: 1 Zeile pro Typ (Pils, Weißbier, ...) — keine Sizes mehr
- `drink_catalog_sizes`: `(catalog_id, amount_liter, is_default)` — pro Typ N Größen
- `drinks`: `(event_id, drink_catalog_id, size_id)` — Event wählt einzelne Sizes
- `drink_logs`: hat `size_id` + `amount_liter` (denormalisiert für historische Werte)
- Warum: saubere Trennung Typ vs. Größe, ermöglicht Mengenrechner + Trinkspiel-Punktevergabe pro Größe

**Sektion 10 — Deploy-Pipeline**
- `develop → staging → production` (production heißt `production`, nicht `main`)
- `docker-compose.yml` ist branch-spezifisch (verschiedene Dockerfiles für dev/prod)
- Coolify deployt automatisch auf Push, Migrations laufen automatisch
- Domain-Beispiele weglassen (Showcase-Repo)

**Wichtig**: Nur relative Datei-Pointer, KEINE fixen Zeilennummern (verrotten zu schnell).

### 2. PHP-Doc-Blocks setzen (breiter Sweep, parallel zu Tag 3)

**Strategie**: Klassen-Docblocks (5–15 Zeilen) an jeder Datei, die in Tag 3 für Tests angefasst wird. Wer die Logik fürs Testen ohnehin durchgeht, kommentiert sie in derselben Session. Schwerpunkt **Why**, nicht What — keine Methoden-Inline-Kommentare, außer die Methode trägt eine nicht-offensichtliche Invariante (siehe Beispiele unten).

**Regel**: Ein Reviewer soll am Klassen-Docblock in 30 Sekunden verstehen, **was die Klasse leistet und welche Sonderfälle relevant sind**. Wenn dafür mehr als 15 Zeilen nötig sind → in `ARCHITECTURE.md` verlagern und im Docblock dorthin verweisen.

**Ausnahmen — KEIN Docblock**:
- Eloquent Models (Relations + `$casts` sprechen für sich)
- Form Requests (`rules()` ist self-evident)
- Vue-Komponenten (Frontend bleibt komplett kommentar-frei)
- Auth-Controller aus dem Starter-Kit (Framework-Boilerplate)

#### 2.1 Services (1 Datei)

**`app/Services/DrinkScoreService.php`** — Klassen-Docblock
```php
/**
 * Berechnet Punkte für getrackte Getränke.
 *
 * Formel alkoholisch:  round(amount_liter × alcohol_percent × 10)
 * Shot-Multiplier:     spirit-Kategorie × 2.0 (schnellere Absorption pur vs. gemixt)
 * Binge-Penalty:       ab 3 alkoholischen Drinks in Folge → 50% der Basispunkte
 * Alkoholfrei:         flat negative_points (Wasser −5, Softdrinks −3)
 *
 * Die Multiplier sind empirisch gewählt, nicht klinisch — Spaßspiel, kein Diagnostik-Tool.
 */
```

#### 2.2 API-Controller (6 Dateien — komplette React-Native-Schnittstelle)

| Datei | Schwerpunkt im Docblock |
|---|---|
| `app/Http/Controllers/Api/QrAuthController.php` | Solo vs. Familie, warum Tokens NICHT beim Scan vorab ausgestellt werden |
| `app/Http/Controllers/Api/EventInfoController.php` | Rollen-zu-Hex Auflösung, Adress-Composing aus Einzelfeldern + Legacy `venue_address`-Fallback |
| `app/Http/Controllers/Api/PhotoController.php` | HEIC→JPEG via Imagick, R2-Upload, Auth via Guest-Token, Cleanup beim Delete |
| `app/Http/Controllers/Api/PhotoGameController.php` | Pool-Aufbau (Base + Typ + Overrides) — Methoden-Docblock zusätzlich an `buildAssignPool()` |
| `app/Http/Controllers/Api/DrinkLogController.php` | Score-Delegation an `DrinkScoreService`, Snapshot von `amount_liter` beim Log |
| `app/Http/Controllers/Api/GuestApiController.php` | Was der Gast über sich selbst sieht (RSVP, Menü etc.) — minimaler Self-Service |

**QrAuthController Beispiel** — Klassen-Docblock
```php
/**
 * QR-Login für Gäste (Sanctum-Bearer-Token).
 *
 * Solo-Gast:    GET /auth/qr/{token} liefert Token sofort zurück.
 * Familie:      GET liefert Mitglieder-Liste OHNE Tokens.
 *               POST /auth/qr/{token}/select { guest_id } stellt Token nur für den gewählten Gast aus.
 *
 * Tokens werden bewusst NICHT beim ersten Scan für alle Mitglieder erstellt — sonst
 * blockieren ungenutzte Tokens andere Familienmitglieder (is_active wäre true obwohl niemand
 * eingeloggt ist).
 */
```

**PhotoGameController** — Methoden-Docblock an `buildAssignPool()` (zusätzlich zum Klassen-Docblock)
```php
/**
 * Baut den Aufgaben-Pool für ein Event:
 *  1. Base-Katalog (`is_base=true`) — immer drin
 *  2. Typ-Katalog (wenn `catalog_id` gesetzt) — z.B. Hochzeit / Geburtstag
 *  3. Overrides:  hidden → entfernen, modified → description ersetzen, added → hinzufügen
 *
 * So bleibt der Standard-Katalog global pflegbar, jedes Event speichert nur Deltas.
 */
```

#### 2.3 Middlewares (5 Dateien — die selbst-geschriebenen)

| Datei | Docblock-Inhalt |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Was global an Vue geteilt wird: `active_event`, `accessible_events`, Flash, Locale |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Schützt `/admin/*` Routes — `role=admin` Check |
| `app/Http/Middleware/EnsureHasEventAccess.php` | Schützt Hauptapp — User muss Owner/Mitveranstalter eines Events sein |
| `app/Http/Middleware/EnsureGuestHasAppAccess.php` | API-Schutz: nur Gäste mit `app_access=true` dürfen die App nutzen |
| `app/Http/Middleware/EnsureGuestHasDrinksAccess.php` | API-Schutz: zusätzliches Flag für Getränke-Tracking |

#### 2.4 Web-Controller (die in Tag 3c getestet werden — 8 Dateien)

Pro Datei ein knapper Klassen-Docblock (5–8 Zeilen), Schwerpunkt **was die Klasse orchestriert, was die Sonderfälle sind**.

| Datei | Sonderfall-Highlight für Docblock |
|---|---|
| `app/Http/Controllers/GuestController.php` | CRUD + RSVP-Verwaltung + app/drinks_access Toggles |
| `app/Http/Controllers/GroupController.php` | Cascade-Delete der Gäste, Event-Scope-Check |
| `app/Http/Controllers/DrinkController.php` | Pro-Event Getränke-Katalog-Auswahl (1 Zeile pro Größe pro Event) |
| `app/Http/Controllers/PhotoGameController.php` | Override-Verwaltung (hidden/modified/added), Re-Submission erlaubt |
| `app/Http/Controllers/EventController.php` | Event-Settings: Adressfelder, Farbsystem (Palette + Rollen), Cover-Upload, Projektor-Token-Generierung |
| `app/Http/Controllers/ProjectorController.php` | Public-Route via Token, Album-spezifisches Label-Mapping |
| `app/Http/Controllers/InvitationController.php` + `InvitationTokenController.php` | Token-Erstellung pro Group/Guest, Resend, Revoke |
| `app/Http/Controllers/PhotoController.php` (Web) | Batch-Delete + R2-Cleanup, Album-Slug-Routing |

#### 2.5 Base-Controller

**`app/Http/Controllers/Controller.php`** — Docblock an `activeEvent()` Helper
```php
/**
 * Liefert das aktuell gewählte Event des Users (Session-basiert).
 * Wird in HandleInertiaRequests global an Vue geteilt. Null wenn der User
 * noch kein Event hat (→ Onboarding).
 */
```

### 3. Workflow-Hinweis: Docblocks während Tag 3 mitschreiben

Tag 2 schreibt **ARCHITECTURE.md komplett**, lässt die Docblocks aber bewusst halb-offen — du setzt sie ein, **während du die jeweilige Datei für Tests durchgehst**:

- Test-Datei für `DrinkScoreService` ↔ Docblock am Service (Tag 3a)
- Test-Datei pro API-Endpoint ↔ Docblock am API-Controller (Tag 3b)
- Test-Datei pro Web-Feature ↔ Docblock am Web-Controller (Tag 3c)
- Test-Datei für Middlewares ↔ Docblock an Middleware (Tag 3d)

So passiert die Logik-Durchsicht nur einmal. Akzeptanzkriterium dieser Etappe ist deswegen **ARCHITECTURE.md + alle Docblocks an den Stellen, die in Tag 3 nicht angefasst werden** (Services nur 1× → fällt mit 3a zusammen, Inertia-Middleware → eigene Runde).

## Datei-Liste

| Datei | Aktion | Wann |
|---|---|---|
| `docs/ARCHITECTURE.md` | neu (~200–250 Zeilen) | **Tag 2** |
| `app/Http/Controllers/Controller.php` | Docblock an `activeEvent()` | **Tag 2** |
| `app/Http/Middleware/HandleInertiaRequests.php` | Klassen-Docblock | **Tag 2** |
| `app/Services/DrinkScoreService.php` | Klassen-Docblock | mit Tag 3a |
| `app/Http/Controllers/Api/*.php` (6 Dateien) | Klassen-Docblock | mit Tag 3b |
| `app/Http/Middleware/EnsureGuest*` + `EnsureHas*` + `EnsureUserIsAdmin` (4 Dateien) | Klassen-Docblock | mit Tag 3b/3d |
| `app/Http/Controllers/{Guest,Group,Drink,PhotoGame,Event,Projector,Invitation,InvitationToken,Photo}Controller.php` (8–9 Dateien) | Klassen-Docblock | mit Tag 3c |

## Akzeptanzkriterien

- [ ] `docs/ARCHITECTURE.md` hat alle 10 Sektionen
- [ ] Mermaid-ER-Diagramm rendert auf GitHub korrekt
- [ ] Jeder Datei-Pointer in der Doku stimmt (manuell prüfen)
- [ ] Klassen-Docblock an `HandleInertiaRequests` + `activeEvent()`-Methodendoc gesetzt
- [ ] README verlinkt `docs/ARCHITECTURE.md`
- [ ] **In Tag 3 mitzuziehen**: Docblocks an allen oben gelisteten Services/Controllern/Middlewares — Tag 3 ist erst grün, wenn die Tests UND die Docblocks der jeweiligen Datei stehen
- [ ] Stil-Check: jeder Docblock 5–15 Zeilen, fokussiert auf **Why** (Sonderfälle, Invarianten), kein What-Repeat des Klassennamens

## Commit-Vorschlag

```
docs: ARCHITECTURE.md + Doc-Blocks an Kern-Services
```
