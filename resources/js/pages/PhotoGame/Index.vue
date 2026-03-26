<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

interface Catalog {
    id: number;
    name: string;
    event_id: number | null;
}

interface Task {
    id: number;
    description: string;
    is_active: boolean;
    sort_order: number;
}

interface SelectedCatalog {
    id: number;
    name: string;
    is_global: boolean;
    tasks: Task[];
}

interface Submission {
    id: number;
    guest_name: string;
    task: string | null;
    photo_url: string | null;
    submitted_at: string;
}

interface Game {
    id: number;
    status: 'draft' | 'active' | 'ended';
    catalog_id: number | null;
}

const props = defineProps<{
    game: Game | null;
    catalogs: Catalog[];
    selected_catalog: SelectedCatalog | null;
    submissions: Submission[];
}>();

const { t } = useI18n();

// --- Katalog wechseln ---
function setCatalog(catalogId: string) {
    router.patch(route('photo-game.catalog'), { catalog_id: catalogId || null }, {
        onSuccess: () => toast.success(t('photoGame.catalogSaved')),
    });
}

// --- Vorlage anpassen (Fork) ---
// Wenn schon ein eigener Katalog existiert, fragen ob Tasks überschrieben werden sollen
const confirmForkOpen = ref(false);

function forkCatalog() {
    if (!props.game?.catalog_id) return;
    // Hat das Event bereits einen eigenen Katalog?
    const hasOwn = props.catalogs.some(c => c.event_id !== null);
    if (hasOwn) {
        confirmForkOpen.value = true;
    } else {
        doFork();
    }
}

function doFork() {
    if (!props.game?.catalog_id) return;
    router.post(route('photo-game.catalog.fork'), { catalog_id: props.game.catalog_id }, {
        onSuccess: () => toast.success(t('photoGame.catalogForked')),
    });
}

// --- Start/Stop ---
function startGame() {
    router.post(route('photo-game.start'), {}, {
        onSuccess: () => toast.success(t('photoGame.started')),
    });
}

function endGame() {
    router.post(route('photo-game.end'), {}, {
        onSuccess: () => toast.success(t('photoGame.ended')),
    });
}

// --- Neue Aufgabe hinzufügen ---
const newTaskText = ref('');

function addTask() {
    const text = newTaskText.value.trim();
    if (!text || !props.selected_catalog) return;
    router.post(route('photo-game.tasks.store'), {
        catalog_id: props.selected_catalog.id,
        description: text,
    }, {
        onSuccess: () => {
            toast.success(t('photoGame.taskCreated'));
            newTaskText.value = '';
        },
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

// --- Aufgabe löschen ---
const confirmTaskDeleteOpen = ref(false);
const pendingTaskDeleteId = ref<number | null>(null);

function askDeleteTask(id: number) {
    pendingTaskDeleteId.value = id;
    confirmTaskDeleteOpen.value = true;
}

function doDeleteTask() {
    if (pendingTaskDeleteId.value === null) return;
    router.delete(route('photo-game.tasks.destroy', pendingTaskDeleteId.value), {
        onSuccess: () => toast.success(t('photoGame.taskDeleted')),
    });
}

// --- Einreichung löschen ---
const confirmDeleteOpen = ref(false);
const pendingDeleteId = ref<number | null>(null);

function askDeleteSubmission(id: number) {
    pendingDeleteId.value = id;
    confirmDeleteOpen.value = true;
}

function doDeleteSubmission() {
    if (pendingDeleteId.value === null) return;
    router.delete(route('photo-game.assignments.destroy', pendingDeleteId.value), {
        onSuccess: () => toast.success(t('photoGame.submissionDeleted')),
    });
}

// --- Foto-Viewer ---
const viewerPhoto = ref<Submission | null>(null);
</script>

<template>
    <Head :title="t('photoGame.title')" />
    <AppLayout>
        <div class="m-4 space-y-4">
            <h1 class="text-xl font-semibold">{{ t('photoGame.title') }}</h1>

            <!-- Spiel-Einstellungen -->
            <div class="rounded-lg border p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">{{ t('photoGame.settings') }}</h2>
                    <!-- Status Badge -->
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-yellow-100 text-yellow-800': !game || game.status === 'draft',
                            'bg-green-100 text-green-800': game?.status === 'active',
                            'bg-gray-100 text-gray-800': game?.status === 'ended',
                        }"
                    >
                        <template v-if="!game || game.status === 'draft'">{{ t('photoGame.status.draft') }}</template>
                        <template v-else-if="game.status === 'active'">{{ t('photoGame.status.active') }}</template>
                        <template v-else>{{ t('photoGame.status.ended') }}</template>
                    </span>
                </div>

                <!-- Katalog-Auswahl -->
                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">{{ t('photoGame.catalog') }}</label>
                    <select
                        :value="String(game?.catalog_id ?? '')"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="setCatalog(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">— {{ t('photoGame.noCatalog') }} —</option>
                        <option v-for="cat in catalogs" :key="cat.id" :value="String(cat.id)">
                            {{ cat.name }}{{ cat.event_id === null ? ' ★' : '' }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">{{ t('photoGame.catalogHint') }}</p>
                </div>

                <!-- Start/Stop Buttons -->
                <div class="flex gap-2">
                    <Button
                        v-if="!game || game.status === 'draft' || game.status === 'ended'"
                        :disabled="!game?.catalog_id || !selected_catalog?.tasks.length"
                        @click="startGame"
                    >
                        {{ t('photoGame.start') }}
                    </Button>
                    <Button
                        v-if="game?.status === 'active'"
                        variant="destructive"
                        @click="endGame"
                    >
                        {{ t('photoGame.end') }}
                    </Button>
                </div>
            </div>

            <!-- Aufgaben-Liste des gewählten Katalogs -->
            <div v-if="selected_catalog" class="rounded-lg border p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">
                            {{ t('photoGame.tasksInCatalog') }}
                            <span class="font-normal text-muted-foreground">— {{ selected_catalog.name }}</span>
                        </h2>
                        <p v-if="selected_catalog.is_global" class="text-xs text-muted-foreground mt-0.5">
                            {{ t('photoGame.globalReadOnly') }}
                        </p>
                    </div>
                    <!-- Globalen Katalog als Vorlage kopieren -->
                    <Button
                        v-if="selected_catalog.is_global"
                        variant="outline"
                        size="sm"
                        class="shrink-0"
                        @click="forkCatalog"
                    >
                        {{ catalogs.some(c => c.event_id !== null) ? t('photoGame.forkCatalogReplace') : t('photoGame.forkCatalog') }}
                    </Button>
                </div>

                <!-- Task-Liste -->
                <div class="space-y-1">
                    <p v-if="selected_catalog.tasks.length === 0" class="text-sm text-muted-foreground">
                        {{ t('photoGame.noTasks') }}
                    </p>

                    <div
                        v-for="task in selected_catalog.tasks"
                        :key="task.id"
                        class="group flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        :class="{ 'opacity-50': !task.is_active }"
                    >
                        <!-- Bearbeiten-Modus (nur eigener Katalog) -->
                        <template v-if="editingTaskId === task.id && !selected_catalog.is_global">
                            <Input
                                v-model="editingText"
                                class="flex-1 h-7 text-sm"
                                @keydown.enter="saveEdit(task.id)"
                                @keydown.esc="editingTaskId = null"
                                autofocus
                            />
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="saveEdit(task.id)">
                                {{ t('common.save') }}
                            </Button>
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="editingTaskId = null">
                                {{ t('common.cancel') }}
                            </Button>
                        </template>

                        <!-- Anzeige-Modus -->
                        <template v-else>
                            <span class="flex-1 leading-snug">{{ task.description }}</span>
                            <template v-if="!selected_catalog.is_global">
                                <button
                                    class="hidden group-hover:inline text-xs text-muted-foreground hover:text-foreground px-1 shrink-0"
                                    @click="startEdit(task)"
                                >{{ t('common.edit') }}</button>
                                <button
                                    class="hidden group-hover:inline text-xs text-muted-foreground hover:text-destructive px-1 shrink-0"
                                    @click="askDeleteTask(task.id)"
                                >{{ t('common.delete') }}</button>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- Neue Aufgabe hinzufügen (nur eigener Katalog) -->
                <div v-if="!selected_catalog.is_global" class="flex gap-2 pt-1">
                    <Input
                        v-model="newTaskText"
                        :placeholder="t('photoGame.taskPlaceholder')"
                        @keydown.enter="addTask"
                    />
                    <Button size="sm" @click="addTask">{{ t('common.add') }}</Button>
                </div>
            </div>

            <!-- Einreichungen -->
            <div class="space-y-2">
                <h2 class="text-sm font-semibold">
                    {{ t('photoGame.submissions') }}
                    <span class="text-muted-foreground font-normal">({{ submissions.length }})</span>
                </h2>

                <p v-if="submissions.length === 0" class="text-sm text-muted-foreground">
                    {{ t('photoGame.noSubmissions') }}
                </p>

                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <div
                        v-for="sub in submissions"
                        :key="sub.id"
                        class="group relative cursor-pointer overflow-hidden rounded-lg border bg-muted aspect-square"
                        @click="viewerPhoto = sub"
                    >
                        <img
                            v-if="sub.photo_url"
                            :src="sub.photo_url"
                            :alt="sub.guest_name"
                            class="h-full w-full object-cover transition-transform group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center text-xs text-muted-foreground p-2 text-center">
                            {{ sub.task }}
                        </div>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 text-xs text-white">
                            <div class="font-medium truncate">{{ sub.guest_name }}</div>
                            <div class="truncate opacity-75">{{ sub.task }}</div>
                        </div>
                        <button
                            class="absolute top-1.5 right-1.5 hidden group-hover:flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white hover:bg-red-600 transition-colors"
                            @click.stop="askDeleteSubmission(sub.id)"
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Foto-Viewer -->
        <div
            v-if="viewerPhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
            @click="viewerPhoto = null"
        >
            <div class="relative max-w-2xl w-full mx-4" @click.stop>
                <img
                    v-if="viewerPhoto.photo_url"
                    :src="viewerPhoto.photo_url"
                    class="max-h-[80vh] w-full object-contain rounded-lg"
                />
                <div class="mt-2 text-white text-sm text-center">
                    <p class="font-semibold">{{ viewerPhoto.guest_name }}</p>
                    <p class="opacity-75">{{ viewerPhoto.task }}</p>
                </div>
                <button
                    class="absolute top-2 right-2 h-8 w-8 flex items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                    @click="viewerPhoto = null"
                >✕</button>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmForkOpen"
            :title="t('photoGame.forkReplaceTitle')"
            :description="t('photoGame.forkReplaceDesc')"
            :confirm-label="t('photoGame.forkCatalogReplace')"
            destructive
            @confirm="doFork"
        />

        <ConfirmDialog
            v-model:open="confirmTaskDeleteOpen"
            :title="t('photoGame.deleteTaskTitle')"
            :description="t('photoGame.deleteTaskDesc')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDeleteTask"
        />

        <ConfirmDialog
            v-model:open="confirmDeleteOpen"
            :title="t('photoGame.deleteSubmissionTitle')"
            :description="t('photoGame.deleteSubmissionDesc')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDeleteSubmission"
        />
    </AppLayout>
</template>
