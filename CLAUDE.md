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

- **Event** — hat user_id (Owner), name, slug, date. Viele Events möglich.
- **User** — role: `admin` (Superadmin = André) oder null (Event-Owner)
- **Guest** — event_id, category_id, group_id, beer/wine, likelihood, invite
- **Group** — event_id (früher Family)
- **Category** — (früher Badge)
- **InvitationToken** — group_id oder guest_id, token (32-char random)
- **Photo** — event_id, r2_key, url
- **FoodSpecial**, **GuestDrink** — Pivot-Tabellen

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

Auth: Sanctum Bearer Token. Guest-Modell ist tokenable. Guard: `web`.

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

**Noch nicht gebaut (Backend + Frontend):**
- `GET /api/guest/me` — eigene Gastdaten
- `POST /api/guest/rsvp` — Zu-/Absage
- `GET /api/event/info` — Hochzeitsinfos (Ort, Zeit)
- `GET /api/event/menu` — Menüoptionen
- `POST /api/guest/menu` — Menüwahl speichern

---

## Web-Frontend (fertige Features)

- **ConfirmDialog** (`components/ConfirmDialog.vue`) — Wiederverwendbarer shadcn-Dialog mit `v-model:open`, `@confirm`, `destructive`-Prop. Pattern: `pendingId` ref + `askDelete()` + `doDelete()`
- **Toast-Benachrichtigungen** — via `vue-sonner` (v2). `<Toaster>` liegt in `AppSidebarLayout.vue`. CSS MUSS explizit importiert werden: `import 'vue-sonner/style.css'`. Toast-Aufrufe in `onSuccess`-Callbacks der Inertia-Forms.
- **i18n (vue-i18n v11)** — Plugin in `resources/js/plugins/i18n.ts`. Locale-Dateien: `resources/js/locales/de.json` + `en.json`. Sprache wird in `localStorage` gespeichert. Standard: Deutsch.
  - Nav-Arrays und Tab-Arrays MÜSSEN als `computed()` definiert sein, damit sie auf Sprachwechsel reagieren.
  - Language-Switcher (DE/EN) in `AppSidebar.vue` oben links.

---

## Wichtige Eigenheiten

- **MariaDB 11**: `renameColumn()` verliert UNSIGNED → stattdessen raw `ALTER TABLE CHANGE` verwenden
- **Email-Verifizierung**: Google OAuth markiert Email automatisch als verifiziert (SocialLoginController)
- **Superadmin setzen**: Migration `2026_03_16_120000_seed_admin_user` setzt `andrehommrich@googlemail.com` auf role=admin
- **Standard-Event**: Migration `2026_03_16_130000` legt "Hochzeit André & Tabea" an und befüllt alle alten Datensätze ohne event_id
