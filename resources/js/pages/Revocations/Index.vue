<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
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

interface RevocationGuest {
    id: number;
    firstname: string;
    lastname: string;
    group_name: string | null;
    rsvp_set_at: string | null;
    set_by_guest: SetByGuest | null;
    set_by_user: SetByUser | null;
}

defineProps<{ guests: RevocationGuest[] }>();

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('revocation.title'), href: '/revocations' }];

const confirmOpen = ref(false);
const pendingAction = ref<{ guest: RevocationGuest; action: 'approve' | 'decline' } | null>(null);

function ask(guest: RevocationGuest, action: 'approve' | 'decline') {
    pendingAction.value = { guest, action };
    confirmOpen.value = true;
}

function doAction() {
    if (!pendingAction.value) return;
    const { guest, action } = pendingAction.value;
    const routeName = action === 'approve' ? 'revocations.approve' : 'revocations.decline';
    useForm({}).post(route(routeName, guest.id), {
        onSuccess: () => toast.success(action === 'approve' ? t('toast.revocationApproved') : t('toast.revocationDeclined')),
    });
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function setterName(g: RevocationGuest): string {
    if (g.set_by_guest) return `${g.set_by_guest.firstname} ${g.set_by_guest.lastname}`;
    if (g.set_by_user) return g.set_by_user.name;
    return '—';
}
</script>

<template>
    <Head :title="t('revocation.title')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="m-4 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('revocation.title') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground mb-4 text-sm">{{ t('revocation.description') }}</p>

                    <div v-if="guests.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                        {{ t('revocation.empty') }}
                    </div>

                    <div v-else class="divide-y">
                        <div v-for="g in guests" :key="g.id" class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ g.firstname }} {{ g.lastname }}</p>
                                <p v-if="g.group_name" class="text-muted-foreground text-sm">{{ g.group_name }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ t('revocation.requestedAt') }}: {{ formatDate(g.rsvp_set_at) }} · {{ t('revocation.setBy') }}:
                                    {{ setterName(g) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button size="sm" @click="ask(g, 'approve')">{{ t('revocation.approve') }}</Button>
                                <Button size="sm" variant="outline" @click="ask(g, 'decline')">{{ t('revocation.decline') }}</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="pendingAction?.action === 'approve' ? t('revocation.confirmApproveTitle') : t('revocation.confirmDeclineTitle')"
            :description="pendingAction?.action === 'approve' ? t('revocation.confirmApproveDesc') : t('revocation.confirmDeclineDesc')"
            :confirm-label="pendingAction?.action === 'approve' ? t('revocation.approve') : t('revocation.decline')"
            :destructive="pendingAction?.action === 'decline'"
            @confirm="doAction"
        />
    </AppLayout>
</template>
