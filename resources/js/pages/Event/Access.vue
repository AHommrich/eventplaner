<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Smartphone, Trash2 } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type Role = 'owner' | 'event_admin' | 'event_manager' | 'superadmin';
interface Member {
    id: number;
    name: string;
    email: string;
    role: Role;
}
interface Device {
    id: number;
    device_label: string | null;
    paired_at: string | null;
    last_used_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
    is_own: boolean;
    user: { id: number; name: string | null; email: string | null };
}

const props = defineProps<{
    event: { id: number; name: string };
    owner: { id: number; name: string; email: string } | null;
    members: Member[];
    my_devices: Device[];
    event_devices: Device[];
    my_role: Role | null;
    can_manage_access: boolean;
    can_assign_admin: boolean;
}>();

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('access.title'), href: '/event/access' }];
const myDevices = ref([...props.my_devices]);
const eventDevices = ref([...props.event_devices]);
const deviceLabel = ref('');
const qrDataUrl = ref<string | null>(null);
const expiresAt = ref<string | null>(null);
const generating = ref(false);

function roleLabel(role: Role): string {
    return {
        owner: t('access.roleOwner'),
        event_admin: t('access.roleAdmin'),
        event_manager: t('access.roleManager'),
        superadmin: t('access.roleOwner'),
    }[role];
}

const canEditRoles = computed(() => props.can_assign_admin);
function canRemove(member: Member): boolean {
    if (props.can_assign_admin) return true;
    return props.my_role === 'event_admin' && member.role === 'event_manager';
}

const form = useForm({ email: '', role: 'event_manager' as 'event_manager' | 'event_admin' });
function invite() {
    form.post(route('event.access.invite'), {
        onSuccess: () => {
            form.reset();
            toast.success(t('toast.accessAdded'));
        },
    });
}

function changeRole(member: Member, role: Role) {
    if (role === member.role) return;
    router.patch(
        route('event.access.role', member.id),
        { role },
        { preserveScroll: true, onSuccess: () => toast.success(t('toast.roleChanged', { role: roleLabel(role) })) },
    );
}

const memberConfirmOpen = ref(false);
const pendingUserId = ref<number | null>(null);
const pendingName = ref('');
function askRemove(member: Member) {
    pendingUserId.value = member.id;
    pendingName.value = member.name;
    memberConfirmOpen.value = true;
}
function doRemove() {
    if (pendingUserId.value)
        router.delete(route('event.access.remove', pendingUserId.value), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('toast.accessRemoved')),
        });
}

async function generatePairing() {
    generating.value = true;
    try {
        const response = await axios.post(route('event.access.pairings'), {
            device_label: deviceLabel.value || null,
        });
        qrDataUrl.value = await QRCode.toDataURL(response.data.pairing_token, { width: 240, margin: 1 });
        expiresAt.value = response.data.expires_at;
    } finally {
        generating.value = false;
    }
}

const deviceConfirmOpen = ref(false);
const pendingDevice = ref<Device | null>(null);
function askRevoke(device: Device) {
    pendingDevice.value = device;
    deviceConfirmOpen.value = true;
}
async function revokeDevice() {
    if (!pendingDevice.value) return;
    await axios.delete(route('event.access.devices.destroy', pendingDevice.value.id));
    const id = pendingDevice.value.id;
    myDevices.value = myDevices.value.filter((device) => device.id !== id);
    eventDevices.value = eventDevices.value.filter((device) => device.id !== id);
    toast.success(t('access.deviceRevoked'));
    pendingDevice.value = null;
}
function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head :title="t('access.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <Card>
                <CardHeader
                    ><CardTitle>{{ event.name }}</CardTitle></CardHeader
                >
                <CardContent
                    ><p class="text-sm text-muted-foreground">{{ t('access.description') }}</p></CardContent
                >
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>{{ t('access.deviceLoginTitle') }}</CardTitle></CardHeader
                >
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">{{ t('access.deviceLoginDescription') }}</p>
                    <div class="grid gap-2">
                        <Label for="device-label">{{ t('access.deviceLabel') }}</Label>
                        <Input id="device-label" v-model="deviceLabel" :placeholder="t('access.deviceLabelPlaceholder')" maxlength="100" />
                    </div>
                    <Button :disabled="generating" @click="generatePairing">{{ t('access.generatePairing') }}</Button>
                    <div v-if="qrDataUrl" class="space-y-2 rounded-md bg-muted p-4 text-center">
                        <img :src="qrDataUrl" :alt="t('access.pairingQrAlt')" class="mx-auto size-60 rounded bg-white p-2" />
                        <p class="text-sm">{{ t('access.pairingHint') }}</p>
                        <p v-if="expiresAt" class="text-xs text-muted-foreground">
                            {{ t('access.pairingExpires', { time: new Date(expiresAt).toLocaleTimeString() }) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>{{ t('access.myDevices') }}</CardTitle></CardHeader
                >
                <CardContent>
                    <ul class="divide-y rounded-md border">
                        <li v-for="device in myDevices" :key="device.id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <Smartphone class="size-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ device.device_label || t('access.unknownDevice') }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ t('access.lastUsedAt', { date: formatDate(device.last_used_at) }) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ t('access.expiresAt', { date: formatDate(device.expires_at) }) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="device.is_expired ? 'text-destructive' : 'text-emerald-600'" class="text-xs">
                                    {{ device.is_expired ? t('access.expired') : t('access.active') }}
                                </span>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askRevoke(device)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                        <li v-if="myDevices.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            {{ t('access.noPairedDevices') }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="can_manage_access">
                <CardHeader
                    ><CardTitle>{{ t('access.eventDevices') }}</CardTitle></CardHeader
                >
                <CardContent>
                    <ul class="divide-y rounded-md border">
                        <li v-for="device in eventDevices" :key="device.id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ device.user.name }} · {{ device.device_label || t('access.unknownDevice') }}
                                </p>
                                <p class="truncate text-xs text-muted-foreground">{{ device.user.email }}</p>
                                <p class="text-xs text-muted-foreground">{{ t('access.lastUsedAt', { date: formatDate(device.last_used_at) }) }}</p>
                                <p class="text-xs text-muted-foreground">{{ t('access.expiresAt', { date: formatDate(device.expires_at) }) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="device.is_expired ? 'text-destructive' : 'text-emerald-600'" class="text-xs">
                                    {{ device.is_expired ? t('access.expired') : t('access.active') }}
                                </span>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askRevoke(device)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                        <li v-if="eventDevices.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            {{ t('access.noPairedDevices') }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <template v-if="can_manage_access">
                <Card>
                    <CardHeader
                        ><CardTitle>{{ t('access.addUser') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <form @submit.prevent="invite" class="flex flex-wrap gap-2">
                            <Input v-model="form.email" type="email" :placeholder="t('access.emailPlaceholder')" required class="min-w-48 flex-1" />
                            <select
                                v-if="can_assign_admin"
                                v-model="form.role"
                                class="rounded-md border border-input bg-transparent px-2 text-sm outline-none"
                            >
                                <option value="event_manager">{{ t('access.roleManager') }}</option>
                                <option value="event_admin">{{ t('access.roleAdmin') }}</option>
                            </select>
                            <Button type="submit" :disabled="form.processing">{{ t('common.add') }}</Button>
                        </form>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-destructive">{{ form.errors.email }}</p>
                        <p v-if="form.errors.role" class="mt-1.5 text-xs text-destructive">{{ form.errors.role }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2"
                            >{{ t('access.currentAccess') }} <InfoTooltip :text="t('access.coOrganizerInfo')"
                        /></CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y">
                            <li v-if="owner" class="flex items-center justify-between py-2.5">
                                <div>
                                    <p class="text-sm font-medium">{{ owner.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ owner.email }}</p>
                                </div>
                                <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">{{ t('access.owner') }}</span>
                            </li>
                            <li v-for="member in members" :key="member.id" class="flex items-center justify-between gap-2 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ member.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <select
                                        v-if="canEditRoles"
                                        :value="member.role"
                                        @change="changeRole(member, ($event.target as HTMLSelectElement).value as Role)"
                                        class="rounded-md border border-input bg-transparent px-2 py-1 text-xs outline-none"
                                    >
                                        <option value="event_manager">{{ t('access.roleManager') }}</option>
                                        <option value="event_admin">{{ t('access.roleAdmin') }}</option>
                                        <option value="owner">{{ t('access.roleOwner') }}</option>
                                    </select>
                                    <span v-else class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">{{
                                        roleLabel(member.role)
                                    }}</span>
                                    <Button
                                        v-if="canRemove(member)"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="askRemove(member)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                            </li>
                            <li v-if="members.length === 0" class="py-4 text-center text-sm text-muted-foreground">{{ t('access.none') }}</li>
                        </ul>
                    </CardContent>
                </Card>
            </template>
        </div>

        <ConfirmDialog
            v-model:open="memberConfirmOpen"
            :title="t('access.removeTitle', { name: pendingName })"
            :description="t('access.removeDescription')"
            :confirm-label="t('common.remove')"
            destructive
            @confirm="doRemove"
        />
        <ConfirmDialog
            v-model:open="deviceConfirmOpen"
            :title="t('access.revokeDeviceTitle')"
            :description="t('access.revokeDeviceDescription')"
            :confirm-label="t('access.revokeDevice')"
            destructive
            @confirm="revokeDevice"
        />
    </AppLayout>
</template>
