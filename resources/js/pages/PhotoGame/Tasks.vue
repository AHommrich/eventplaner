<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

interface Task {
    id: number;
    description: string;
    sort_order: number;
    is_active: boolean;
}

interface Catalog {
    id: number;
    name: string;
    is_global: boolean;
    is_active: boolean;
    tasks: Task[];
}

const props = defineProps<{ catalogs: Catalog[] }>();
const { t } = useI18n();

// Nur 1 eigener Katalog pro Event erlaubt
const hasOwnCatalog = computed(() => props.catalogs.some(c => !c.is_global));

// --- Neuer Katalog ---
const newCatalogName = ref('');

function createCatalog() {
    if (!newCatalogName.value.trim()) return;
    router.post(route('photo-game.catalogs.store'), { name: newCatalogName.value }, {
        onSuccess: () => {
            toast.success(t('photoGame.catalogCreated'));
            newCatalogName.value = '';
        },
    });
}

// --- Neue Aufgabe ---
const newTaskText = ref<Record<number, string>>({});

function createTask(catalogId: number) {
    const text = newTaskText.value[catalogId]?.trim();
    if (!text) return;
    router.post(route('photo-game.tasks.store'), { catalog_id: catalogId, description: text }, {
        onSuccess: () => {
            toast.success(t('photoGame.taskCreated'));
            newTaskText.value[catalogId] = '';
        },
    });
}

// --- Aufgabe löschen ---
const confirmDeleteOpen = ref(false);
const pendingDeleteId = ref<number | null>(null);

function askDeleteTask(id: number) {
    pendingDeleteId.value = id;
    confirmDeleteOpen.value = true;
}

function doDeleteTask() {
    if (pendingDeleteId.value === null) return;
    router.delete(route('photo-game.tasks.destroy', pendingDeleteId.value), {
        onSuccess: () => toast.success(t('photoGame.taskDeleted')),
    });
}

// --- Aufgabe bearbeiten ---
const editingTaskId = ref<number | null>(null);
const editingText = ref('');

function startEdit(task: Task) {
    editingTaskId.value = task.id;
    editingText.value = task.description;
}

function saveEdit(taskId: number) {
    router.patch(route('photo-game.tasks.update', taskId), { description: editingText.value }, {
        onSuccess: () => {
            toast.success(t('photoGame.taskSaved'));
            editingTaskId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('photoGame.tasks')" />
    <AppLayout>
        <div class="m-4 space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ t('photoGame.tasks') }}</h1>
                <Button variant="outline" size="sm" as="a" :href="route('photo-game.index')">
                    ← {{ t('photoGame.title') }}
                </Button>
            </div>

            <!-- Neuer Katalog (nur wenn noch keiner existiert) -->
            <div v-if="!hasOwnCatalog" class="rounded-lg border p-4 space-y-3">
                <h2 class="text-sm font-semibold">{{ t('photoGame.newCatalog') }}</h2>
                <p class="text-xs text-muted-foreground">{{ t('photoGame.oneCatalogHint') }}</p>
                <div class="flex gap-2">
                    <Input v-model="newCatalogName" :placeholder="t('photoGame.catalogNamePlaceholder')" @keydown.enter="createCatalog" />
                    <Button @click="createCatalog">{{ t('common.create') }}</Button>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                {{ t('photoGame.oneCatalogExists') }}
            </div>

            <!-- Kataloge -->
            <div v-for="catalog in catalogs" :key="catalog.id" class="rounded-lg border p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ catalog.name }}</h2>
                    <span v-if="catalog.is_global" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                        {{ t('photoGame.globalCatalog') }}
                    </span>
                    <span class="text-xs text-muted-foreground">({{ catalog.tasks.length }} {{ t('photoGame.tasksCount') }})</span>
                </div>

                <!-- Aufgaben-Liste -->
                <div class="space-y-1">
                    <div
                        v-for="task in catalog.tasks"
                        :key="task.id"
                        class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        :class="{ 'opacity-50': !task.is_active }"
                    >
                        <template v-if="editingTaskId === task.id && !catalog.is_global">
                            <Input v-model="editingText" class="flex-1 h-7 text-sm" @keydown.enter="saveEdit(task.id)" @keydown.esc="editingTaskId = null" />
                            <Button size="sm" variant="ghost" class="h-7 px-2" @click="saveEdit(task.id)">{{ t('common.save') }}</Button>
                            <Button size="sm" variant="ghost" class="h-7 px-2" @click="editingTaskId = null">{{ t('common.cancel') }}</Button>
                        </template>
                        <template v-else>
                            <span class="flex-1">{{ task.description }}</span>
                            <template v-if="!catalog.is_global">
                                <button class="text-muted-foreground hover:text-foreground text-xs px-1" @click="startEdit(task)">
                                    {{ t('common.edit') }}
                                </button>
                                <button class="text-muted-foreground hover:text-destructive text-xs px-1" @click="askDeleteTask(task.id)">
                                    {{ t('common.delete') }}
                                </button>
                            </template>
                        </template>
                    </div>

                    <p v-if="catalog.tasks.length === 0" class="text-xs text-muted-foreground px-1">
                        {{ t('photoGame.noTasks') }}
                    </p>
                </div>

                <!-- Neue Aufgabe (nur eigene Kataloge) -->
                <div v-if="!catalog.is_global" class="flex gap-2 pt-1">
                    <Input
                        v-model="newTaskText[catalog.id]"
                        :placeholder="t('photoGame.taskPlaceholder')"
                        @keydown.enter="createTask(catalog.id)"
                    />
                    <Button size="sm" @click="createTask(catalog.id)">{{ t('common.add') }}</Button>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmDeleteOpen"
            :title="t('photoGame.deleteTaskTitle')"
            :description="t('photoGame.deleteTaskDesc')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDeleteTask"
        />
    </AppLayout>
</template>
