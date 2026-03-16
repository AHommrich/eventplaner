<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Getränke', href: '/drinks' }];

const page = usePage();
const drinks = computed(() => page.props.drinks as { id: number; name: string }[]);

const form = useForm({ name: '' });

function submit() {
    form.post(route('drinks.store'), { onSuccess: () => form.reset() });
}

function deleteDrink(id: number) {
    router.delete(route('drinks.destroy', id));
}
</script>

<template>
    <Head title="Getränke" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <!-- Neues Getränk -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h2 class="mb-3 text-lg font-semibold">Getränk hinzufügen</h2>
                <form @submit.prevent="submit" class="flex gap-2">
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="z.B. Bier, Wein, Limo, Wasser..."
                        class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        required
                    />
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Hinzufügen
                    </button>
                </form>
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
            </div>

            <!-- Liste -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h2 class="mb-3 text-lg font-semibold">Getränke für dieses Event</h2>
                <p v-if="drinks.length === 0" class="text-sm text-gray-400">Noch keine Getränke angelegt.</p>
                <ul v-else class="divide-y dark:divide-gray-700">
                    <li v-for="drink in drinks" :key="drink.id" class="flex items-center justify-between py-2">
                        <span class="text-sm">{{ drink.name }}</span>
                        <button
                            @click="deleteDrink(drink.id)"
                            class="rounded bg-red-600 px-3 py-1 text-xs text-white hover:bg-red-700"
                        >
                            Löschen
                        </button>
                    </li>
                </ul>
            </div>

        </div>
    </AppLayout>
</template>
