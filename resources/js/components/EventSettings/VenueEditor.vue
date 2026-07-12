<script setup lang="ts">
import LocationPicker from '@/components/EventSettings/LocationPicker.vue';
import { ref } from 'vue';

// Venue-specific adapter around the generic LocationPicker. Keeps the venue*
// model names so Settings.vue binds unchanged, and turns on the venue chrome
// (name + home-screen display mode) and the single-page dirty-reset UX.
const venueName = defineModel<string>('venueName', { default: '' });
const venueLat = defineModel<number | null>('venueLat', { default: null });
const venueLng = defineModel<number | null>('venueLng', { default: null });
const venueStreet = defineModel<string>('venueStreet', { default: '' });
const venueHouseNumber = defineModel<string>('venueHouseNumber', { default: '' });
const venuePostalCode = defineModel<string>('venuePostalCode', { default: '' });
const venueCity = defineModel<string>('venueCity', { default: '' });
const venueState = defineModel<string>('venueState', { default: '' });
const venueCountry = defineModel<string>('venueCountry', { default: 'Deutschland' });
const venueDisplayMode = defineModel<string>('venueDisplayMode', { default: 'both' });

const picker = ref<InstanceType<typeof LocationPicker> | null>(null);

// Forwarded to Settings.vue submit onSuccess to update the dirty baseline.
function resetDirtyBaseline() {
    picker.value?.resetDirtyBaseline();
}
defineExpose({ resetDirtyBaseline });
</script>

<template>
    <LocationPicker
        ref="picker"
        v-model:name="venueName"
        v-model:lat="venueLat"
        v-model:lng="venueLng"
        v-model:street="venueStreet"
        v-model:house-number="venueHouseNumber"
        v-model:postal-code="venuePostalCode"
        v-model:city="venueCity"
        v-model:state="venueState"
        v-model:country="venueCountry"
        v-model:display-mode="venueDisplayMode"
        :enable-field-reset="true"
    />
</template>
