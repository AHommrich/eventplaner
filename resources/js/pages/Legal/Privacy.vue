<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Datenschutzerklärung — eveplan" />
    <div class="min-h-screen bg-white text-gray-900 dark:bg-[#0d0d14] dark:text-gray-100">
        <main class="mx-auto max-w-2xl px-6 py-16">
            <Link href="/" class="text-sm text-muted-foreground hover:underline">&larr; Zurück zur Startseite</Link>

            <h1 class="mt-6 text-3xl font-semibold tracking-tight">Datenschutzerklärung</h1>
            <p class="mt-2 text-sm text-muted-foreground">Stand: 30. Juni 2026</p>

            <section class="prose dark:prose-invert mt-10 max-w-none">
                <h2>1. Verantwortliche Stelle</h2>
                <p>
                    Verantwortlich für die Datenverarbeitung auf dieser Website ist:
                </p>
                <p>
                    André Hommrich<br />
                    Dernbacher Str. 26<br />
                    56410 Montabaur<br />
                    E-Mail: <a href="mailto:andre-hommrich@web.de">andre-hommrich@web.de</a>
                </p>

                <h2>2. Zwecke der Datenverarbeitung</h2>
                <p>Wir verarbeiten personenbezogene Daten ausschließlich, um die Funktionen der App bereitzustellen:</p>
                <ul>
                    <li>Bereitstellung eines persönlichen Veranstalter-Accounts (Login, Event-Verwaltung)</li>
                    <li>Versand von Bestätigungs-, Verifizierungs- und Einladungs-Mails</li>
                    <li>Verwaltung von Gastlisten, Zu- und Absagen sowie Foto-Uploads zur Veranstaltung</li>
                    <li>Anzeige einer Diashow während der Feier auf einem vom Veranstalter gewählten Endgerät</li>
                </ul>

                <h2>3. Welche Daten verarbeitet werden</h2>
                <p>Wir verarbeiten ausschließlich die folgenden Datenkategorien:</p>
                <ul>
                    <li>
                        <strong>Veranstalter-Account:</strong> Name, E-Mail-Adresse, verschlüsseltes Passwort, Zeitpunkte von Registrierung
                        und E-Mail-Verifizierung, Zustimmung zur Datenschutzerklärung
                    </li>
                    <li>
                        <strong>Gästedaten:</strong> Vorname, Nachname, Lebensmittel-Präferenzen, Zu-/Absage-Status, optionale Getränke-Logs
                        und Foto-Spiel-Einreichungen
                    </li>
                    <li>
                        <strong>Foto-Uploads:</strong> Die Bild-Datei, optionale Beschreibung, die hochladende Person sowie die Zuordnung zur
                        Veranstaltung. Beim Upload werden enthaltene Metadaten (insbesondere GPS-Koordinaten, Gerätemodell und
                        Originalzeitstempel) automatisch entfernt; gespeichert wird nur das reine Bild.
                    </li>
                    <li>
                        <strong>Technisch erforderliche Cookies:</strong> Session-Cookie für die Anmeldung, CSRF-Schutz-Cookie. Diese Cookies
                        sind technisch notwendig und benötigen keine separate Einwilligung.
                    </li>
                </ul>
                <p>Wir verarbeiten keine besonderen Kategorien personenbezogener Daten im Sinne von Art. 9 DSGVO.</p>

                <h2>4. Rechtsgrundlage</h2>
                <ul>
                    <li>
                        <strong>Art. 6 Abs. 1 lit. b DSGVO</strong> — Vertragserfüllung gegenüber Veranstaltern, die einen Account anlegen,
                        um ihre Veranstaltung zu organisieren.
                    </li>
                    <li>
                        <strong>Art. 6 Abs. 1 lit. a DSGVO</strong> — Einwilligung von Gästen, die per QR-Code einer Veranstaltung beitreten
                        und freiwillig Inhalte (Fotos, RSVP, Getränke-Logs) beitragen.
                    </li>
                    <li>
                        <strong>Art. 6 Abs. 1 lit. f DSGVO</strong> — Berechtigtes Interesse an einem stabilen, sicheren Betrieb der
                        Plattform (Logging technischer Fehler, Schutz vor Missbrauch).
                    </li>
                </ul>

                <h2>5. Empfänger und Auftragsverarbeiter</h2>
                <p>
                    Wir setzen sorgfältig ausgewählte Dienstleister ein, mit denen jeweils ein Vertrag zur Auftragsverarbeitung gemäß
                    Art. 28 DSGVO besteht oder die ihre Dienste auf Basis vergleichbarer Schutzregelungen erbringen:
                </p>
                <ul>
                    <li>
                        <strong>Hetzner Online GmbH</strong>, Industriestr. 25, 91710 Gunzenhausen, Deutschland — Hosting der
                        Anwendung und der Datenbank. Serverstandort Falkenstein (Deutschland); Verarbeitung ausschließlich
                        innerhalb der EU. AVV gemäß Art. 28 DSGVO geschlossen.
                    </li>
                    <li><strong>Cloudflare R2</strong> (EU-Region) — Speicherung hochgeladener Fotos</li>
                    <li>
                        <strong>Plus Five Five, Inc. (Resend)</strong>, USA — Versand von System-E-Mails
                        (Verifizierung, Passwort-Reset, Einladungen). Datenübermittlung in die USA auf Grundlage der
                        EU-Standardvertragsklauseln.
                    </li>
                    <li><strong>Google LLC</strong> (USA) — nur, wenn Sie sich aktiv mit „Mit Google anmelden" registrieren</li>
                </ul>

                <h2>6. Speicherdauer</h2>
                <ul>
                    <li>Veranstalter-Accounts werden gespeichert, solange der Account aktiv ist; Löschung jederzeit über „Konto löschen".</li>
                    <li>
                        Einladungs-Tokens werden 30 Tage nach dem Veranstaltungsdatum automatisch entfernt; abgesagte Gäste ohne App-Zugang
                        nach 180 Tagen.
                    </li>
                    <li>Persönliche Zugangstokens (z. B. App-Login der Gäste) verfallen nach Ablauf und werden täglich gelöscht.</li>
                    <li>Beim Löschen einer Veranstaltung werden die zugehörigen Fotos auch im Cloudflare-R2-Speicher entfernt.</li>
                </ul>

                <h2>7. Ihre Rechte</h2>
                <p>Sie haben jederzeit das Recht auf:</p>
                <ul>
                    <li>
                        <strong>Auskunft</strong> (Art. 15 DSGVO) — über
                        <Link :href="route('profile.edit')" class="underline">Einstellungen → Datenschutz → Meine Daten exportieren</Link>
                        können Sie Ihre gespeicherten Daten als JSON-Datei herunterladen
                    </li>
                    <li><strong>Berichtigung</strong> (Art. 16 DSGVO) — direkt in den Einstellungen änderbar</li>
                    <li><strong>Löschung</strong> (Art. 17 DSGVO) — über „Konto löschen" in den Einstellungen</li>
                    <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO)</li>
                    <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO) — abgedeckt durch den JSON-Export</li>
                    <li><strong>Widerspruch</strong> gegen die Verarbeitung (Art. 21 DSGVO)</li>
                    <li>
                        <strong>Beschwerde</strong> bei einer Datenschutz-Aufsichtsbehörde. Zuständig ist der
                        <a href="https://www.datenschutz.rlp.de" target="_blank" rel="noopener noreferrer" class="underline">
                            Landesbeauftragte für den Datenschutz und die Informationsfreiheit Rheinland-Pfalz</a>
                        oder die für Ihren Wohnort zuständige Landesdatenschutzbehörde.
                    </li>
                </ul>

                <h2>8. Sicherheit</h2>
                <p>
                    Die Kommunikation mit der App erfolgt ausschließlich verschlüsselt über HTTPS. Passwörter werden gehasht gespeichert
                    (kein Klartext). Es werden keine personenbezogenen Daten in Anwendungs-Logs geschrieben. Tracking- oder
                    Analyse-Cookies werden nicht eingesetzt.
                </p>

                <h2>9. Änderungen dieser Datenschutzerklärung</h2>
                <p>
                    Diese Datenschutzerklärung kann bei Änderungen der Anwendung oder gesetzlicher Vorgaben angepasst werden. Die jeweils
                    aktuelle Fassung finden Sie stets auf dieser Seite. Bei wesentlichen Änderungen werden Sie zusätzlich per E-Mail oder
                    über die App informiert.
                </p>
            </section>

            <footer class="mt-12 flex gap-4 text-sm text-muted-foreground">
                <Link href="/" class="hover:underline">Startseite</Link>
                <Link :href="route('legal.imprint')" class="hover:underline">Impressum</Link>
            </footer>
        </main>
    </div>
</template>
