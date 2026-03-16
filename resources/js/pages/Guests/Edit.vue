<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    guest: any;
    categories: any[];
    groups: any[];
    food_specials: any[];
    qr_url: string | null;
}>();

const { t } = useI18n();
const qrDataUrl = ref<string | null>(null);

onMounted(async () => {
    if (props.qr_url) {
        qrDataUrl.value = await QRCode.toDataURL(props.qr_url, { width: 200, margin: 1 });
    }
});

function handleUpdate(form: any) {
    form.put(route('guests.update', props.guest.id), { onSuccess: () => toast.success(t('toast.guestSaved')) });
}
</script>

<template>
    <AppLayout>
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">{{ t('guest.edit') }}</h2>
            <GuestForm
                :categories="categories"
                :groups="groups"
                :food-specials="food_specials"
                :initial-form="guest"
                :submit-label="t('guest.saveChanges')"
                @submit="handleUpdate"
            />

            <div class="mt-6 border-t pt-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.invitationQR') }}</h3>
                <template v-if="qrDataUrl">
                    <img :src="qrDataUrl" alt="QR-Code" class="rounded border" />
                    <p class="mt-2 break-all text-xs text-gray-400">{{ qr_url }}</p>
                </template>
                <p v-else class="text-sm text-gray-400">{{ t('guest.noToken') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
