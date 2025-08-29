<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue'; // <-- Neue Form Komponente
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// Zugriff auf Daten von Inertia
const page = usePage();
const badges = computed(() => page.props.badges as { id: number; title: string }[]);
const guests = computed(() => page.props.guests as any[]);
const families = computed(() => page.props.families as { id: number; name: string }[]);
const foodSpecials = computed(() => page.props.food_specials as { id: number; name: string }[]);

// Kategorie-Formular
const categoryForm = useForm({ title: '' });
const submitCategory = () => categoryForm.post(route('badges.store'), { onSuccess: () => categoryForm.reset() });

// Family-Formular
const familyForm = useForm({ name: '' });
const submitFamily = () => familyForm.post(route('families.store'), { onSuccess: () => familyForm.reset() });

// Food Special-Formular
const foodSpecialForm = useForm({ name: '' });
const submitFoodSpecial = () => foodSpecialForm.post(route('foodspecials.store'), { onSuccess: () => foodSpecialForm.reset() });

// Filter
const badgeFilter = ref('');
const filteredGuests = computed(() => {
    if (!badgeFilter.value) return guests.value;
    return guests.value.filter((guest: any) => guest.badge?.title === badgeFilter.value);
});

// Gast anlegen (wird von GuestForm genutzt)
function handleCreate(form: any) {
    form.post(route('guests.store'), { onSuccess: () => form.reset() });
}

// Gast löschen
function deleteGuest(id: number) {
    if (confirm('Möchten Sie diesen Gast wirklich löschen?')) {
        router.delete(route('guests.destroy', id));
    }
}
</script>

<template>
    <Head title="Formulare" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid h-full flex-1 grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <!-- Linke Spalte: Kategorie, Familie, FoodSpecial -->
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

                <!-- Food Special Formular -->
                <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                    <h2 class="mb-2 text-lg font-semibold">Neue Essensbesonderheit</h2>
                    <form @submit.prevent="submitFoodSpecial" class="flex flex-col gap-2 sm:flex-row">
                        <input v-model="foodSpecialForm.name" type="text" placeholder="Essensbesonderheit" class="flex-1 rounded border p-2" />
                        <button
                            type="submit"
                            class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto"
                            :disabled="foodSpecialForm.processing"
                        >
                            Erstellen
                        </button>
                    </form>
                    <p v-if="foodSpecialForm.errors.name" class="mt-1 text-sm text-red-500">{{ foodSpecialForm.errors.name }}</p>
                </div>
            </div>

            <!-- Rechte Spalte: Guest-Formular (Komponente) -->
            <div class="rounded-xl border bg-white p-4 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold">Neuen Gast erstellen</h2>
                <GuestForm :badges="badges" :families="families" :food-specials="foodSpecials" submit-label="Gast erstellen" @submit="handleCreate" />
            </div>
        </div>

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
                        <th class="p-3">Einladung</th>
                        <th class="p-3">Food Specials</th>
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

                        <td class="p-3">
                            {{ guest.drinks?.find((d: any) => d.drink_type === 'beer')?.thirst_level ?? '-' }}
                        </td>

                        <td class="p-3">
                            {{ guest.drinks?.find((d: any) => d.drink_type === 'wine')?.thirst_level ?? '-' }}
                        </td>

                        <td class="p-3">
                            {{ guest.invite ? 'Ja' : 'Nein' }}
                        </td>

                        <td class="p-3">
                            <span v-if="guest.food_specials?.length">
                                {{ guest.food_specials.map((fs: any) => fs.name).join(', ') }}
                            </span>
                            <span v-else>-</span>
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
    </AppLayout>
</template>
