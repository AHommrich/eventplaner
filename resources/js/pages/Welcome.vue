<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="eveplan — Dein Eventplaner" />
    <div class="min-h-screen bg-white text-gray-900 dark:bg-[#0d0d14] dark:text-white">
        <!-- Hero -->
        <section class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 text-center">
            <!-- Gradient Glow -->
            <div class="pointer-events-none absolute inset-0">
                <div
                    class="absolute top-1/3 left-1/2 h-[700px] w-[700px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-red-600/20 blur-[130px]"
                ></div>
                <div class="absolute top-1/2 left-1/4 h-[400px] w-[400px] rounded-full bg-emerald-500/10 blur-[100px]"></div>
                <div class="absolute top-2/3 right-1/4 h-[300px] w-[300px] rounded-full bg-green-700/15 blur-[80px]"></div>
            </div>

            <div class="relative z-10 mx-auto flex max-w-3xl flex-col items-center gap-8">
                <!-- Logo -->
                <img src="/logo-admin.png" alt="eve" class="h-28 w-28 rounded-3xl shadow-2xl shadow-red-900/60" />

                <!-- Headline -->
                <div class="space-y-5">
                    <h1
                        class="bg-gradient-to-br from-gray-900 via-red-600 to-emerald-600 bg-clip-text text-6xl font-bold tracking-tight text-transparent sm:text-8xl dark:from-white dark:via-red-200 dark:to-emerald-300"
                    >
                        eveplan
                    </h1>
                    <p class="text-2xl leading-snug font-light text-gray-700 sm:text-3xl dark:text-white/80">
                        Deine Hochzeit. Dein Geburtstag.<br class="hidden sm:block" />
                        Dein Event — perfekt organisiert.
                    </p>
                    <p class="mx-auto max-w-xl text-base leading-relaxed text-gray-500 sm:text-lg dark:text-white/50">
                        eveplan ist die All-in-One-App für Veranstalter: Gäste verwalten, Einladungen per QR-Code verschicken, gemeinsam Fotos sammeln
                        und das Trinkspiel starten — alles an einem Ort.
                    </p>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 px-8 py-4 text-lg font-semibold text-white shadow-lg shadow-red-900/40 transition-opacity hover:opacity-90"
                        >
                            Zum Dashboard →
                        </Link>
                    </template>
                    <template v-else>
                        <a
                            :href="route('oauth.google.redirect', {}, false)"
                            class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 px-7 py-4 text-lg font-semibold text-white shadow-lg shadow-red-900/40 transition-opacity hover:opacity-90"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                    fill="#fff"
                                />
                                <path
                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                    fill="#fff"
                                />
                                <path
                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                                    fill="#fff"
                                />
                                <path
                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                    fill="#fff"
                                />
                            </svg>
                            Kostenlos starten
                        </a>
                        <Link
                            :href="route('login')"
                            class="rounded-2xl border border-gray-300 px-7 py-4 text-lg font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-white/20 dark:text-white/80 dark:hover:bg-white/5"
                        >
                            Einloggen
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-2xl border border-gray-300 px-7 py-4 text-lg font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-white/20 dark:text-white/80 dark:hover:bg-white/5"
                        >
                            Registrieren
                        </Link>
                    </template>
                </div>

                <p class="text-sm text-gray-400 dark:text-white/25">Kostenlos · Läuft im Browser · Mit Begleit-App für Gäste</p>
            </div>

            <!-- Scroll hint -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce text-gray-400 dark:text-white/30">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </section>

        <!-- Wie es funktioniert -->
        <section class="mx-auto max-w-4xl px-6 py-24">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-bold sm:text-4xl">So funktioniert eveplan</h2>
                <p class="mx-auto max-w-xl text-lg text-gray-500 dark:text-white/50">In drei Schritten von der leeren Seite zur perfekten Party-App.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                <div class="flex flex-col items-center gap-4 text-center">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-red-500/30 bg-red-600/20 text-2xl font-bold text-red-600 dark:text-red-300"
                    >
                        1
                    </div>
                    <h3 class="text-lg font-semibold">Event anlegen</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Du legst dein Event an, gibst Datum, Ort und Dresscode ein und lädst ein Titelbild hoch. Die App bekommt automatisch deine
                        Farben und Schriften.
                    </p>
                </div>
                <div class="flex flex-col items-center gap-4 text-center">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-600/20 text-2xl font-bold text-emerald-600 dark:text-emerald-300"
                    >
                        2
                    </div>
                    <h3 class="text-lg font-semibold">Gäste einladen</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Trag deine Gäste ein und verschick personalisierte QR-Codes. Ein Scan aus der Begleitapp genügt — kein Passwort. Die Gäste
                        sind sofort drin.
                    </p>
                </div>
                <div class="flex flex-col items-center gap-4 text-center">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-green-500/30 bg-green-600/20 text-2xl font-bold text-green-600 dark:text-green-300"
                    >
                        3
                    </div>
                    <h3 class="text-lg font-semibold">Event genießen</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Am großen Tag läuft alles von selbst. Fotos werden gesammelt, das Trinkspiel startet und die Diashow läuft auf der Leinwand.
                    </p>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-5xl px-6 py-16">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Alles was du brauchst</h2>
                <p class="mx-auto max-w-xl text-lg text-gray-500 dark:text-white/50">
                    Von der Einladung bis zum letzten Foto — eveplan begleitet dein Event von Anfang bis Ende.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">👥</div>
                    <h3 class="mb-2 text-lg font-semibold">Gästeverwaltung</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Gäste anlegen, in Gruppen organisieren und RSVP verfolgen. Diätwünsche, Allergien und Menüwahl direkt dabei — immer den
                        Überblick behalten.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">📲</div>
                    <h3 class="mb-2 text-lg font-semibold">QR-Einladungen</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Jeder Gast bekommt einen persönlichen QR-Code. Scannen → App öffnet sich → sofort eingeloggt. Kein Passwort, kein Download,
                        keine Hürden.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">📸</div>
                    <h3 class="mb-2 text-lg font-semibold">Gemeinsame Fotogalerie</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Alle Gäste laden Fotos in die geteilte Galerie hoch. Am Ende hast du die komplette Feier aus 100 Augenpaaren — automatisch
                        gesammelt.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">🎞️</div>
                    <h3 class="mb-2 text-lg font-semibold">Live-Diashow</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Die Galerie läuft als Diashow auf der Leinwand oder dem TV — in Echtzeit. Jedes neue Foto erscheint automatisch. Gäste sehen
                        sich auf der großen Leinwand.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">🎯</div>
                    <h3 class="mb-2 text-lg font-semibold">Fotospiel</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Gäste bekommen zufällige Fotoaufgaben — "Zwei Generationen zusammen", "Ein heimlicher Kuss", "Das Buffet vor dem Ansturm".
                        Sorgt für unvergessliche Momente.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">🍾</div>
                    <h3 class="mb-2 text-lg font-semibold">Getränke & Trinkspiel</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Gäste loggen ihre Getränke, ein Mengenrechner sagt dir wie viel du bestellen musst und eine Live-Rangliste zeigt wer vorne
                        liegt — mit Punktesystem.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">🎨</div>
                    <h3 class="mb-2 text-lg font-semibold">Ganz dein Look</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Titelbild, Schrift, Farben, Dresscode und Ablaufplan — die App sieht aus wie dein Event. Jeder Gast öffnet eine App die sich
                        genau richtig anfühlt.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">🧮</div>
                    <h3 class="mb-2 text-lg font-semibold">Mengenrechner</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Wie viele Flaschen Sekt für 80 Gäste? eveplan rechnet es dir aus — nach Eventdauer, Gästezahl und deinem Getränkeangebot. Kein
                        Rätselraten mehr.
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/8">
                    <div class="mb-4 text-3xl">📍</div>
                    <h3 class="mb-2 text-lg font-semibold">Infos für Gäste</h3>
                    <p class="text-sm leading-relaxed text-gray-500 dark:text-white/50">
                        Adresse, Anfahrtskarte, Dresscode, Zeitplan — alles gebündelt in der App. Kein "Wo war das nochmal?" und kein 50-mal dieselbe
                        Antwort schreiben.
                    </p>
                </div>
            </div>
        </section>

        <!-- Für wen -->
        <section class="mx-auto max-w-4xl px-6 py-20">
            <div class="rounded-3xl border border-gray-200 bg-gray-50/50 p-10 text-center sm:p-14 dark:border-white/10 dark:bg-white/3">
                <h2 class="mb-6 text-3xl font-bold sm:text-4xl">Für jedes große Event</h2>
                <div class="mb-8 flex flex-wrap justify-center gap-3">
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">💍 Hochzeit</span>
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">🎂 Geburtstag</span>
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">🎉 Jubiläum</span>
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">🥳 Silvester</span>
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">🎓 Abschlussfeier</span>
                    <span class="rounded-full border border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-white/70">🏡 Gartenparty</span>
                </div>
                <p class="mx-auto max-w-xl text-base leading-relaxed text-gray-500 dark:text-white/50">
                    Ob 20 oder 200 Gäste — eveplan skaliert mit. Kein technisches Vorwissen nötig. Du brauchst nur einen Google-Account und fünf
                    Minuten.
                </p>
            </div>
        </section>

        <!-- Footer CTA -->
        <section class="border-t border-gray-200 px-6 py-20 text-center dark:border-white/10">
            <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Bereit loszulegen?</h2>
            <p class="mb-8 text-lg text-gray-500 dark:text-white/50">Erstelle dein erstes Event in weniger als fünf Minuten.</p>
            <template v-if="!$page.props.auth.user">
                <a
                    :href="route('oauth.google.redirect', {}, false)"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 px-10 py-5 text-xl font-semibold text-white shadow-lg shadow-red-900/40 transition-opacity hover:opacity-90"
                >
                    Jetzt kostenlos starten →
                </a>
                <p class="mt-4 text-sm text-gray-400 dark:text-white/25">Mit Google-Account · Keine Kreditkarte · Sofort loslegen</p>
            </template>
            <template v-else>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 px-10 py-5 text-xl font-semibold text-white transition-opacity hover:opacity-90"
                >
                    Zum Dashboard →
                </Link>
            </template>
            <p class="mt-10 text-sm text-gray-400 dark:text-white/20">© {{ new Date().getFullYear() }} eveplan · eveplan.de</p>
        </section>
    </div>
</template>
