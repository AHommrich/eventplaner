<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}
interface Event {
    id: number;
    name: string;
}
interface Member {
    id: number;
    name: string;
    email: string;
}
interface EventAccess {
    event: { id: number; name: string };
    owner: Member | null;
    members: Member[];
}

const props = defineProps<{ users: User[]; events: Event[]; event_access: EventAccess | null }>();

const { t } = useI18n();
const selectClass =
    'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

const approvedUsers = computed(() => props.users);

// --- Manage access (unified) ---
const addForm = useForm({ email: '', event_id: String(props.event_access?.event.id ?? '') });
function addToEvent() {
    addForm.post(route('admin.users.addToEvent'), {
        onSuccess: () => {
            addForm.reset('email');
            toast.success(t('toast.userAdded'));
        },
    });
}

// When event changes in dropdown → reload members list
watch(
    () => addForm.event_id,
    (eventId) => {
        if (eventId) {
            router.get(route('admin.users.index'), { event_id: eventId }, { preserveState: true, only: ['event_access'] });
        }
    },
);

// --- Change role ---
function updateRole(user: User, role: string) {
    useForm({ role }).put(route('admin.users.update', user.id), { onSuccess: () => toast.success(t('toast.roleChanged', { role })) });
}

// --- Delete user ---
const confirmOpen = ref(false);
const pendingUser = ref<User | null>(null);
function askDelete(user: User) {
    pendingUser.value = user;
    confirmOpen.value = true;
}
function doDelete() {
    if (pendingUser.value)
        useForm({}).delete(route('admin.users.destroy', pendingUser.value.id), { onSuccess: () => toast.success(t('toast.userDeleted')) });
}

// --- Remove user from selected event ---
const removeConfirmOpen = ref(false);
const pendingRemoveMember = ref<Member | null>(null);
function askRemove(member: Member) {
    pendingRemoveMember.value = member;
    removeConfirmOpen.value = true;
}
function doRemove() {
    if (!pendingRemoveMember.value) return;
    useForm({ event_id: addForm.event_id }).delete(route('admin.users.removeFromEvent', pendingRemoveMember.value.id), {
        onSuccess: () => toast.success(t('toast.accessRemoved')),
    });
}
</script>

<template>
    <Head :title="t('admin.title')" />
    <AppLayout>
        <div class="m-4 space-y-4">
            <!-- Manage event access -->
            <Card>
                <CardHeader
                    ><CardTitle>{{ t('access.manageTitle') }}</CardTitle></CardHeader
                >
                <CardContent class="space-y-0">
                    <!-- 1. Event selection -->
                    <div class="pb-4">
                        <p class="text-muted-foreground mb-1.5 text-xs font-medium">{{ t('admin.selectEventLabel') }}</p>
                        <select v-model="addForm.event_id" :class="selectClass + ' w-full'">
                            <option value="">{{ t('admin.selectEvent') }}</option>
                            <option v-for="event in events" :key="event.id" :value="String(event.id)">{{ event.name }}</option>
                        </select>
                    </div>

                    <!-- 2. Members list of the selected event -->
                    <template v-if="event_access">
                        <div class="border-t pb-2 pt-4">
                            <p class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">{{ t('access.currentAccess') }}</p>
                            <ul class="divide-y">
                                <li v-if="event_access.owner" class="flex items-center justify-between py-2.5">
                                    <div>
                                        <p class="text-sm font-medium">{{ event_access.owner.name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ event_access.owner.email }}</p>
                                    </div>
                                    <span class="bg-primary/10 text-primary rounded-full px-2.5 py-0.5 text-xs font-medium">{{
                                        t('access.owner')
                                    }}</span>
                                </li>
                                <li v-for="member in event_access.members" :key="member.id" class="flex items-center justify-between py-2.5">
                                    <div>
                                        <p class="text-sm font-medium">{{ member.name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ member.email }}</p>
                                    </div>
                                    <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askRemove(member)"
                                        ><Trash2 class="h-4 w-4"
                                    /></Button>
                                </li>
                                <li v-if="event_access.members.length === 0" class="text-muted-foreground py-3 text-sm">
                                    {{ t('access.none') }}
                                </li>
                            </ul>
                        </div>

                        <!-- 3. Add user -->
                        <div class="border-t pt-4">
                            <p class="text-muted-foreground mb-1.5 text-xs font-medium">{{ t('admin.addToEvent') }}</p>
                            <form @submit.prevent="addToEvent" class="flex gap-2">
                                <Input v-model="addForm.email" type="email" :placeholder="t('admin.emailPlaceholder')" required class="flex-1" />
                                <Button type="submit" :disabled="addForm.processing" class="whitespace-nowrap">{{ t('common.add') }}</Button>
                            </form>
                            <p v-if="addForm.errors.email" class="text-destructive mt-1 text-xs">{{ addForm.errors.email }}</p>
                            <p v-if="addForm.errors.event_id" class="text-destructive mt-1 text-xs">{{ addForm.errors.event_id }}</p>
                        </div>
                    </template>

                    <div v-else class="text-muted-foreground border-t pb-2 pt-4 text-sm">
                        {{ t('admin.selectEventHint') }}
                    </div>
                </CardContent>
            </Card>

            <!-- All users -->
            <Card>
                <CardHeader
                    ><CardTitle>{{ t('admin.allUsers', { count: approvedUsers.length }) }}</CardTitle></CardHeader
                >
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr>
                                <th class="text-muted-foreground h-10 px-6 text-left align-middle font-medium">{{ t('common.name') }}</th>
                                <th class="text-muted-foreground h-10 px-6 text-left align-middle font-medium">{{ t('common.email') }}</th>
                                <th class="text-muted-foreground h-10 px-6 text-left align-middle font-medium">{{ t('common.role') }}</th>
                                <th class="text-muted-foreground h-10 px-6 text-left align-middle font-medium">{{ t('admin.registered') }}</th>
                                <th class="h-10 px-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in approvedUsers" :key="user.id" class="hover:bg-muted/50 border-b transition-colors last:border-0">
                                <td class="px-6 py-3 font-medium">{{ user.name }}</td>
                                <td class="text-muted-foreground px-6 py-3">{{ user.email }}</td>
                                <td class="px-6 py-3">
                                    <select
                                        :value="user.role"
                                        @change="updateRole(user, ($event.target as HTMLSelectElement).value)"
                                        :class="selectClass"
                                    >
                                        <option value="user">{{ t('admin.user') }}</option>
                                        <option value="admin">{{ t('admin.admin') }}</option>
                                    </select>
                                </td>
                                <td class="text-muted-foreground px-6 py-3 text-xs">{{ new Date(user.created_at).toLocaleDateString('de-DE') }}</td>
                                <td class="px-6 py-3 text-right">
                                    <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDelete(user)"
                                        ><Trash2 class="h-4 w-4"
                                    /></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('admin.deleteTitle', { name: pendingUser?.name })"
            :description="t('admin.deleteDescription')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDelete"
        />

        <ConfirmDialog
            v-model:open="removeConfirmOpen"
            :title="t('access.removeTitle', { name: pendingRemoveMember?.name })"
            :description="t('access.removeDescription')"
            :confirm-label="t('common.remove')"
            destructive
            @confirm="doRemove"
        />
    </AppLayout>
</template>
