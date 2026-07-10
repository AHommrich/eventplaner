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

interface PhotoReportGuest {
    id: number;
    firstname: string;
    lastname: string;
}

interface PhotoReportItem {
    id: number;
    type: 'photo_report';
    event_id: number;
    event_name: string | null;
    photo: {
        id: number;
        url: string | null;
        album_slug: string | null;
    };
    reporter: PhotoReportGuest | null;
    reported_uploader: PhotoReportGuest | null;
    reason: 'inappropriate_content' | 'privacy' | 'other';
    message: string | null;
    created_at: string;
}

defineProps<{
    revocations: RevocationRequest[];
    event_requests: EventRequestItem[];
    photo_reports: PhotoReportItem[];
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

// --- Photo reports ---
const photoReportConfirmOpen = ref(false);
const photoReportPending = ref<PhotoReportItem | null>(null);

function askResolvePhotoReport(item: PhotoReportItem) {
    photoReportPending.value = item;
    photoReportConfirmOpen.value = true;
}

function doResolvePhotoReport() {
    if (!photoReportPending.value) return;
    const item = photoReportPending.value;
    useForm({}).post(route('requests.photo-reports.resolve', item.id), {
        onSuccess: () => toast.success(t('toast.photoReportResolved')),
    });
}

// destructive: delete the photo itself. Two confirmations because it's
// irreversible (S3 blob + DB row + implicit report resolution).
const deletePhotoConfirmOpen1 = ref(false);
const deletePhotoConfirmOpen2 = ref(false);
const deletePhotoPending = ref<PhotoReportItem | null>(null);

function askDeletePhoto(item: PhotoReportItem) {
    deletePhotoPending.value = item;
    deletePhotoConfirmOpen1.value = true;
}

function askDeletePhotoStep2() {
    deletePhotoConfirmOpen1.value = false;
    deletePhotoConfirmOpen2.value = true;
}

function doDeletePhoto() {
    if (!deletePhotoPending.value) return;
    const item = deletePhotoPending.value;
    useForm({}).post(route('requests.photo-reports.delete-photo', item.id), {
        onSuccess: () => toast.success(t('toast.photoReportPhotoDeleted')),
    });
}

function reasonLabel(reason: PhotoReportItem['reason']): string {
    switch (reason) {
        case 'inappropriate_content':
            return t('requests.photoReportReasonInappropriate');
        case 'privacy':
            return t('requests.photoReportReasonPrivacy');
        default:
            return t('requests.photoReportReasonOther');
    }
}

function uploaderLabel(item: PhotoReportItem): string {
    if (item.reported_uploader) {
        return `${item.reported_uploader.firstname} ${item.reported_uploader.lastname}`;
    }
    return t('requests.photoReportOwnerUpload');
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

            <!-- Photo reports (App Store Guideline 1.2) -->
            <Card v-if="photo_reports.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('requests.photoReportsTitle') }}
                        <span class="inline-flex size-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white">{{
                            photo_reports.length
                        }}</span>
                        <InfoTooltip :text="t('requests.photoReportsInfo')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="mb-4 text-sm text-muted-foreground">{{ t('requests.photoReportsDesc') }}</p>
                    <div class="divide-y">
                        <div v-for="item in photo_reports" :key="item.id" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:gap-4">
                            <a
                                v-if="item.photo.url"
                                :href="item.photo.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block shrink-0"
                                :title="t('requests.photoReportOpenPhoto')"
                            >
                                <img :src="item.photo.url" alt="" class="h-24 w-24 rounded object-cover" />
                            </a>
                            <div class="min-w-0 flex-1 space-y-1 text-sm">
                                <p v-if="item.event_name" class="text-xs text-muted-foreground">
                                    {{ t('requests.photoReportEvent') }}: {{ item.event_name }}
                                </p>
                                <p>
                                    <span class="font-medium">{{ t('requests.photoReportReason') }}:</span>
                                    {{ reasonLabel(item.reason) }}
                                </p>
                                <p>
                                    <span class="font-medium">{{ t('requests.photoReportReported') }}:</span>
                                    {{ uploaderLabel(item) }}
                                </p>
                                <p v-if="item.message" class="whitespace-pre-wrap">
                                    <span class="font-medium">{{ t('requests.photoReportMessage') }}:</span>
                                    {{ item.message }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(item.created_at) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                <Button size="sm" @click="askResolvePhotoReport(item)">{{ t('requests.photoReportResolve') }}</Button>
                                <Button size="sm" variant="destructive" @click="askDeletePhoto(item)">
                                    {{ t('requests.photoReportDeletePhoto') }}
                                </Button>
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

        <!-- Photo report resolve dialog -->
        <ConfirmDialog
            v-model:open="photoReportConfirmOpen"
            :title="t('requests.confirmResolvePhotoReportTitle')"
            :description="t('requests.confirmResolvePhotoReportDesc')"
            :confirm-label="t('requests.photoReportResolve')"
            @confirm="doResolvePhotoReport"
        />

        <!-- Delete photo — step 1 -->
        <ConfirmDialog
            v-model:open="deletePhotoConfirmOpen1"
            :title="t('requests.confirmDeletePhotoTitle1')"
            :description="t('requests.confirmDeletePhotoDesc1')"
            :confirm-label="t('requests.photoReportDeletePhoto')"
            destructive
            @confirm="askDeletePhotoStep2"
        />

        <!-- Delete photo — step 2 -->
        <ConfirmDialog
            v-model:open="deletePhotoConfirmOpen2"
            :title="t('requests.confirmDeletePhotoTitle2')"
            :description="t('requests.confirmDeletePhotoDesc2')"
            :confirm-label="t('requests.photoReportDeletePhotoFinal')"
            destructive
            @confirm="doDeletePhoto"
        />
    </AppLayout>
</template>
