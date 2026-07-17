---
updated_at: 2026-07-17T12:00:00Z
---

## Verantwortlicher {#verantwortlich}

Verantwortlich für die Datenverarbeitung in dieser App und auf dieser Website ist:

André Hommrich
Dernbacher Str. 26
56410 Montabaur
E-Mail: andre-hommrich@web.de

## Welche Daten wir erheben {#datenerhebung}

Wir verarbeiten personenbezogene Daten ausschließlich, um die Funktionen der App bereitzustellen: einen persönlichen Veranstalter-Account zu betreiben, System-Mails zu versenden (Verifizierung, Passwort-Reset, Einladungen), Gastlisten und RSVP zu verwalten, Foto-Uploads zur Veranstaltung zu sammeln, das Foto-Spiel und optionale Getränke-Logs bereitzustellen sowie eine Diashow während der Feier auf einem vom Veranstalter gewählten Endgerät bereitzustellen.

Konkret verarbeiten wir folgende Datenkategorien:

- **Veranstalter-Account:** Name, E-Mail-Adresse, verschlüsseltes Passwort, Zeitpunkte von Registrierung und E-Mail-Verifizierung, Zustimmung zur Datenschutzerklärung.
- **Geräte- und Sitzungsdaten für Veranstalter:** frei gewählter Gerätename, gehashter einmaliger Pairing-Code, Ablauf-, Einlöse- und letzte Nutzungszeitpunkte sowie persönliche Zugangstokens.
- **RSVP- und Gästedaten:** Vorname, Nachname, Lebensmittel-Präferenzen, Zu-/Absage-Status, Gruppenzuordnung und Einladungsstatus.
- **Foto-Galerie:** Die Bild-Datei, optionale Beschreibung, die hochladende Person sowie die Zuordnung zur Veranstaltung. Beim Upload werden enthaltene Metadaten (insbesondere GPS-Koordinaten, Gerätemodell und Originalzeitstempel) automatisch entfernt; gespeichert wird nur das reine Bild.
- **Foto-Spiel:** Aufgabenstatus, zugewiesene Aufgabe, eingereichte Foto-Datei, optionale Beschreibung und die hochladende Person.
- **Getränke-Log:** Ausgewähltes Getränk, Menge, Zeitpunkt und zugehöriger Gast, sofern der Veranstalter diese Funktion aktiviert und der Gast sie nutzt.
- **Meldungen und Ausblendungen:** Wenn Du ein Foto meldest oder Inhalte eines anderen Gastes ausblendest, speichern wir die Foto- bzw. Gast-ID, den Zeitpunkt, den Meldungsgrund und Deine optionale Nachricht. Der gemeldete Gast erfährt niemals, wer ihn gemeldet hat.
- **Kamera und Fotobibliothek:** Die App greift auf Kamera oder Fotobibliothek nur zu, wenn Du dies am Gerät erlaubst und aktiv ein Foto aufnehmen oder auswählen möchtest. Ohne Deine Geräte-Einwilligung erfolgt kein Zugriff.
- **Optionale Push-Benachrichtigungen für Veranstalter:** Expo-Push-Token, Geräteplattform, Zeitpunkt der letzten Registrierung sowie technische Versand-Tickets und -Belege. Push-Nachrichten enthalten nur einen allgemeinen Hinweis und technische Event-/Notiz-IDs; Titel oder Inhalte einer Notiz, Gastdaten, Eventname und Absendername werden nicht auf dem Sperrbildschirm übertragen. Push kann in der App jederzeit deaktiviert werden.
- **Technisch erforderliche Cookies:** Session-Cookie für die Anmeldung sowie CSRF-Schutz-Cookie. Diese Cookies sind technisch notwendig und benötigen keine separate Einwilligung.

Wir verarbeiten keine besonderen Kategorien personenbezogener Daten im Sinne von Art. 9 DSGVO. Die Kommunikation mit der App erfolgt ausschließlich verschlüsselt über HTTPS. Passwörter werden gehasht gespeichert (kein Klartext). Es werden keine personenbezogenen Daten in Anwendungs-Logs geschrieben. Wir zeigen keine Werbung an und setzen kein Tracking, keine Third-Party-Analytics und keine Marketing-Profile ein. Schriftarten werden lokal mit der Anwendung gebündelt; es werden keine Fonts von einem CDN oder externen Font-Anbieter geladen.

## Rechtsgrundlage {#rechtsgrundlage}

- **Art. 6 Abs. 1 lit. b DSGVO** — Vertragserfüllung gegenüber Veranstaltern, die einen Account anlegen, um ihre Veranstaltung zu organisieren.
- **Art. 6 Abs. 1 lit. a DSGVO** — Einwilligung von Gästen, die per QR-Code einer Veranstaltung beitreten, Kamera- oder Fotobibliothekszugriff erlauben und freiwillig Inhalte (Fotos, RSVP, Getränke-Logs, Foto-Spiel-Beiträge) beitragen.
- **Art. 6 Abs. 1 lit. f DSGVO** — Berechtigtes Interesse an einem stabilen, sicheren Betrieb der Plattform (Logging technischer Fehler, Schutz vor Missbrauch).

## Speicherdauer {#speicherdauer}

- Veranstalter-Accounts werden gespeichert, solange der Account aktiv ist. Löschung ist jederzeit über „Konto löschen" möglich.
- Einladungs-Tokens werden {{retention.invitation_tokens_days}} Tage nach dem Veranstaltungsdatum automatisch entfernt.
- Abgesagte Gäste ohne App-Zugang werden {{retention.declined_guests_days}} Tage nach dem Veranstaltungsdatum entfernt.
- Persönliche Zugangstokens für die Veranstalter-App verfallen nach {{auth.management_token_ttl_days}} Tagen; Gast-Tokens nach ihrer jeweils festgelegten Gültigkeit. Abgelaufene Tokens werden täglich gelöscht. Nicht eingelöste Pairing-Datensätze werden spätestens {{retention.device_pairings_hours}} Stunden nach Ablauf entfernt.
- Expo-Push-Token werden beim Deaktivieren von Push in der App, beim Widerruf der zugehörigen Gerätesitzung, beim Löschen des Accounts oder nach der Rückmeldung `DeviceNotRegistered` durch Expo gelöscht. Technische Versand-Tickets und -Belege werden nur kurzfristig zur Zustellkontrolle gespeichert und anschließend automatisch entfernt. Fehlgeschlagene Warteschlangenaufträge mit technischen Nutzer-/Notiz-IDs werden nach {{retention.failed_jobs_days}} Tagen gelöscht.
- Gäste können in der App einen Löschantrag stellen. Der Datensatz wird für {{retention.guest_erasure_grace_days}} Tage zur möglichen Rücknahme markiert und danach automatisch gelöscht.
- Foto-Meldungen werden {{retention.photo_reports_days}} Tage nach dem Veranstaltungsdatum entfernt. Ausblendungen einzelner Gäste werden automatisch mit dem betroffenen Gast oder Foto gelöscht.
- Beim Löschen einer Veranstaltung werden die zugehörigen Fotos auch im Object Storage entfernt.

## Deine Rechte {#rechte}

Du hast jederzeit das Recht auf:

- **Auskunft** (Art. 15 DSGVO) — Deine Rechte nach Art. 15 (Auskunft) und Art. 17 (Löschung) kannst du direkt in der App unter Einstellungen → „Meine Daten exportieren" bzw. „Konto löschen" ausüben.
- **Berichtigung** (Art. 16 DSGVO) — direkt in den Einstellungen änderbar.
- **Löschung** (Art. 17 DSGVO) — über „Konto löschen" in den Einstellungen.
- **Einschränkung der Verarbeitung** (Art. 18 DSGVO).
- **Datenübertragbarkeit** (Art. 20 DSGVO) — abgedeckt durch den JSON-Datenexport in der App.
- **Widerspruch** gegen die Verarbeitung (Art. 21 DSGVO).
- **Beschwerde** bei einer Datenschutz-Aufsichtsbehörde. Zuständig ist der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Rheinland-Pfalz (datenschutz.rlp.de) oder die für deinen Wohnort zuständige Landesdatenschutzbehörde.

## Drittanbieter {#drittanbieter}

Wir setzen keine Werbung, kein Tracking, keine Third-Party-Analytics und keine Third-Party-Cookies ein. Die App kommuniziert für ihre Kernfunktionen ausschließlich mit unserem eigenen Backend auf eveplan.de. Schriftarten sind lokal gebündelt; es findet kein Font-Abruf bei Google Fonts, Adobe Fonts oder einem CDN statt.

Für den Betrieb setzen wir sorgfältig ausgewählte Auftragsverarbeiter ein, mit denen jeweils ein Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO oder ein entsprechender Auftragsverarbeitungszusatz besteht. Die Verarbeitung findet überwiegend innerhalb der Europäischen Union statt. Bei der optionalen Google-Anmeldung und bei optionalen Push-Benachrichtigungen für Veranstalter kann eine Übermittlung in die USA stattfinden:

- **Hetzner Online GmbH**, Industriestr. 25, 91710 Gunzenhausen, Deutschland — Hosting der Anwendung, der Datenbank sowie Object Storage für hochgeladene Fotos. Standort Nürnberg (Deutschland); Verarbeitung ausschließlich innerhalb der EU. AVV gemäß Art. 28 DSGVO geschlossen. EXIF-, IPTC- und XMP-Metadaten werden vor dem Speichern serverseitig aus den Bildern entfernt.
- **Plus Five Five, Inc. (Resend)** — Versand von System-E-Mails (Verifizierung, Passwort-Reset, Einladungen). Verarbeitung in der EU (Region Irland, eu-west-1); Datenverarbeitungsvertrag geschlossen.
- **Functional Software, Inc. (Sentry)** — automatisierte Fehlerüberwachung sowohl des Backends (unbehandelte Ausnahmen und Warnungen) als auch des Webclients im Browser (JavaScript-Ausnahmen). Verarbeitung in der EU (Region Frankfurt, `de.sentry.io`). Standardmäßig werden **keine** IP-Adressen, Session-Cookies, angemeldete Nutzerkontexte oder Request-Header übertragen; im Browser findet keine Session-Aufzeichnung statt. Datenverarbeitungsvertrag mit Aktivierung des Accounts geschlossen.
- **650 Industries, Inc. (Expo)** (USA) — **nur für optionale Push-Benachrichtigungen an Veranstaltergeräte**. Expo erhält den Push-Token, die Geräteplattform, einen allgemeinen Benachrichtigungstext und technische Event-/Notiz-IDs und leitet die Nachricht an Apple (iOS) oder Google (Android) weiter. Es werden keine Notiztexte, Gastdaten, Eventnamen oder Absendernamen übertragen. Der Auftragsverarbeitungszusatz und EU-Standardvertragsklauseln (Modul 2) sind in §3.2 der Expo-Nutzungsbedingungen eingebunden. Push kann jederzeit in der App deaktiviert werden.
- **Google LLC** (USA) — **nur**, wenn Du Dich aktiv mit „Mit Google anmelden" registrierst. Google erhält dann Deine Google-ID, E-Mail und den Namen (keine weiteren Scopes). Datenübermittlung in die USA auf Grundlage der EU-Standardvertragsklauseln (SCCs).

## Kontakt {#kontakt}

Bei Fragen zum Datenschutz oder zur Ausübung Deiner Rechte erreichst Du uns per E-Mail:

andre-hommrich@web.de

Diese Datenschutzerklärung kann bei Änderungen der Anwendung oder gesetzlicher Vorgaben angepasst werden. Die jeweils aktuelle Fassung findest Du stets in der App und auf eveplan.de/datenschutz. Bei wesentlichen Änderungen wirst Du zusätzlich per E-Mail oder über die App informiert.
