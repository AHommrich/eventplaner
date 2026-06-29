# Tag 5 — CI + Coverage + Badges + Final-Polish

**Aufwand**: ~2h
**Outcome**: CI läuft auf allen relevanten Branches (develop, staging, production), beinhaltet Pest + Vitest mit Coverage-Reports, README hat Test- und Coverage-Badges, Repo ist bereit für externe Augen.

## Schritte

### 1. `.github/workflows/tests.yml` erweitern

**Trigger-Branches**: `develop`, `staging`, `production` (statt `main`, der nicht mehr existiert).

```yaml
on:
  push:
    branches: [develop, staging, production]
  pull_request:
    branches: [develop, staging, production]
```

**Composer-Cache hinzufügen** (npm-Cache ist schon da via `cache: 'npm'` an setup-node):
```yaml
- name: Get Composer cache directory
  id: composer-cache
  run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

- name: Cache Composer dependencies
  uses: actions/cache@v4
  with:
    path: ${{ steps.composer-cache.outputs.dir }}
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: ${{ runner.os }}-composer-
```

Vor dem `composer install`-Step einfügen. Spart bei Cache-Hit ~30-60s pro CI-Run.

**(Optional) MariaDB-Service** statt SQLite — fängt MariaDB-spezifische Bugs (FK `ON DELETE SET NULL` auf NOT NULL, `renameColumn` UNSIGNED-Verlust, SSL-Quirks) die SQLite still schluckt:

```yaml
services:
  mariadb:
    image: mariadb:11
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: eventplaner_test
    ports: ['3306:3306']
    options: >-
      --health-cmd="healthcheck.sh --connect --innodb_initialized"
      --health-interval=10s --health-timeout=5s --health-retries=5
```

`.env`-Override in CI: `DB_CONNECTION=mariadb`, `DB_HOST=127.0.0.1`, `DB_DATABASE=eventplaner_test`. Lokal weiterhin `sqlite :memory:` für schnelles Feedback. **Empfehlung**: erstmal nur `develop` + `production` mit MariaDB, `staging` mit SQLite — sonst CI-Dauer verdoppelt sich.

**PHP-Test-Step auf Pest mit Coverage umstellen**:
```yaml
- name: Run Pest tests with coverage
  run: ./vendor/bin/pest --parallel --ci --coverage --min=50 --coverage-clover=coverage/clover.xml
```

`--min=50` als ehrlicher Backend-Coverage-Threshold (siehe Plan-Übersicht — wir erwarten ~65%).

**Vitest-Step nach `npm ci` und vor `npm run build`**:
```yaml
- name: Run Vitest
  run: npm run test:coverage -- --reporter=verbose
```

**Coverage-Upload zu Codecov** (optional, kostenlos für Public Repos):
```yaml
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v4
  with:
    files: ./coverage/clover.xml,./coverage/coverage-final.json
    flags: backend,frontend
    fail_ci_if_error: false
  env:
    CODECOV_TOKEN: ${{ secrets.CODECOV_TOKEN }}
```

### 2. `.github/workflows/lint.yml` erweitern

Gleiche Trigger-Branches wie `tests.yml`: `develop`, `staging`, `production`. Sonst unverändert.

```yaml
on:
  push:
    branches: [develop, staging, production]
  pull_request:
    branches: [develop, staging, production]
```

### 3. README-Badges ergänzen

Im Hero-Block von `README.md`:

```markdown
[![Tests](https://github.com/USER/eventplaner/actions/workflows/tests.yml/badge.svg)](https://github.com/USER/eventplaner/actions/workflows/tests.yml)
[![Lint](https://github.com/USER/eventplaner/actions/workflows/lint.yml/badge.svg)](https://github.com/USER/eventplaner/actions/workflows/lint.yml)
[![Coverage](https://codecov.io/gh/USER/eventplaner/branch/develop/graph/badge.svg)](https://codecov.io/gh/USER/eventplaner)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
```

`USER` durch GitHub-Username ersetzen.

### 4. README-Tests-Sektion finalisieren

```markdown
## Tests

| Stack | Befehl | Was läuft |
|---|---|---|
| Backend | `composer test` | Pest mit `--parallel` (~Faktor 4 schneller als seriell) |
| Backend filter | `./vendor/bin/pest --filter=DrinkScore` | Einzelne Test-Datei |
| Frontend | `npm test` | Vitest mit Worker-Threads (default parallel) |
| Frontend watch | `npm run test:watch` | Hot-Reload-Tests |

**Coverage** (~65% Backend, kritische Frontend-Komponenten):
- 26 Backend-Test-Files mit ~127 Cases — alle API-Endpunkte, alle Kernfeature-Web-Controller, Services mit Edge Cases, Auth-Flows, Permission-Middlewares
- 4 Frontend-Specs für ConfirmDialog, CreatableCombobox, InfoTooltip, i18n-Plugin
```

### 5. README-Endlauf-Check (aus Sicht eines Fremden)

Konkrete Fragen, die du dir selbst stellst:
- [ ] Kann ich das Projekt in <5 Min starten? (Quick-Start-Block testet sich selbst)
- [ ] Verstehe ich, was es kann? (Feature-Highlights klar formuliert)
- [ ] Sehe ich die Tech-Highlights? (Datei-Pointer existieren)
- [ ] Stimmen die Datei-Pointer noch? (`ls`-prüfen, ggf. Refactorings nach 3a haben Pfade verschoben)
- [ ] Screenshots sind eingefügt (keine TODO-Platzhalter mehr)
- [ ] Badges rendern (auf github.com geprüft, nicht nur in IDE-Preview)

### 6. ARCHITECTURE.md-Check

Folge selbst den Datei-Pointern aus `docs/ARCHITECTURE.md` — stimmen sie nach den Refactorings aus Tag 3a noch?
- `app/Services/PhotoGameTaskPool.php` (falls extrahiert)
- `app/Services/ColorRoleResolver.php` (falls extrahiert)

### 7. Repo-Hygiene Final-Sweep

```bash
# Keine versehentlich committeten Secrets
git log --all --full-history --source -- '.env'

# Backend-Tests grün
docker exec laravel-app composer test

# Backend-Lint grün
docker exec laravel-app vendor/bin/pint --test

# Frontend-Tests grün
docker exec eventplaner-vite-1 npm test

# Frontend-Lint grün
docker exec eventplaner-vite-1 npm run lint

# TypeScript-Check grün (npm run build ist auf Mac kaputt — siehe CLAUDE.md)
docker exec eventplaner-vite-1 npx tsc --noEmit
```

### 8. Verification (du pushst, ich nicht)

- [ ] Push auf `develop` → beide Workflows grün, Pest --coverage Step + Vitest Step sichtbar im Log
- [ ] Coverage-Badge zeigt einen Wert (nicht „unknown")
- [ ] Merge `develop` → `staging` und Push → Workflows feuern auch dort, grün
- [ ] Merge `staging` → `production` und Push → Workflows feuern auch dort, grün

## Datei-Liste

| Datei | Aktion |
|---|---|
| `.github/workflows/tests.yml` | Branches + Pest --coverage + Vitest + Codecov |
| `.github/workflows/lint.yml` | Branches |
| `README.md` | Badges + Tests-Sektion finalisieren |
| `docs/screenshots/*.png` | Echte Screenshots statt TODO-Platzhalter |

## Akzeptanzkriterien

- [ ] CI feuert auf `develop`, `staging`, `production` (Push + PR)
- [ ] Pest läuft mit `--parallel --coverage --min=50` und schlägt fehl wenn unter 50% Coverage
- [ ] Vitest läuft mit `--coverage` in CI
- [ ] Composer-Cache aktiv (zweiter CI-Run zeigt „Cache restored from key: ..." im Log)
- [ ] (Optional) MariaDB-Service in CI für `develop` + `production` — fängt MariaDB-spezifische Bugs die SQLite still schluckt
- [ ] (Optional) Codecov-Upload funktioniert, Coverage-Badge im README zeigt Wert
- [ ] 4 Badges im README rendern (Tests, Lint, Coverage, License)
- [ ] README hat Tests-Sektion mit Befehlen und Coverage-Aussage
- [ ] Alle Datei-Pointer in README + ARCHITECTURE.md stimmen
- [ ] Lokal alle 5 Checks grün: `composer test`, `pint --test`, `npm test`, `npm run lint`, `npx tsc --noEmit`

## Commit-Vorschlag

```
chore: CI auf staging+production erweitert, Vitest + Pest --parallel --coverage integriert
```
