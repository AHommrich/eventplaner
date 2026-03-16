<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    event:   { id: number; name: string };
    owner:   { id: number; name: string; email: string };
    members: { id: number; name: string; email: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Zugang verwalten', href: '/event/access' }];

const form = useForm({ email: '' });

function invite() {
    form.post(route('event.access.invite'), { onSuccess: () => form.reset() });
}

function remove(userId: number) {
    router.delete(route('event.access.remove', userId));
}
</script>

<template>
    <Head title="Zugang verwalten" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <!-- Header -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h2 class="text-lg font-semibold">{{ event.name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Verwalte wer Zugang zu diesem Event hat.
                </p>
            </div>

            <!-- User einladen -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h3 class="mb-3 font-medium">User hinzufügen</h3>
                <form @submit.prevent="invite" class="flex gap-2">
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="Email-Adresse des Users"
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
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-500">{{ form.errors.email }}</p>
            </div>

            <!-- Aktueller Zugang -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h3 class="mb-3 font-medium">Aktueller Zugang</h3>
                <ul class="divide-y dark:divide-gray-700">

                    <!-- Owner -->
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <p class="text-sm font-medium">{{ owner.name }}</p>
                            <p class="text-xs text-gray-400">{{ owner.email }}</p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            Owner
                        </span>
                    </li>

                    <!-- Geteilte Member -->
                    <li v-for="member in members" :key="member.id" class="flex items-center justify-between py-2">
                        <div>
                            <p class="text-sm font-medium">{{ member.name }}</p>
                            <p class="text-xs text-gray-400">{{ member.email }}</p>
                        </div>
                        <button
                            @click="remove(member.id)"
                            class="rounded bg-red-600 px-3 py-1 text-xs text-white hover:bg-red-700"
                        >
                            Entfernen
                        </button>
                    </li>

                    <li v-if="members.length === 0" class="py-3 text-center text-sm text-gray-400">
                        Noch niemand anderes hat Zugang.
                    </li>
                </ul>
            </div>

        </div>
    </AppLayout>
</template>
