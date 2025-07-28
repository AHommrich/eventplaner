<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// Zugriff auf Daten von Inertia
const page = usePage();
const categories = computed(() => page.props.badges as { id: number; title: string }[]);
const guests = computed(() => page.props.guests as any[]);
const families = computed(() => page.props.families as { id: number; name: string }[]);

// Kategorie-Formular
const categoryForm = useForm({ title: '' });
const submitCategory = () => categoryForm.post(route('badges.store'), { onSuccess: () => categoryForm.reset() });

// Family-Formular
const familyForm = useForm({ name: '' });
const submitFamily = () => familyForm.post(route('families.store'), { onSuccess: () => familyForm.reset() });

// Guest-Formular
const guestForm = useForm<{
    firstname: string;
    lastname: string;
    badge_id: string;
    family_id: string;
    beer: boolean;
    beer_thirst: number | null;
    wine: boolean;
    wine_thirst: number | null;
    likelihood: string;
}>({
    firstname: '',
    lastname: '',
    badge_id: '',
    family_id: '',
    beer: false,
    beer_thirst: 5,
    wine: false,
    wine_thirst: 5,
    likelihood: 'maybe',
});
const submitGuest = () => guestForm.post(route('guests.store'), { onSuccess: () => guestForm.reset() });

// Gast löschen
function deleteGuest(id: number) {
    if (confirm('Möchten Sie diesen Gast wirklich löschen?')) {
        router.delete(route('guests.destroy', id));
    }
}
//
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid h-full flex-1 grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <!-- Linke Spalte: Kategorie & Familie -->
            <div class="flex flex-col gap-4">
                <!-- Kategorie Formular -->
                <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                    <h2 class="mb-2 text-lg font-semibold">Neue Kategorie</h2>
                    <form @submit.prevent="submitCategory" class="flex flex-col gap-2 sm:flex-row">
                        <input v-model="categoryForm.title" type="text" placeholder="Kategoriename" class="flex-1 rounded border p-2" />
                        <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="categoryForm.processing">
                            Erstellen
                        </button>
                    </form>
                    <p v-if="categoryForm.errors.title" class="mt-1 text-sm text-red-500">{{ categoryForm.errors.title }}</p>
                </div>

                <!-- Family Formular -->
                <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                    <h2 class="mb-2 text-lg font-semibold">Neue Familie</h2>
                    <form @submit.prevent="submitFamily" class="flex flex-col gap-2 sm:flex-row">
                        <input v-model="familyForm.name" type="text" placeholder="Familienname" class="flex-1 rounded border p-2" />
                        <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="familyForm.processing">
                            Erstellen
                        </button>
                    </form>
                    <p v-if="familyForm.errors.name" class="mt-1 text-sm text-red-500">{{ familyForm.errors.name }}</p>
                </div>
            </div>

            <!-- Rechte Spalte: Guest-Formular -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold">Neuen Gast erstellen</h2>
                <form @submit.prevent="submitGuest" class="grid gap-4 sm:grid-cols-2">
                    <!-- Vorname -->
                    <div>
                        <input v-model="guestForm.firstname" type="text" placeholder="Vorname" class="w-full rounded border p-2" />
                        <p v-if="guestForm.errors.firstname" class="mt-1 text-sm text-red-500">{{ guestForm.errors.firstname }}</p>
                    </div>

                    <!-- Nachname -->
                    <div>
                        <input v-model="guestForm.lastname" type="text" placeholder="Nachname" class="w-full rounded border p-2" />
                        <p v-if="guestForm.errors.lastname" class="mt-1 text-sm text-red-500">{{ guestForm.errors.lastname }}</p>
                    </div>

                    <!-- Kategorie -->
                    <div>
                        <select v-model="guestForm.badge_id" class="w-full rounded border p-2">
                            <option disabled value="">-- Kategorie auswählen --</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.title }}
                            </option>
                        </select>
                        <p v-if="guestForm.errors.badge_id" class="mt-1 text-sm text-red-500">{{ guestForm.errors.badge_id }}</p>
                    </div>

                    <!-- Familie -->
                    <div>
                        <select v-model="guestForm.family_id" class="w-full rounded border p-2">
                            <option value="">-- Keine Familie --</option>
                            <option v-for="family in families" :key="family.id" :value="family.id">
                                {{ family.name }}
                            </option>
                        </select>
                        <p v-if="guestForm.errors.family_id" class="mt-1 text-sm text-red-500">{{ guestForm.errors.family_id }}</p>
                    </div>

                    <!-- Bier -->
                    <div class="flex flex-col gap-2">
                        <button
                            type="button"
                            @click="
                                guestForm.beer = !guestForm.beer;
                                if (!guestForm.beer) guestForm.beer_thirst = 1; // Reset, wenn deaktiviert
                            "
                            :class="[
                                'rounded border px-4 py-2 transition-colors duration-200',
                                guestForm.beer ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-gray-200 text-gray-500',
                            ]"
                        >
                            Bier
                        </button>
                        <div v-if="guestForm.beer" class="flex items-center gap-2">
                            <input type="range" v-model="guestForm.beer_thirst" min="1" max="10" class="w-full accent-green-600" />
                            <span class="w-6 text-center font-semibold">{{ guestForm.beer_thirst }}</span>
                        </div>
                    </div>

                    <!-- Wein -->
                    <div class="flex flex-col gap-2">
                        <button
                            type="button"
                            @click="
                                guestForm.wine = !guestForm.wine;
                                if (!guestForm.wine) guestForm.wine_thirst = 1; // Reset, wenn deaktiviert
                            "
                            :class="[
                                'rounded border px-4 py-2 transition-colors duration-200',
                                guestForm.wine ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-gray-200 text-gray-500',
                            ]"
                        >
                            Wein
                        </button>
                        <div v-if="guestForm.wine" class="flex items-center gap-2">
                            <input type="range" v-model="guestForm.wine_thirst" min="1" max="10" class="w-full accent-green-600" />
                            <span class="w-6 text-center font-semibold">{{ guestForm.wine_thirst }}</span>
                        </div>
                    </div>

                    <div>
                        <label>Wahrscheinlichkeit</label>
                        <select v-model="guestForm.likelihood" class="w-full rounded border p-2">
                            <option value="sure">Sicher</option>
                            <option value="likely">Wahrscheinlich</option>
                            <option value="maybe">Vielleicht</option>
                            <option value="unlikely">Unwahrscheinlich</option>
                            <option value="no">Nein</option>
                        </select>
                    </div>

                    <!-- Submit -->
                    <div class="col-span-2 flex justify-end">
                        <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="guestForm.processing">
                            Gast erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gäste Liste -->
        <!-- Gäste Liste -->
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">Aktuelle Gäste</h2>
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
                        <tr v-for="guest in guests" :key="guest.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3">{{ guest.firstname }}</td>
                            <td class="p-3">{{ guest.lastname }}</td>
                            <td class="p-3">{{ guest.badge?.title ?? '-' }}</td>
                            <td class="p-3">{{ guest.family?.name ?? '-' }}</td>

                            <!-- Likelihood mit deutschen Übersetzungen und Farben -->
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
                                <button @click="deleteGuest(guest.id)" class="rounded bg-red-600 px-3 py-1 text-white hover:bg-red-700">
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
