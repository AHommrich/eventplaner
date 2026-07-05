# Audit — Ampel-Punkte von Gelb auf Grün

Umsetzungsplan aus dem Audit vom 2026-07-05. Internes Working-File (deutsch), analog zu `AUDIT_ACTION_PLAN.md` / `SHOWCASE_PLAN.md`.

**Ziel:** Die drei gelben Ampeln des Gesamt-Audits — **Tests / Deployment / Betrieb** — auf grün ziehen. Die grünen Ampeln (Entwicklung, Security, Datenschutz, Dokumentation, Stakeholder) sind bereits stabil, hier bewusst nicht angefasst.

**Reihenfolge-Logik:** erst die schnellen Wins mit hoher Betriebs-Sichtbarkeit (Coolify + Sentry + Session-Encrypt), dann das eine echte Muss (Cross-Region-Backup), dann die grösseren Bausteine (E2E-Suite + Post-Deploy-Verify + A11y). Wenn E2E-Setup einmal steht, hängt axe-a11y ohne Zusatzaufwand rein — deshalb E2E vor A11y.

---

## Blockübersicht

| Block | Ampel-Ziel | Enthält | Aufwand kumuliert |
|---|---|---|---|
| A | Betrieb 🟡 → 🟢 | 1, 2, 3, 4 | ~1 h |
| B | Betrieb 🟡 → 🟢 (Datenschutz-relevant) | 5 | ~½ Tag |
| C | Deployment 🟡 → 🟢 | 6, 7 | ~2 h |
| D | Tests 🟡 → 🟢 | 8, 9 | ~1,5 Tage |
| E | Barrierefreiheit als Beifang | 10 | ~2 h (nur wenn D läuft) |

Wenn Du alles durchziehst, landest Du bei **~2,5 Tagen** verteilter Arbeit — realistisch also über 3–4 Sessions.

---

## Block A — Schnelle Betriebs-Wins (~1 h)

### [ ] 1. Coolify-Notifications aktivieren
**Warum:** Aktuell merkt kein Mensch, wenn ein Deploy fehlschlägt oder ein Container umkippt. Ohne Kanal bleibt Sentry allein — das sieht nur die App, nicht die Infra.

**Konkret:**
- Coolify → Notifications → **Discord** (oder Telegram) Webhook einrichten
- Events aktivieren: Deployment Success, Deployment Failed, Container Restart, Server Issue
- Test-Notification aus Coolify schicken → im Kanal ankommen lassen

**Definition of Done:** ein absichtlich failing Deploy (z. B. Syntax-Error auf `develop`, sofort revertet) löst eine Notification aus.

**Aufwand:** ~10 min.

---

### [ ] 2. Sentry-Test-Exception auf staging feuern
**Warum:** Wir haben Sentry frisch eingebunden, aber noch nicht End-to-End verifiziert. Ohne diesen Ping wissen wir nicht, ob Ingestion + EU-Region + DSN in Coolify wirklich greifen.

**Konkret:**
- SSH auf VPS → Container: `docker exec eventplaner-staging php artisan sentry:test`
- Sentry EU-Dashboard (`de.sentry.io`) → Issues → Test-Exception muss binnen 60 s erscheinen
- Environment-Tag muss `staging` sein (nicht `production` oder `local`)
- **Danach:** dasselbe für production, um sicherzugehen dass die DSN dort auch gesetzt ist

**Definition of Done:** je eine Test-Exception aus staging + production im Sentry-Dashboard sichtbar, korrekt getaggt.

**Aufwand:** ~10 min.

---

### [ ] 3. `SESSION_ENCRYPT=true` in staging + production
**Warum:** `SESSION_DRIVER=database` legt Session-Payloads (Auth-Kontext, CSRF-Token, `active_event_id`) unverschlüsselt in der DB ab. Ein DB-Leak wäre damit direkt ein Sitzungs-Hijack-Leak. Verschlüsselung kostet <1 ms, kein Grund es weiter aus zu lassen.

**Konkret:**
- Coolify → staging Env-Vars → `SESSION_ENCRYPT=true`
- Nach Redeploy: Login testen, `/dashboard` aufrufen, sicherstellen dass Session hält
- Danach production dieselbe Änderung (**sequentiell**, nie parallel — VPS-4-GB-Regel)
- Optional: `.env.example` mit Kommentar ergänzen (`SESSION_ENCRYPT=false` als Dev-Default, Kommentar-Zeile warum prod ≠ dev)

**Definition of Done:** in staging + prod ist die Env-Var gesetzt, Login funktioniert nach Redeploy.

**Aufwand:** ~10 min.

---

### [ ] 4. `@sentry/vue` fürs Web-Frontend
**Warum:** Backend-Fehler laufen jetzt in Sentry, aber jede JS-Exception im Web-Client (Vue-Router-Fehler, Inertia-Payload-Parse-Fehler, Upload-Client-Crash) bleibt unsichtbar. Damit wäre der Betriebs-Zustand konsistent instrumentiert.

**Konkret:**
- `npm i @sentry/vue`
- In `resources/js/app.ts` beim App-Bootstrap:
  ```ts
  import * as Sentry from '@sentry/vue'
  Sentry.init({
      app,
      dsn: import.meta.env.VITE_SENTRY_DSN,
      environment: import.meta.env.MODE,
      tracesSampleRate: 0.05,
      // Kein PII: keine IP-Erfassung, keine Session-Replay
      sendDefaultPii: false,
  })
  ```
- Neuer DSN aus derselben Sentry-Org (neues Projekt „eventplaner-web"), Region-Auswahl beim Anlegen wieder auf **EU/Frankfurt**
- `VITE_SENTRY_DSN` in Coolify staging + production setzen; `.env.example` mit leerem Platzhalter
- **Governance-Check (CLAUDE.md-Regel):** Sub-Processor-Eintrag „Sentry" abgedeckt schon einen Vertrag — nur den Purpose in `docs/legal/sub-processors.md` erweitern (»backend + browser errors«) und die Privacy-Sektion in `resources/legal/privacy.*.md` entsprechend anpassen; `updated_at` bumpen.
- Verifikation: absichtlich `throw new Error('sentry-vue-smoke')` in einem Test-Klick auf staging → im neuen Sentry-Projekt sichtbar

**Definition of Done:** JS-Smoke-Exception aus staging landet im „eventplaner-web"-Projekt im Sentry-Dashboard; Sub-Processor-Register + Privacy-Markdown zeigen den erweiterten Zweck.

**Hinweis RN-App:** analog im separaten Repo mit `@sentry/react-native` — bewusst getrennter Punkt, nicht Teil dieses Plans.

**Aufwand:** ~45 min inkl. Doku-Nachzug.

---

## Block B — Cross-Region-Foto-Backup (~½ Tag)

### [ ] 5. Zweiter Hetzner-Bucket in Helsinki + Nightly-Copy
**Warum:** Der aktuelle In-Bucket-Snapshot (`snapshots/YYYY-MM-DD/…`) schützt nur gegen versehentliches File-Delete. Bei Credential-Compromise, Bucket-Delete oder Regional-Ausfall in Nürnberg sind Original **und** Snapshot weg. Für die eigene Hochzeit vertretbares Risiko, für Fremdkunden-Betrieb nicht.

**Konkret:**
- Hetzner Console → Object Storage → neuer Bucket in `hel1` (Helsinki), Name z. B. `eveplan-photos-backup-hel1`
- Separater Access-Key **nur** mit `s3:PutObject` + `s3:ListBucket` auf dem Backup-Bucket (nicht auf dem Prod-Bucket!). Das entkoppelt Credential-Compromise auf dem App-Server vom Backup-Zugriff.
- Neuen Filesystem-Disk `s3_backup` in `config/filesystems.php` definieren, Env-Vars via Coolify:
  ```
  AWS_BACKUP_ACCESS_KEY_ID=…
  AWS_BACKUP_SECRET_ACCESS_KEY=…
  AWS_BACKUP_BUCKET=eveplan-photos-backup-hel1
  AWS_BACKUP_ENDPOINT=https://hel1.your-objectstorage.com
  ```
- `app/Console/Commands/BackupPhotoBucket.php` um zweiten Modus erweitern, z. B. `--target=hel1`, der `Storage::disk('s3_backup')->put(...)` benutzt.
  - **Wichtig:** cross-region CopyObject geht bei S3-kompatiblen Endpoints unterschiedlich — realistisch wird das eher `readStream → writeStream`, kein serverseitiges Copy. Das ist okay für einmal wöchentlich.
- Retention: Backup-Bucket eigene Retention (z. B. `--keep=8`), damit Backup länger vorgehalten wird als der In-Bucket-Snapshot.
- Scheduler in `routes/console.php` erweitern:
  ```php
  Schedule::command('photos:backup-to-prefix --target=hel1')->weekly()->sundays()->at('04:30');
  ```
- **Governance-Check:** `docs/legal/sub-processors.md` — der Hetzner-Eintrag deckt beides ab (»Falkenstein + Nürnberg + Helsinki«), Location-Zeile aktualisieren; `docs/RUNBOOK.md` §3.2 „Photo backup" um den Cross-Region-Layer erweitern; „Known gaps"-Sektion entsprechend entfernen.
- Testabdeckung: neuen Pest-Test analog zu `BackupPhotoBucketTest`, aber mit `Storage::fake('s3_backup')` zusätzlich, um den Cross-Disk-Copy-Pfad zu verifizieren.

**Definition of Done:** einmal manuell `docker exec eventplaner-prod php artisan photos:backup-to-prefix --target=hel1 --dry-run` sieht die Files, echter Lauf legt sie in Helsinki ab; Hetzner-Console zeigt Objekte im hel1-Bucket; RUNBOOK aktualisiert; Sub-Processor-Register + Privacy passen.

**Aufwand:** ~½ Tag inkl. Tests + Doku.

---

## Block C — Deployment-Verifikation (~2 h)

### [ ] 6. Post-Deploy-Smoke-Test
**Warum:** Migrations laufen im `Dockerfile.prod`-Startup blind (Zeile 128). Wenn eine schiefgeht, kommt der Container hoch, aber Routen sind kaputt — Coolify sieht nur „Container läuft", die Fehler landen erst dann in Sentry, wenn ein echter User draufklickt.

**Konkret:** zwei parallel mögliche Wege, einer reicht:

**Weg A (empfohlen):** GitHub Action nach Push auf staging/production, die eine Weile wartet + eine Handvoll Routen pingt.
- Neue `.github/workflows/post-deploy.yml`:
  ```yaml
  on:
    push:
      branches: [staging, production]
  jobs:
    smoke:
      runs-on: ubuntu-latest
      steps:
        - name: Wait for deploy
          run: sleep 90
        - name: Smoke test staging
          if: github.ref_name == 'staging'
          run: |
            curl -fsS https://beta.hommrich.app/up
            curl -fsS -o /dev/null -w "%{http_code}" https://beta.hommrich.app/login | grep -q 200
            curl -fsS -o /dev/null -w "%{http_code}" https://beta.hommrich.app/impressum | grep -q 200
        - name: Smoke test production
          if: github.ref_name == 'production'
          run: |
            curl -fsS https://eveplan.de/up
            curl -fsS -o /dev/null -w "%{http_code}" https://eveplan.de/login | grep -q 200
            curl -fsS -o /dev/null -w "%{http_code}" https://eveplan.de/impressum | grep -q 200
  ```
- Bei Fehlschlag: GitHub-Action-Status wird rot → Coolify-Notification aus Punkt 1 zieht (via GitHub → Discord).

**Weg B:** Coolify hat unter „Custom Command after Deployment" die Möglichkeit, ein Shell-Skript laufen zu lassen. Denselben Curl-Block dort einhängen. Vorteil: läuft nicht auf GitHub-Runner. Nachteil: läuft im Container gegen sich selbst, kein echter Außen-Ping.

**Definition of Done:** ein absichtlich kaputter Merge (z. B. Route entfernt) → Post-Deploy-Smoke rot → Notification kommt.

**Aufwand:** ~1 h.

---

### [ ] 7. Rollback-Prozedur konkretisieren
**Warum:** `docs/RUNBOOK.md` §4 nennt „Deploy fails" nur allgemein. Für echten Rollback brauchen wir einen Ablauf, der auch nachts um 2 funktioniert, wenn keiner klaren Kopf hat.

**Konkret:** `docs/RUNBOOK.md` einen neuen Abschnitt §4.4 „Rollback nach fehlgeschlagenem Deploy" ergänzen:

1. **Immer zuerst:** in Sentry + Post-Deploy-Smoke schauen, welche Route bricht — oft ist eine hotfixbare Kleinigkeit schneller als ein Rollback.
2. **Wenn Rollback nötig:**
   ```bash
   git checkout production
   git log --oneline -5           # letzten grünen SHA identifizieren
   git revert <bad-sha> --no-edit
   git push origin production
   ```
3. **Nie** `git reset --hard` auf `production` — das ist die Force-Push-Falle.
4. Migrations die schon liefen: nur rückgängig machen, wenn die Down-Methode robust ist. Andernfalls Forward-Fix bevorzugen (neue Migration die den Schaden korrigiert). Notiz: die meisten unserer Migrations sind rückwärts-safe, aber die neueren `add_erasure_fields_to_guests` u. ä. rücken Spalten hinzu — Down-Delete verliert Daten. Deshalb: **Forward-Fix als Default.**
5. Nach Rollback: Post-Deploy-Smoke aus Punkt 6 muss grün werden, sonst weitere Schritte notwendig.

**Definition of Done:** RUNBOOK §4.4 existiert, ist so konkret dass man ihn nachts ohne Nachdenken abarbeiten kann.

**Aufwand:** ~30 min.

---

## Block D — Tests: E2E-Smoke + Frontend-Coverage (~1,5 Tage)

### [ ] 8. Playwright-Smoke-Suite
**Warum:** Frontend-Coverage ist aktuell 4 Vitest-Specs für 164 Vue-Dateien. Ein Golden-Path-E2E-Test fängt 90 % der regressiven Bugs, die ein Unit-Test nie sieht (Inertia-Payload-Fehler, Vue-Router-Bruch, Session-Timing).

**Umfang:** genau drei Szenarien, mehr nicht — nicht in eine E2E-Coverage-Falle rennen.

**Konkret:**
- `npm i -D @playwright/test`
- `npx playwright install chromium` (nur Chromium reicht als Basis-Browser)
- `playwright.config.ts`:
  ```ts
  export default defineConfig({
      testDir: './tests/e2e',
      timeout: 30_000,
      use: { baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080' },
      webServer: process.env.CI ? undefined : {
          command: 'echo "using existing docker stack"',
          url: 'http://localhost:8080',
          reuseExistingServer: true,
      },
  })
  ```
- Drei Test-Files unter `tests/e2e/`:
  - `owner.spec.ts` — Login als Owner (Test-User via Seeder), Gast anlegen, Invitation-Token generieren, Guest taucht in `/guests` auf.
  - `guest.spec.ts` — GET `/api/auth/qr/{token}` → Token aus Response, danach `POST /api/photos` mit Test-JPEG, danach GET `/api/photos` → hochgeladenes Bild in Liste. Reines API-E2E, ohne UI — schnell und stabil.
  - `projector.spec.ts` — GET `/projector/{token}` lädt, mindestens ein `<img>` im DOM, `document.title` enthält Event-Namen.
- Test-User-Seeder: `DemoDataSeeder` hat schon `demo@eveplan.app / demo-1234` — als E2E-Fixture wiederverwenden.
- CI-Integration: neue Job-Section in `.github/workflows/tests.yml`:
  ```yaml
  - name: Playwright
    run: npx playwright test
  ```
  Reihenfolge: nach Backend-Tests, gegen dieselbe MariaDB-Service-Instanz + `php artisan serve` als Background-Prozess.
- **Nicht** gegen staging/production laufen lassen — E2E schreibt in die DB, das ist eine Local-CI-only-Sache.

**Definition of Done:** `npx playwright test` grün auf CI; drei Szenarien laufen; ein absichtlicher Regressions-Commit (z. B. Route auskommentiert) → CI rot.

**Aufwand:** ~1 Tag Setup + Szenarien.

---

### [ ] 9. Vitest-Coverage-Ausbau (Ziel-Level, nicht Ziel-Prozent)
**Warum:** Nicht jede Vue-Komponente braucht einen Unit-Test — aber die zentralen Bausteine ohne Test sind aktuell:
- `resources/js/pages/Photos/Index.vue` — Tab-Wechsel, Upload-Dialog-State
- `resources/js/pages/PhotoGame/Index.vue` — Delta-Logik-Anzeige
- `resources/js/pages/Event/Settings.vue` — Farbsystem-Auflösung (Palette → Rollen)
- `resources/js/lib/*` — Helpers, falls existent

**Konkret:** pro Datei einen Vitest-Spec mit 2–3 verhaltensorientierten Cases (nicht Snapshot-Spam). Zusammen ca. 10–12 neue Cases.

- Priorität 1: **Farbsystem-Resolver in `Settings.vue`** — die `resolve()`-computed die aus Palette-Keys Hex-Werte macht. Reine Logik, testbar ohne Mount.
- Priorität 2: Photo-Game-Delta-Anzeige — welche Task-Description wird gerendert (Base / Override modified / Override added).
- Priorität 3: Photos-Index Tab-State (welcher Tab aktiv bei welcher URL-Query).

**Definition of Done:** `npm run test:coverage` zeigt ≥60 % Statement-Coverage für die drei zentralen Pages/Services; CI-Job grün.

**Aufwand:** ~4 h.

---

## Block E — A11y als Beifang (~2 h)

### [ ] 10. axe-a11y im E2E-Setup
**Warum:** Sobald Block D steht, ist axe für ~10 zusätzliche Zeilen dabei. Bringt Barrierefreiheit von 🟡 auf 🟢 ohne separaten Aufwand. Deshalb ans Ende — nur sinnvoll wenn Playwright läuft.

**Konkret:**
- `npm i -D @axe-core/playwright`
- Neuer Test-File `tests/e2e/a11y.spec.ts`:
  ```ts
  import { test, expect } from '@playwright/test'
  import { AxeBuilder } from '@axe-core/playwright'

  const ROUTES = ['/', '/login', '/register', '/impressum', '/datenschutz']

  for (const route of ROUTES) {
      test(`a11y: ${route}`, async ({ page }) => {
          await page.goto(route)
          const results = await new AxeBuilder({ page })
              .withTags(['wcag2a', 'wcag2aa'])
              .analyze()
          expect(results.violations).toEqual([])
      })
  }
  ```
- Erste Läufe werden vermutlich 3–5 Violations werfen (fehlende `<label>`, Kontrast, Alt-Text). Die einfachen fixen, die Farbsystem-bezogenen als Follow-up notieren (Kontrast-Guard im Color-Editor ist ein eigener Bug).
- Zusätzlich: **Kontrast-Guard im Color-Editor** — im `ColorSystemEditor`-Bereich der `Settings.vue` einen Warn-Hinweis rendern, wenn `color_card_text` auf `color_card_bg` einen WCAG-AA-Kontrast von < 4.5 hat. Das ist der schärfste offene A11y-Punkt, weil der Farbeditor user-driven ist.

**Definition of Done:** axe-Tests grün auf den 5 öffentlichen Routen; Kontrast-Warnung im Color-Editor sichtbar wenn User Kombi wählt die durchfällt.

**Aufwand:** ~2 h (Setup + erste Fixes).

---

## Offene Follow-ups aus dieser Runde

### heic2any → WASM-Decoder
- `heic2any` benutzt `new Function(...)` in seinem Worker. Deshalb steht heute `'unsafe-eval'` in `worker-src` der CSP (`app/Http/Middleware/SecurityHeaders.php`). Der Scope ist auf Worker-Kontext begrenzt, aber es bleibt ein bewusster Sicherheits-Trade-off. Alternativen: `libheif-js` als reine WASM-Version, oder serverseitige HEIC-Konvertierung (Imagick kann das schon — clientseitige Konvertierung war eine Bandbreiten-Optimierung). **Aufwand:** ~2–4 h Recherche + Umbau des Cover-Upload-Flows in `resources/js/pages/Event/Settings.vue`.

### Welcome.vue a11y-Sweep
- `tests/e2e/a11y.spec.ts` — der Test für `/` ist `test.fixme` (nicht red, aber sichtbar in Reports). Lokal `E2E_BASE_URL=http://localhost:8080 npx playwright test a11y --grep '/ passes'` fahren, den axe-Output lesen (die Konsole-Zeile mit den Violation-IDs), die 2–3 offensichtlichen Findings (vermutlich Kontrast im Hero + fehlende aria-labels auf Icon-Buttons) in `resources/js/pages/Welcome.vue` fixen. Danach `.fixme` in Line 44 des specs entfernen. **Aufwand:** ~1 h.

## Was NICHT in diesem Plan ist (bewusst)

- **CSP ohne `unsafe-inline`** — Härtungspunkt, aber Security-Ampel ist schon 🟢. Bleibt Kann-Punkt in `AUDIT_ACTION_PLAN.md`.
- **`Settings.vue` in Sub-Komponenten schneiden** — Wartbarkeit, keine Ampel-Verschiebung. Kann-Punkt.
- **`r2_key` → `object_key`** — kosmetisch, keine Ampel-Bewegung.
- **`ExampleTest.php` löschen + `.env.example` vervollständigen** — Kleinkram, sammeln wir bei nächster Gelegenheit ein.
- **PHP-Version-Konsistenz** — kosmetisch, keine akute Auswirkung.
- **RN-App Sentry** — separates Repo, separater Plan.

Falls Du eine dieser Sachen doch mit reinnehmen willst, sag Bescheid — sie sind bewusst nicht drin, weil sie die Ampel nicht bewegen.

---

## Fortschritt

| Block | # | Titel | Ampel-Ziel | Aufwand | Status |
|---|---:|---|---|---|---|
| A | 1 | Coolify-Notifications | Betrieb | 10 min | offen |
| A | 2 | Sentry-Test-Exception staging + prod | Betrieb | 10 min | offen |
| A | 3 | `SESSION_ENCRYPT=true` | Security-Härtung | 10 min | offen |
| A | 4 | `@sentry/vue` Web-Frontend | Betrieb | 45 min | **Code fertig, wartet auf DSN in Coolify** |
| B | 5 | Cross-Region-Backup (hel1) | Betrieb | ½ Tag | **Code + Tests + Doku fertig, Aktivierung bewusst zurückgestellt (Scheduler `when()`-gated)** |
| C | 6 | Post-Deploy-Smoke-Test | Deployment | 1 h | **erledigt (post-deploy.yml)** |
| C | 7 | Rollback-Prozedur konkretisieren | Deployment | 30 min | **erledigt (RUNBOOK §4.4)** |
| D | 8 | Playwright-Smoke-Suite | Tests | 1 Tag | **erledigt (config + 9 tests + CI e2e.yml)** |
| D | 9 | Vitest-Ausbau (3 Bereiche) | Tests | 4 h | **erledigt (34 tests, +18)** |
| E | 10 | axe-a11y + Kontrast-Guard | A11y (Beifang) | 2 h | **erledigt bis auf Welcome-Page** (4 von 5 Routen grün, `/` als `test.fixme` markiert — Follow-up unten) |

Beim Abarbeiten pro Punkt: Status auf „in Arbeit" bzw. „erledigt (Commit-SHA)" setzen, damit die Historie im Doc bleibt — analog zur Konvention in `AUDIT_ACTION_PLAN.md`.

---

## Ampel-Zwischenstände beim Durchziehen

- Nach **Block A** (1–4): Betrieb 🟡 → 🟢 in Sicht, aber noch nicht ganz — Punkt 5 fehlt für echtes Grün.
- Nach **Block B** (5): Betrieb 🟢.
- Nach **Block C** (6–7): Deployment 🟢.
- Nach **Block D** (8–9): Tests 🟢.
- Nach **Block E** (10): Barrierefreiheit 🟡 → 🟢 zusätzlich mitgenommen.

Wenn Blöcke A + B + C durch sind, hat das Projekt praktisch keine gelbe Ampel mehr — Block D + E sind dann Reife-Investition, nicht Blocker.
