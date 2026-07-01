<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface SetByGuest {
    id: number;
    firstname: string;
    lastname: string;
}
interface SetByUser {
    id: number;
    name: string;
}

interface RevocationRequest {
    id: number;
    type: 'revocation';
    firstname: string;
    lastname: string;
    group_name: string | null;
    rsvp_status: string;
    rsvp_set_at: string | null;
    set_by_guest: SetByGuest | null;
    set_by_user: SetByUser | null;
}

interface EventRequestItem {
    id: number;
    user_name: string;
    user_email: string;
    event_name: string;
    created_at: string;
}

defineProps<{
    revocations: RevocationRequest[];
    event_requests: EventRequestItem[];
}>();

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('requests.title'), href: '/requests' }];

// --- Revocations ---
const confirmOpen = ref(false);
const pendingAction = ref<{ item: RevocationRequest; action: 'approve' | 'decline' } | null>(null);

function ask(item: RevocationRequest, action: 'approve' | 'decline') {
    pendingAction.value = { item, action };
    confirmOpen.value = true;
}

function doAction() {
    if (!pendingAction.value) return;
    const { item, action } = pendingAction.value;
    const routeName = action === 'approve' ? 'requests.revocations.approve' : 'requests.revocations.decline';
    useForm({}).post(route(routeName, item.id), {
        onSuccess: () => toast.success(action === 'approve' ? t('toast.revocationApproved') : t('toast.revocationDeclined')),
    });
}

// --- Event requests ---
const eventReqConfirmOpen = ref(false);
const eventReqPendingAction = ref<{ item: EventRequestItem; action: 'approve' | 'decline' } | null>(null);

function askEventReq(item: EventRequestItem, action: 'approve' | 'decline') {
    eventReqPendingAction.value = { item, action };
    eventReqConfirmOpen.value = true;
}

function doEventReqAction() {
    if (!eventReqPendingAction.value) return;
    const { item, action } = eventReqPendingAction.value;
    const routeName = action === 'approve' ? 'requests.event-requests.approve' : 'requests.event-requests.decline';
    useForm({}).post(route(routeName, item.id), {
        onSuccess: () => toast.success(action === 'approve' ? t('toast.eventRequestApproved') : t('toast.eventRequestDeclined')),
    });
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function setterName(item: RevocationRequest): string {
    if (item.set_by_guest) return `${item.set_by_guest.firstname} ${item.set_by_guest.lastname}`;
    if (item.set_by_user) return item.set_by_user.name;
    return '—';
}
</script>

<template>
    <Head :title="t('requests.title')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="m-4 space-y-4">
            <!-- Event requests (admin only) -->
            <Card v-if="event_requests.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('requests.eventRequestsTitle') }}
                        <span class="inline-flex size-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white">{{
                            event_requests.length
                        }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="mb-4 text-sm text-muted-foreground">{{ t('requests.eventRequestsDesc') }}</p>
                    <div class="divide-y">
                        <div v-for="item in event_requests" :key="item.id" class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ item.event_name }}</p>
                                <p class="text-sm text-muted-foreground">{{ item.user_name }} · {{ item.user_email }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(item.created_at) }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button size="sm" @click="askEventReq(item, 'approve')">{{ t('requests.approveEvent') }}</Button>
                                <Button size="sm" variant="outline" @click="askEventReq(item, 'decline')">{{ t('requests.declineEvent') }}</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Revocation requests -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('requests.revocationsTitle') }}
                        <span
                            v-if="revocations.length > 0"
                            class="inline-flex size-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white"
                            >{{ revocations.length }}</span
                        >
                        <InfoTooltip :text="t('requests.revocationsInfo')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="mb-4 text-sm text-muted-foreground">{{ t('requests.revocationsDesc') }}</p>

                    <div v-if="revocations.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ t('requests.empty') }}
                    </div>

                    <div v-else class="divide-y">
                        <div v-for="item in revocations" :key="item.id" class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ item.firstname }} {{ item.lastname }}</p>
                                <p v-if="item.group_name" class="text-sm text-muted-foreground">{{ item.group_name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('requests.requestedAt') }}: {{ formatDate(item.rsvp_set_at) }} · {{ t('requests.setBy') }}:
                                    {{ setterName(item) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button size="sm" @click="ask(item, 'approve')">{{ t('requests.approve') }}</Button>
                                <Button size="sm" variant="outline" @click="ask(item, 'decline')">{{ t('requests.decline') }}</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Revocation dialog -->
        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="pendingAction?.action === 'approve' ? t('requests.confirmApproveTitle') : t('requests.confirmDeclineTitle')"
            :description="pendingAction?.action === 'approve' ? t('requests.confirmApproveDesc') : t('requests.confirmDeclineDesc')"
            :confirm-label="pendingAction?.action === 'approve' ? t('requests.approve') : t('requests.decline')"
            :destructive="pendingAction?.action === 'decline'"
            @confirm="doAction"
        />

        <!-- Event requests dialog -->
        <ConfirmDialog
            v-model:open="eventReqConfirmOpen"
            :title="eventReqPendingAction?.action === 'approve' ? t('requests.confirmApproveEventTitle') : t('requests.confirmDeclineEventTitle')"
            :description="eventReqPendingAction?.action === 'approve' ? t('requests.confirmApproveEventDesc') : t('requests.confirmDeclineEventDesc')"
            :confirm-label="eventReqPendingAction?.action === 'approve' ? t('requests.approveEvent') : t('requests.declineEvent')"
            :destructive="eventReqPendingAction?.action === 'decline'"
            @confirm="doEventReqAction"
        />
    </AppLayout>
</template>
