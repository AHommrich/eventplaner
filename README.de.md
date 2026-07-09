# Eventplaner

[![Tests](https://github.com/AHommrich/eventplaner/actions/workflows/tests.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/tests.yml)
[![Lint](https://github.com/AHommrich/eventplaner/actions/workflows/lint.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/lint.yml)
[![E2E](https://github.com/AHommrich/eventplaner/actions/workflows/e2e.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/e2e.yml)
[![License](https://img.shields.io/badge/License-All%20Rights%20Reserved-red.svg)](LICENSE)

Hochzeits- und Eventplaner als Progressive Web App mit React-Native-Companion: Gäste-, RSVP- und Foto-Verwaltung, Trinkspiel, Fotospiel und eine Token-geschützte Projektor-Diashow für die Feier.

> Auch auf Englisch verfügbar: [README.md](README.md)

![Event-Einstellungen mit Live-Handy-Preview](docs/screenshots/event-settings.png)

![Foto-Galerie mit Gast-Attribution](docs/screenshots/photos.png)

![Projektor-Diashow im Vollbild](docs/screenshots/projector.png)

---

## Feature-Highlights

Sortiert von „technisch interessant" zu „UX-Polish". Jeder Punkt verlinkt auf die zentrale Datei, die den Mechanismus implementiert.

1. **QR-Login für Gäste** — Sanctum-Bearer-Tokens via QR-Code. Solo-Gäste bekommen den Token sofort, Familien wählen erst ein Mitglied aus, damit ungenutzte Tokens andere nicht blockieren.
   → [`app/Http/Controllers/Api/QrAuthController.php`](app/Http/Controllers/Api/QrAuthController.php)

2. **Fotospiel mit Delta-Override-Modell** — globale Task-Kataloge (Allgemein + Event-Typ) plus pro-Event Overrides (`hidden` / `modified` / `added`). Standard-Aufgaben bleiben an einer Stelle pflegbar, Events speichern nur Deltas.
   → [`app/Http/Controllers/Api/PhotoGameController.php`](app/Http/Controllers/Api/PhotoGameController.php)

3. **Trinkspiel-Score mit physiologisch motivierten Multiplikatoren** — Basisformel `Liter × % × 10`, Shot-Multiplier 2.0 für Spirits (schnellere Absorption), Binge-Penalty 50 % ab drei Drinks in Folge, Negativ-Punkte für Wasser und Softdrinks.
   → [`app/Services/DrinkScoreService.php`](app/Services/DrinkScoreService.php)

4. **Konfigurierbares Farbsystem mit Live-Preview** — 3 Palette-Slots (primär/sekundär/tertiär) und 9 Rollen-Felder, die Keys statt Hex-Werten speichern. Palette ändern → alle Rollen folgen. Vier simulierte App-Screens reagieren live im Settings-Splitscreen.
   → [`resources/js/pages/Event/Settings.vue`](resources/js/pages/Event/Settings.vue)

5. **Saubere Drink-Catalog-Modellierung** — eine Zeile pro Getränketyp in `drink_catalog`, eine Zeile pro Größe in `drink_catalog_sizes`, Event-Auswahl in `drinks` referenziert beide. `drink_logs` speichern `amount_liter` denormalisiert, damit historische Punkte stabil bleiben.
   → [`app/Models/DrinkCatalog.php`](app/Models/DrinkCatalog.php), [`database/migrations/2026_03_29_000001_refactor_drink_catalog_to_type_only.php`](database/migrations/2026_03_29_000001_refactor_drink_catalog_to_type_only.php)

6. **Projektor-Diashow** — Public-Route via `projector_token`, Auto-Poll alle 10 s, 5 s Crossfade. Kontext-Label je Album: Gastname (Galerie), Beschreibung (Präsentation) oder Aufgabentext (Fotospiel).
   → [`resources/js/pages/Projector/Show.vue`](resources/js/pages/Projector/Show.vue)

7. **Stil-Presets für One-Click-Theme-Wechsel** — vordefinierte Paletten lassen sich auf ein Event anwenden, ohne Einzelfelder anzufassen.
   → [`app/Http/Controllers/EventStylePresetController.php`](app/Http/Controllers/EventStylePresetController.php)

8. **Mobile-PWA** — installierbar auf iOS/Android, eigenes Icon-Set, Offline-Fähigkeit via `vite-plugin-pwa`.
   → [`vite.config.ts`](vite.config.ts)

9. **i18n im Front- und Backend** — vue-i18n v11 für die Web-App, `Accept-Language`-Middleware für API-Responses, damit die React-Native-App lokalisierte Drink- und Fotospiel-Texte erhält.
   → [`resources/js/plugins/i18n.ts`](resources/js/plugins/i18n.ts)

10. **Public Landing Page** — drei-Schritte-Erklärung und Feature-Cards für Erstbesucher, ohne Auth-Zwang.
    → [`resources/js/pages/Welcome.vue`](resources/js/pages/Welcome.vue)

---

## Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | Laravel 12 (PHP 8.3) + Inertia.js + Sanctum |
| Web-Frontend | Vue 3 + TypeScript + Tailwind CSS 4 + Reka UI |
| Mobile | React Native (Expo) — separates Repo |
| Build | Vite 6 + `vite-plugin-pwa` |
| Storage | Hetzner Object Storage (Fotos, Nürnberg) |
| Mail | Resend |
| Deploy | Docker + Coolify |

---

## Quick Start

Das Projekt läuft komplett containerisiert. Migrations werden beim Start automatisch ausgeführt.

```bash
docker compose up -d
```

Den initialen Admin-User setzt du im laufenden Container:

```bash
docker exec laravel-app php artisan tinker
# > User::where('email', 'you@example.com')->update(['role' => 'admin'])
```

Vite läuft in einem eigenen Container und reicht Assets per HMR an die App durch.

---

## Deploy-Workflow

Drei langlebige Branches, die von Coolify beim Push automatisch deployt werden. Migrations laufen automatisch mit.

| Branch | Umgebung | Domain | Dockerfile |
|---|---|---|---|
| `develop` | lokale Entwicklung | — | `Dockerfile` (artisan serve, Port 8080) |
| `staging` | Staging | `beta.hommrich.app` | `Dockerfile.prod` (nginx + php-fpm) |
| `production` | Live | `eveplan.de` | `Dockerfile.prod` (nginx + php-fpm) |

`docker-compose.yml` ist bewusst branch-spezifisch (unterschiedliches Dockerfile, unterschiedliche exposed Ports). **Nie darf die develop-Version staging oder production überschreiben.** Jeder Merge nach `staging` oder `production` setzt diese Datei auf die Version des Ziel-Branches zurück.

Drei Safety-Nets stützen das ab:

- **Immer lokal mergen, nie über die GitHub-UI.** Ein Server-Side-Merge auf github.com ignoriert den `.gitattributes merge=ours`-Treiber *und* hat keinen Richtungs-Guard — der „Create pull request"-Button auf dem falschen Branch hat auf `develop` schon einmal die dev-Compose durch die Prod-Version überschrieben (PR #4). Alle Promotions laufen über die Terminal-Snippets unten.
- **`merge=ours`-Treiber** — `.gitattributes` markiert `docker-compose.yml` (und `Dockerfile`) als `merge=ours`, sodass git bei jedem *lokalen* Merge die Version des Ziel-Branches behält, statt einen Three-Way-Merge zu versuchen. Einmalig pro Clone aktivieren:

  ```bash
  git config --local merge.ours.driver true
  ```

- **CI-Guard** — `.github/workflows/compose-guard.yml` läuft bei jedem Push auf `staging` und `production` und lässt den Build failen, wenn die Compose-Datei auf das dev-Dockerfile oder `artisan serve` verweist. Falls ein manueller Merge doch die falsche Datei durchlässt, fängt das den Fehler ab, bevor Coolify redeployt.

### develop → staging

```bash
git checkout staging
git pull --ff-only origin staging          # abbrechen falls jemand anderes gepusht hat
git merge --no-ff --no-commit develop
git checkout HEAD -- docker-compose.yml    # staging-Compose-Datei behalten
grep -q 'Dockerfile.prod' docker-compose.yml || { echo "compose drift"; exit 1; }
git commit -m "Merge branch 'develop' into staging"
git push origin staging
git checkout develop
```

Coolify erkennt den Push, rebuildet und redeployt. Vor dem Weiterreichen gegenprüfen unter `https://beta.hommrich.app`.

### develop → production

Erst promoten, wenn Staging grün ist.

```bash
git checkout production
git pull --ff-only origin production       # abbrechen falls jemand anderes gepusht hat
git merge --no-ff --no-commit develop
git checkout HEAD -- docker-compose.yml    # production-Compose-Datei behalten
grep -q 'Dockerfile.prod' docker-compose.yml || { echo "compose drift"; exit 1; }
git commit -m "Merge branch 'develop' into production"
git push origin production
git checkout develop
```

> ⚠️ **Nie staging und production parallel pushen.** Zwei parallele Coolify-Redeploys erschöpfen das VPS-RAM. Immer sequentiell: erst `staging` pushen, warten bis `beta.hommrich.app` antwortet, dann `production` pushen.

---

## Architektur (Kurzfassung)

Das Datenmodell ist auf **Event als Root** zentriert: Gäste, Gruppen, Kategorien, Fotos, Getränke und Fotospiel-Aufgaben hängen alle an einem Event. Web-Owner authentifizieren sich klassisch über Email/Passwort (Sanctum-Session + Inertia), Gäste authentifizieren sich über QR-Code-Tokens (Sanctum-Bearer). Das aktive Event wird per Session gehalten und über einen Inertia-Share global an alle Vue-Seiten weitergegeben.

Schwerpunkt-Subsysteme sind:

- **Fotospiel** — Delta-Modell auf globalen Aufgaben-Katalogen
- **Trinkspiel** — Score-Service mit Shot-Multiplikator und Binge-Penalty
- **Projektor** — Token-geschützte Diashow mit Kontext-Labels
- **Farbsystem** — Palette + Rollen-Mapping mit Live-Preview

Die ausführliche Architekturbeschreibung mit ER-Diagramm, Auth-Schichten und Subsystem-Details liegt in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Tests

| Stack    | Befehl                                  | Was läuft                                                              |
| -------- | --------------------------------------- | ---------------------------------------------------------------------- |
| Backend  | `composer test`                         | Pest gegen eine eigene `laravel_test`-DB mit Safety-Guard              |
| Backend  | `./vendor/bin/pest --filter=DrinkScore` | Einzelne Test-Datei                                                    |
| Frontend | `npm test`                              | Vitest, Worker-Threads parallel                                        |
| Frontend | `npm run test:watch`                    | Hot-Reload-Tests                                                       |
| Coverage | `./vendor/bin/pest --coverage`          | Backend-Coverage (Clover-XML + Text-Report); Vitest via `--coverage`   |

**Abdeckung:**

- ~25 Backend-Test-Dateien mit ~200 Cases — alle API-Endpunkte, alle Kernfeature-Web-Controller, Services mit Edge Cases, Auth-Flows, Permission-Middlewares
- 4 Frontend-Specs für ConfirmDialog, CreatableCombobox, InfoTooltip und das i18n-Plugin (16 Cases)

Test-Aufbau, Coverage-Ziele und Strategie pro Schicht stehen in [`docs/SHOWCASE_PLAN.md`](docs/SHOWCASE_PLAN.md).

---

## Companion-App (React Native)

Die mobile App liegt unter [**github.com/AHommrich/eventplaner-app**](https://github.com/AHommrich/eventplaner-app) und teilt sich mit der Web-App nur die HTTP-API. Sie deckt QR-Login, Foto-Galerie inkl. Upload und dynamisches Theming via `/api/event/info` ab. Das Mobile-Repo hat einen eigenen Portfolio-Refactor durchlaufen (siehe [`docs/REFACTOR_PLAN.md`](https://github.com/AHommrich/eventplaner-app/blob/main/docs/REFACTOR_PLAN.md) dort) und ist feature-complete für den realen Hochzeitseinsatz.

---

## DSGVO / Datenschutz

Das Projekt wird aus Deutschland betrieben und ist dokumentiert DSGVO-ready:

- Impressum unter `/impressum` (§5 DDG)
- Datenschutzerklärung unter `/datenschutz` (Art. 13 DSGVO), Signup-Consent verpflichtend
- Data-Export-Endpoint für das Auskunftsrecht (Art. 15)
- Kaskadierendes Löschen von Fotos in Hetzner Object Storage bei Account-/Event-Löschung (Art. 17)
- Geplante Retention-Cleanups für abgelaufene Invitation-Tokens und abgesagte Gäste (Art. 5)
- Sub-Prozessor-Register in [`docs/legal/sub-processors.md`](docs/legal/sub-processors.md)

Der vollständige Plan mit den einzelnen Etappen liegt in [`docs/GDPR_COMPLIANCE_PLAN.md`](docs/GDPR_COMPLIANCE_PLAN.md).

---

## Dokumentation

Neben dieser README liegen einige gezielte Dokumente im Repo, jeweils mit klarem Zweck:

- [`docs/GETTING_STARTED.md`](docs/GETTING_STARTED.md) — Projekt lokal aufsetzen inkl. der typischen Fußfallen
- [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) — Branch-Modell, Commit-Konvention, Doc-Sync-Regel
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — Subsystem-Deep-Dives (Fotospiel, Drink-Score, Projektor, Farbsystem)
- [`SECURITY.md`](SECURITY.md) — Sicherheits-Disclosure

---

## Lizenz

Alle Rechte vorbehalten. Siehe [LICENSE](LICENSE). Der Code ist öffentlich einsehbar (Portfolio-Zweck); Nutzung, Fork oder Weiterverbreitung nur mit schriftlicher Zustimmung.
