<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    email: string;
    hasEvent: boolean;
}>();

const copied = ref(false);
function copyEmail() {
    navigator.clipboard.writeText(props.email);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const eventForm = useForm({ name: '', date: '' });
const showCreateForm = ref(false);

function createEvent() {
    eventForm.post(route('events.store'));
}
</script>

<template>
    <Head title="Willkommen" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center p-6">
        <div class="w-full max-w-md space-y-6">

            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Willkommen!</h1>
                <p v-if="hasEvent" class="mt-2 text-sm text-green-600 dark:text-green-400">
                    Dein Event wurde erstellt. Der Admin gibt dir bald Zugriff auf alle Funktionen.
                </p>
                <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Du hast noch kein Event. Erstelle eines oder werde von einem Admin eingeladen.
                </p>
            </div>

            <!-- Email anzeigen mit Kopier-Funktion -->
            <div class="rounded-xl border bg-white dark:bg-gray-900 p-4 space-y-2">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Deine Email-Adresse</p>
                <div class="flex items-center gap-2">
                    <span class="flex-1 font-mono text-sm text-gray-800 dark:text-gray-200 truncate">{{ email }}</span>
                    <button
                        @click="copyEmail"
                        class="shrink-0 px-3 py-1.5 text-xs rounded-lg border transition"
                        :class="copied
                            ? 'bg-green-100 border-green-300 text-green-700'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50'"
                    >
                        {{ copied ? 'Kopiert!' : 'Kopieren' }}
                    </button>
                </div>
                <p class="text-xs text-gray-400">
                    Teile diese Adresse mit dem Admin, damit er dich zu einem Event hinzufügen kann.
                </p>
            </div>

            <!-- Event erstellen -->
            <div class="rounded-xl border bg-white dark:bg-gray-900 p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Eigenes Event erstellen</p>
                    <button
                        v-if="!showCreateForm"
                        @click="showCreateForm = true"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                    >
                        Event erstellen
                    </button>
                </div>

                <form v-if="showCreateForm" @submit.prevent="createEvent" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name</label>
                        <input
                            v-model="eventForm.name"
                            type="text"
                            placeholder="z.B. Hochzeit Max & Anna"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        />
                        <p v-if="eventForm.errors.name" class="mt-1 text-xs text-red-500">{{ eventForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Datum (optional)</label>
                        <input
                            v-model="eventForm.date"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="eventForm.processing"
                            class="flex-1 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ eventForm.processing ? 'Wird erstellt...' : 'Erstellen' }}
                        </button>
                        <button
                            type="button"
                            @click="showCreateForm = false"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</template>
