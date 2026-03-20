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

- **Event** — user_id (Owner), name, slug, date, rsvp_deadline, cover_image_url, cover_image_r2_key, venue_name, venue_address, venue_lat, venue_lng, dresscode, schedule, font_heading, drink_game_enabled, drink_game_end_time
- **Event Farbsystem** — 3 Palette-Felder (`color_primary`, `color_secondary`, `color_tertiary`) + 9 Rollen-Felder die Keys `'primary'|'secondary'|'tertiary'` speichern: `role_screen_bg`, `role_card_bg`, `role_card_text`, `role_card_button`, `role_card_button_text`, `role_tab_tint`, `role_border`, `role_fab`, `role_fab_icon`. Dazu `color_home_text` (freier Hex-Picker, nur relevant wenn Cover gesetzt).
- **User** — role: `admin` (Superadmin = André) oder null (Event-Owner)
- **Guest** — event_id, category_id, group_id, beer/wine, likelihood, invite, app_access (bool), drinks_access (bool)
- **Group** — event_id (früher Family)
- **Category** — (früher Badge)
- **InvitationToken** — group_id oder guest_id, token (32-char random)
- **Photo** — event_id, r2_key, url
- **FoodSpecial**, **GuestDrink** — Pivot-Tabellen
- **Drink** — event_id, name (Getränke-Katalog pro Event)

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

Auth: Sanctum Bearer Token. Guest-Modell ist tokenable. Guard: `web`.

### `/api/event/info` — Farb-Response

Der Endpunkt löst Rollen-Keys zu Hex auf. Die App bekommt fertige Hex-Werte:
- `color_primary`, `color_secondary`, `color_tertiary` — Palette
- `color_screen_bg`, `color_card`, `color_card_text`, `color_card_button`, `color_card_button_text`, `color_tab_tint`, `color_border`, `color_fab`, `color_fab_icon` — aufgelöste Rollen
- `color_home_text` — kann `null` sein wenn kein Cover gesetzt
- `venue_lat`, `venue_lng` — können `null` sein wenn noch kein Standort ermittelt wurde
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
- **Event-Einstellungen** (`pages/Event/Settings.vue`) — Split-Screen (Preview rechts / Formular links). Formular-Reihenfolge: Textfelder → Cover-Upload (+ Home-Textfarbe darin) → Design-Card (Schrift + Farbsystem).
  - **Farbsystem**: 3 Palette-Picker (Primär/Sekundär/Tertiär) + 9 Radio-Selektoren die Keys speichern. `palette` + `resolve()` computed, `cScreenBg` … `cFabIcon` als Convenience-Computeds für Preview.
  - **Phone-Preview**: 4 simulierte App-Screens (Home, Zusage, Fotos, Einstellungen) — reagieren live auf alle Farb-/Font-Änderungen.
- **Getränke-Tracking** — Gäste können Getränke loggen; Trinkspiel mit Rangliste + Punkte. Aktivierbar pro Event (`drink_game_enabled`), optionales Spielende-Datum.
- **RSVP-Verwaltung** — Zu-/Absage durch Gast oder Admin, Rücknahme-Anfragen mit Approve/Decline.
- **App-Zugang pro Gast** — `app_access` + `drinks_access` togglebar pro Gast.

---

## Wichtige Eigenheiten

- **MariaDB 11**: `renameColumn()` verliert UNSIGNED → stattdessen raw `ALTER TABLE CHANGE` verwenden
- **Email-Verifizierung**: Google OAuth markiert Email automatisch als verifiziert (SocialLoginController)
- **Superadmin setzen**: Migration `2026_03_16_120000_seed_admin_user` setzt `andrehommrich@googlemail.com` auf role=admin
- **Standard-Event**: Migration `2026_03_16_130000` legt "Hochzeit André & Tabea" an und befüllt alle alten Datensätze ohne event_id
- **Farbsystem-Palette-Mapping**: `color_primary` = Bordeaux (#7c2d3e, ex-`color_accent`), `color_secondary` = Beige (#e8e3de, ex-`color_background`), `color_tertiary` = Weiß (#ffffff, ex-`color_card`). Migration: `2026_03_19_000010_refactor_color_system_to_palette_and_roles`.
- **HEIC-Upload**: Wird clientseitig via `heic2any` zu JPEG konvertiert (Settings.vue Cover-Upload) und serverseitig via Imagick (API Photo-Upload).
- **`npm run build` lokal schlägt fehl** (esbuild macOS vs. Linux Docker) — ist ein bekanntes Pre-existing Issue, kein Fehler in unserem Code. TypeScript-Check mit `npx tsc --noEmit` als Ersatz.
