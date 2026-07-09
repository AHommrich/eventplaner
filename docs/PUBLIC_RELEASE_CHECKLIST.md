# Public-Release-Checkliste

Ziel: Dieses Repository so bereinigen, dass es öffentlich auf GitHub und in Bewerbungen verlinkt werden kann, ohne private Daten, interne Betriebsdetails oder unnötig schlechte Außenwirkung zu riskieren.

Arbeitsregel für Agenten:
- Änderungen klein und nachvollziehbar halten.
- Keine Secrets, privaten Adressen, Kundennummern oder internen Infrastrukturdetails in neue Dateien übernehmen.
- Nach jedem Block kurz `git diff --stat` und relevante Tests/Checks nennen.
- Keine echten Credentials rotieren oder externe Dienste anfassen, außer explizit beauftragt.

## Prio 0 - Vor jeder Veröffentlichung zwingend

### 1. Private/personenbezogene Legal-Daten bereinigen

Betroffene Dateien:
- `resources/legal/imprint.de.md`
- `resources/legal/imprint.en.md`
- ggf. README/SECURITY, falls dort private Kontaktdaten stehen

Aufgabe:
- Prüfen, ob Privatadresse und private E-Mail wirklich öffentlich bleiben sollen.
- Für Portfolio-Repo entweder anonymisieren, durch generischen Kontakt ersetzen oder bewusst dokumentieren, dass diese Daten öffentlich sein dürfen.

Akzeptanz:
- Keine private Wohnadresse im Public-Showcase, sofern nicht ausdrücklich gewollt.
- Impressum/Legal-Texte bleiben fachlich konsistent.

### 2. Sub-Processor- und Provider-Details anonymisieren

Betroffene Dateien:
- `docs/legal/sub-processors.md`
- `docs/legal/hetzner-object-storage-migration.md`
- `docs/legal/r2-eu-jurisdiction-migration.md`
- ggf. `resources/legal/privacy.de.md`
- ggf. `resources/legal/privacy.en.md`

Aufgabe:
- Kundennummern entfernen.
- Lokale DPA-/PDF-Pfade entfernen.
- Konkrete Bucket-Namen, interne Provider-Details und unnötig genaue Infrastrukturangaben anonymisieren.
- Nur das behalten, was für einen Portfolio-Reviewer relevant ist: Datenschutzbewusstsein, Kategorien von Sub-Prozessoren, EU-Hosting, EXIF-Stripping.

Akzeptanz:
- Keine Kundennummern.
- Keine lokalen Pfade wie `~/legal/...`.
- Keine nicht notwendigen Bucket-/Account-Details.

### 3. Secret-Rotation- und Betriebsdetails aus Public-Version entfernen

Betroffene Dateien:
- `docs/security-rotations.md`
- `docs/RUNBOOK.md`
- `CLAUDE.md`
- `docs/FOLLOWUP_2026-07-07.md`
- `docs/AUDIT_ACTION_PLAN.md`
- `docs/AUDIT_YELLOW_TO_GREEN.md`

Aufgabe:
- Entscheiden: löschen, in private Notizen verschieben oder stark anonymisieren.
- Für Public nur eine kurze, nicht-sensitive Betriebsübersicht behalten.

Akzeptanz:
- Keine OAuth-Client-Struktur, Rotationsabläufe, Coolify-Interna, VPS-Größen, private Deploy-Disziplin oder interne Agenten-Notizen im Public-Showcase.
- README verweist nicht auf entfernte interne Dateien.

### 4. Git-Historie auf Secrets prüfen

Aufgabe:
- Mit einem Secret-Scanner die komplette Git-Historie prüfen, nicht nur den aktuellen Working Tree.
- Beispiele: `gitleaks`, `trufflehog` oder GitHub Secret Scanning nach Veröffentlichung.

Akzeptanz:
- Keine `.env` mit echten Secrets in der Historie.
- Keine API-Keys, OAuth-Secrets, S3-Keys, APP_KEYs oder privaten Dumps in der Historie.
- Falls etwas gefunden wird: Secrets rotieren und History-Rewrite/Public-Showcase-Repo erwägen.

### 5. Artefakte aus Git entfernen

Betroffene Dateien:
- `test-results/.last-run.json`

Aufgabe:
- Datei aus Git entfernen.
- Sicherstellen, dass `test-results/` ignoriert wird.

Akzeptanz:
- `git ls-files test-results` liefert nichts mehr.
- Testartefakte bleiben lokal ignoriert.

## Prio 1 - Sehr empfehlenswert für Bewerbungen

### 6. Composer-Metadaten professionalisieren

Betroffene Datei:
- `composer.json`

Aufgabe:
- `name`, `description`, `keywords` von Laravel-Starter-Kit auf Projektidentität ändern.
- Beispielrichtung:
  - `name`: `andrehommrich/eventplaner`
  - `description`: `Event planning platform with guest QR login, RSVP, photo sharing and projector mode`
  - `keywords`: `event-planning`, `laravel`, `vue`, `sanctum`, `inertia`, `portfolio`

Akzeptanz:
- Keine Starter-Kit-Metadaten mehr.
- Composer-Datei wirkt wie ein echtes Projekt.

### 7. README in Public-Portfolio-Version überarbeiten

Betroffene Dateien:
- `README.md`
- `README.de.md`

Aufgabe:
- README kürzen und auf Bewerbungswirkung fokussieren.
- Deployment-Branch-Details, VPS-OOM-Hinweise und interne Merge-Regeln entfernen oder in stark gekürzte "Operations" Sektion verschieben.
- Klare Abschnitte:
  - Kurzbeschreibung
  - Tech Stack
  - Features
  - Architekturentscheidungen
  - Setup
  - Screenshots/Demo
  - Tests
  - Security/Privacy Highlights
  - Known limitations

Akzeptanz:
- Externer Reviewer versteht in 3-5 Minuten, warum das Projekt relevant ist.
- Keine internen Betriebsanweisungen dominieren die README.

### 8. CI-Lint auf Check-Modus umstellen

Betroffene Datei:
- `.github/workflows/lint.yml`

Aufgabe:
- Mutierende Befehle in CI ersetzen:
  - `vendor/bin/pint` -> `vendor/bin/pint --test`
  - `npm run format` -> `npm run format:check`
- `permissions: contents: write` auf minimal nötige Rechte reduzieren, falls kein Auto-Commit mehr genutzt wird.

Akzeptanz:
- CI prüft Formatierung, verändert aber keine Dateien.
- Keine unnötigen Schreibrechte.

### 9. Public API Auth/Rate-Limits härten

Betroffene Dateien:
- `routes/api.php`
- `app/Http/Controllers/Api/QrAuthController.php`
- ggf. Tests unter `tests/Feature/Api/QrAuthTest.php`

Aufgabe:
- QR-Login-Endpunkte explizit rate-limiten:
  - `GET /api/auth/qr/{token}`
  - `POST /api/auth/qr/{token}/select`
- Sinnvolle Limits wählen, z. B. scan-freundlich aber brute-force-resistent.

Akzeptanz:
- Rate-Limit ist im Routing sichtbar.
- Tests decken 429-Verhalten mindestens grob ab oder bestehende Middleware-Konfiguration ist dokumentiert.

### 10. Upload-Fehlervertrag verbessern

Betroffene Dateien:
- `app/Http/Controllers/Api/PhotoController.php`
- `app/Http/Controllers/Api/PhotoGameController.php`
- `config/filesystems.php`
- Tests unter `tests/Feature/Api/PhotoApiTest.php`

Aufgabe:
- Storage-Schreibfehler bewusst behandeln.
- Entweder `throw => true` für relevante Disks oder Rückgabewert von `Storage::put()` prüfen.
- Definiertes JSON-Fehlerformat für Upload-Fehler einführen, z. B. `503` mit `code: storage_unavailable`.

Akzeptanz:
- Upload erzeugt bei Storage-Ausfall keinen scheinbar erfolgreichen DB-Eintrag.
- Test deckt Storage-Fehler ab.

### 11. CORS explizit konfigurieren oder bewusst dokumentieren

Betroffene Dateien:
- ggf. `config/cors.php`
- README/API-Doku

Aufgabe:
- Entscheiden, ob externe Clients offiziell unterstützt werden.
- Falls ja: `config/cors.php` veröffentlichen und erlaubte Origins einschränken.
- Falls nein: README klar auf same-origin Web + mobile Bearer-API hinweisen.

Akzeptanz:
- Kein implizites Laravel-Default-Verhalten als stiller Vertrag.
- Frontend-/Mobile-Clients wissen, welche Base URLs erlaubt sind.

## Prio 2 - Optionaler Feinschliff

### 12. Authorization-Logik in Policies bündeln

Betroffene Bereiche:
- Controller mit wiederholtem `event_id !== activeEvent()?->id`
- Photo-/Guest-/Drink-/EventAccess-Controller

Aufgabe:
- Wiederkehrende Event-Scope-Prüfungen in Policies oder kleine Guard-Methoden ziehen.

Akzeptanz:
- Controller werden schmaler.
- Verhalten bleibt durch bestehende Tests abgesichert.

### 13. API-Response-Typen oder OpenAPI-Skizze ergänzen

Betroffene Dateien:
- neue oder bestehende Doku unter `docs/`
- README-Verweise

Aufgabe:
- Wichtigste Mobile-/Guest-API dokumentieren:
  - QR Login
  - Guest profile
  - RSVP
  - Photo upload/list/delete/report
  - Event info

Akzeptanz:
- Frontend-Entwickler kann TypeScript-Typen ohne Code-Reading ableiten.

### 14. Galerie-Pagination prüfen

Betroffene Dateien:
- `app/Http/Controllers/Api/PhotoController.php`
- Frontend/Mobile-API-Vertrag

Aufgabe:
- Für größere Events Pagination oder Cursor-Loading einführen.

Akzeptanz:
- `/api/photos` lädt nicht unbegrenzt alle Fotos.
- Response-Format ist dokumentiert.

### 15. Public-Screenshots/Demo finalisieren

Betroffene Dateien:
- `docs/screenshots/*`
- README

Aufgabe:
- Screenshots prüfen: keine echten Namen, E-Mails, privaten Events, private Fotos.
- Optional Demo-Video/GIF ergänzen.

Akzeptanz:
- Alle Bilder sind public-tauglich.
- README wirkt visuell stark und professionell.

## Frischer Session-Prompt

Du kannst in einer neuen Session folgenden Prompt verwenden:

```text
Bitte arbeite die Datei docs/PUBLIC_RELEASE_CHECKLIST.md priorisiert ab.
Beginne mit Prio 0.
Wichtig:
- Keine echten Secrets anzeigen.
- Keine externen Dienste anfassen.
- Kleine, reviewbare Änderungen.
- Nach jedem erledigten Punkt Tests/Checks nennen.
- Wenn eine Entscheidung offen ist, erst stoppen und konkret fragen.
```
