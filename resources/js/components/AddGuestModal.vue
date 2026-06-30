<script setup lang="ts">
import { useAddGuestModal } from '@/composables/useAddGuestModal';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import GuestForm from './GuestForm.vue';
import InputModal from './InputModal.vue';

const { t } = useI18n();
const { open } = useAddGuestModal();
const page = usePage();

const groups = computed(() => (page.props as any).groups as { id: number; name: string }[]);
const foodSpecials = computed(() => (page.props as any).food_specials as { id: number; name: string }[]);

function handleCreate(form: any) {
    form.post(route('guests.store'), {
        onSuccess: () => {
            form.reset();
            open.value = false;
            toast.success(t('toast.guestCreated'));
            router.reload();
        },
    });
}
</script>

<template>
    <InputModal v-model:open="open" :title="t('guest.createNew')" max-width="max-w-lg">
        <GuestForm :groups="groups ?? []" :food-specials="foodSpecials ?? []" :submit-label="t('guest.create')" @submit="handleCreate" />
    </InputModal>
</template>
