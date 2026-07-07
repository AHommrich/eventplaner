# Repo extern präsentierbar machen — Übersicht

## Fortschritt (Master-Tracker)

Hier abhaken nach Push der jeweiligen Etappe — gibt dir auf einen Blick wo du stehst.

- [x] **Tag 1** — README + LICENSE → [tag-1-readme-license.md](showcase/tag-1-readme-license.md)
- [x] **Tag 2** — ARCHITECTURE.md + Doc-Blocks → [tag-2-architecture-docs.md](showcase/tag-2-architecture-docs.md) (Sweep-Rest in Tag 3a–3d)
- [x] **Tag 3a** — Pest-Setup + Critical Services → [tag-3a-pest-services.md](showcase/tag-3a-pest-services.md)
- [x] **Tag 3b** — Alle API-Endpunkte → [tag-3b-api-tests.md](showcase/tag-3b-api-tests.md)
- [x] **Tag 3c** — Web-Controller Kernfeatures → [tag-3c-web-tests.md](showcase/tag-3c-web-tests.md)
- [x] **Tag 3d** — Auth + Album-Routing + Middlewares → [tag-3d-auth-album-middleware.md](showcase/tag-3d-auth-album-middleware.md)
- [x] **Tag 4** — Frontend nur kritisch (Vitest) → [tag-4-frontend-tests.md](showcase/tag-4-frontend-tests.md)
- [x] **Tag 5** — CI + Coverage + Badges → [tag-5-ci-coverage.md](showcase/tag-5-ci-coverage.md)

Jede Etappe ist **self-contained** — du kannst einzeln einsteigen, die Akzeptanzkriterien am Ende jeder Datei abhaken, committen, fertig.

---

Der Eventplaner soll extern zugänglich gemacht werden, um Können zu zeigen. Dieser Plan macht das Repo so präsentierbar, dass ein Außenstehender in 5 Minuten versteht **was das Projekt kann**, in 30 Minuten **wie es funktioniert**, und am Code sieht, dass **Test- und CI-Disziplin** vorhanden sind.

## Heute-Stand (Stand 2026-06-29)

- **README ist 8 Zeilen Merge-Notiz** — externe Leser verstehen nichts.
- **CI läuft schon** (`.github/workflows/tests.yml`, `lint.yml`) mit Pint, ESLint, Prettier, PHPUnit, npm build — solides Fundament.
- **Backend-Tests**: nur Starter-Kit-Tests (Auth, Settings, Dashboard) — keine Business-Logik abgedeckt.
- **Frontend-Tests**: keine, kein Vitest, keine Specs für die 162 Vue-Komponenten.
- **Statisches Code-Tooling**: TS strict ist an (gut), aber kein PHPStan/Larastan.
- **LICENSE fehlt** als Datei (composer.json deklariert MIT).
- **Code ist sauber strukturiert** (keine TODOs, kein `dd()`, kein `console.log`), aber komplexe Services sind kommentarlos.

## Leitlinien

- **Backend tief, Frontend nur kritisch** — der Wert liegt in den Backend-Tests (API-Kontrakt zur React-Native-App + Kernfeatures).
- **Vorzeige-Qualität statt Breite** — kritische Stellen mit Edge Cases, einfache Stellen mit Smoke-Test für Vollständigkeit.
- **Lesbarkeit hat höchste Priorität** — README + ARCHITECTURE.md + gezielte Doc-Blocks, damit der Code erzählt was er tut.
- **Pragmatischer Scope** — kein PHPStan, keine Pre-commit-Hooks. Coverage-Threshold im CI, aber moderat (50% Backend).
- **Companion-App** wird nur in der README erwähnt, kein Code-Übergriff.
- **Keine git-Aktionen durch Claude** — alle Commits und Pushes machst du selbst.

## Tages-Etappen

| # | Etappe | Datei | Aufwand | Outcome |
|---|---|---|---|---|
| 1 | README + LICENSE | [tag-1-readme-license.md](showcase/tag-1-readme-license.md) | 2h | Fremder versteht in 5min was das Projekt kann |
| 2 | ARCHITECTURE.md + Doc-Blocks (Sweep) | [tag-2-architecture-docs.md](showcase/tag-2-architecture-docs.md) | 3h¹ | Reviewer findet sich in 20-30min in der Architektur zurecht |
| 3a | Pest-Setup + Critical Services | [tag-3a-pest-services.md](showcase/tag-3a-pest-services.md) | 3h | Pest läuft parallel, 3 Unit-Test-Files für DrinkScore, PhotoGameTaskPool, ColorRoleResolver |
| 3b | Alle API-Endpunkte (React-Native-Kontrakt) | [tag-3b-api-tests.md](showcase/tag-3b-api-tests.md) | 4h | 7 Feature-Test-Files für alle 16 API-Endpoints + Guest-Middlewares |
| 3c | Web-Controller (Kernfeatures) | [tag-3c-web-tests.md](showcase/tag-3c-web-tests.md) | 5h | 14 Test-Files für Gäste, Drinks, Fotospiel, Event-Settings, Projektor, Invitations, FoodSpecials |
| 3d | Auth-Flows + Album-Routing + Middlewares | [tag-3d-auth-album-middleware.md](showcase/tag-3d-auth-album-middleware.md) | 2h | 3 Test-Files schließen die letzten Sicherheits-/Login-Lücken |
| 4 | Frontend nur kritisch | [tag-4-frontend-tests.md](showcase/tag-4-frontend-tests.md) | 3h | Vitest läuft, 4 Specs für die wirklich kritischen Komponenten |
| 5 | CI + Coverage + Badges | [tag-5-ci-coverage.md](showcase/tag-5-ci-coverage.md) | 2h | CI auf develop/staging/production, Coverage-Berichte + Badges, README final |

→ **Gesamt ~24h, verteilt auf 8 Etappen.** Jede Etappe ist ein eigenständiger, mergebarer Stand — du musst nicht alle hintereinander machen.

¹ Tag 2 selbst schreibt ARCHITECTURE.md + 2 Docblocks. Der breite Docblock-Sweep über Services / API-Controller / Web-Controller / Middlewares läuft **parallel zu Tag 3** mit, weil dort dieselben Dateien ohnehin für Tests durchgegangen werden — Doppelarbeit vermieden.

## Erwartete Coverage am Ende

| | Tests | Coverage |
|---|---|---|
| Backend | **27 Files, ~130 Cases** | **~65% Line-Coverage** |
| Frontend | 4 Files, 15 Cases | ~8% Line, aber die 4 wirklich kritischen Komponenten |

Coverage-Badge im README zeigt **Backend-Coverage** (die ehrliche Zahl).

## Out of Scope (bewusst nicht eingeplant)

- **PHPStan/Larastan**: Wertvoll, aber zieht laufende Pflege nach sich. Erst aufsetzen, wenn jemand aktiv mit dem Repo arbeitet.
- ~~**Playwright/E2E**~~: Ursprünglich aus dem Showcase ausgeklammert. Mittlerweile eingezogen (`tests/e2e/*.spec.ts`), deckt öffentliche Smoke-Routes, a11y auf allen public routes inkl. `/`, Owner-Settings-Flow, Guest-API-Journey und Projektor-Rendering ab. Läuft in `.github/workflows/e2e.yml` gegen ephemeres `artisan serve` + MinIO.
- **Mehr Frontend-Tests**: 162 Vue-Komponenten test-mäßig hochzufahren wäre 1-2 Wochen Arbeit ohne klaren Showcase-Mehrwert.
- **CONTRIBUTING.md / Issue-Templates**: Nur sinnvoll, wenn echte Community ankommt.
- **Pre-commit hooks**: Persönliche Disziplin reicht; CI fängt den Rest.
- **React Native Repo**: Nur in README erwähnt, kein Code-Übergriff.
- **Admin-User-Verwaltung tief testen**: Admin-Feature, nicht Gast-Kern. Smoke-Test reicht.
- **Dedizierte Model-Tests**: Eloquent ist Framework. Models werden implizit über Feature-Tests gestreift.

## Wenn du Zeit/Lust für mehr hast (Folgewochen)

**Tiefer-Stufe (1-2 Tage Aufwand):**
1. **PHPStan Level 5** mit Larastan (`composer require --dev larastan/larastan`, `phpstan.neon` mit Level 5, in CI). Erwarte ~1 Tag Aufräumarbeit beim ersten Lauf.
2. **Playwright E2E** für 2-3 Critical Paths (QR-Login + Foto-Upload + RSVP-Zusage).
3. **Frontend-Coverage hochschrauben** auf 30%+ mit Page-Mount-Tests für alle wichtigen Seiten.
4. **React-Native-Repo** in gleicher Manier aufpolieren (separater Plan).

**Schnelle Showcase-Verstärker (je 15-30min, kein Pflicht-Bestandteil):**
5. **`.github/dependabot.yml`** mit composer + npm weekly — signalisiert „kümmert sich um Security-Updates", 1 File, ~20 Zeilen.
6. **CodeQL Security Scan** (`.github/workflows/codeql.yml`) — GitHub-kostenlos für Public Repos, „Sicherheit denkt mit", 1 Workflow-File.
7. **`SECURITY.md`** mit kurzer Disclosure-Policy — Standard für ernsthafte Public Repos.
8. **`.github/PULL_REQUEST_TEMPLATE.md`** — eher Kosmetik, aber konsistente PR-Bodies wenn jemand contributed.
