<script setup lang="ts">
import { useEventRequestModal } from '@/composables/useEventRequestModal';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { open } = useEventRequestModal();

onMounted(() => {
    open.value = true;
});
</script>

<template>
    <Head :title="t('onboarding.requestTitle')" />
    <AppLayout />
</template>
