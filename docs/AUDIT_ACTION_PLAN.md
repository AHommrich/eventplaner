# Audit Action Plan — Stand 2026-07-05

Umsetzungsplan aus dem Gesamt-Audit vom 05.07.2026.
Internes Working-File (deutsch), analog zu `SHOWCASE_PLAN.md` / `NEXT_SESSION.md`.

Abarbeitungslogik: **erst „Muss", dann „Sollte", dann „Kann".** Monitoring bewusst als offener Follow-up (Tool-Wahl steht noch aus, siehe Abschnitt am Ende).

---

## Muss vor produktivem Fremdkunden-Betrieb

### [ ] 1. IDOR-Guards nachziehen (Web-Backend)
**Warum:** Cross-Event-Zugriff möglich. Sobald mehr als ein Owner das System nutzt, ist das eine meldepflichtige Datenpanne nach Art. 33 DSGVO.

**Konkrete Stellen:**
- `app/Http/Controllers/PhotoController.php:115` — `destroy(Photo $photo)`: Guard fehlt, S3-Blob wird direkt gelöscht
- `app/Http/Controllers/PhotoController.php:145` — `updateProjectorAlbum`: `exists:photo_albums,id` scopet nicht auf `event_id`
- `app/Http/Controllers/GuestController.php:56` — `destroy(Guest $guest)`: kein Guard
- `app/Http/Controllers/GuestController.php:63` — `edit(Guest $guest)`: liest Guest-Daten anderer Events aus
- `app/Http/Controllers/GuestController.php:94` — `update(Guest $guest)`: kann Guest fremder Events überschreiben

**Muster (analog zu den anderen Guards weiter unten in `GuestController`):**
```php
$event = $this->activeEvent();
abort_if($guest->event_id !== $event?->id, 403);
```
Für die Album-Validierung: `Rule::exists('photo_albums', 'id')->where('event_id', $event->id)`.

**Tests:** je einen Feature-Test „User A darf Photo/Guest von User B nicht löschen/editieren" — passt zu `tests/Feature/Middleware/PermissionMiddlewareTest.php`.

**Aufwand:** ~2 h inkl. Tests.

---

### [ ] 2. `.dockerignore` anlegen
**Warum:** `Dockerfile.prod` macht `COPY . .`. Ohne `.dockerignore` landen `.env`, `.env.docker`, `.env.develop`, `laravel`/`laravel_test_*` (SQLite), `dbcheck.php`, `hochzeits_einladung.json` (potenziell echte Gästedaten), `coverage/`, `node_modules`, `.git` im Prod-Image. Kritisch, sobald das Image je aus der Registry abfließt.

**Mindestinhalt:**
```
.env
.env.*
!.env.example
.git
.github
node_modules
vendor
coverage
storage/logs/*
!storage/logs/.gitkeep
tests
docs
laravel
laravel_test_*
dbcheck.php
hochzeits_einladung.json
.phpunit.cache
.phpunit.result.cache
```

**Prüfen:** nach dem Anlegen `docker build -f Dockerfile.prod . --no-cache` lokal probieren; Image mit `docker run --rm <image> ls -la /var/www` schnell durchsehen — nichts Verräterisches drin?

**Aufwand:** ~15 min.

---

### [ ] 3. Sanctum-Guest-Tokens: Expiry setzen
**Warum:** `config/sanctum.php` — `'expiration' => null`. Guest-Tokens bleiben nach dem Event unbegrenzt gültig. Widerspricht Datenminimierung (Art. 5 lit. c/e DSGVO).

**Vorschlag:** zwei Wege möglich, entscheiden bei der Umsetzung:

- **Global** in `config/sanctum.php`: `'expiration' => 60 * 24 * 90` (90 Tage). Nachteil: gilt auch für zukünftige Owner-API-Tokens.
- **Pro Token beim Erstellen** in `QrAuthController::login`/`select`:
  ```php
  $guest->createToken('guest-login', ['role:guest'], now()->addDays(90));
  ```
  Sauberer, weil scoped.

**Zusätzlich:** `sanctum:prune-expired --hours=24` läuft bereits daily (siehe `routes/console.php:21`) — sobald Tokens Expiry haben, greift der Job automatisch.

**Aufwand:** ~30 min inkl. Test-Update in `QrAuthTest`.

---

### [ ] 4. Backup + Restore dokumentieren
**Warum:** Migrations laufen bei jedem Coolify-Deploy blind (`Dockerfile.prod:125`). Ein fehlerhafter Deploy oder eine irreversible Migration ist derzeit ohne Recovery-Pfad. Roadmap-Memo hat „VPS-Backups" bereits als offen vermerkt.

**Konkret:**
- `docs/RUNBOOK.md` anlegen mit:
  - Wie mache ich ein manuelles `mysqldump` auf dem VPS?
  - Wo liegt der Dump nach dem Backup? (Ziel: Hetzner Storage Box oder anderer separater Ort)
  - Restore-Prozedur Schritt für Schritt.
  - Was tun, wenn eine Migration in Coolify fehlschlägt (Container-Log, Rollback-Merge auf den vorletzten `production`-Commit).
- Cron auf dem VPS: `mysqldump` + `rclone` (oder `mc` / `sftp`) auf externes Storage, täglich.
- Object-Storage: entweder Bucket-Versioning aktivieren (falls Hetzner das unterstützt — sonst zweiten „Backup"-Bucket via nächtlichem Sync). Roadmap-Memo hatte „Foto-Backup-Bucket" ebenfalls schon vermerkt.

**Aufwand:** ~½ Tag inkl. Test-Restore auf einer Staging-DB.

---

### [ ] 5. Root-Altlasten aufräumen
**Warum:** liegen im Docker-Build-Kontext (siehe Punkt 2). Sobald `.dockerignore` existiert, ist das Sicherheitsproblem weg — aber diese Dateien haben auch in der lokalen Working-Copy nichts verloren, weil sie leicht mit „echten" Files verwechselt werden.

**Zu entfernen bzw. umziehen:**
- `dbcheck.php` — war ein Ad-hoc-Debug-Script. Löschen.
- `laravel`, `laravel_test_1..10` — historische SQLite-Files. Löschen oder in `~/tmp/eventplaner-sqlite-archive/` umziehen.
- `hochzeits_einladung.json` — falls das echte Namen enthält: aus dem Repo-Kontext raus, an sicheren Ort verschieben.

**Aufwand:** ~10 min.

---

## Sollte zeitnah verbessert werden

### [ ] 6. E2E-Smoke-Suite (Playwright)
**Warum:** Frontend-Coverage ist 4 Specs für 164 Vue-Komponenten. Ein Golden-Path-Test fängt die 90 %-Regressionen ab.

**Umfang minimal viable:**
- Owner-Flow: Login → Guest anlegen → Invitation-Token generieren → auf `/guests` sichtbar
- Guest-Flow: `/api/auth/qr/{token}` scannen (simulieren) → Photo hochladen → in `/photos` sichtbar
- Projector: `/projector/{token}` lädt und rendert Bild

Playwright-Setup: `npm i -D @playwright/test`, Config gegen `http://localhost:8080` (Dev-Container), im CI gegen den Vite-Build.

**Aufwand:** ~1 Tag Setup + 2 Szenarien.

---

### [ ] 7. Pagination auf `/api/photos` und `/api/drinks/*`
**Warum:** aktueller `Photo::all()`-Style. Bei einer echten Hochzeit mit 500+ Bildern lädt der RN-Client alles auf einmal.

**Stellen:**
- `app/Http/Controllers/Api/PhotoController.php` (index) — `->paginate(50)` oder Cursor via `since_id`
- `app/Http/Controllers/Api/DrinkLogController.php` (index, stats)

**Client-Anpassung im separaten `eventplaner-app`-Repo dokumentieren.**

**Aufwand:** ~2 h.

---

### [ ] 8. `HandleInertiaRequests` verschlanken
**Warum:** teilt aktuell `active_event` + **Liste** `accessible_events` global. Für Superadmin heißt das: jede Server-Response enthält alle Event-Namen inkl. Owner-Metadaten. Kein akuter Leak, aber unnötiger Daten-Fußabdruck.

**Fix:** Liste nur da teilen, wo der Sidebar-Switcher tatsächlich rendert (dedizierter Endpunkt, Lazy-Load).

**Aufwand:** ~1 h.

---

### [ ] 9. `Settings.vue` schneiden
**Warum:** 2 929 Zeilen — jede Änderung dort ist teuer.

**Extraktions-Kandidaten:**
- `<PhonePreview>` (Home / Zusage / Fotos / Einstellungen als eigene Sub-Komponenten)
- `<VenueEditor>` (Nominatim-Autocomplete + Leaflet-Map)
- `<ColorSystemEditor>` (3 Palette-Picker + 9 Rollen-Selektoren)
- `<CoverUpload>` (mit Home-Text + Shadow)

Nur schneiden, nicht umschreiben. Business-Logik bleibt gleich.

**Aufwand:** ~½ Tag.

---

### [ ] 10. `config/cors.php` explizit scopen
**Warum:** Datei existiert aktuell gar nicht → Laravel-Default greift auf `/api/*`. Bearer-Token-API bricht ohne CORS nicht, aber sobald ein zweiter Web-Client hinzukommt, ist das eine Falle.

**Fix:**
```bash
php artisan config:publish cors
```
Dann `allowed_origins` auf `['https://eveplan.de', 'https://beta.hommrich.app']` scopen.

**Aufwand:** ~15 min.

---

### [ ] 11. Runbook `docs/RUNBOOK.md`
**Warum:** Was tun, wenn ein Coolify-Deploy hängt, eine Migration crasht, die Domain unerreichbar ist, das Object Storage nicht antwortet. Aktuell alles Kopfwissen.

Wird ohnehin bei Punkt 4 mit angelegt — hier nur nochmal als eigener Punkt gelistet, damit die Doku-Perspektive nicht untergeht.

---

## Kann langfristig optimiert werden

### [ ] 12. CSP ohne `unsafe-inline`
Bereits als Follow-up in `docs/gdpr/stage-4-security-headers.md` notiert. Nonce-basiert. Erfordert Inertia-Payload-Injection anzupassen. Nicht dringend.

### [ ] 13. `r2_key` → `object_key` umbenennen
Feld heißt aus historischen Gründen (Cloudflare R2) so, obwohl der Storage seit 2026-07-01 auf Hetzner läuft. Refactor beim nächsten größeren Schwung.

### [ ] 14. axe-a11y-Scan in CI
`@axe-core/playwright` beim Playwright-Setup mit einbauen — kostet fast nichts extra.

### [ ] 15. Cookie-Consent (Stage 7)
Bewusst zurückgestellt bis Tracking dazukommt. Solange keine nicht-notwendigen Cookies gesetzt werden, ist das kein Muss.

### [ ] 16. Sync-Check-Skript für Sub-Processor-Doku
Kleines CI-Skript, das sicherstellt: jeder Provider-Name in `docs/legal/sub-processors.md` taucht auch in `resources/js/pages/Legal/Privacy.vue` (Sektion 5) auf und umgekehrt. Verhindert Drift.

### [ ] 17. `ExampleTest.php` löschen
`tests/Feature/ExampleTest.php` und `tests/Unit/ExampleTest.php` — Laravel-Standard-Reste.

### [ ] 18. `.env.example` vervollständigen
Fehlt aktuell: `SANCTUM_STATEFUL_DOMAINS`, `MAIL_FROM_ADDRESS` (Resend-Setup), `RESEND_API_KEY` ist da, aber die Erklärungen dünn.

### [ ] 19. PHP-Version konsistent machen
`composer.json: ^8.2`, CI: `8.4`, `Dockerfile.prod: 8.3`, `CLAUDE.md: 8.3`. Einheitlich auf 8.3 oder 8.4.

---

## Follow-up (Entscheidung offen)

### [ ] Monitoring-/Alerting-Stack wählen
**Status:** offen, User entscheidet später.

**Kontext für die Entscheidung:**
- Error-Monitoring aktuell **nur** über Laravel-Log-File. Ein 500er auf `eveplan.de` fällt nur auf, wenn ein Gast sich meldet.
- Uptime-Monitoring: keins.
- Ziel: Sentry-ähnliche Fehlerübersicht + Uptime-Ping.

**Kandidaten, jeweils DSGVO-freundlich:**
- **Sentry SaaS (EU-Region Frankfurt)** — Free-Tier 5 k Events/Monat, DPA verfügbar. Schnellste Anbindung, aber weiteres Sub-Processor-Register-Item.
- **GlitchTip self-hosted** — Sentry-kompatible API, läuft auf demselben VPS oder einem zusätzlichen Container. Kein Sub-Processor-Add-on. Betriebs-Overhead.
- **BetterStack** (früher Logtail/Better Uptime) — Uptime + Log-Aggregation zusammen, EU-Region verfügbar.
- **UptimeRobot** — nur Uptime, aber gratis und ausreichend für den Anfang.

**Wenn entschieden, ist auf jeden Fall zu ergänzen:**
1. Anbieter in `docs/legal/sub-processors.md` eintragen (siehe Governance-Regel in `CLAUDE.md`)
2. Privacy.vue Sektion 5 synchronisieren
3. `.env.example` erweitern
4. Sentry/GlitchTip: `sentry/sentry-laravel` per Composer + Konfiguration nur in staging/production aktiv
5. Alerting-Ziel (E-Mail / Slack / Telegram) festlegen

---

## Fortschritt

| # | Titel | Priorität | Status |
|---:|---|---|---|
| 1 | IDOR-Guards | Muss | offen |
| 2 | `.dockerignore` | Muss | offen |
| 3 | Guest-Token-Expiry | Muss | offen |
| 4 | Backup + Restore | Muss | offen |
| 5 | Root-Aufräumen | Muss | offen |
| 6 | E2E-Smoke (Playwright) | Sollte | offen |
| 7 | API-Pagination | Sollte | offen |
| 8 | Inertia-Share verschlanken | Sollte | offen |
| 9 | `Settings.vue` schneiden | Sollte | offen |
| 10 | CORS explizit | Sollte | offen |
| 11 | Runbook | Sollte | offen |
| 12 | CSP ohne unsafe-inline | Kann | offen |
| 13 | `r2_key` → `object_key` | Kann | offen |
| 14 | axe-a11y in CI | Kann | offen |
| 15 | Cookie-Consent (Stage 7) | Kann | zurückgestellt |
| 16 | Sub-Processor-Sync-Check | Kann | offen |
| 17 | `ExampleTest.php` löschen | Kann | offen |
| 18 | `.env.example` vervollständigen | Kann | offen |
| 19 | PHP-Version konsistent | Kann | offen |
| — | Monitoring-Tool wählen | Follow-up | Entscheidung offen |

Beim Abarbeiten pro Punkt: Status auf „in Arbeit" / „erledigt (Commit-SHA)" setzen, damit die Historie im Doc bleibt.
