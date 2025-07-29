<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    guest: any;
    badges: any[];
    families: any[];
    food_specials: any[];
}>();

function handleUpdate(form: any) {
    form.put(route('guests.update', props.guest.id));
}
</script>

<template>
    <AppLayout>
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">Gast bearbeiten</h2>
            <GuestForm
                :badges="badges"
                :families="families"
                :food-specials="food_specials"
                :initial-form="guest"
                submit-label="Änderungen speichern"
                @submit="handleUpdate"
            />
        </div>
    </AppLayout>
</template>
