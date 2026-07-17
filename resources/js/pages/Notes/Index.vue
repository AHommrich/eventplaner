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

interface Note {
    id: number;
    type: 'note' | 'todo';
    title: string;
    body: string | null;
    is_done: boolean;
    done_at: string | null;
    author_name: string | null;
    assignee_user_id: number | null;
    assignee_name: string | null;
    created_at: string;
}
interface Manager {
    id: number;
    name: string;
}

const props = defineProps<{
    personal: Note[];
    assigned_to_me: Note[];
    assigned_team: Note[];
    can_assign: boolean;
    managers: Manager[];
}>();

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notizen', href: '/notes' }];

const hideDone = ref(false);
function visible(list: Note[]): Note[] {
    return hideDone.value ? list.filter((n) => !(n.type === 'todo' && n.is_done)) : list;
}

const myList = computed(() => visible([...props.assigned_to_me, ...props.personal]));
const teamList = computed(() => visible(props.assigned_team));

const form = useForm({
    type: 'todo' as 'note' | 'todo',
    title: '',
    body: '',
    assignee_user_id: '' as string,
});

function create() {
    form.transform((data) => ({
        ...data,
        assignee_user_id: data.assignee_user_id === '' ? null : Number(data.assignee_user_id),
    })).post(route('notes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.type = 'todo';
            toast.success(t('notes.created'));
        },
    });
}

function toggleDone(note: Note) {
    router.patch(route('notes.update', note.id), { is_done: !note.is_done }, { preserveScroll: true, preserveState: false });
}

const confirmOpen = ref(false);
const pending = ref<Note | null>(null);
function askDelete(note: Note) {
    pending.value = note;
    confirmOpen.value = true;
}
function doDelete() {
    if (pending.value)
        router.delete(route('notes.destroy', pending.value.id), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('notes.deleted')),
        });
}

function assignedByOrganizer(note: Note): boolean {
    return note.assignee_user_id !== null && props.assigned_to_me.some((n) => n.id === note.id);
}
</script>

<template>
    <Head :title="t('notes.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <!-- Create -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('notes.addTitle') }}
                        <InfoTooltip :text="t('notes.info')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="create" class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <select v-model="form.type" class="rounded-md border border-input bg-transparent px-2 text-sm outline-none">
                                <option value="todo">{{ t('notes.typeTodo') }}</option>
                                <option value="note">{{ t('notes.typeNote') }}</option>
                            </select>
                            <Input v-model="form.title" :placeholder="t('notes.titlePlaceholder')" required class="min-w-48 flex-1" />
                            <select
                                v-if="can_assign"
                                v-model="form.assignee_user_id"
                                class="rounded-md border border-input bg-transparent px-2 text-sm outline-none"
                            >
                                <option value="">{{ t('notes.assignSelf') }}</option>
                                <option v-for="m in managers" :key="m.id" :value="String(m.id)">{{ t('notes.assignTo', { name: m.name }) }}</option>
                            </select>
                            <Button type="submit" :disabled="form.processing">{{ t('common.add') }}</Button>
                        </div>
                        <textarea
                            v-model="form.body"
                            :placeholder="t('notes.bodyPlaceholder')"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none"
                        />
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                        <p v-if="form.errors.assignee_user_id" class="text-xs text-destructive">{{ form.errors.assignee_user_id }}</p>
                    </form>
                </CardContent>
            </Card>

            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" v-model="hideDone" />
                {{ t('notes.hideDone') }}
            </label>

            <!-- My notes/todos -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('notes.mine') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li v-for="note in myList" :key="note.id" class="flex items-start gap-3 py-2.5">
                            <input v-if="note.type === 'todo'" type="checkbox" :checked="note.is_done" class="mt-1" @change="toggleDone(note)" />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-medium"
                                    :class="{ 'text-muted-foreground line-through': note.type === 'todo' && note.is_done }"
                                >
                                    {{ note.title }}
                                </p>
                                <p v-if="note.body" class="text-xs whitespace-pre-line text-muted-foreground">{{ note.body }}</p>
                                <p v-if="assignedByOrganizer(note)" class="mt-0.5 text-xs text-primary">{{ t('notes.fromOrganizer') }}</p>
                            </div>
                            <Button
                                v-if="!assignedByOrganizer(note)"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="askDelete(note)"
                                ><Trash2 class="h-4 w-4"
                            /></Button>
                        </li>
                        <li v-if="myList.length === 0" class="py-4 text-center text-sm text-muted-foreground">{{ t('notes.empty') }}</li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Assigned to team (assign-tier only) -->
            <Card v-if="can_assign">
                <CardHeader>
                    <CardTitle>{{ t('notes.team') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li v-for="note in teamList" :key="note.id" class="flex items-start gap-3 py-2.5">
                            <span
                                class="mt-0.5 inline-block h-4 w-4 rounded-full"
                                :class="note.is_done ? 'bg-primary' : 'border border-muted-foreground/40'"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" :class="{ 'text-muted-foreground line-through': note.is_done }">{{ note.title }}</p>
                                <p v-if="note.body" class="text-xs whitespace-pre-line text-muted-foreground">{{ note.body }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ t('notes.assignedTo', { name: note.assignee_name ?? '' }) }}</p>
                            </div>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDelete(note)"
                                ><Trash2 class="h-4 w-4"
                            /></Button>
                        </li>
                        <li v-if="teamList.length === 0" class="py-4 text-center text-sm text-muted-foreground">{{ t('notes.teamEmpty') }}</li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('notes.deleteTitle')"
            :description="t('notes.deleteDescription')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDelete"
        />
    </AppLayout>
</template>
