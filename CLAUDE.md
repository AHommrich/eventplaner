# Eventplaner – CLAUDE.md

Hochzeitsplaner für André & Tabea. Echte Gäste werden damit arbeiten — Einfachheit ist oberstes Gebot.

---

## Stack

| Schicht | Technologie |
|---|---|
| Backend | Laravel 12 (PHP 8.3) + Inertia.js + Sanctum |
| Web-Frontend | Vue 3 + TypeScript + Tailwind CSS 4 + Reka UI |
| Mobile | React Native (Expo) — separates Repo |
| Build | Vite 6 + PWA |
| Deploy | Docker + Coolify (Hetzner Cloud) |
| Storage | Hetzner Object Storage (Fotos, Bucket in Nürnberg) |
| Mail | Resend (Domain eveplan.de verifiziert, EU-Region Ireland) |

---

## Domains

- **Production:** `https://eveplan.de`
- **Staging:** `https://beta.hommrich.app`

---

## Git-Branches & Deploy-Workflow

**WICHTIG: Production-Branch heißt `production`, NICHT `main`!**

```
develop → staging → production
```

1. Auf `develop` committen und pushen
2. `git checkout staging && git merge develop && git push origin staging`
3. `git checkout production && git merge develop && git push origin production`
4. `git checkout develop`

**Agenten-Regel:** Wenn ein Agent Code- oder Doku-Änderungen macht, liefert er am Ende immer eine englische 3-Zeiler-Commit-Message im bestehenden Stil mit (`type(scope): summary` + Leerzeile + kurze Body-Zeilen). Der Mensch reviewt und committet danach selbst.

**docker-compose.yml ist branch-spezifisch — NIE überschreiben beim Merge!**
- `develop`: `Dockerfile` (artisan serve, Port 8080)
- `staging` + `production`: `Dockerfile.prod` (nginx + php-fpm, expose 80)

Coolify deployt automatisch nach Push. Migrations laufen automatisch.

**⚠️ Redeploy-Disziplin:** Nie staging und production gleichzeitig redeployen — beide Container zusammen erschöpfen das VPS-RAM. Deshalb bei Multi-Env-Changes (Env-Vars, Force-Pushes, APP_KEY-Rotation) **immer sequentiell**: erst staging, verify dass `beta.hommrich.app` grün ist, dann prod.

**Sicherheitshebel gegen destruktive Ops (tool-übergreifend, auch für Codex/OpenCode):** `.githooks/pre-push` blockt Force-Push/History-Rewrite und Branch-Löschung auf `develop`/`staging`/`production` und verlangt eine Tipp-Bestätigung bei jedem Push auf `production`. Einmaliges Setup pro Clone: `git config core.hooksPath .githooks`. Details + manuelle GitHub-Branch-Protection-Checkliste in `docs/SAFETY_LEVER.md`. Nicht-triviale Entscheidungen/Erkenntnisse gehören ins append-only `docs/DECISIONS.md`, damit sie ein Session-Limit überleben — siehe auch `AGENTS.md` für die tool-übergreifende Kurzfassung dieser Regeln.

---

## Datenmodelle

- **Event** — user_id (Owner), name, slug, date, rsvp_deadline, cover_image_url, cover_image_r2_key, venue_name, venue_street, venue_house_number, venue_postal_code, venue_city, venue_state, venue_country, venue_display_mode (`'both'|'name'|'address'`), venue_lat, venue_lng, dresscode, schedule, font_heading, design_preset (`'classic'|'soft-luxury'`, default `'classic'`), drink_game_enabled, drink_game_end_time, photo_game_enabled, projector_token, projector_album_id, projector_name_mode (`'first'|'full'|'none'`, default `'first'`)
  - Legacy-Feld `venue_address` bleibt in DB (Fallback in EventInfoController)
- **Event Farbsystem** — 3 Palette-Felder (`color_primary`, `color_secondary`, `color_tertiary`) + 10 Rollen-Felder die Keys `'primary'|'secondary'|'tertiary'` speichern: `role_screen_bg`, `role_card_bg`, `role_card_text`, `role_card_button`, `role_card_button_text`, `role_tab_tint`, `role_border`, `role_fab`, `role_fab_icon`, `role_nav_bg`. Dazu `color_home_text`, `color_home_shadow`, `home_shadow_opacity` (Cover-Overlay, nur relevant wenn Cover gesetzt).
  - **`role_nav_bg`** (default `secondary`) = Hintergrund der Bottom-Navbar — eigene Rolle statt Ableitung. Vorher zog classic die `screen_bg`- und soft-luxury die `card`-Farbe, was beim Preset-Swap inkonsistent war; jetzt nutzen beide `role_nav_bg` (classic solid, soft-luxury frosted), und `role_tab_tint` bleibt der Vordergrund (Icons/Text/aktive Disc).
- **User** — role: `admin` (Superadmin = André) oder null (Event-Owner)
  - **Löschung (P0.5, seit 2026-07-16):** `events.user_id` ist `ON DELETE RESTRICT` (nicht mehr CASCADE). Ein Admin kann einen User, der noch ein Event besitzt, **nicht** löschen (`Admin/UserController::destroy` blockt + schützt den letzten Superadmin). Self-Service-Kontolöschung (`Settings/ProfileController::destroy`) löscht die eigenen Events **bewusst vorher** (DSGVO Art. 17). Siehe `docs/EVENT_MANAGER_ROLE_PLAN.md` P0.5.
- **Guest** — event_id, group_id, beer/wine, likelihood, invite, app_access (bool), drinks_access (bool)
- **Group** — event_id (früher Family)
- **InvitationToken** — group_id oder guest_id, token (32-char random)
- **Photo** — event_id, album_id, guest_id, uploaded_by, uploader_user_id (FK users, nullable), uploader_role (nullable, Snapshot der Uploader-Rolle bei Upload — `owner|event_admin|event_manager|superadmin`; null = Gast-Upload. P0.4: Badge liest die Spalte statt dynamischer Ableitung; P0-Write nutzt primary-owner-Heuristik owner/event_manager, P1 ersetzt durch `roleOn()`), url, r2_key, description (nullable, für Präsentationsfotos)
- **PhotoAlbum** — event_id, slug (`app_gallery`|`presentation`|`photo_game`), name, sort_order
- **FoodSpecial** — event_id (nullable: null = globales read-only Seed-Template, für jedes Event sichtbar; non-null = event-lokaler Custom-Eintrag), name, translation_key. Delta-Modell wie `PhotoGameTaskCatalog`; Reads = Templates ∪ event-lokal, Writes immer `event_id = activeEvent()`, FK `cascadeOnDelete` (P0.1, seit 2026-07-17). Pivot `guest_food_special`. `GuestDrink` — Pivot-Tabelle
  - Das frühere **Category**-Modell (ex-Badge) wurde 2026-07-17 (P0.1) samt Tabelle/Controller/Route entfernt — war totes Feature ohne Read-Pfad seit `guests.category_id` gedroppt wurde.
- **Drink** — event_id, name (Getränke-Katalog pro Event)
- **Note** (P2) — event_id (cascade), author_user_id (nullable, `nullOnDelete`), author_name (Snapshot für lesbare Historie nach Account-Löschung), assignee_user_id (nullable, `nullOnDelete`), type (`note|todo`), title, body (nullable), is_done, done_at, `SoftDeletes`. `assignee_user_id = null` = persönliche Notiz (privat für den Autor); non-null = vom Owner/Event-Admin zugewiesenes ToDo für einen aktiven `event_manager`. Zuweisung gated durch `EventPolicy::assignNote` (event_admin ∪ owner ∪ superadmin); Assignee darf nur lesen + abhaken, nicht editieren/löschen. Retention: `app:prune-notes` (`config('retention.notes_after_delete_days')`, default 30) hard-purged soft-deleted Notes nach N Tagen + alle Notes eines Events N Tage nach Event-Datum. Art. 15-Export enthält soft-deleted Notes (mit `deleted_at`-Marker) bis zum Purge.
- **EventPhotoGame** — event_id, status (`draft`|`active`|`ended`), catalog_id (FK → Typ-Katalog, nullable)
- **PhotoGameTaskCatalog** — event_id (null = global), name, is_base (bool), event_type (nullable: `'hochzeit'`|`'geburtstag'`). Global-Kataloge: 1× is_base=true (Allgemein, immer aktiv), n× is_base=false mit event_type (optionaler Typ-Zusatz)
- **PhotoGameTask** — catalog_id, description, is_active
- **EventTaskOverride** — event_id, task_id (nullable), action (`hidden`|`modified`|`added`), custom_text
- **PhotoGameAssignment** — game_id, guest_id, task_id (nullable), override_id (nullable → EventTaskOverride), photo_id (nullable), submitted_at

---

## Zugriffsrollen

Globale Rolle (`users.role`): `admin` (Superadmin) oder null. Pro-Event-Tier steht im Pivot
`event_user.role` (`owner` | `event_admin` | `event_manager`, default `event_manager`) — seit P1.

| Tier | Zugriff |
|---|---|
| Superadmin (`role=admin`) | Alles, inkl. `/admin/users` (globale User-Verwaltung); `before()`-Kurzschluss auf allen Coarse-Gates |
| Owner (`events.user_id` **oder** Pivot `owner`) | Volle Event-Kontrolle inkl. Deep-Settings, Design, Zeitplan, Zugang, Event-Löschung, Rollen-/Owner-Vergabe |
| Event-Admin (Pivot `event_admin`) | Wie Owner, aber **kein** Grant/Revoke von `event_admin`/`owner`; darf nur `event_manager` verwalten |
| Event-Manager (Pivot `event_manager`) | Manage-Level: Gäste (außer Volllöschung), Fotos, Getränke, Spiele-Toggles, Rücknahme-Anfragen — **keine** Deep-Settings/Design/Zeitplan/Zugang/Projektor-Config/Photo-Reports |
| Ohne Event | Nur Onboarding-Seite |

- **Rollen-Auflösung:** `User::roleOn(Event)` (Präzedenz superadmin → owner → event_admin/event_manager),
  `User::canManage()`/`canAdminister()`, `Event::owners()`/`isOwnedBy()`. Owner = Primär-Owner
  (`events.user_id`) ∪ Pivot-`owner` (alle Owner sind gleichwertig; André = Primär, Tabea = Pivot-`owner`).
- **`EventPolicy`** (`app/Policies/EventPolicy.php`, erste Policy im Projekt): `view`/`manage`/`administer`/
  `manageAccess` (Coarse) + `changeAccess`/`removeMember`/`grantOwner`/`transferOwnership`/`deleteEvent`
  (target-aware, kein Blanket-Superadmin-Grant). Der zentrale Access-Checkpoint `changeAccess` ist
  server-autoritativ; Frontend-Gating ist kosmetisch. Last-Owner-Schutz + transaktionale Writes in
  `App\Services\EventAccessService` (`lockForUpdate`).
- **Frontend:** `active_event.my_role` (via `HandleInertiaRequests`) steuert Sidebar-/Seiten-Gating.
- **§8.1-Split (erledigt):** `/admin/users` ist rein globale User-Verwaltung (Rolle/Löschung);
  Event-Zugang + Tiers laufen komplett über `/event/access` (`EventAccessController`). Superadmins
  erreichen jede Event-Zugangsseite über den Event-Switcher.

Middleware-Aliase: `admin` = EnsureUserIsAdmin, `has_event` = EnsureHasEventAccess,
`can_administer` = EnsureCanAdministerEvent (Session-Event → `administer`-Gate für Routen ohne `{event}`-Binding)

---

## Aktives Event (Session-basiert)

- `activeEvent()` in `Controller.php` — liest `session('active_event_id')`
- `HandleInertiaRequests` teilt `active_event` + `accessible_events` global
- Event-Switcher in Sidebar: bei >1 Event Dropdown, sonst nur Anzeige

---

## API-Endpunkte (fertig)

| Endpoint | Methode | Was es macht |
|---|---|---|
| `/api/auth/qr/{token}` | GET | QR-Login Schritt 1: gibt Gästeliste + is_active zurück, erstellt KEINE Tokens |
| `/api/auth/qr/{token}/select` | POST | QR-Login Schritt 2 (nur Familie): `{guest_id}` → erstellt Token für gewählten Gast |
| `/api/auth/logout` | DELETE | Token serverseitig löschen (Bearer im Header) |
| `/api/photos` | GET | Alle Fotos des Events laden |
| `/api/photos` | POST | Foto hochladen (multipart/form-data, HEIC→JPEG Konvertierung) |
| `/api/event/info` | GET | Event-Infos inkl. aufgelöste Hex-Farben (Palette + Rollen) |
| `/api/photo-game/status` | GET | Aktueller Spielstatus + offene Aufgabe des Gastes |
| `/api/photo-game/assign` | POST | Neue Aufgabe zuweisen (Pool: Basis + Typ-Katalog + Overrides) |
| `/api/photo-game/submit` | POST | Foto zur Aufgabe einreichen (multipart/form-data) |

Auth: Sanctum Bearer Token. Guest-Modell ist tokenable. Guard: `web`.

### `/api/event/info` — Response-Felder

- `name`, `date`, `rsvp_deadline`, `dresscode`, `schedule`
- `cover_image_url`, `venue_name`, `venue_address` (zusammengesetzt aus Adressfeldern), `venue_lat`, `venue_lng`, `venue_display_mode`
- `color_primary`, `color_secondary`, `color_tertiary` — Palette
- `color_screen_bg`, `color_card`, `color_card_text`, `color_card_button`, `color_card_button_text`, `color_tab_tint`, `color_border`, `color_fab`, `color_fab_icon`, `color_nav_bg` — aufgelöste Rollen (fertige Hex-Werte)
- `color_home_text`, `color_home_shadow`, `home_shadow_opacity` — Cover-Overlay (können `null` sein wenn kein Cover)
- `font_heading`, `design_preset` (`'classic'|'soft-luxury'`, App-Formsprache), `drink_game_enabled`, `drink_game_end_time`

### QR-Login Flow

**Solo-Gast** (`type: "solo"`): Token kommt direkt in der GET-Response, kein zweiter Schritt.

**Familien-Gast** (`type: "family"`): Zweistufig:
1. `GET /api/auth/qr/{token}` → Liste aller Familienmitglieder, alle `token: null`
2. User tippt Namen an → `POST /api/auth/qr/{token}/select` mit `{"guest_id": 42}`
   - 200 → `{token: "..."}` speichern, einloggen
   - 409 → Gast bereits eingeloggt

**Wichtig:** Tokens NIEMALS beim Scan für alle erstellen — nur der ausgewählte Gast bekommt einen Token. Sonst blockieren ungenutzte Tokens andere Familienmitglieder (`is_active: true` obwohl niemand eingeloggt).

**`is_active`-Prüfung:** Immer explizit via `PersonalAccessToken::where('tokenable_type', Guest::class)->where('tokenable_id', $guest->id)` — NICHT über `$guest->tokens()` auf eager-geladenen Objekten (MorphMany scoped dort nicht korrekt).

---

## React Native App (separates Repo)

**Fertig:**
- `lib/api.ts` — Axios-Instanz mit automatischem Bearer-Token
- `lib/auth.ts` — Session via expo-secure-store
- `app/scan.tsx` — QR-Scanner + manueller Token-Input (DEV)
- `app/(tabs)/home.tsx` — Begrüßungsscreen
- `app/(tabs)/photos.tsx` — Fotogalerie mit Upload + Auto-Refresh (30s)
- `app/(tabs)/settings.tsx` — Logout + Benutzerinfo
- Dynamisches Theming via `/api/event/info` (Palette + aufgelöste Rollen)

**Nicht geplant (bewusst):** In-App RSVP, Menu-API, In-App-Getränke-Tracking. Die App ist feature-complete für die reale Nutzung — keine neuen Features vorschlagen ohne explizite Anfrage.

---

## Web-Frontend (fertige Features)

- **ConfirmDialog** (`components/ConfirmDialog.vue`) — Wiederverwendbarer shadcn-Dialog mit `v-model:open`, `@confirm`, `destructive`-Prop. Pattern: `pendingId` ref + `askDelete()` + `doDelete()`
- **InfoTooltip** (`components/InfoTooltip.vue`) — Kleines `?`-Icon das bei Hover einen Erklärungstext einblendet. Props: `text: string`. Verwendet Reka UI TooltipProvider intern. Überall in der App für kontextuelle Hilfe eingesetzt (Farbsystem, Einladungen, Gäste-Zugang, Fotospiel, Diashow, Rücknahme-Anfragen, etc.).
- **Toast-Benachrichtigungen** — via `vue-sonner` (v2). `<Toaster>` liegt in `AppSidebarLayout.vue`. CSS MUSS explizit importiert werden: `import 'vue-sonner/style.css'`. Toast-Aufrufe in `onSuccess`-Callbacks der Inertia-Forms.
- **i18n (vue-i18n v11)** — Plugin in `resources/js/plugins/i18n.ts`. Locale-Dateien: `resources/js/locales/de.json` + `en.json`. Sprache wird in `localStorage` gespeichert. Standard: Deutsch.
  - Nav-Arrays und Tab-Arrays MÜSSEN als `computed()` definiert sein, damit sie auf Sprachwechsel reagieren.
  - Language-Switcher (DE/EN) in `AppSidebar.vue` oben links.
- **Event-Einstellungen** (`pages/Event/Settings.vue`) — Split-Screen (Preview rechts / Formular links, auf Mobile collapsed). Formular-Reihenfolge: Textfelder → Veranstaltungsort (strukturierte Adressfelder + Nominatim-Autocomplete + Leaflet-Map) → Cover-Upload (+ Home-Textfarbe + Shadow-Picker) → Design-Card (Schrift + Farbsystem).
  - **Nominatim**: `dedupe=0` im Request-Parameter, damit auch gleichnamige Adressen in verschiedenen Orten erscheinen.
  - **Farbsystem**: 3 Palette-Picker (Primär/Sekundär/Tertiär) + 10 Radio-Selektoren die Keys speichern. `palette` + `resolve()` computed, `cScreenBg` … `cNavBg` als Convenience-Computeds für Preview.
  - **Phone-Preview**: 4 simulierte App-Screens (Home, Zusage, Fotos, Einstellungen) — reagieren live auf alle Farb-/Font-Änderungen. Auf Mobile einklappbar.
  - **Dirty-Guard**: `router.on('before', ...)` zeigt `window.confirm()` bei ungespeicherten Änderungen. Floating Save Bar unten rechts wenn `isDirty`.
- **Getränke-Tracking** — Gäste können Getränke loggen; Trinkspiel mit Rangliste + Punkte. Aktivierbar pro Event (`drink_game_enabled`), optionales Spielende-Datum.
  - **Getränke-Katalog** (`pages/Drinks/Index.vue`): Veranstalter wählt welche Getränke auf der Feier verfügbar sind. Info-Box immer sichtbar (allgemeiner Hinweis), Trinkspiel-Zusatz nur wenn `active_event.drink_game_enabled === true`.
  - **Punkteberechnung** (`DrinkScoreService`): Alkoholisch: `round(Liter × % × 10)`. Shots (category `spirit`) erhalten `×SHOT_MULTIPLIER` (aktuell 2.0) wegen schnellerer Absorption. Alkoholfrei: Flat-Wert (`negative_points`, Wasser −5, Softdrinks −3). Binge-Penalty: ≥3 alkoholische Getränke hintereinander → 50% der Basispunkte.
  - **Longdrink alcohol_percent**: realistisch ~7% (nicht 10%) — 4cl Spirit auf ~0,25l Mixer.
- **Gästeliste** (`components/GuestTable.vue`) — kollabierbare Gruppenblöcke mit Chevron (collapsed by default), Suchfeld filtert Gäste und klappt Gruppen automatisch auf.
- **RSVP-Verwaltung** — Zu-/Absage durch Gast oder Admin, Rücknahme-Anfragen mit Approve/Decline.
- **App-Zugang pro Gast** — `app_access` + `drinks_access` togglebar pro Gast.
- **CreatableCombobox / CreatableMultiCombobox** — ESC stoppt Propagation wenn Dropdown offen (verhindert Modal-Schließen). `:create-option` immer `true`, Text im `#option`-Slot zeigt „erneut anlegen?" wenn Duplikat.
- **Foto-System** (`pages/Photos/Index.vue`) — Zwei aufklappbare Sektionen (beide starten zugeklappt): **Diashow** und **Alben**. Jede Sektion hat ein `?`-InfoTooltip. Alben-Sektion enthält 3 Tabs (App-Galerie, Präsentation, Fotospiel) mit Grid, Einzel- und Batch-Löschen, Viewer-Dialog, Tab-spezifischen ℹ-Beschreibungen.
  - Upload für Präsentation-Album zeigt optionalen Beschreibungs-Dialog (wird in Diashow angezeigt).
  - App-Galerie zeigt vollständigen Gastnamen; Veranstalter-Uploads ohne Badge, Mitveranstalter mit "(Mitveranstalter)".
  - Namensanzeige-Einstellung (`projector_name_mode`) nur bei App-Galerie-Tab sichtbar.
- **Diashow / Projektor** (`pages/Projector/Show.vue`) — Vollbild-Diashow mit Crossfade (5s), Auto-Poll alle 10s für neue Fotos. Info-Overlay blendet sich nach 4s aus. Kontextuelles Label-Overlay:
  - App-Galerie: Gastname (konfigurierbar: Nur Vorname / Vollname / Kein Name via `projector_name_mode`)
  - Präsentation: Beschreibung (wenn gesetzt)
  - Fotospiel: Aufgabentext des Assignments
- **Fotospiel** (`pages/PhotoGame/Index.vue`) — Delta-Modell: globale Task-Kataloge (Allgemein immer aktiv + optionaler Typ-Katalog), pro Event nur Overrides gespeichert.
  - Admin kann Tasks ausblenden, anpassen (modified) oder eigene hinzufügen (added).
  - Gäste bekommen via API on-the-fly eine Aufgabe aus dem Pool zugewiesen.
  - Einreichungen als Foto-Grid mit Viewer + Löschfunktion.

---

## Fotospiel — Delta-Modell (wichtig)

- **Globale Kataloge** (`photo_game_task_catalogs` mit `event_id=null`):
  - 1× `is_base=true` (Allgemein, 15 Tasks) — immer im Pool
  - n× `is_base=false` mit `event_type` (`hochzeit` 9 Tasks, `geburtstag` 8 Tasks) — optional wählbar
- **Event-Typ wählen**: `event_photo_games.catalog_id` zeigt auf den gewählten Typ-Katalog
- **Overrides** (`event_task_overrides`): `hidden` | `modified` | `added`. `added` hat `task_id=null`.
- **Pool-Aufbau** (in `PhotoGameController::buildTaskPool()` und `Api/PhotoGameController::buildAssignPool()`):
  1. Base-Tasks laden
  2. Typ-Tasks anhängen (wenn catalog_id gesetzt)
  3. Overrides anwenden: hidden überschreibt state, modified ersetzt description, added wird hinzugefügt
- **Assignment**: speichert `task_id` ODER `override_id` (für `added` Tasks). `resolveTaskDescription()` prüft modified-Override zuerst.
- **Re-Submission erlaubt**: Gäste können ein neues Foto für dieselbe Aufgabe einreichen (kein 409 mehr).
- **S3-Cleanup**: Beim Löschen eines Assignments wird das Foto aus dem Object Storage mitgelöscht.

---

## Wichtige Eigenheiten

- **⚠️ Tests laufen NUR gegen `laravel_test`**: separate Connection `mysql_testing` in `config/database.php` (hartkodiert auf `laravel_test`), in `phpunit.xml` als `DB_CONNECTION` aktiv. Doppel-Guard in `tests/TestCase::setUp()` wirft `RuntimeException` bei jeder anderen DB. **Hintergrund**: einmal lief RefreshDatabase versehentlich gegen `laravel` (Dev-DB) und hat alle Daten gewiped. Beim Anlegen einer neuen Test-DB darauf achten, dass der Name auf `_test` oder `_testing` endet — sonst muss der Guard erweitert werden. `composer test` ruft `pest` auf der gesicherten Connection auf.
- **MariaDB 11**: `renameColumn()` verliert UNSIGNED → stattdessen raw `ALTER TABLE CHANGE` verwenden
- **Email-Verifizierung**: Google OAuth markiert Email automatisch als verifiziert (SocialLoginController)
- **Superadmin setzen**: Migration `2026_03_16_120000_seed_admin_user` setzt `andrehommrich@googlemail.com` auf role=admin
- **Standard-Event**: Migration `2026_03_16_130000` legt "Hochzeit André & Tabea" an und befüllt alle alten Datensätze ohne event_id
- **Farbsystem-Palette-Mapping**: `color_primary` = Bordeaux (#7c2d3e, ex-`color_accent`), `color_secondary` = Beige (#e8e3de, ex-`color_background`), `color_tertiary` = Weiß (#ffffff, ex-`color_card`). Migration: `2026_03_19_000010_refactor_color_system_to_palette_and_roles`.
- **HEIC-Upload**: Wird clientseitig via `heic2any` zu JPEG konvertiert (Settings.vue Cover-Upload) und serverseitig via Imagick (API Photo-Upload).
- **`npm run build` lokal schlägt fehl** (esbuild macOS vs. Linux Docker) — ist ein bekanntes Pre-existing Issue, kein Fehler in unserem Code. TypeScript-Check mit `npm run typecheck` (vue-tsc) als Ersatz.
- **Reka UI SidebarGroupLabel**: Im collapsed mode wird der Label mit `-mt-8 opacity-0` versteckt, belegt aber weiterhin Platz und blockiert pointer-events. Daher `group-data-[collapsible=icon]:pointer-events-none` auf dem Label — fehlt das, ist das letzte Item der vorherigen NavMain-Gruppe nicht vollständig klickbar.
- **Vite HMR im Docker**: Dateiänderungen auf dem Host werden vom Vite-Container manchmal nicht erkannt. Fix: `docker restart eventplaner-vite-1`.
- **Demo-Daten für Screenshots / öffentliche Demos**: `DemoDataSeeder` legt ein plausibles Fake-Event „Hochzeit Anna & Ben 2027" mit 36 Gästen, 27 Fotos (via picsum-Placeholder), Trinkspiel-Leaderboard und Fotospiel-Einreichungen an — echte Daten bleiben unberührt. `docker exec laravel-app php artisan db:seed --class=DemoDataSeeder`. Login `demo@eveplan.app` / `demo-1234`. Cleanup: `User::where('email', 'demo@eveplan.app')->delete()`. Seeder ist idempotent — Re-Run löscht den alten Demo-User und legt neu an.

---

## DSGVO / Datenschutz

### Öffentliche Rechts-Seiten

- `GET /impressum` → `LegalController@imprint` → `resources/js/pages/Legal/Imprint.vue`
- `GET /datenschutz` → `LegalController@privacy` → `resources/js/pages/Legal/Privacy.vue`
- Beide Seiten sind auf Deutsch (Zielgruppe + Art. 12 DSGVO Transparenz-Anforderung); Dev-Doku bleibt Englisch.

### Signup-Consent

- Spalte `users.privacy_accepted_at` (Migration `2026_07_01_000001_add_privacy_accepted_at_to_users`).
- Registrieren-Formular hat eine verpflichtende Checkbox („Ich akzeptiere die Datenschutzerklärung"); `RegisteredUserController` schreibt den Timestamp.
- Bei OAuth-Anmeldungen wird das Feld ebenfalls gesetzt.

### Security-Headers (`app/Http/Middleware/SecurityHeaders.php`)

- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera=self, microphone/geolocation=off) — in **allen** Environments aktiv.
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` — **nur `production` + `staging`** (Long-lived HSTS würde lokalen HTTP-Dev bricken).
- `Content-Security-Policy` — in `production`, `staging`, `testing` aktiv. **In `local` deaktiviert**, weil der Vite-Dev-Server auf `localhost:5173` sonst geblockt wird.
- CSP erlaubt `nominatim.openstreetmap.org` als `connect-src` (Adress-Autocomplete in Event-Settings) und `fonts.googleapis.com` / `fonts.gstatic.com` als style/font-src.

### Object-Storage-Cleanup bei Löschung

- `app/Observers/PhotoObserver.php` löscht das Object-Storage-Blob (`$photo->r2_key` — Feldname historisch aus R2-Zeit, jetzt Hetzner-Object-Key) im `deleting`-Event.
- Event-/User-Löschungen kaskadieren über Foreign-Keys auf `photos`, wodurch der Observer für jedes verwaiste Foto auslöst.
- Fallback-Sweep: `php artisan photos:cleanup-orphans` (`app/Console/Commands/CleanupOrphanPhotos.php`) räumt Bucket-Objekte weg, deren DB-Zeile bereits weg ist.

### Data-Export (Art. 15 DSGVO)

- Route `GET /settings/export-data` → `DataExportController` nutzt `app/Services/UserDataExporter.php` und liefert einen JSON-Dump aller Daten die dem User „gehören" (Events, Gäste, Fotos-Metadaten, Getränke-Logs, …). Foto-Binärdaten werden nicht mit reingebacken — der Export enthält nur URLs.

### Retention (Art. 5 DSGVO)

- Konfiguriert in `config/retention.php`. Defaults: `RETENTION_INVITATION_TOKENS_DAYS=30`, `RETENTION_DECLINED_GUESTS_DAYS=180`.
- `app:prune-invitation-tokens` löscht `InvitationToken`s deren Event schon länger als N Tage vorbei ist.
- `app:prune-declined-guests` löscht abgesagte Gäste ohne `app_access`/`drinks_access` nach Event-Ende + N Tage.
- Beide Commands sind in `bootstrap/app.php` bzw. `routes/console.php` täglich gescheduled.

### EXIF-Stripping (`app/Services/PhotoSanitizer.php`)

- **Für jeden neuen Upload-Pfad benutzen.** Konvertiert das Bild deterministisch nach JPEG und entfernt EXIF/IPTC/XMP.
- Aktuell eingehängt in: `Api/PhotoController` (App-Upload), `PhotoController` (Web-Upload), `Api/PhotoGameController` (Foto-Aufgabe), `EventSettingsController` (Cover).
- Treiber: Imagick wenn verfügbar, sonst GD-Fallback.
- Guard-Test: `tests/Feature/Photo/ExifStrippingTest.php`.

### Sub-Processor-Register

- `docs/legal/sub-processors.md` — authoritative Quelle für die Datenschutzerklärung. Bei neuem Dienst **zuerst dort dokumentieren**, dann Privacy.vue Sektion 5 aktualisieren, dann Integration mergen.
- Foto-Storage-Migration R2 → Hetzner Object Storage am 2026-07-01 abgeschlossen — dokumentiert in `docs/legal/hetzner-object-storage-migration.md`.

### Gesamt-Plan

- Übersicht + Etappen 1–7 in `docs/GDPR_COMPLIANCE_PLAN.md`. Stage 7 (Cookie-Consent) ist bewusst deferred bis Tracking landet.

### ⚠️ Governance-Regel — Sub-Processor / Infrastruktur-Änderungen

**Immer wenn sich etwas an den Sub-Processors oder der Infrastruktur ändert** (neuer Anbieter, Anbieterwechsel, Region-Wechsel, Domain-Wechsel, neuer Datentyp der verarbeitet wird, …), **müssen alle diese Stellen synchron nachgezogen werden** — sonst driftet die öffentliche Datenschutzerklärung von der Realität ab. Das ist ein Rechtsproblem: DSGVO Art. 13 verlangt korrekte Angaben.

Checkliste bei jeder Sub-Processor-/Infrastruktur-Änderung:

- [ ] `docs/legal/sub-processors.md` — authoritative Register mit Purpose, Location, DPA
- [ ] `resources/legal/privacy.de.md` + `resources/legal/privacy.en.md` — user-facing Datenschutzerklärung (Sektion „Drittanbieter"). Wird von `resources/js/pages/Legal/Privacy.vue` gerendert; muss inhaltlich mit dem Register übereinstimmen.
- [ ] `resources/js/pages/Legal/Imprint.vue` — Impressum wenn Verantwortlicher/Adresse/Domain betroffen
- [ ] `docs/ARCHITECTURE.md` — falls Erwähnung des Providers
- [ ] `README.md` + `README.de.md` — Stack-Tabelle + DSGVO-Sektion
- [ ] `CLAUDE.md` (dieses File) — Stack-Tabelle + relevante Sub-Sektionen
- [ ] Code-Docblocks in `PhotoSanitizer`, `PhotoObserver`, `CleanupOrphanPhotos`, Controllern die Storage nutzen — bei Storage-Provider-Wechsel
- [ ] Test-Beschreibungen (`it('… on R2', …)` etc.) wenn sie den Provider-Namen enthalten
- [ ] `.env.example` — Kommentare + Default-Werte
- [ ] `docs/legal/*` — Migrations-Doku (z. B. `hetzner-object-storage-migration.md`) mit Datum + Playbook für die Zukunft
- [ ] Wenn eine _AGB_ / Terms-of-Service existiert (aktuell nicht) → dort auch nachziehen

**Faustregel: Wenn Du einen Provider-Namen im Register änderst, must Du grep-en:**
```bash
grep -rln "Cloudflare\|R2\|hommrich.app" --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git .
```
Alle Treffer die nicht historische Doku (`docs/gdpr/*`, `docs/showcase/*`) sind, müssen angepasst werden.

**Merge-Regel**: In der PR-Beschreibung explizit auflisten welche der obigen Stellen upgedated wurden. Das PR-Template (`.github/PULL_REQUEST_TEMPLATE.md`) hat dafür die Sektion „GDPR / privacy touched?".

---

## Verwandte Dokumentation

- `README.md` / `README.de.md` — externe Projekt-Übersicht, Feature-Highlights, Deploy-Workflow
- `docs/GETTING_STARTED.md` — Lokales Dev-Setup + typische Fußfallen
- `docs/CONTRIBUTING.md` — Branch-Modell, Commit-Convention, Doc-Sync-Regel
- `docs/ARCHITECTURE.md` — Subsystem-Deep-Dives (Photo-Game, Drink-Score, Projector, Color System)
- `docs/GDPR_COMPLIANCE_PLAN.md` — DSGVO-Etappen 1–6 abgeschlossen; Stage 7 (Cookie-Consent) deferred
- `docs/legal/sub-processors.md` — Sub-Processor-Register (authoritative)
- `SECURITY.md` — Disclosure-Prozess
