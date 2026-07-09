<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import GuestForm from '@/components/GuestForm.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    guest: any;
    categories: any[];
    groups: any[];
    food_specials: any[];
    qr_url: string | null;
}>();

const { t } = useI18n();
const qrDataUrl = ref<string | null>(null);
const generatingQr = ref(false);

watch(
    () => props.qr_url,
    async (url) => {
        qrDataUrl.value = url ? await QRCode.toDataURL(url, { width: 200, margin: 1 }) : null;
    },
    { immediate: true },
);

function generateQr() {
    generatingQr.value = true;
    const routeName = props.guest.group_id ? 'invitations.generate.group' : 'invitations.generate.guest';
    const id = props.guest.group_id ?? props.guest.id;
    router.post(
        route(routeName, id),
        {},
        {
            onFinish: () => {
                generatingQr.value = false;
            },
        },
    );
}

function openPdf(url: string, name: string) {
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(
        `<html><head><title>${name}</title></head><body style="display:flex;justify-content:center;align-items:center;height:100vh;margin:0"><img src="${url}" style="width:300px;height:300px" onload="window.print()"/></body></html>`,
    );
    win.document.close();
}

function downloadPng(url: string, name: string) {
    const a = document.createElement('a');
    a.href = url;
    a.download = `${name}.png`;
    a.click();
}

function handleUpdate(form: any) {
    form.put(route('guests.update', props.guest.id), { onSuccess: () => toast.success(t('toast.guestSaved')) });
}

// App access toggle
const appAccessForm = useForm({ app_access: props.guest.app_access ?? true });
function toggleAppAccess() {
    appAccessForm.patch(route('guests.app-access', props.guest.id), {
        onSuccess: () => toast.success(appAccessForm.app_access ? t('toast.appAccessEnabled') : t('toast.appAccessDisabled')),
    });
}

const resetAppLoginOpen = ref(false);
function doResetAppLogin() {
    router.delete(route('guests.app-login.reset', props.guest.id), {
        onSuccess: () => toast.success(t('toast.appLoginReset')),
    });
}

// Drinks access toggle
const drinksAccessForm = useForm({ drinks_access: props.guest.drinks_access ?? true });
function toggleDrinksAccess() {
    drinksAccessForm.patch(route('guests.drinks-access', props.guest.id), {
        onSuccess: () => toast.success(drinksAccessForm.drinks_access ? t('toast.drinksAccessEnabled') : t('toast.drinksAccessDisabled')),
    });
}

// Drink logs reset
const resetLogsOpen = ref(false);
function doResetLogs() {
    router.delete(route('guests.drink-logs.reset', props.guest.id), {
        onSuccess: () => toast.success(t('toast.drinkLogsReset')),
    });
}

// RSVP admin override (separate)
const rsvpForm = useForm({ rsvp_status: props.guest.rsvp_status ?? '' });
function submitRsvp() {
    rsvpForm
        .transform((data) => ({ rsvp_status: data.rsvp_status === '' ? null : data.rsvp_status }))
        .post(route('guests.admin-rsvp', props.guest.id), {
            onSuccess: () => toast.success(t('toast.rsvpOverridden')),
        });
}

const rsvpLabel: Record<string, string> = {
    accepted_pending: 'guest.rsvpAcceptedPending',
    accepted: 'guest.rsvpAccepted',
    declined_pending: 'guest.rsvpDeclinedPending',
    declined: 'guest.rsvpDeclined',
    revocation_requested: 'guest.rsvpRevocationRequested',
};
const rsvpClass: Record<string, string> = {
    accepted_pending: 'text-lime-700 dark:text-lime-400',
    accepted: 'text-green-700 dark:text-green-400',
    declined_pending: 'text-orange-700 dark:text-orange-400',
    declined: 'text-red-700 dark:text-red-400',
    revocation_requested: 'text-purple-700 dark:text-purple-400',
};

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function setterName(guest: any): string | null {
    if (guest.rsvp_set_by_guest) return `${guest.rsvp_set_by_guest.firstname} ${guest.rsvp_set_by_guest.lastname} (${t('guest.rsvpByGuest')})`;
    if (guest.rsvp_set_by_user) return `${guest.rsvp_set_by_user.name} (${t('guest.rsvpByUser')})`;
    return null;
}
</script>

<template>
    <AppLayout>
        <div class="m-4 rounded-xl border bg-white p-4 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">{{ t('guest.edit') }}</h2>
            <GuestForm
                :groups="groups"
                :food-specials="food_specials"
                :initial-form="guest"
                :submit-label="t('guest.saveChanges')"
                @submit="handleUpdate"
            />

            <!-- App access -->
            <div class="mt-6 space-y-3 border-t pt-6">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.appAccess') }}</h3>
                    <InfoTooltip :text="t('guest.appAccessInfo')" />
                </div>
                <div class="bg-muted/30 flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm">
                        <p class="font-medium">{{ appAccessForm.app_access ? t('guest.appAccessEnabled') : t('guest.appAccessDisabled') }}</p>
                        <p class="text-muted-foreground">{{ t('guest.appAccessDesc') }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ t('guest.appLoginStatus') }}:
                            <span :class="guest.is_active ? 'font-medium text-green-700 dark:text-green-400' : ''">
                                {{ guest.is_active ? t('guest.appLoginActive') : t('guest.appLoginInactive') }}
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button size="sm" variant="outline" :disabled="!guest.is_active" @click="resetAppLoginOpen = true">
                            {{ t('guest.appLoginReset') }}
                        </Button>
                        <Button
                            size="sm"
                            :variant="appAccessForm.app_access ? 'destructive' : 'default'"
                            :disabled="appAccessForm.processing"
                            @click="
                                appAccessForm.app_access = !appAccessForm.app_access;
                                toggleAppAccess();
                            "
                        >
                            {{ appAccessForm.app_access ? t('guest.appAccessRevoke') : t('guest.appAccessGrant') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Drinks access -->
            <div class="mt-6 space-y-3 border-t pt-6">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.drinksAccess') }}</h3>
                    <InfoTooltip :text="t('guest.drinksAccessInfo')" />
                </div>
                <div class="bg-muted/30 flex items-center justify-between rounded-lg border px-4 py-3">
                    <div class="text-sm">
                        <p class="font-medium">
                            {{ drinksAccessForm.drinks_access ? t('guest.drinksAccessEnabled') : t('guest.drinksAccessDisabled') }}
                        </p>
                        <p class="text-muted-foreground">{{ t('guest.drinksAccessDesc') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="resetLogsOpen = true">
                            {{ t('guest.drinksReset') }}
                        </Button>
                        <Button
                            size="sm"
                            :variant="drinksAccessForm.drinks_access ? 'destructive' : 'default'"
                            :disabled="drinksAccessForm.processing"
                            @click="
                                drinksAccessForm.drinks_access = !drinksAccessForm.drinks_access;
                                toggleDrinksAccess();
                            "
                        >
                            {{ drinksAccessForm.drinks_access ? t('guest.drinksAccessRevoke') : t('guest.drinksAccessGrant') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- RSVP status (separate from the form) -->
            <div class="mt-6 space-y-3 border-t pt-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.rsvpStatus') }}</h3>

                <!-- Current status + who set it -->
                <div class="bg-muted/30 space-y-1 rounded-lg border px-4 py-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">{{ t('guest.rsvpStatus') }}:</span>
                        <span v-if="guest.rsvp_status" :class="['font-medium', rsvpClass[guest.rsvp_status]]">
                            {{ t(rsvpLabel[guest.rsvp_status]) }}
                        </span>
                        <span v-else class="text-muted-foreground">{{ t('guest.rsvpNull') }}</span>
                    </div>
                    <div v-if="setterName(guest)" class="text-muted-foreground">
                        {{ t('guest.rsvpSetBy') }}: <span class="text-foreground font-medium">{{ setterName(guest) }}</span>
                    </div>
                    <div v-if="guest.rsvp_set_at" class="text-muted-foreground">{{ t('guest.rsvpSetAt') }}: {{ formatDate(guest.rsvp_set_at) }}</div>
                </div>

                <!-- Admin override -->
                <form @submit.prevent="submitRsvp" class="flex items-center gap-2">
                    <select
                        v-model="rsvpForm.rsvp_status"
                        class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
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

            <!-- QR code -->
            <div class="mt-6 border-t pt-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('guest.invitationQR') }}</h3>
                <template v-if="qrDataUrl">
                    <img :src="qrDataUrl" alt="QR-Code" class="rounded border" />
                    <p class="mt-2 break-all text-xs text-gray-400">{{ qr_url }}</p>
                    <div class="mt-3 flex gap-2">
                        <Button variant="outline" size="sm" @click="openPdf(qrDataUrl, `${guest.firstname} ${guest.lastname}`)">{{
                            t('invitation.downloadPdf')
                        }}</Button>
                        <Button variant="outline" size="sm" @click="downloadPng(qrDataUrl, `${guest.firstname} ${guest.lastname}`)">{{
                            t('invitation.downloadPng')
                        }}</Button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-gray-400">{{ t('guest.noToken') }}</p>
                    <Button class="mt-3" size="sm" :disabled="generatingQr" @click="generateQr">
                        {{ generatingQr ? t('invitation.generating') : t('invitation.generateSingle') }}
                    </Button>
                </template>
            </div>
        </div>
        <ConfirmDialog
            v-model:open="resetAppLoginOpen"
            :title="t('guest.appLoginResetTitle')"
            :description="t('guest.appLoginResetDesc')"
            :confirm-label="t('guest.appLoginReset')"
            destructive
            @confirm="doResetAppLogin"
        />
        <ConfirmDialog
            v-model:open="resetLogsOpen"
            :title="t('guest.drinksResetTitle')"
            :description="t('guest.drinksResetDesc')"
            :confirm-label="t('guest.drinksReset')"
            destructive
            @confirm="doResetLogs"
        />
    </AppLayout>
</template>
