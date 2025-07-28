<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    guest: any;
    badges: any[];
    families: any[];
}>();

const form = useForm({
    firstname: props.guest.firstname,
    lastname: props.guest.lastname,
    badge_id: props.guest.badge_id ?? '',
    family_id: props.guest.family_id ?? '',
    likelihood: props.guest.likelihood ?? 'maybe',
    beer: !!props.guest.drinks.find((d: any) => d.drink_type === 'beer'),
    beer_thirst: props.guest.drinks.find((d: any) => d.drink_type === 'beer')?.thirst_level ?? 1,
    wine: !!props.guest.drinks.find((d: any) => d.drink_type === 'wine'),
    wine_thirst: props.guest.drinks.find((d: any) => d.drink_type === 'wine')?.thirst_level ?? 1,
});

function submit() {
    form.put(route('guests.update', props.guest.id));
}
</script>

<template>
    <AppLayout>
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">Gast bearbeiten</h2>
            <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                <!-- Vorname -->
                <div>
                    <input v-model="form.firstname" type="text" placeholder="Vorname" class="w-full rounded border p-2" />
                    <p v-if="form.errors.firstname" class="mt-1 text-sm text-red-500">{{ form.errors.firstname }}</p>
                </div>

                <!-- Nachname -->
                <div>
                    <input v-model="form.lastname" type="text" placeholder="Nachname" class="w-full rounded border p-2" />
                    <p v-if="form.errors.lastname" class="mt-1 text-sm text-red-500">{{ form.errors.lastname }}</p>
                </div>

                <!-- Kategorie -->
                <div>
                    <select v-model="form.badge_id" class="w-full rounded border p-2">
                        <option disabled value="">-- Kategorie auswählen --</option>
                        <option v-for="category in badges" :key="category.id" :value="category.id">
                            {{ category.title }}
                        </option>
                    </select>
                    <p v-if="form.errors.badge_id" class="mt-1 text-sm text-red-500">{{ form.errors.badge_id }}</p>
                </div>

                <!-- Familie -->
                <div>
                    <select v-model="form.family_id" class="w-full rounded border p-2">
                        <option value="">-- Keine Familie --</option>
                        <option v-for="family in families" :key="family.id" :value="family.id">
                            {{ family.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.family_id" class="mt-1 text-sm text-red-500">{{ form.errors.family_id }}</p>
                </div>

                <!-- Bier -->
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        @click="
                            form.beer = !form.beer;
                            if (!form.beer) form.beer_thirst = 1;
                        "
                        :class="[
                            'rounded border px-4 py-2 transition-colors duration-200',
                            form.beer ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-gray-200 text-gray-500',
                        ]"
                    >
                        Bier
                    </button>
                    <div v-if="form.beer" class="flex items-center gap-2">
                        <input type="range" v-model="form.beer_thirst" min="1" max="10" class="w-full accent-green-600" />
                        <span class="w-6 text-center font-semibold">{{ form.beer_thirst }}</span>
                    </div>
                </div>

                <!-- Wein -->
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        @click="
                            form.wine = !form.wine;
                            if (!form.wine) form.wine_thirst = 1;
                        "
                        :class="[
                            'rounded border px-4 py-2 transition-colors duration-200',
                            form.wine ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-gray-200 text-gray-500',
                        ]"
                    >
                        Wein
                    </button>
                    <div v-if="form.wine" class="flex items-center gap-2">
                        <input type="range" v-model="form.wine_thirst" min="1" max="10" class="w-full accent-green-600" />
                        <span class="w-6 text-center font-semibold">{{ form.wine_thirst }}</span>
                    </div>
                </div>

                <!-- Wahrscheinlichkeit -->
                <div>
                    <label>Wahrscheinlichkeit</label>
                    <select v-model="form.likelihood" class="w-full rounded border p-2">
                        <option value="sure">Sicher</option>
                        <option value="likely">Wahrscheinlich</option>
                        <option value="maybe">Vielleicht</option>
                        <option value="unlikely">Unwahrscheinlich</option>
                        <option value="no">Nein</option>
                    </select>
                    <p v-if="form.errors.likelihood" class="mt-1 text-sm text-red-500">{{ form.errors.likelihood }}</p>
                </div>

                <!-- Submit -->
                <div class="col-span-2 flex justify-end">
                    <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="form.processing">
                        Änderungen speichern
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
