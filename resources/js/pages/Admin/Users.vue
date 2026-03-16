<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface Event {
    id: number;
    name: string;
}

const props = defineProps<{
    users: User[];
    events: Event[];
}>();

// User zu Event hinzufügen
const addForm = useForm({ email: '', event_id: '' });
function addToEvent() {
    addForm.post(route('admin.users.addToEvent'), { onSuccess: () => addForm.reset() });
}

// Rolle ändern
function updateRole(user: User, role: string) {
    useForm({ role }).put(route('admin.users.update', user.id));
}

// User löschen
const deleteForm = useForm({});
function deleteUser(user: User) {
    if (confirm(`${user.name} wirklich löschen?`)) {
        deleteForm.delete(route('admin.users.destroy', user.id));
    }
}
</script>

<template>
    <Head title="User-Verwaltung" />
    <AppLayout>
        <div class="p-6 space-y-8">
            <h1 class="text-2xl font-bold">User-Verwaltung</h1>

            <!-- User zu Event hinzufügen -->
            <section class="rounded-xl border bg-white dark:bg-gray-900 p-5 space-y-4">
                <h2 class="text-base font-semibold">User zu Event hinzufügen</h2>
                <form @submit.prevent="addToEvent" class="flex flex-col sm:flex-row gap-3">
                    <input
                        v-model="addForm.email"
                        type="email"
                        placeholder="Email-Adresse des Users"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    />
                    <select
                        v-model="addForm.event_id"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <option value="">Event auswählen</option>
                        <option v-for="event in events" :key="event.id" :value="event.id">
                            {{ event.name }}
                        </option>
                    </select>
                    <button
                        type="submit"
                        :disabled="addForm.processing"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 whitespace-nowrap"
                    >
                        Hinzufügen
                    </button>
                </form>
                <p v-if="addForm.errors.email" class="text-xs text-red-500">{{ addForm.errors.email }}</p>
                <p v-if="addForm.errors.event_id" class="text-xs text-red-500">{{ addForm.errors.event_id }}</p>
            </section>

            <!-- User-Liste -->
            <section>
                <h2 class="text-base font-semibold mb-3">Alle User ({{ users.length }})</h2>
                <div class="rounded-xl border overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Rolle</th>
                                <th class="px-4 py-3 text-left">Registriert</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ user.email }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        :value="user.role"
                                        @change="updateRole(user, ($event.target as HTMLSelectElement).value)"
                                        class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 text-xs"
                                    >
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs">
                                    {{ new Date(user.created_at).toLocaleDateString('de-DE') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="deleteUser(user)"
                                        class="text-xs text-red-500 hover:text-red-700"
                                    >
                                        Löschen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
