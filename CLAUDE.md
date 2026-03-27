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
| Deploy | Docker + Coolify (Hetzner, 142.132.165.232) |
| Storage | Cloudflare R2 (Fotos) |
| Mail | Resend (Domain hommrich.app verifiziert) |

---

## Domains

- **Production:** `https://hommrich.app`
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

**docker-compose.yml ist branch-spezifisch — NIE überschreiben beim Merge!**
- `develop`: `Dockerfile` (artisan serve, Port 8080)
- `staging` + `production`: `Dockerfile.prod` (nginx + php-fpm, expose 80)

Coolify deployt automatisch nach Push. Migrations laufen automatisch.

---

## Datenmodelle

- **Event** — user_id (Owner), name, slug, date, rsvp_deadline, cover_image_url, cover_image_r2_key, venue_name, venue_street, venue_house_number, venue_postal_code, venue_city, venue_state, venue_country, venue_display_mode (`'both'|'name'|'address'`), venue_lat, venue_lng, dresscode, schedule, font_heading, drink_game_enabled, drink_game_end_time, photo_game_enabled, projector_token, projector_album_id, projector_name_mode (`'first'|'full'|'none'`, default `'first'`)
  - Legacy-Feld `venue_address` bleibt in DB (Fallback in EventInfoController)
- **Event Farbsystem** — 3 Palette-Felder (`color_primary`, `color_secondary`, `color_tertiary`) + 9 Rollen-Felder die Keys `'primary'|'secondary'|'tertiary'` speichern: `role_screen_bg`, `role_card_bg`, `role_card_text`, `role_card_button`, `role_card_button_text`, `role_tab_tint`, `role_border`, `role_fab`, `role_fab_icon`. Dazu `color_home_text`, `color_home_shadow`, `home_shadow_opacity` (Cover-Overlay, nur relevant wenn Cover gesetzt).
- **User** — role: `admin` (Superadmin = André) oder null (Event-Owner)
- **Guest** — event_id, category_id, group_id, beer/wine, likelihood, invite, app_access (bool), drinks_access (bool)
- **Group** — event_id (früher Family)
- **Category** — (früher Badge)
- **InvitationToken** — group_id oder guest_id, token (32-char random)
- **Photo** — event_id, album_id, guest_id, uploaded_by, uploader_user_id (FK users, nullable), url, r2_key, description (nullable, für Präsentationsfotos)
- **PhotoAlbum** — event_id, slug (`app_gallery`|`presentation`|`photo_game`), name, sort_order
- **FoodSpecial**, **GuestDrink** — Pivot-Tabellen
- **Drink** — event_id, name (Getränke-Katalog pro Event)
- **EventPhotoGame** — event_id, status (`draft`|`active`|`ended`), catalog_id (FK → Typ-Katalog, nullable)
- **PhotoGameTaskCatalog** — event_id (null = global), name, is_base (bool), event_type (nullable: `'hochzeit'`|`'geburtstag'`). Global-Kataloge: 1× is_base=true (Allgemein, immer aktiv), n× is_base=false mit event_type (optionaler Typ-Zusatz)
- **PhotoGameTask** — catalog_id, description, is_active
- **EventTaskOverride** — event_id, task_id (nullable), action (`hidden`|`modified`|`added`), custom_text
- **PhotoGameAssignment** — game_id, guest_id, task_id (nullable), override_id (nullable → EventTaskOverride), photo_id (nullable), submitted_at

---

## Zugriffsrollen

| Rolle | Zugriff |
|---|---|
| Superadmin (`role=admin`) | Alles, inkl. `/admin/users` (globale User-Verwaltung) |
| Event-Owner | Hauptapp (dashboard, table, guests, photos, invitations) für eigene Events |
| Ohne Event | Nur Onboarding-Seite |

Middleware-Aliase: `admin` = EnsureUserIsAdmin, `has_event` = EnsureHasEventAccess

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
- `color_screen_bg`, `color_card`, `color_card_text`, `color_card_button`, `color_card_button_text`, `color_tab_tint`, `color_border`, `color_fab`, `color_fab_icon` — aufgelöste Rollen (fertige Hex-Werte)
- `color_home_text`, `color_home_shadow`, `home_shadow_opacity` — Cover-Overlay (können `null` sein wenn kein Cover)
- `font_heading`, `drink_game_enabled`, `drink_game_end_time`

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

**Noch nicht gebaut:**
- `GET /api/guest/me` — eigene Gastdaten
- `POST /api/guest/rsvp` — Zu-/Absage
- `GET /api/event/menu` — Menüoptionen
- `POST /api/guest/menu` — Menüwahl speichern
- Getränke-Tracking / Trinkspiel in App

---

## Web-Frontend (fertige Features)

- **ConfirmDialog** (`components/ConfirmDialog.vue`) — Wiederverwendbarer shadcn-Dialog mit `v-model:open`, `@confirm`, `destructive`-Prop. Pattern: `pendingId` ref + `askDelete()` + `doDelete()`
- **Toast-Benachrichtigungen** — via `vue-sonner` (v2). `<Toaster>` liegt in `AppSidebarLayout.vue`. CSS MUSS explizit importiert werden: `import 'vue-sonner/style.css'`. Toast-Aufrufe in `onSuccess`-Callbacks der Inertia-Forms.
- **i18n (vue-i18n v11)** — Plugin in `resources/js/plugins/i18n.ts`. Locale-Dateien: `resources/js/locales/de.json` + `en.json`. Sprache wird in `localStorage` gespeichert. Standard: Deutsch.
  - Nav-Arrays und Tab-Arrays MÜSSEN als `computed()` definiert sein, damit sie auf Sprachwechsel reagieren.
  - Language-Switcher (DE/EN) in `AppSidebar.vue` oben links.
- **Event-Einstellungen** (`pages/Event/Settings.vue`) — Split-Screen (Preview rechts / Formular links, auf Mobile collapsed). Formular-Reihenfolge: Textfelder → Veranstaltungsort (strukturierte Adressfelder + Nominatim-Autocomplete + Leaflet-Map) → Cover-Upload (+ Home-Textfarbe + Shadow-Picker) → Design-Card (Schrift + Farbsystem).
  - **Nominatim**: `dedupe=0` im Request-Parameter, damit auch gleichnamige Adressen in verschiedenen Orten erscheinen.
  - **Farbsystem**: 3 Palette-Picker (Primär/Sekundär/Tertiär) + 9 Radio-Selektoren die Keys speichern. `palette` + `resolve()` computed, `cScreenBg` … `cFabIcon` als Convenience-Computeds für Preview.
  - **Phone-Preview**: 4 simulierte App-Screens (Home, Zusage, Fotos, Einstellungen) — reagieren live auf alle Farb-/Font-Änderungen. Auf Mobile einklappbar.
  - **Dirty-Guard**: `router.on('before', ...)` zeigt `window.confirm()` bei ungespeicherten Änderungen. Floating Save Bar unten rechts wenn `isDirty`.
- **Getränke-Tracking** — Gäste können Getränke loggen; Trinkspiel mit Rangliste + Punkte. Aktivierbar pro Event (`drink_game_enabled`), optionales Spielende-Datum.
  - **Punkteberechnung** (`DrinkScoreService`): Alkoholisch: `round(Liter × % × 10)`. Shots (category `spirit`) erhalten `×SHOT_MULTIPLIER` (aktuell 2.0) wegen schnellerer Absorption. Alkoholfrei: Flat-Wert (`negative_points`, Wasser −5, Softdrinks −3). Binge-Penalty: ≥3 alkoholische Getränke hintereinander → 50% der Basispunkte.
  - **Longdrink alcohol_percent**: realistisch ~7% (nicht 10%) — 4cl Spirit auf ~0,25l Mixer.
- **Gästeliste** (`components/GuestTable.vue`) — kollabierbare Gruppenblöcke mit Chevron (collapsed by default), Suchfeld filtert Gäste und klappt Gruppen automatisch auf.
- **RSVP-Verwaltung** — Zu-/Absage durch Gast oder Admin, Rücknahme-Anfragen mit Approve/Decline.
- **App-Zugang pro Gast** — `app_access` + `drinks_access` togglebar pro Gast.
- **CreatableCombobox / CreatableMultiCombobox** — ESC stoppt Propagation wenn Dropdown offen (verhindert Modal-Schließen). `:create-option` immer `true`, Text im `#option`-Slot zeigt „erneut anlegen?" wenn Duplikat.
- **Foto-System** (`pages/Photos/Index.vue`) — 3 Alben: App-Galerie, Präsentation, Fotospiel. Tab-basierte Ansicht mit Grid, Einzel- und Batch-Löschen, Viewer-Dialog.
  - Upload für Präsentation-Album zeigt optionalen Beschreibungs-Dialog (wird in Diashow angezeigt).
  - App-Galerie zeigt vollständigen Gastnamen; Veranstalter-Uploads ohne Badge, Mitveranstalter mit "(Mitveranstalter)".
- **Diashow / Projektor** (`pages/Projector/Show.vue`) — Vollbild-Diashow mit Crossfade (5s), Auto-Poll alle 10s für neue Fotos. Kontextuelles Label-Overlay:
  - App-Galerie: Gastname (konfigurierbar: Nur Vorname / Vollname / Kein Name via `projector_name_mode`)
  - Präsentation: Beschreibung (wenn gesetzt)
  - Fotospiel: Aufgabentext des Assignments
  - Einstellung im Foto-Tab sichtbar wenn App-Galerie aktiv.
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
- **S3-Cleanup**: Beim Löschen eines Assignments wird das R2-Foto mitgelöscht.

---

## Wichtige Eigenheiten

- **MariaDB 11**: `renameColumn()` verliert UNSIGNED → stattdessen raw `ALTER TABLE CHANGE` verwenden
- **Email-Verifizierung**: Google OAuth markiert Email automatisch als verifiziert (SocialLoginController)
- **Superadmin setzen**: Migration `2026_03_16_120000_seed_admin_user` setzt `andrehommrich@googlemail.com` auf role=admin
- **Standard-Event**: Migration `2026_03_16_130000` legt "Hochzeit André & Tabea" an und befüllt alle alten Datensätze ohne event_id
- **Farbsystem-Palette-Mapping**: `color_primary` = Bordeaux (#7c2d3e, ex-`color_accent`), `color_secondary` = Beige (#e8e3de, ex-`color_background`), `color_tertiary` = Weiß (#ffffff, ex-`color_card`). Migration: `2026_03_19_000010_refactor_color_system_to_palette_and_roles`.
- **HEIC-Upload**: Wird clientseitig via `heic2any` zu JPEG konvertiert (Settings.vue Cover-Upload) und serverseitig via Imagick (API Photo-Upload).
- **`npm run build` lokal schlägt fehl** (esbuild macOS vs. Linux Docker) — ist ein bekanntes Pre-existing Issue, kein Fehler in unserem Code. TypeScript-Check mit `npx tsc --noEmit` als Ersatz.
- **Reka UI SidebarGroupLabel**: Im collapsed mode wird der Label mit `-mt-8 opacity-0` versteckt, belegt aber weiterhin Platz und blockiert pointer-events. Daher `group-data-[collapsible=icon]:pointer-events-none` auf dem Label — fehlt das, ist das letzte Item der vorherigen NavMain-Gruppe nicht vollständig klickbar.
- **Vite HMR im Docker**: Dateiänderungen auf dem Host werden vom Vite-Container manchmal nicht erkannt. Fix: `docker restart eventplaner-vite-1`.
