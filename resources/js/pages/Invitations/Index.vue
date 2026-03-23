<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const generateForm = useForm({});
function generateTokens() {
    generateForm.post(route('invitations.generate'), { onSuccess: () => window.location.reload() });
}

interface GroupInvitation  { id: number; name: string; guests: string[]; token: string | null; qr_url: string | null; }
interface SoloInvitation   { id: number; name: string; token: string | null; qr_url: string | null; }

const props = defineProps<{ groups: GroupInvitation[]; soloGuests: SoloInvitation[]; }>();

const groupQrCodes = ref<Record<number, string>>({});
const soloQrCodes  = ref<Record<number, string>>({});
const generatingId    = ref<string | null>(null);
const confirmOpen     = ref(false);
const pendingRegenerate = ref<{ type: 'group' | 'guest'; id: number } | null>(null);

function generateSingle(type: 'group' | 'guest', id: number) {
    const key = `${type}-${id}`;
    generatingId.value = key;
    const routeName = type === 'group' ? 'invitations.generate.group' : 'invitations.generate.guest';
    router.post(route(routeName, id), {}, {
        onFinish: () => { generatingId.value = null; },
    });
}

function askRegenerate(type: 'group' | 'guest', id: number) {
    pendingRegenerate.value = { type, id };
    confirmOpen.value = true;
}

function doRegenerate() {
    if (!pendingRegenerate.value) return;
    generateSingle(pendingRegenerate.value.type, pendingRegenerate.value.id);
    pendingRegenerate.value = null;
}

async function renderQrCodes() {
    for (const f of props.groups)     if (f.qr_url) groupQrCodes.value[f.id] = await QRCode.toDataURL(f.qr_url, { width: 200, margin: 1 });
    for (const g of props.soloGuests) if (g.qr_url) soloQrCodes.value[g.id]  = await QRCode.toDataURL(g.qr_url, { width: 200, margin: 1 });
}

watch(() => [props.groups, props.soloGuests], renderQrCodes, { immediate: true, deep: true });

function openPdf(url: string, name: string) {
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(`<html><head><title>${name}</title></head><body style="display:flex;justify-content:center;align-items:center;height:100vh;margin:0"><img src="${url}" style="width:300px;height:300px" onload="window.print()"/></body></html>`);
    win.document.close();
}

function downloadPng(url: string, name: string) {
    const a = document.createElement('a');
    a.href = url;
    a.download = `${name}.png`;
    a.click();
}
</script>

<template>
    <Head :title="t('invitation.title')" />
    <AppLayout>
        <div class="m-4 space-y-6">

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ t('invitation.heading') }}</h1>
                <Button @click="generateTokens" :disabled="generateForm.processing">
                    {{ generateForm.processing ? t('invitation.generating') : t('invitation.generate') }}
                </Button>
            </div>

            <!-- Gruppen -->
            <section v-if="groups.length">
                <h2 class="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">{{ t('invitation.groups') }}</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card v-for="group in groups" :key="group.id" class="items-center gap-3 py-4">
                        <CardContent class="flex flex-col items-center gap-2 px-4">
                            <p class="font-semibold text-center text-sm">{{ group.name }}</p>
                            <p class="text-xs text-muted-foreground text-center">{{ group.guests.join(', ') }}</p>
                            <img v-if="groupQrCodes[group.id]" :src="groupQrCodes[group.id]" alt="QR Code" class="w-36 h-36" />
                            <template v-else>
                                <p class="text-xs text-muted-foreground italic">{{ t('invitation.noQR') }}</p>
                                <Button variant="outline" size="sm"
                                    :disabled="generatingId === `group-${group.id}`"
                                    @click="generateSingle('group', group.id)">
                                    {{ generatingId === `group-${group.id}` ? t('invitation.generating') : t('invitation.generateSingle') }}
                                </Button>
                            </template>
                            <div v-if="groupQrCodes[group.id]" class="flex gap-2 flex-wrap justify-center">
                                <Button variant="outline" size="sm" @click="openPdf(groupQrCodes[group.id], group.name)">{{ t('invitation.downloadPdf') }}</Button>
                                <Button variant="outline" size="sm" @click="downloadPng(groupQrCodes[group.id], group.name)">{{ t('invitation.downloadPng') }}</Button>
                                <Button variant="ghost" size="sm" @click="askRegenerate('group', group.id)">{{ t('invitation.regenerate') }}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Solo-Gäste -->
            <section v-if="soloGuests.length">
                <h2 class="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">{{ t('invitation.solo') }}</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card v-for="guest in soloGuests" :key="guest.id" class="items-center gap-3 py-4">
                        <CardContent class="flex flex-col items-center gap-2 px-4">
                            <p class="font-semibold text-center text-sm">{{ guest.name }}</p>
                            <img v-if="soloQrCodes[guest.id]" :src="soloQrCodes[guest.id]" alt="QR Code" class="w-36 h-36" />
                            <template v-else>
                                <p class="text-xs text-muted-foreground italic">{{ t('invitation.noQR') }}</p>
                                <Button variant="outline" size="sm"
                                    :disabled="generatingId === `guest-${guest.id}`"
                                    @click="generateSingle('guest', guest.id)">
                                    {{ generatingId === `guest-${guest.id}` ? t('invitation.generating') : t('invitation.generateSingle') }}
                                </Button>
                            </template>
                            <div v-if="soloQrCodes[guest.id]" class="flex gap-2 flex-wrap justify-center">
                                <Button variant="outline" size="sm" @click="openPdf(soloQrCodes[guest.id], guest.name)">{{ t('invitation.downloadPdf') }}</Button>
                                <Button variant="outline" size="sm" @click="downloadPng(soloQrCodes[guest.id], guest.name)">{{ t('invitation.downloadPng') }}</Button>
                                <Button variant="ghost" size="sm" @click="askRegenerate('guest', guest.id)">{{ t('invitation.regenerate') }}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <p v-if="!groups.length && !soloGuests.length" class="text-sm text-muted-foreground">
                {{ t('invitation.none') }}
            </p>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('invitation.regenerateConfirmTitle')"
            :description="t('invitation.regenerateConfirmDescription')"
            :confirm-label="t('invitation.regenerate')"
            :destructive="true"
            @confirm="doRegenerate"
        />
    </AppLayout>
</template>
