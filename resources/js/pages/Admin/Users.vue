<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface User  { id: number; name: string; email: string; role: string; created_at: string; }
interface Event { id: number; name: string; }

const props = defineProps<{ users: User[]; events: Event[]; }>();

const selectClass = 'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

const addForm = useForm({ email: '', event_id: '' });
function addToEvent() { addForm.post(route('admin.users.addToEvent'), { onSuccess: () => { addForm.reset(); toast.success('User zum Event hinzugefügt'); } }); }

function updateRole(user: User, role: string) {
    useForm({ role }).put(route('admin.users.update', user.id), { onSuccess: () => toast.success(`Rolle auf „${role}" geändert`) });
}

const confirmOpen   = ref(false);
const pendingUser   = ref<User | null>(null);
function askDelete(user: User) { pendingUser.value = user; confirmOpen.value = true; }
function doDelete() {
    if (pendingUser.value) useForm({}).delete(route('admin.users.destroy', pendingUser.value.id), { onSuccess: () => toast.success('User gelöscht') });
}
</script>

<template>
    <Head title="User-Verwaltung" />
    <AppLayout>
        <div class="m-4 space-y-4">

            <Card>
                <CardHeader><CardTitle>User zu Event hinzufügen</CardTitle></CardHeader>
                <CardContent>
                    <form @submit.prevent="addToEvent" class="flex flex-col sm:flex-row gap-2">
                        <Input v-model="addForm.email" type="email" placeholder="Email-Adresse" required class="flex-1" />
                        <select v-model="addForm.event_id" :class="selectClass" required>
                            <option value="">Event auswählen</option>
                            <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }}</option>
                        </select>
                        <Button type="submit" :disabled="addForm.processing" class="whitespace-nowrap">Hinzufügen</Button>
                    </form>
                    <p v-if="addForm.errors.email"    class="mt-1.5 text-xs text-destructive">{{ addForm.errors.email }}</p>
                    <p v-if="addForm.errors.event_id" class="mt-1.5 text-xs text-destructive">{{ addForm.errors.event_id }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Alle User ({{ users.length }})</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Name</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Email</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Rolle</th>
                                <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Registriert</th>
                                <th class="h-10 px-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="border-b transition-colors hover:bg-muted/50 last:border-0">
                                <td class="px-6 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-6 py-3 text-muted-foreground">{{ user.email }}</td>
                                <td class="px-6 py-3">
                                    <select :value="user.role" @change="updateRole(user, ($event.target as HTMLSelectElement).value)" :class="selectClass">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground text-xs">{{ new Date(user.created_at).toLocaleDateString('de-DE') }}</td>
                                <td class="px-6 py-3 text-right">
                                    <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDelete(user)">Löschen</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`${pendingUser?.name} löschen?`"
            description="Der Account wird dauerhaft gelöscht und kann nicht wiederhergestellt werden."
            confirm-label="Löschen"
            destructive
            @confirm="doDelete"
        />
    </AppLayout>
</template>
