# Tag 1 — README + LICENSE + Screenshot-Vorbereitung

**Aufwand**: ~2h
**Outcome**: Ein Fremder kann das Projekt nach 5 Minuten Lesen einschätzen.

## Schritte

### 1. README umbenennen

```bash
git mv readme.md README.md
```

Sonst sieht GitHub die alte Datei nicht als Standard-Readme.

### 2. README komplett neu schreiben

Mit folgenden Sektionen (Reihenfolge wichtig):

**Hero-Block**
- 1-Satz-Pitch: „Hochzeitsplaner als PWA + Companion-App"
- 2-3 Screenshots inline (Web-Dashboard / Mobile-App / Diashow)
- Tech-Badges (optional, kommen in Tag 5)

**Feature-Highlights** (Bullet-Liste, je 1 Datei-Pointer auf interessanten Code)

10 Highlights, geordnet von „technisch interessant" zu „UX-Polish":

1. **QR-Login für Gäste** (Solo + Familie, Sanctum-Tokens, Token erst beim Select)
   → `app/Http/Controllers/Api/QrAuthController.php`
2. **Fotospiel mit Delta-Override-Modell** auf globalen Task-Katalogen
   → `app/Http/Controllers/Api/PhotoGameController.php`
3. **Trinkspiel mit physiologisch motivierter Punkteberechnung** (Shot-Multiplier, Binge-Penalty)
   → `app/Services/DrinkScoreService.php`
4. **Konfigurierbares Farbsystem** (Palette + Rollen) mit Live-Preview
   → `resources/js/pages/Event/Settings.vue`
5. **Drink-Catalog mit 1-row-per-type + Size-Tabelle** (saubere Datenmodellierung)
   → `app/Models/DrinkCatalog.php`, `database/migrations/2026_03_29_*`
6. **Diashow/Projektor** mit Auto-Poll, Crossfade, Context-Label pro Album
   → `resources/js/pages/Projector/Show.vue`
7. **Stil-Presets** für One-Click-Theme-Wechsel
   → `app/Http/Controllers/EventStylePresetController.php`
8. **Mobile-PWA** (vite-plugin-pwa, eigenes Icon-Set)
   → `vite.config.ts`
9. **i18n Front- und Backend** (DE/EN, Accept-Language-Middleware für API-Responses)
   → `resources/js/plugins/i18n.ts`
10. **Public Landing Page** mit 3-Schritte-Erklärung und Feature-Cards
    → `resources/js/pages/Welcome.vue`

**Tech-Stack-Tabelle** (kompakter als CLAUDE.md, ohne Hetzner-IPs/Domains)

| Schicht | Tech |
|---|---|
| Backend | Laravel 12 (PHP 8.3) + Inertia.js + Sanctum |
| Web-Frontend | Vue 3 + TypeScript + Tailwind CSS 4 + Reka UI |
| Mobile | React Native (Expo) — separates Repo |
| Build | Vite 6 + PWA |
| Storage | Cloudflare R2 (Fotos) |
| Mail | Resend |
| Deploy | Docker + Coolify |

**Quick Start** (Docker)
```bash
docker compose up -d
# Migrations laufen automatisch
docker exec laravel-app php artisan tinker
# > User::where('email', 'you@example.com')->update(['role' => 'admin'])
```

**Architektur** (5-10 Zeilen + Verweis auf `docs/ARCHITECTURE.md`, das in Tag 2 folgt)

**Tests** (3 Zeilen, kommt in Tag 3-5 inhaltlich rein)
```bash
composer test    # Pest + parallel (Backend)
npm test         # Vitest (Frontend)
```

**Companion-App-Block**
- Eventplaner-Mobile (React Native) liegt in separatem Repo
- Kurze Erklärung was sie kann + Link-Platzhalter

**License**: MIT

**Bewusst weggelassen**: Hetzner-IP, hommrich.app-Domains, Mail-Domain. Kein Bedrohungsvektor für Showcase-Repo.

### 3. LICENSE-Datei neu

Standard MIT-Template, Copyright 2026 André Hommrich.

```
MIT License

Copyright (c) 2026 André Hommrich

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### 4. Screenshots vorbereiten

3 PNGs in `docs/screenshots/`:
- `web-dashboard.png` — Hauptansicht Web-Admin (Dashboard nach Login)
- `mobile-app.png` — React Native App (Home oder Photos-Screen)
- `diashow.png` — Projektor-Vollbild mit Foto + Label-Overlay

Wenn die noch nicht vorhanden sind: Platzhalter in README markieren mit `<!-- TODO: Screenshot -->` und in Tag 5 finalisieren.

### 5. .gitignore Quick-Check

`.env*` Patterns sind schon korrekt — keine Änderung nötig. Trotzdem kurz prüfen:
```bash
grep -E "^\.env" .gitignore
```

## Datei-Liste

| Datei | Aktion |
|---|---|
| `readme.md` → `README.md` | umbenennen + komplett neu schreiben |
| `LICENSE` | neu |
| `docs/screenshots/*.png` | neu (oder Platzhalter-TODOs) |

## Akzeptanzkriterien

- [ ] `README.md` (Großschreibung!) existiert, ist >150 Zeilen mit allen Sektionen oben
- [ ] `LICENSE` existiert mit MIT-Text und korrektem Copyright-Jahr
- [ ] Mindestens 3 Screenshot-Slots im README (mit Bild oder TODO-Platzhalter)
- [ ] Keine Hetzner-IPs, keine `hommrich.app`-Domain im README
- [ ] Alle 10 Datei-Pointer im Feature-Block stimmen (`ls`-prüfen)
- [ ] `git ls-files | grep -i readme` zeigt nur noch `README.md`, nicht mehr `readme.md`

## Commit-Vorschlag

```
docs: aussagekräftige README + LICENSE für externe Sichtbarkeit
```
