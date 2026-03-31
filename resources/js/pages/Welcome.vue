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
                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-purple-600/20 blur-[120px]"></div>
                <div class="absolute top-1/2 left-1/3 w-[400px] h-[400px] rounded-full bg-blue-500/15 blur-[100px]"></div>
            </div>

            <div class="relative z-10 flex flex-col items-center gap-8 max-w-2xl mx-auto">
                <!-- Logo -->
                <img src="/logo.png" alt="eve" class="w-24 h-24 rounded-2xl shadow-2xl shadow-purple-900/50" />

                <!-- Headline -->
                <div class="space-y-4">
                    <h1 class="text-5xl sm:text-7xl font-bold tracking-tight bg-gradient-to-br from-white via-purple-200 to-blue-300 bg-clip-text text-transparent">
                        eve
                    </h1>
                    <p class="text-xl sm:text-2xl text-white/70 font-light">
                        Alles für dein Event — in einer App.
                    </p>
                    <p class="text-base text-white/50 max-w-lg mx-auto leading-relaxed">
                        Gäste verwalten, Einladungen verschicken, Fotos sammeln und das Trinkspiel starten — eve bringt deine Feier auf ein neues Level.
                    </p>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-500 text-white font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-purple-900/40"
                        >
                            Zum Dashboard →
                        </Link>
                    </template>
                    <template v-else>
                        <a
                            :href="route('oauth.google.redirect', {}, false)"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-500 text-white font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-purple-900/40 flex items-center gap-2"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#fff"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/></svg>
                            Mit Google starten
                        </a>
                        <Link
                            :href="route('login')"
                            class="px-6 py-3 rounded-xl border border-white/20 text-white/80 font-medium hover:bg-white/5 transition-colors"
                        >
                            Einloggen
                        </Link>
                        <Link
                            :href="route('register')"
                            class="px-6 py-3 rounded-xl border border-white/20 text-white/80 font-medium hover:bg-white/5 transition-colors"
                        >
                            Registrieren
                        </Link>
                    </template>
                </div>
            </div>

            <!-- Scroll hint -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-white/30 animate-bounce">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </section>

        <!-- Features -->
        <section class="px-6 py-24 max-w-5xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">Alles was du brauchst</h2>
                <p class="text-white/50 text-lg max-w-xl mx-auto">Von der Einladung bis zum letzten Foto — eve begleitet dein Event von Anfang bis Ende.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                <!-- Gästeverwaltung -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">👥</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Gästeverwaltung</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Gäste anlegen, in Gruppen organisieren, RSVP verfolgen und Besonderheiten wie Diätwünsche festhalten.</p>
                </div>

                <!-- QR-Einladungen -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">📲</div>
                    <h3 class="text-white font-semibold text-lg mb-2">QR-Einladungen</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Jeder Gast bekommt einen persönlichen QR-Code. Scannen → App öffnet sich → sofort eingeloggt. Kein Passwort nötig.</p>
                </div>

                <!-- Fotogalerie -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">📸</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Fotogalerie</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Alle Gäste laden Fotos direkt in die gemeinsame Galerie hoch. Diashow-Modus für die Leinwand inklusive.</p>
                </div>

                <!-- Fotospiel -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🎯</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Fotospiel</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Gäste bekommen zufällige Foto-Aufgaben zugewiesen — "Zwei Generationen zusammen", "Ein Kuss" und mehr. Interaktion garantiert.</p>
                </div>

                <!-- Getränke & Trinkspiel -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🍾</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Getränke & Trinkspiel</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Getränke tracken, Rangliste in Echtzeit und ein Mengenrechner der dir sagt wieviel du bestellen musst.</p>
                </div>

                <!-- Design & Anpassung -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 hover:bg-white/8 transition-colors">
                    <div class="text-3xl mb-4">🎨</div>
                    <h3 class="text-white font-semibold text-lg mb-2">Ganz dein Event</h3>
                    <p class="text-white/50 text-sm leading-relaxed">Farben, Schriften, Titelbild, Dresscode und Ablaufplan — die App sieht so aus wie dein Event.</p>
                </div>

            </div>
        </section>

        <!-- Footer CTA -->
        <section class="px-6 py-20 text-center border-t border-white/10">
            <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4">Bereit loszulegen?</h2>
            <p class="text-white/50 mb-8">Erstelle dein erstes Event in weniger als einer Minute.</p>
            <template v-if="!$page.props.auth.user">
                <a
                    :href="route('oauth.google.redirect', {}, false)"
                    class="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-gradient-to-r from-purple-600 to-blue-500 text-white font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-purple-900/40 text-lg"
                >
                    Jetzt kostenlos starten →
                </a>
            </template>
            <template v-else>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-gradient-to-r from-purple-600 to-blue-500 text-white font-semibold hover:opacity-90 transition-opacity"
                >
                    Zum Dashboard →
                </Link>
            </template>
            <p class="text-white/30 text-sm mt-6">© {{ new Date().getFullYear() }} eve · eveplan.de</p>
        </section>

    </div>
</template>
