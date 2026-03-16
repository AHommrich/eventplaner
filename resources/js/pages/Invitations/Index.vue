<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';

const generateForm = useForm({});
function generateTokens() {
    generateForm.post(route('invitations.generate'), {
        onSuccess: () => window.location.reload(),
    });
}

interface GroupInvitation {
    id: number;
    name: string;
    guests: string[];
    token: string | null;
    qr_url: string | null;
}

interface SoloInvitation {
    id: number;
    name: string;
    token: string | null;
    qr_url: string | null;
}

const props = defineProps<{
    groups: GroupInvitation[];
    soloGuests: SoloInvitation[];
}>();

const groupQrCodes = ref<Record<number, string>>({});
const soloQrCodes = ref<Record<number, string>>({});

async function generateQr(url: string): Promise<string> {
    return QRCode.toDataURL(url, { width: 200, margin: 1 });
}

onMounted(async () => {
    for (const f of props.groups) {
        if (f.qr_url) {
            groupQrCodes.value[f.id] = await generateQr(f.qr_url);
        }
    }
    for (const g of props.soloGuests) {
        if (g.qr_url) {
            soloQrCodes.value[g.id] = await generateQr(g.qr_url);
        }
    }
});

function print(url: string) {
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(`
        <html><body style="display:flex;justify-content:center;align-items:center;height:100vh;margin:0">
        <img src="${url}" style="width:300px;height:300px" onload="window.print();window.close()"/>
        </body></html>
    `);
    win.document.close();
}
</script>

<template>
    <Head title="Einladungen" />
    <AppLayout>
        <div class="p-6 space-y-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Einladungen & QR-Codes</h1>
                <button
                    @click="generateTokens"
                    :disabled="generateForm.processing"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ generateForm.processing ? 'Generiere...' : 'QR-Codes generieren' }}
                </button>
            </div>

            <!-- Gruppen -->
            <section>
                <h2 class="text-lg font-semibold mb-4">Gruppen</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div
                        v-for="group in groups"
                        :key="group.id"
                        class="border rounded-xl p-4 flex flex-col items-center gap-2 bg-white dark:bg-zinc-900 shadow-sm"
                    >
                        <p class="font-semibold text-center">{{ group.name }}</p>
                        <p class="text-xs text-zinc-500 text-center">{{ group.guests.join(', ') }}</p>

                        <img
                            v-if="groupQrCodes[group.id]"
                            :src="groupQrCodes[group.id]"
                            alt="QR Code"
                            class="w-36 h-36"
                        />
                        <p v-else class="text-xs text-zinc-400 italic">Noch kein QR-Code</p>

                        <button
                            v-if="groupQrCodes[group.id]"
                            class="text-xs text-blue-600 underline"
                            @click="print(groupQrCodes[group.id])"
                        >
                            Drucken
                        </button>
                    </div>
                </div>
            </section>

            <!-- Solo-Gäste -->
            <section v-if="soloGuests.length">
                <h2 class="text-lg font-semibold mb-4">Einzeleinladungen</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div
                        v-for="guest in soloGuests"
                        :key="guest.id"
                        class="border rounded-xl p-4 flex flex-col items-center gap-2 bg-white dark:bg-zinc-900 shadow-sm"
                    >
                        <p class="font-semibold text-center">{{ guest.name }}</p>

                        <img
                            v-if="soloQrCodes[guest.id]"
                            :src="soloQrCodes[guest.id]"
                            alt="QR Code"
                            class="w-36 h-36"
                        />
                        <p v-else class="text-xs text-zinc-400 italic">Noch kein QR-Code</p>

                        <button
                            v-if="soloQrCodes[guest.id]"
                            class="text-xs text-blue-600 underline"
                            @click="print(soloQrCodes[guest.id])"
                        >
                            Drucken
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
