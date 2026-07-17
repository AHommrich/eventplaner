<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    is_approved: boolean;
    created_at: string;
}

const props = defineProps<{ users: User[] }>();

const { t } = useI18n();
const selectClass =
    'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

const approvedUsers = computed(() => props.users);

// --- Change global platform role ---
function updateRole(user: User, role: string) {
    useForm({ role }).put(route('admin.users.update', user.id), { onSuccess: () => toast.success(t('toast.roleChanged', { role })) });
}

function updateApproval(user: User, isApproved: boolean) {
    useForm({ is_approved: isApproved }).put(route('admin.users.update', user.id), {
        onSuccess: () => toast.success(t('toast.approvalChanged')),
    });
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
</script>

<template>
    <Head :title="t('admin.title')" />
    <AppLayout>
        <div class="m-4 space-y-4">
            <!-- Per-event access moved to /event/access (reachable via the event switcher). -->
            <p class="text-sm text-muted-foreground">{{ t('admin.accessMovedHint') }}</p>

            <!-- All users -->
            <Card>
                <CardHeader
                    ><CardTitle>{{ t('admin.allUsers', { count: approvedUsers.length }) }}</CardTitle></CardHeader
                >
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('common.name') }}</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('common.email') }}</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('common.role') }}</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('admin.approved') }}</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('admin.registered') }}</th>
                                <th class="h-10 px-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in approvedUsers" :key="user.id" class="border-b transition-colors last:border-0 hover:bg-muted/50">
                                <td class="px-6 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-6 py-3 text-muted-foreground">{{ user.email }}</td>
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
                                <td class="px-6 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="user.role === 'admin' || user.is_approved"
                                        :disabled="user.role === 'admin'"
                                        :aria-label="t('admin.approved')"
                                        class="h-4 w-4 rounded border-input accent-primary disabled:opacity-50"
                                        @change="updateApproval(user, ($event.target as HTMLInputElement).checked)"
                                    />
                                </td>
                                <td class="px-6 py-3 text-xs text-muted-foreground">{{ new Date(user.created_at).toLocaleDateString('de-DE') }}</td>
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
    </AppLayout>
</template>
