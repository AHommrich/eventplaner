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
| `/api/auth/qr/{token}` | GET | QR-Login, gibt guests-Array + Sanctum-Tokens zurück |
| `/api/auth/logout` | DELETE | Token serverseitig löschen (Bearer im Header) |
| `/api/photos` | GET | Alle Fotos des Events laden |
| `/api/photos` | POST | Foto hochladen (multipart/form-data, HEIC→JPEG Konvertierung) |

Auth: Sanctum Bearer Token. Guest-Modell ist tokenable. Guard: `web`.

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

## Wichtige Eigenheiten

- **MariaDB 11**: `renameColumn()` verliert UNSIGNED → stattdessen raw `ALTER TABLE CHANGE` verwenden
- **Email-Verifizierung**: Google OAuth markiert Email automatisch als verifiziert (SocialLoginController)
- **Superadmin setzen**: Migration `2026_03_16_120000_seed_admin_user` setzt `andrehommrich@googlemail.com` auf role=admin
- **Standard-Event**: Migration `2026_03_16_130000` legt "Hochzeit André & Tabea" an und befüllt alle alten Datensätze ohne event_id
