<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Impressum — eveplan" />
    <div class="min-h-screen bg-white text-gray-900 dark:bg-[#0d0d14] dark:text-gray-100">
        <main class="mx-auto max-w-2xl px-6 py-16">
            <Link href="/" class="text-sm text-muted-foreground hover:underline">&larr; Zurück zur Startseite</Link>

            <h1 class="mt-6 text-3xl font-semibold tracking-tight">Impressum</h1>
            <p class="mt-2 text-sm text-muted-foreground">Angaben gemäß § 5 DDG</p>

            <section class="prose dark:prose-invert mt-10 max-w-none">
                <h2>Verantwortlich für den Inhalt</h2>
                <p>
                    André Hommrich<br />
                    Dernbacher Str. 26<br />
                    56410 Montabaur<br />
                    Deutschland
                </p>

                <h2>Kontakt</h2>
                <p>
                    E-Mail: <a href="mailto:andre-hommrich@web.de">andre-hommrich@web.de</a>
                </p>

                <h2>Verantwortlich i. S. d. § 18 Abs. 2 MStV</h2>
                <p>
                    André Hommrich (Anschrift wie oben)
                </p>

                <h2>Haftungsausschluss</h2>
                <p>
                    Die Inhalte dieser Seite werden mit größtmöglicher Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität
                    übernehme ich jedoch keine Gewähr. Als Diensteanbieter bin ich gemäß § 7 Abs. 1 DDG für eigene Inhalte verantwortlich. Nach
                    §§ 8 bis 10 DDG bin ich als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen
                    zu überwachen.
                </p>

                <h2>Urheberrecht</h2>
                <p>
                    Inhalte und Werke auf dieser Seite unterliegen dem deutschen Urheberrecht. Sofern Inhalte auf dieser Seite nicht vom
                    Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet.
                </p>

                <h2>Online-Streitbeilegung</h2>
                <p>
                    Die EU-Kommission stellt eine Plattform zur Online-Streitbeilegung bereit, die unter
                    <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">
                        https://ec.europa.eu/consumers/odr
                    </a>
                    erreichbar ist. Ich bin nicht verpflichtet und nicht bereit, an einem Streitbeilegungsverfahren vor einer
                    Verbraucherschlichtungsstelle teilzunehmen.
                </p>
            </section>

            <footer class="mt-12 flex gap-4 text-sm text-muted-foreground">
                <Link href="/" class="hover:underline">Startseite</Link>
                <Link :href="route('legal.privacy')" class="hover:underline">Datenschutz</Link>
            </footer>
        </main>
    </div>
</template>
