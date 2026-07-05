---
updated_at: 2026-07-05T12:00:00Z
---

## Verantwortlicher {#verantwortlich}

Verantwortlich für die Datenverarbeitung in dieser App und auf dieser Website ist:

André Hommrich
Dernbacher Str. 26
56410 Montabaur
E-Mail: andre-hommrich@web.de

## Welche Daten wir erheben {#datenerhebung}

Wir verarbeiten personenbezogene Daten ausschließlich, um die Funktionen der App bereitzustellen: einen persönlichen Veranstalter-Account zu betreiben, System-Mails zu versenden (Verifizierung, Passwort-Reset, Einladungen), Gastlisten und RSVP zu verwalten, Foto-Uploads zur Veranstaltung zu sammeln sowie eine Diashow während der Feier auf einem vom Veranstalter gewählten Endgerät bereitzustellen.

Konkret verarbeiten wir folgende Datenkategorien:

- **Veranstalter-Account:** Name, E-Mail-Adresse, verschlüsseltes Passwort, Zeitpunkte von Registrierung und E-Mail-Verifizierung, Zustimmung zur Datenschutzerklärung.
- **Gästedaten:** Vorname, Nachname, Lebensmittel-Präferenzen, Zu-/Absage-Status, optionale Getränke-Logs und Foto-Spiel-Einreichungen.
- **Foto-Uploads:** Die Bild-Datei, optionale Beschreibung, die hochladende Person sowie die Zuordnung zur Veranstaltung. Beim Upload werden enthaltene Metadaten (insbesondere GPS-Koordinaten, Gerätemodell und Originalzeitstempel) automatisch entfernt; gespeichert wird nur das reine Bild.
- **Technisch erforderliche Cookies:** Session-Cookie für die Anmeldung sowie CSRF-Schutz-Cookie. Diese Cookies sind technisch notwendig und benötigen keine separate Einwilligung.

Wir verarbeiten keine besonderen Kategorien personenbezogener Daten im Sinne von Art. 9 DSGVO. Die Kommunikation mit der App erfolgt ausschließlich verschlüsselt über HTTPS. Passwörter werden gehasht gespeichert (kein Klartext). Es werden keine personenbezogenen Daten in Anwendungs-Logs geschrieben.

## Rechtsgrundlage {#rechtsgrundlage}

- **Art. 6 Abs. 1 lit. b DSGVO** — Vertragserfüllung gegenüber Veranstaltern, die einen Account anlegen, um ihre Veranstaltung zu organisieren.
- **Art. 6 Abs. 1 lit. a DSGVO** — Einwilligung von Gästen, die per QR-Code einer Veranstaltung beitreten und freiwillig Inhalte (Fotos, RSVP, Getränke-Logs) beitragen.
- **Art. 6 Abs. 1 lit. f DSGVO** — Berechtigtes Interesse an einem stabilen, sicheren Betrieb der Plattform (Logging technischer Fehler, Schutz vor Missbrauch).

## Speicherdauer {#speicherdauer}

- Veranstalter-Accounts werden gespeichert, solange der Account aktiv ist. Löschung ist jederzeit über „Konto löschen" möglich.
- Einladungs-Tokens werden {{retention.invitation_tokens_days}} Tage nach dem Veranstaltungsdatum automatisch entfernt.
- Abgesagte Gäste ohne App-Zugang werden {{retention.declined_guests_days}} Tage nach dem Veranstaltungsdatum entfernt.
- Persönliche Zugangstokens (z. B. App-Login der Gäste) verfallen nach Ablauf und werden täglich gelöscht.
- Beim Löschen einer Veranstaltung werden die zugehörigen Fotos auch im Object Storage entfernt.

## Deine Rechte {#rechte}

Du hast jederzeit das Recht auf:

- **Auskunft** (Art. 15 DSGVO) — Deine Rechte nach Art. 15 (Auskunft) und Art. 17 (Löschung) kannst du direkt in der App unter Einstellungen → „Meine Daten exportieren" bzw. „Konto löschen" ausüben.
- **Berichtigung** (Art. 16 DSGVO) — direkt in den Einstellungen änderbar.
- **Löschung** (Art. 17 DSGVO) — über „Konto löschen" in den Einstellungen.
- **Einschränkung der Verarbeitung** (Art. 18 DSGVO).
- **Datenübertragbarkeit** (Art. 20 DSGVO) — abgedeckt durch den JSON-Export.
- **Widerspruch** gegen die Verarbeitung (Art. 21 DSGVO).
- **Beschwerde** bei einer Datenschutz-Aufsichtsbehörde. Zuständig ist der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Rheinland-Pfalz (datenschutz.rlp.de) oder die für deinen Wohnort zuständige Landesdatenschutzbehörde.

## Drittanbieter {#drittanbieter}

Wir setzen kein Tracking, keine Analytics und keine Third-Party-Cookies ein. Die App kommuniziert ausschließlich mit unserem eigenen Backend auf eveplan.de.

Für den Betrieb setzen wir sorgfältig ausgewählte Auftragsverarbeiter ein, mit denen jeweils ein Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO besteht. Die gesamte Verarbeitung Deiner Daten findet innerhalb der Europäischen Union statt — einzige Ausnahme ist die optionale „Mit Google anmelden"-Funktion, die Du aktiv auslösen musst:

- **Hetzner Online GmbH**, Industriestr. 25, 91710 Gunzenhausen, Deutschland — Hosting der Anwendung, der Datenbank sowie Object Storage für hochgeladene Fotos. Standort Nürnberg (Deutschland); Verarbeitung ausschließlich innerhalb der EU. AVV gemäß Art. 28 DSGVO geschlossen. EXIF-, IPTC- und XMP-Metadaten werden vor dem Speichern serverseitig aus den Bildern entfernt.
- **Plus Five Five, Inc. (Resend)** — Versand von System-E-Mails (Verifizierung, Passwort-Reset, Einladungen). Verarbeitung in der EU (Region Irland, eu-west-1); Datenverarbeitungsvertrag geschlossen.
- **Functional Software, Inc. (Sentry)** — automatisierte Fehlerüberwachung sowohl des Backends (unbehandelte Ausnahmen und Warnungen) als auch des Webclients im Browser (JavaScript-Ausnahmen). Verarbeitung in der EU (Region Frankfurt, `de.sentry.io`). Standardmäßig werden **keine** IP-Adressen, Session-Cookies, angemeldete Nutzerkontexte oder Request-Header übertragen; im Browser findet keine Session-Aufzeichnung statt. Datenverarbeitungsvertrag mit Aktivierung des Accounts geschlossen.
- **Google LLC** (USA) — **nur**, wenn Du Dich aktiv mit „Mit Google anmelden" registrierst. Google erhält dann Deine Google-ID, E-Mail und den Namen (keine weiteren Scopes). Datenübermittlung in die USA auf Grundlage der EU-Standardvertragsklauseln (SCCs).

## Kontakt {#kontakt}

Bei Fragen zum Datenschutz oder zur Ausübung Deiner Rechte erreichst Du uns per E-Mail:

andre-hommrich@web.de

Diese Datenschutzerklärung kann bei Änderungen der Anwendung oder gesetzlicher Vorgaben angepasst werden. Die jeweils aktuelle Fassung findest Du stets in der App und auf eveplan.de/datenschutz. Bei wesentlichen Änderungen wirst Du zusätzlich per E-Mail oder über die App informiert.
