<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Smartphone, Trash2 } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Device {
    id: number;
    device_label: string | null;
    paired_at: string | null;
    last_used_at: string | null;
}

const props = defineProps<{ devices: Device[] }>();
const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('settings.devices'), href: '/settings/devices' }];
const devices = ref([...props.devices]);
const deviceLabel = ref('');
const qrDataUrl = ref<string | null>(null);
const expiresAt = ref<string | null>(null);
const generating = ref(false);
const confirmOpen = ref(false);
const pendingDevice = ref<Device | null>(null);

async function generatePairing() {
    generating.value = true;
    try {
        const response = await axios.post(route('settings.devices.pairings'), {
            device_label: deviceLabel.value || null,
        });
        qrDataUrl.value = await QRCode.toDataURL(response.data.pairing_token, { width: 240, margin: 1 });
        expiresAt.value = response.data.expires_at;
    } finally {
        generating.value = false;
    }
}

function askRevoke(device: Device) {
    pendingDevice.value = device;
    confirmOpen.value = true;
}

async function revokeDevice() {
    if (!pendingDevice.value) return;
    await axios.delete(route('settings.devices.destroy', pendingDevice.value.id));
    devices.value = devices.value.filter((device) => device.id !== pendingDevice.value?.id);
    toast.success(t('settings.deviceRevoked'));
    pendingDevice.value = null;
}
</script>

<template>
    <Head :title="t('settings.devicesTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('settings.devicesTitle')" :description="t('settings.devicesDesc')" />

                <div class="space-y-3 rounded-md border p-4">
                    <div class="grid gap-2">
                        <Label for="device-label">{{ t('settings.deviceLabel') }}</Label>
                        <Input id="device-label" v-model="deviceLabel" :placeholder="t('settings.deviceLabelPlaceholder')" maxlength="100" />
                    </div>
                    <Button :disabled="generating" @click="generatePairing">{{ t('settings.generatePairing') }}</Button>

                    <div v-if="qrDataUrl" class="space-y-2 rounded-md bg-muted p-4 text-center">
                        <img :src="qrDataUrl" :alt="t('settings.pairingQrAlt')" class="mx-auto size-60 rounded bg-white p-2" />
                        <p class="text-sm">{{ t('settings.pairingHint') }}</p>
                        <p v-if="expiresAt" class="text-xs text-muted-foreground">
                            {{ t('settings.pairingExpires', { time: new Date(expiresAt).toLocaleTimeString() }) }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-medium">{{ t('settings.pairedDevices') }}</h3>
                    <ul class="divide-y rounded-md border">
                        <li v-for="device in devices" :key="device.id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <Smartphone class="size-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ device.device_label || t('settings.unknownDevice') }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ t('settings.pairedAt', { date: device.paired_at ? new Date(device.paired_at).toLocaleString() : '—' }) }}
                                    </p>
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askRevoke(device)">
                                <Trash2 class="size-4" />
                            </Button>
                        </li>
                        <li v-if="devices.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            {{ t('settings.noPairedDevices') }}
                        </li>
                    </ul>
                </div>
            </div>
        </SettingsLayout>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('settings.revokeDeviceTitle')"
            :description="t('settings.revokeDeviceDesc')"
            :confirm-label="t('settings.revokeDevice')"
            destructive
            @confirm="revokeDevice"
        />
    </AppLayout>
</template>
