<script setup lang="ts">
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import FoodSpecialMultiSelect from './FoodSpecialMultiSelect.vue';

type GuestFormData = {
    firstname: string;
    lastname: string;
    badge_id: string | number;
    family_id: string | number;
    likelihood: 'sure' | 'likely' | 'maybe' | 'unlikely' | 'no';
    beer: boolean;
    beer_thirst: number;
    wine: boolean;
    wine_thirst: number;
    invite: boolean;
    food_specials: number[];
};

const props = defineProps<{
    badges: { id: number; title: string }[];
    families: { id: number; name: string }[];
    foodSpecials: { id: number; name: string }[];
    initialForm?: Partial<GuestFormData>;
    submitLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'submit', form: InertiaForm<GuestFormData>): void;
}>();

// Defaultwerte oder initiale Werte übernehmen
const form = useForm<GuestFormData>({
    firstname: props.initialForm?.firstname ?? '',
    lastname: props.initialForm?.lastname ?? '',
    badge_id: props.initialForm?.badge_id ?? '',
    family_id: props.initialForm?.family_id ?? '',
    likelihood: props.initialForm?.likelihood ?? 'maybe',
    beer: props.initialForm?.beer ?? false,
    beer_thirst: props.initialForm?.beer_thirst ?? 5,
    wine: props.initialForm?.wine ?? false,
    wine_thirst: props.initialForm?.wine_thirst ?? 5,
    invite: props.initialForm?.invite ?? false,
    food_specials: props.initialForm?.food_specials ?? [],
});

// Sende-Event
function submit() {
    emit('submit', form);
}
</script>

<template>
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
                <option v-for="badge in badges" :key="badge.id" :value="badge.id">
                    {{ badge.title }}
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
        </div>

        <!-- Einladung -->
        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.invite" />
                Einladung nötig?
            </label>
        </div>

        <!-- Food Specials -->
        <FoodSpecialMultiSelect v-model="form.food_specials" :options="foodSpecials" label="Essensbesonderheiten" placeholder="Bitte auswählen..." />

        <!-- Submit -->
        <div class="col-span-2 flex justify-end">
            <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="form.processing">
                {{ submitLabel ?? 'Speichern' }}
            </button>
        </div>
    </form>
</template>
