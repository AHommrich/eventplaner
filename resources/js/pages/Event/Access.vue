<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
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

const props = defineProps<{
    event: { id: number; name: string };
    owner: { id: number; name: string; email: string };
    members: Member[];
    my_role: Role | null;
    can_assign_admin: boolean;
}>();

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Zugang verwalten', href: '/event/access' }];

function roleLabel(role: Role): string {
    return {
        owner: t('access.roleOwner'),
        event_admin: t('access.roleAdmin'),
        event_manager: t('access.roleManager'),
        superadmin: t('access.roleOwner'),
    }[role];
}

// Only owners/superadmins change tiers; an event_admin may only remove managers.
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

const confirmOpen = ref(false);
const pendingUserId = ref<number | null>(null);
const pendingName = ref('');
function askRemove(member: Member) {
    pendingUserId.value = member.id;
    pendingName.value = member.name;
    confirmOpen.value = true;
}
function doRemove() {
    if (pendingUserId.value)
        router.delete(route('event.access.remove', pendingUserId.value), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('toast.accessRemoved')),
        });
}
</script>

<template>
    <Head :title="t('access.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ event.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ t('access.description') }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('access.addUser') }}</CardTitle>
                </CardHeader>
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
                    <CardTitle class="flex items-center gap-2">
                        {{ t('access.currentAccess') }}
                        <InfoTooltip :text="t('access.coOrganizerInfo')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li class="flex items-center justify-between py-2.5">
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
                                    ><Trash2 class="h-4 w-4"
                                /></Button>
                            </div>
                        </li>
                        <li v-if="members.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                            {{ t('access.none') }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('access.removeTitle', { name: pendingName })"
            :description="t('access.removeDescription')"
            :confirm-label="t('common.remove')"
            destructive
            @confirm="doRemove"
        />
    </AppLayout>
</template>
