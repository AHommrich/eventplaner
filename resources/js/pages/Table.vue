<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gäste', href: '/dashboard' }];

// Zugriff auf Daten von Inertia
const page = usePage();
const guests = computed(() => page.props.guests as any[]);

// Filter
const badgeFilter = ref('');

// Gefilterte Gäste
const filteredGuests = computed(() => {
    if (!badgeFilter.value) return guests.value;
    return guests.value.filter((guest: any) => guest.badge?.title === badgeFilter.value);
});

function deleteGuest(id: number) {
    if (confirm('Möchten Sie diesen Gast wirklich löschen?')) {
        router.delete(route('guests.destroy', id));
    }
}
</script>

<template>
    <Head title="Gäste" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">Aktuelle Gäste</h2>

            <!-- Filter -->
            <div class="mb-4 flex items-center space-x-4">
                <label for="badgeFilter" class="font-medium">Nach Kategorie filtern:</label>
                <select id="badgeFilter" v-model="badgeFilter" class="rounded border p-2">
                    <option value="">Alle</option>
                    <option v-for="badge in [...new Set(guests.map((g) => g.badge?.title).filter(Boolean))]" :key="badge" :value="badge">
                        {{ badge }}
                    </option>
                </select>
            </div>

            <!-- Gäste Liste -->
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="p-3">Vorname</th>
                            <th class="p-3">Nachname</th>
                            <th class="p-3">Kategorie</th>
                            <th class="p-3">Familie</th>
                            <th class="p-3">Wahrscheinlichkeit</th>
                            <th class="p-3">Bier (1–10)</th>
                            <th class="p-3">Wein (1–10)</th>
                            <th class="p-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="guest in filteredGuests"
                            :key="guest.id"
                            class="cursor-pointer border-b hover:bg-gray-50 dark:hover:bg-gray-800"
                            @click="router.visit(route('guests.edit', guest.id))"
                        >
                            <td class="p-3">{{ guest.firstname }}</td>
                            <td class="p-3">{{ guest.lastname }}</td>
                            <td class="p-3">{{ guest.badge?.title ?? '-' }}</td>
                            <td class="p-3">{{ guest.family?.name ?? '-' }}</td>

                            <!-- Likelihood -->
                            <td class="p-3">
                                <span
                                    :class="[
                                        'rounded px-2 py-1 text-sm font-medium text-white',
                                        {
                                            'bg-green-600': guest.likelihood === 'sure',
                                            'bg-lime-500': guest.likelihood === 'likely',
                                            'bg-yellow-400 text-black': guest.likelihood === 'maybe',
                                            'bg-orange-500': guest.likelihood === 'unlikely',
                                            'bg-red-600': guest.likelihood === 'no',
                                        },
                                    ]"
                                >
                                    {{
                                        guest.likelihood === 'sure'
                                            ? 'Sicher'
                                            : guest.likelihood === 'likely'
                                              ? 'Wahrscheinlich'
                                              : guest.likelihood === 'maybe'
                                                ? 'Vielleicht'
                                                : guest.likelihood === 'unlikely'
                                                  ? 'Unwahrscheinlich'
                                                  : 'Nein'
                                    }}
                                </span>
                            </td>

                            <!-- Bier Durstgrad -->
                            <td class="p-3">
                                {{ guest.drinks?.find((d: any) => d.drink_type === 'beer')?.thirst_level ?? '-' }}
                            </td>

                            <!-- Wein Durstgrad -->
                            <td class="p-3">
                                {{ guest.drinks?.find((d: any) => d.drink_type === 'wine')?.thirst_level ?? '-' }}
                            </td>

                            <td class="p-3 text-right">
                                <button @click.stop="deleteGuest(guest.id)" class="rounded bg-red-600 px-3 py-1 text-white hover:bg-red-700">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
