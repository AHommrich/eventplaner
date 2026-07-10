<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    updated_at: string;
    sections: Array<{
        id: string;
        heading: string;
        body_html: string;
    }>;
}>();

const updatedAtFormatted = computed(() => {
    const d = new Date(props.updated_at);
    return d.toLocaleDateString('de-DE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <Head title="Datenschutzerklärung — eveplan" />
    <div class="min-h-screen bg-white text-gray-900 dark:bg-[#0d0d14] dark:text-gray-100">
        <main class="mx-auto max-w-2xl px-6 py-16">
            <Link href="/" class="text-muted-foreground text-sm hover:underline">&larr; Zurück zur Startseite</Link>

            <h1 class="mt-6 text-3xl font-semibold tracking-tight">Datenschutzerklärung</h1>
            <p class="text-muted-foreground mt-2 text-sm">Zuletzt aktualisiert: {{ updatedAtFormatted }}</p>

            <section class="prose dark:prose-invert mt-10 max-w-none">
                <template v-for="section in sections" :key="section.id">
                    <h2 :id="section.id">{{ section.heading }}</h2>
                    <div v-html="section.body_html" />
                </template>
            </section>

            <footer class="text-muted-foreground mt-12 flex gap-4 text-sm">
                <Link href="/" class="hover:underline">Startseite</Link>
                <Link :href="route('legal.imprint')" class="hover:underline">Impressum</Link>
            </footer>
        </main>
    </div>
</template>
