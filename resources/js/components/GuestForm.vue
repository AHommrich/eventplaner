<script setup lang="ts">
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import FoodSpecialMultiSelect from './FoodSpecialMultiSelect.vue';

type GuestFormData = {
    firstname: string;
    lastname: string;
    category_id: string | number;
    group_id: string | number;
    likelihood: 'sure' | 'likely' | 'maybe' | 'unlikely' | 'no';
    invite: boolean;
    food_specials: number[];
};

const props = defineProps<{
    categories: { id: number; title: string }[];
    groups: { id: number; name: string }[];
    foodSpecials: { id: number; name: string }[];
    initialForm?: Partial<GuestFormData>;
    submitLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'submit', form: InertiaForm<GuestFormData>): void;
}>();

const form = useForm<GuestFormData>({
    firstname:   props.initialForm?.firstname   ?? '',
    lastname:    props.initialForm?.lastname    ?? '',
    category_id: props.initialForm?.category_id ?? '',
    group_id:    props.initialForm?.group_id    ?? '',
    likelihood:  props.initialForm?.likelihood  ?? 'maybe',
    invite:      props.initialForm?.invite      ?? false,
    food_specials: props.initialForm?.food_specials ?? [],
});

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
            <select v-model="form.category_id" class="w-full rounded border p-2">
                <option disabled value="">-- Kategorie auswählen --</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.title }}
                </option>
            </select>
            <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-500">{{ form.errors.category_id }}</p>
        </div>

        <!-- Gruppe -->
        <div>
            <select v-model="form.group_id" class="w-full rounded border p-2">
                <option value="">-- Keine Gruppe --</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ group.name }}
                </option>
            </select>
            <p v-if="form.errors.group_id" class="mt-1 text-sm text-red-500">{{ form.errors.group_id }}</p>
        </div>

        <!-- Wahrscheinlichkeit -->
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-600 dark:text-gray-400">Wahrscheinlichkeit</label>
            <select v-model="form.likelihood" class="w-full rounded border p-2">
                <option value="sure">Sicher</option>
                <option value="likely">Wahrscheinlich</option>
                <option value="maybe">Vielleicht</option>
                <option value="unlikely">Unwahrscheinlich</option>
                <option value="no">Nein</option>
            </select>
        </div>

        <!-- Einladung -->
        <div class="flex items-center">
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.invite" />
                Einladung nötig?
            </label>
        </div>

        <!-- Food Specials -->
        <div class="sm:col-span-2">
            <FoodSpecialMultiSelect v-model="form.food_specials" :options="foodSpecials" label="Essensbesonderheiten" placeholder="Bitte auswählen..." />
        </div>

        <!-- Submit -->
        <div class="col-span-2 flex justify-end">
            <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-white sm:w-auto" :disabled="form.processing">
                {{ submitLabel ?? 'Speichern' }}
            </button>
        </div>
    </form>
</template>
