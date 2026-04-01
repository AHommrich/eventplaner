<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="eve — Dein Eventplaner" />
    <div class="min-h-screen bg-[#0d0d14] text-white">

        <!-- Hero -->
        <section class="relative flex flex-col items-center justify-center min-h-screen px-6 text-center overflow-hidden">
            <!-- Gradient Glow -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full bg-red-600/20 blur-[130px]"></div>
                <div class="absolute top-1/2 left-1/4 w-[400px] h-[400px] rounded-full bg-emerald-500/10 blur-[100px]"></div>
                <div class="absolute top-2/3 right-1/4 w-[300px] h-[300px] rounded-full bg-green-700/15 blur-[80px]"></div>
            </div>

            <div class="relative z-10 flex flex-col items-center gap-8 max-w-3xl mx-auto">
                <!-- Logo -->
                <img src="/logo-admin.png" alt="eve" class="w-28 h-28 rounded-3xl shadow-2xl shadow-red-900/60" />

                <!-- Headline -->
                <div class="space-y-5">
                    <h1 class="text-6xl sm:text-8xl font-bold tracking-tight bg-gradient-to-br from-white via-red-200 to-emerald-300 bg-clip-text text-transparent">
                        eve
                    </h1>
                    <p class="text-2xl sm:text-3xl text-white/80 font-light leading-snug">
                        Deine Hochzeit. Dein Geburtstag.<br class="hidden sm:block" /> Dein Event — perfekt organisiert.
                    </p>
                    <p class="text-base sm:text-lg text-white/50 max-w-xl mx-auto leading-relaxed">
                        eve ist die All-in-One-App für Veranstalter: Gäste verwalten, Einladungen per QR-Code verschicken, gemeinsam Fotos sammeln und das Trinkspiel starten — alles an einem Ort.
                    </p>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="px-8 py-4 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 text-white font-semibold text-lg hover:opacity-90 transition-opacity shadow-lg shadow-red-900/40"
                        >
                            Zum Dashboard →
                        </Link>
                    </template>
                    <template v-else>
                        <a
                            :href="route('oauth.google.redirect', {}, false)"
                            class="px-7 py-4 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 text-white font-semibold text-lg hover:opacity-90 transition-opacity shadow-lg shadow-red-900/40 flex items-center gap-2"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#fff"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/></svg>
                            Kostenlos starten
                        </a>
                        <Link
                            :href="route('login')"
                            class="px-7 py-4 rounded-2xl border border-white/20 text-white/80 font-medium text-lg hover:bg-white/5 transition-colors"
                        >
                            Einloggen
                        </Link>
                    </template>
                </div>

                <p class="text-white/25 text-sm">Kostenlos · Keine App-Installation nötig · Läuft im Browser</p>
            </div>

            <!-- Scroll hint -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-white/30 animate-bounce">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </section>

        <!-- Wie es funktioniert -->
        <section class="px-6 py-24 max-w-4xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">So funktioniert eve</h2>
                <p class="text-white/50 text-lg max-w-xl mx-auto">In drei Schritten von der leeren Seite zur perfekten Party-App.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                <div class="text-center flex flex-col items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-2xl font-bold text-red-300">1</div>
                    <h3 class="text-white font-semibold text-lg">Event anlegen</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Du legst dein Event an, gibst Datum, Ort und Dresscode ein und lädst ein Titelbild hoch. Die App bekommt automatisch deine Farben und Schriften.</p>
                </div>
                <div class="text-center flex flex-col items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-2xl font-bold text-emerald-300">2</div>
                    <h3 class="text-white font-semibold text-lg">Gäste einladen</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Trag deine Gäste ein und verschick personalisierte QR-Codes. Ein Scan genügt — kein Passwort, kein App-Download. Die Gäste sind sofort drin.</p>
                </div>
                <div class="text-center flex flex-col items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-green-600/20 border border-green-500/30 flex items-center justify-center text-2xl font-bold text-green-300">3</div>
                    <h3 class="text-white font-semibold text-lg">Event genießen</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Am großen Tag läuft alles von selbst. Fotos werden gesammelt, das Trinkspiel startet und die Diashow läuft auf der Leinwand.</p>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="px-6 py-16 max-w-5xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">Alles was du brauchst</h2>
                <p class="text-white/50 text-lg max-w-xl mx-auto">Von der Einladung bis zum letzten Foto — eve begleitet dein Event von Anfang bis Ende.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">👥</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Gästeverwaltung</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Gäste anlegen, in Gruppen organisieren und RSVP verfolgen. Diätwünsche, Allergien und Menüwahl direkt dabei — immer den Überblick behalten.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">📲</div>
                    <h3 class="text-white font-semibold text-lg mb-2">QR-Einladungen</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Jeder Gast bekommt einen persönlichen QR-Code. Scannen → App öffnet sich → sofort eingeloggt. Kein Passwort, kein Download, keine Hürden.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">📸</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Gemeinsame Fotogalerie</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Alle Gäste laden Fotos in die geteilte Galerie hoch. Am Ende hast du die komplette Feier aus 100 Augenpaaren — automatisch gesammelt.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🎞️</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Live-Diashow</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Die Galerie läuft als Diashow auf der Leinwand oder dem TV — in Echtzeit. Jedes neue Foto erscheint automatisch. Gäste sehen sich auf der großen Leinwand.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🎯</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Fotospiel</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Gäste bekommen zufällige Fotoaufgaben — "Zwei Generationen zusammen", "Ein heimlicher Kuss", "Das Buffet vor dem Ansturm". Sorgt für unvergessliche Momente.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🍾</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Getränke & Trinkspiel</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Gäste loggen ihre Getränke, ein Mengenrechner sagt dir wie viel du bestellen musst und eine Live-Rangliste zeigt wer vorne liegt — mit Punktesystem.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🎨</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Ganz dein Look</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Titelbild, Schrift, Farben, Dresscode und Ablaufplan — die App sieht aus wie dein Event. Jeder Gast öffnet eine App die sich genau richtig anfühlt.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🧮</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Mengenrechner</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Wie viele Flaschen Sekt für 80 Gäste? eve rechnet es dir aus — nach Eventdauer, Gästezahl und deinem Getränkeangebot. Kein Rätselraten mehr.</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">📍</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Infos für Gäste</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Adresse, Anfahrtskarte, Dresscode, Zeitplan — alles gebündelt in der App. Kein "Wo war das nochmal?" und kein 50-mal dieselbe Antwort schreiben.</p>
                </div>

            </div>
        </section>

        <!-- Für wen -->
        <section class="px-6 py-20 max-w-4xl mx-auto">
            <div class="rounded-3xl border border-white/10 bg-white/3 p-10 sm:p-14 text-center">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-6">Für jedes große Event</h2>
                <div class="flex flex-wrap justify-center gap-3 mb-8">
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">💍 Hochzeit</span>
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">🎂 Geburtstag</span>
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">🎉 Jubiläum</span>
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">🥳 Silvester</span>
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">🎓 Abschlussfeier</span>
                    <span class="px-4 py-2 rounded-full border border-white/15 bg-white/5 text-white/70 text-sm">🏡 Gartenparty</span>
                </div>
                <p class="text-white/50 text-base max-w-xl mx-auto leading-relaxed">
                    Ob 20 oder 200 Gäste — eve skaliert mit. Kein technisches Vorwissen nötig. Du brauchst nur einen Google-Account und fünf Minuten.
                </p>
            </div>
        </section>

        <!-- Footer CTA -->
        <section class="px-6 py-20 text-center border-t border-white/10">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">Bereit loszulegen?</h2>
            <p class="text-white/50 mb-8 text-lg">Erstelle dein erstes Event in weniger als fünf Minuten.</p>
            <template v-if="!$page.props.auth.user">
                <a
                    :href="route('oauth.google.redirect', {}, false)"
                    class="inline-flex items-center gap-2 px-10 py-5 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 text-white font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-red-900/40 text-xl"
                >
                    Jetzt kostenlos starten →
                </a>
                <p class="text-white/25 text-sm mt-4">Mit Google-Account · Keine Kreditkarte · Sofort loslegen</p>
            </template>
            <template v-else>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-2 px-10 py-5 rounded-2xl bg-gradient-to-r from-red-500 to-emerald-500 text-white font-semibold hover:opacity-90 transition-opacity text-xl"
                >
                    Zum Dashboard →
                </Link>
            </template>
            <p class="text-white/20 text-sm mt-10">© {{ new Date().getFullYear() }} eve · eveplan.de</p>
        </section>

    </div>
</template>
