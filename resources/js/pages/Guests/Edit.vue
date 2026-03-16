<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';

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

// App-Zugang Toggle
const appAccessForm = useForm({ app_access: props.guest.app_access ?? true });
function toggleAppAccess() {
    appAccessForm.patch(route('guests.app-access', props.guest.id), {
        onSuccess: () => toast.success(appAccessForm.app_access ? t('toast.appAccessEnabled') : t('toast.appAccessDisabled')),
    });
}

// RSVP Admin-Override (separat)
const rsvpForm = useForm({ rsvp_status: props.guest.rsvp_status ?? '' });
function submitRsvp() {
    rsvpForm.transform(data => ({ rsvp_status: data.rsvp_status === '' ? null : data.rsvp_status }))
        .post(route('guests.admin-rsvp', props.guest.id), {
            onSuccess: () => toast.success(t('toast.rsvpOverridden')),
        });
}

const rsvpLabel: Record<string, string> = {
    accepted_pending:     'guest.rsvpAcceptedPending',
    accepted:             'guest.rsvpAccepted',
    declined_pending:     'guest.rsvpDeclinedPending',
    declined:             'guest.rsvpDeclined',
    revocation_requested: 'guest.rsvpRevocationRequested',
};
const rsvpClass: Record<string, string> = {
    accepted_pending:     'text-lime-700 dark:text-lime-400',
    accepted:             'text-green-700 dark:text-green-400',
    declined_pending:     'text-orange-700 dark:text-orange-400',
    declined:             'text-red-700 dark:text-red-400',
    revocation_requested: 'text-purple-700 dark:text-purple-400',
};

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function setterName(guest: any): string | null {
    if (guest.rsvp_set_by_guest) return `${guest.rsvp_set_by_guest.firstname} ${guest.rsvp_set_by_guest.lastname} (${t('guest.rsvpByGuest')})`;
    if (guest.rsvp_set_by_user)  return `${guest.rsvp_set_by_user.name} (${t('guest.rsvpByUser')})`;
    return null;
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

            <!-- App-Zugang -->
            <div class="mt-6 border-t pt-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.appAccess') }}</h3>
                <div class="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                    <div class="text-sm">
                        <p class="font-medium">{{ appAccessForm.app_access ? t('guest.appAccessEnabled') : t('guest.appAccessDisabled') }}</p>
                        <p class="text-muted-foreground">{{ t('guest.appAccessDesc') }}</p>
                    </div>
                    <Button
                        size="sm"
                        :variant="appAccessForm.app_access ? 'destructive' : 'default'"
                        :disabled="appAccessForm.processing"
                        @click="appAccessForm.app_access = !appAccessForm.app_access; toggleAppAccess()"
                    >
                        {{ appAccessForm.app_access ? t('guest.appAccessRevoke') : t('guest.appAccessGrant') }}
                    </Button>
                </div>
            </div>

            <!-- RSVP-Status (separat vom Formular) -->
            <div class="mt-6 border-t pt-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.rsvpStatus') }}</h3>

                <!-- Aktueller Status + Wer hat ihn gesetzt -->
                <div class="rounded-lg border bg-muted/30 px-4 py-3 text-sm space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">{{ t('guest.rsvpStatus') }}:</span>
                        <span v-if="guest.rsvp_status" :class="['font-medium', rsvpClass[guest.rsvp_status]]">
                            {{ t(rsvpLabel[guest.rsvp_status]) }}
                        </span>
                        <span v-else class="text-muted-foreground">{{ t('guest.rsvpNull') }}</span>
                    </div>
                    <div v-if="setterName(guest)" class="text-muted-foreground">
                        {{ t('guest.rsvpSetBy') }}: <span class="font-medium text-foreground">{{ setterName(guest) }}</span>
                    </div>
                    <div v-if="guest.rsvp_set_at" class="text-muted-foreground">
                        {{ t('guest.rsvpSetAt') }}: {{ formatDate(guest.rsvp_set_at) }}
                    </div>
                </div>

                <!-- Admin-Override -->
                <form @submit.prevent="submitRsvp" class="flex items-center gap-2">
                    <select
                        v-model="rsvpForm.rsvp_status"
                        class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    >
                        <option value="">{{ t('guest.rsvpNull') }}</option>
                        <option value="accepted_pending">{{ t('guest.rsvpAcceptedPending') }}</option>
                        <option value="accepted">{{ t('guest.rsvpAccepted') }}</option>
                        <option value="declined_pending">{{ t('guest.rsvpDeclinedPending') }}</option>
                        <option value="declined">{{ t('guest.rsvpDeclined') }}</option>
                        <option value="revocation_requested">{{ t('guest.rsvpRevocationRequested') }}</option>
                    </select>
                    <Button type="submit" size="sm" :disabled="rsvpForm.processing">{{ t('guest.rsvpSave') }}</Button>
                </form>
            </div>

            <!-- QR-Code -->
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
