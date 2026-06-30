# Eventplaner

Hochzeits- und Eventplaner als Progressive Web App mit React-Native-Companion: Gäste-, RSVP- und Foto-Verwaltung, Trinkspiel, Fotospiel und eine Token-geschützte Projektor-Diashow für die Feier.

<!-- TODO: Screenshot — Web-Dashboard nach Login -->
<!-- ![Web Dashboard](docs/screenshots/web-dashboard.png) -->

<!-- TODO: Screenshot — React Native App (Home oder Photos) -->
<!-- ![Mobile App](docs/screenshots/mobile-app.png) -->

<!-- TODO: Screenshot — Projektor-Diashow im Vollbild mit Label-Overlay -->
<!-- ![Diashow](docs/screenshots/diashow.png) -->

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
| Storage | Cloudflare R2 (Fotos) |
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

```bash
composer test   # Pest, parallel — Backend
npm test        # Vitest — Frontend
```

Test-Aufbau, Coverage-Ziele und Strategie pro Schicht stehen in [`docs/SHOWCASE_PLAN.md`](docs/SHOWCASE_PLAN.md). <!-- TODO: Coverage-Badge folgt in Tag 5 -->

---

## Companion-App (React Native)

Die mobile App liegt in einem separaten Repo und teilt sich mit der Web-App nur die HTTP-API. Sie deckt aktuell QR-Login, Foto-Galerie inkl. Upload und dynamisches Theming via `/api/event/info` ab. Geplant sind RSVP, Menüwahl und Getränke-Tracking direkt aus der App.

<!-- TODO: Link zum öffentlichen Mobile-Repo, sobald veröffentlicht -->

---

## License

[MIT](LICENSE) — frei nutzbar, ohne Gewährleistung.
