<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';

const generateForm = useForm({});
function generateTokens() {
    generateForm.post(route('invitations.generate'), { onSuccess: () => window.location.reload() });
}

interface GroupInvitation  { id: number; name: string; guests: string[]; token: string | null; qr_url: string | null; }
interface SoloInvitation   { id: number; name: string; token: string | null; qr_url: string | null; }

const props = defineProps<{ groups: GroupInvitation[]; soloGuests: SoloInvitation[]; }>();

const groupQrCodes = ref<Record<number, string>>({});
const soloQrCodes  = ref<Record<number, string>>({});

onMounted(async () => {
    for (const f of props.groups)     if (f.qr_url) groupQrCodes.value[f.id] = await QRCode.toDataURL(f.qr_url, { width: 200, margin: 1 });
    for (const g of props.soloGuests) if (g.qr_url) soloQrCodes.value[g.id]  = await QRCode.toDataURL(g.qr_url, { width: 200, margin: 1 });
});

function print(url: string) {
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(`<html><body style="display:flex;justify-content:center;align-items:center;height:100vh;margin:0"><img src="${url}" style="width:300px;height:300px" onload="window.print();window.close()"/></body></html>`);
    win.document.close();
}
</script>

<template>
    <Head title="Einladungen" />
    <AppLayout>
        <div class="m-4 space-y-6">

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Einladungen & QR-Codes</h1>
                <Button @click="generateTokens" :disabled="generateForm.processing">
                    {{ generateForm.processing ? 'Generiere…' : 'QR-Codes generieren' }}
                </Button>
            </div>

            <!-- Gruppen -->
            <section v-if="groups.length">
                <h2 class="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">Gruppen</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card v-for="group in groups" :key="group.id" class="items-center gap-3 py-4">
                        <CardContent class="flex flex-col items-center gap-2 px-4">
                            <p class="font-semibold text-center text-sm">{{ group.name }}</p>
                            <p class="text-xs text-muted-foreground text-center">{{ group.guests.join(', ') }}</p>
                            <img v-if="groupQrCodes[group.id]" :src="groupQrCodes[group.id]" alt="QR Code" class="w-36 h-36" />
                            <p v-else class="text-xs text-muted-foreground italic">Noch kein QR-Code</p>
                            <Button v-if="groupQrCodes[group.id]" variant="outline" size="sm" @click="print(groupQrCodes[group.id])">
                                Drucken
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Solo-Gäste -->
            <section v-if="soloGuests.length">
                <h2 class="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">Einzeleinladungen</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card v-for="guest in soloGuests" :key="guest.id" class="items-center gap-3 py-4">
                        <CardContent class="flex flex-col items-center gap-2 px-4">
                            <p class="font-semibold text-center text-sm">{{ guest.name }}</p>
                            <img v-if="soloQrCodes[guest.id]" :src="soloQrCodes[guest.id]" alt="QR Code" class="w-36 h-36" />
                            <p v-else class="text-xs text-muted-foreground italic">Noch kein QR-Code</p>
                            <Button v-if="soloQrCodes[guest.id]" variant="outline" size="sm" @click="print(soloQrCodes[guest.id])">
                                Drucken
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <p v-if="!groups.length && !soloGuests.length" class="text-sm text-muted-foreground">
                Noch keine Gäste oder Gruppen mit Einladungstoken vorhanden.
            </p>
        </div>
    </AppLayout>
</template>
