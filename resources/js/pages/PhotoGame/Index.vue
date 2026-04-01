<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

interface Catalog {
    id: number;
    name: string;
    event_type: string | null;
}

interface TaskInPool {
    id: number | null;
    override_id: number | null;
    description: string;
    translation_key: string | null;
    state: 'normal' | 'hidden' | 'modified' | 'added';
    original_text: string | null;
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
    task_pool: TaskInPool[];
    overrides: { id: number; task_id: number | null; action: string; custom_text: string | null }[];
    submissions: Submission[];
}>();

const { t, te } = useI18n();

function taskLabel(task: TaskInPool): string {
    if (task.translation_key && te(task.translation_key)) return t(task.translation_key);
    return task.description;
}

function originalLabel(task: TaskInPool): string {
    if (task.translation_key && te(task.translation_key)) return t(task.translation_key);
    return task.original_text ?? '';
}

// --- Event-Typ wechseln ---
function setCatalog(catalogId: string) {
    router.patch(route('photo-game.catalog'), { catalog_id: catalogId || null }, {
        onSuccess: () => toast.success(t('photoGame.catalogSaved')),
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

// --- Aufgabe ausblenden ---
function hideTask(taskId: number) {
    router.post(route('photo-game.overrides.upsert'), { task_id: taskId, action: 'hidden' }, {
        onSuccess: () => toast.success(t('photoGame.overrideSaved')),
    });
}

// --- Aufgabe wiederherstellen / Override zurücksetzen ---
function deleteOverride(overrideId: number) {
    router.delete(route('photo-game.overrides.destroy', overrideId), {
        onSuccess: () => toast.success(t('photoGame.overrideDeleted')),
    });
}

// --- Aufgabe bearbeiten (modified) ---
const editingTaskId = ref<number | null>(null);   // task.id (für normal/hidden tasks aus dem Pool)
const editingAddedId = ref<number | null>(null);  // override_id (für 'added' tasks)
const editingText = ref('');

function startEditTask(task: TaskInPool) {
    editingTaskId.value = task.id;
    editingAddedId.value = null;
    editingText.value = task.description;
}

function startEditAdded(task: TaskInPool) {
    editingAddedId.value = task.override_id;
    editingTaskId.value = null;
    editingText.value = task.description;
}

function cancelEdit() {
    editingTaskId.value = null;
    editingAddedId.value = null;
}

function saveModify(taskId: number) {
    const text = editingText.value.trim();
    if (!text) return;
    router.post(route('photo-game.overrides.upsert'), { task_id: taskId, action: 'modified', custom_text: text }, {
        onSuccess: () => {
            toast.success(t('photoGame.overrideSaved'));
            cancelEdit();
        },
    });
}

function saveAddedEdit(overrideId: number) {
    const text = editingText.value.trim();
    if (!text) return;
    // We update an 'added' override — we need to delete and recreate because 'added' has no task_id
    // Instead: upsert via override_id is not exposed. We workaround by deleting the old one and creating new.
    // The backend handles 'added' as always-new, so we patch via a delete+create approach.
    // Actually we can't easily upsert 'added' without an override_id route.
    // For now: we just post a new one (duplicate) — but that creates 2 entries.
    // Better: expose a PATCH route, but we don't have one yet.
    // For now: delete old + store new via two requests.
    router.delete(route('photo-game.overrides.destroy', overrideId), {
        onSuccess: () => {
            router.post(route('photo-game.overrides.upsert'), { action: 'added', custom_text: text }, {
                onSuccess: () => {
                    toast.success(t('photoGame.overrideSaved'));
                    cancelEdit();
                },
            });
        },
    });
}

// --- Eigene Aufgabe hinzufügen ---
const newTaskText = ref('');

function addOwnTask() {
    const text = newTaskText.value.trim();
    if (!text) return;
    router.post(route('photo-game.overrides.upsert'), { action: 'added', custom_text: text }, {
        onSuccess: () => {
            toast.success(t('photoGame.overrideSaved'));
            newTaskText.value = '';
        },
    });
}

// --- Eigene Aufgabe löschen ---
const confirmDeleteOverrideOpen = ref(false);
const pendingDeleteOverrideId = ref<number | null>(null);

function askDeleteAdded(overrideId: number) {
    pendingDeleteOverrideId.value = overrideId;
    confirmDeleteOverrideOpen.value = true;
}

function doDeleteAdded() {
    if (pendingDeleteOverrideId.value === null) return;
    deleteOverride(pendingDeleteOverrideId.value);
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

// Active task count (hidden tasks don't count)
const activeTaskCount = () => props.task_pool.filter(t => t.state !== 'hidden').length;
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

                <!-- Event-Typ-Auswahl -->
                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">{{ t('photoGame.eventTypeCatalog') }}</label>
                    <select
                        :value="String(game?.catalog_id ?? '')"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="setCatalog(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">{{ t('photoGame.noEventType') }}</option>
                        <option v-for="cat in catalogs" :key="cat.id" :value="String(cat.id)">
                            {{ cat.event_type && te('photoGame.catalogType.' + cat.event_type) ? t('photoGame.catalogType.' + cat.event_type) : cat.name }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">{{ t('photoGame.eventTypeHint') }}</p>
                </div>

                <!-- Start/Stop Buttons -->
                <div class="flex gap-2">
                    <Button
                        v-if="!game || game.status === 'draft' || game.status === 'ended'"
                        :disabled="activeTaskCount() === 0"
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

            <!-- Aufgaben-Pool -->
            <div class="rounded-lg border p-4 space-y-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold">
                            {{ t('photoGame.taskPool') }}
                            <span class="font-normal text-muted-foreground">({{ activeTaskCount() }} {{ t('photoGame.tasksCount') }})</span>
                        </h2>
                        <InfoTooltip :text="t('photoGame.taskPoolInfo')" />
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ t('photoGame.taskPoolHint') }}</p>
                </div>

                <div class="space-y-1">
                    <p v-if="task_pool.length === 0" class="text-sm text-muted-foreground">
                        {{ t('photoGame.noTasks') }}
                    </p>

                    <div
                        v-for="task in task_pool"
                        :key="(task.id ?? 'add') + '-' + (task.override_id ?? 'none')"
                        class="group flex items-start gap-2 rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'opacity-40': task.state === 'hidden',
                            'border-dashed': task.state === 'added',
                        }"
                    >
                        <!-- Bearbeitungs-Modus für normale/modified tasks -->
                        <template v-if="editingTaskId === task.id && task.id !== null">
                            <Input
                                v-model="editingText"
                                class="flex-1 h-7 text-sm"
                                @keydown.enter="saveModify(task.id!)"
                                @keydown.esc="cancelEdit"
                                autofocus
                            />
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="saveModify(task.id!)">
                                {{ t('common.save') }}
                            </Button>
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="cancelEdit">
                                {{ t('common.cancel') }}
                            </Button>
                        </template>

                        <!-- Bearbeitungs-Modus für 'added' tasks -->
                        <template v-else-if="editingAddedId === task.override_id && task.override_id !== null && task.state === 'added'">
                            <Input
                                v-model="editingText"
                                class="flex-1 h-7 text-sm"
                                @keydown.enter="saveAddedEdit(task.override_id!)"
                                @keydown.esc="cancelEdit"
                                autofocus
                            />
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="saveAddedEdit(task.override_id!)">
                                {{ t('common.save') }}
                            </Button>
                            <Button size="sm" variant="ghost" class="h-7 px-2 shrink-0" @click="cancelEdit">
                                {{ t('common.cancel') }}
                            </Button>
                        </template>

                        <!-- Anzeige-Modus -->
                        <template v-else>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="leading-snug"
                                    :class="{
                                        'line-through text-muted-foreground': task.state === 'hidden',
                                        'text-blue-600': task.state === 'added',
                                    }"
                                >{{ task.state === 'modified' ? task.description : taskLabel(task) }}</span>
                                <!-- Original-Text bei modified -->
                                <p v-if="task.state === 'modified' && task.original_text" class="text-xs text-muted-foreground mt-0.5">
                                    {{ t('photoGame.originalText') }}: {{ originalLabel(task) }}
                                </p>
                            </div>

                            <!-- State Badge -->
                            <span
                                v-if="task.state !== 'normal'"
                                class="shrink-0 text-xs px-1.5 py-0.5 rounded-full"
                                :class="{
                                    'bg-red-100 text-red-700': task.state === 'hidden',
                                    'bg-amber-100 text-amber-700': task.state === 'modified',
                                    'bg-blue-100 text-blue-700': task.state === 'added',
                                }"
                            >
                                {{ t('photoGame.taskState.' + task.state) }}
                            </span>

                            <!-- Aktions-Menü -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        @click.stop
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                                        </svg>
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="min-w-[160px]">
                                    <!-- Normal: bearbeiten + ausblenden -->
                                    <template v-if="task.state === 'normal' && task.id !== null">
                                        <DropdownMenuItem @click="startEditTask(task)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            {{ t('photoGame.editTask') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @click="hideTask(task.id!)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                            {{ t('photoGame.hideTask') }}
                                        </DropdownMenuItem>
                                    </template>

                                    <!-- Hidden: wiederherstellen -->
                                    <template v-else-if="task.state === 'hidden' && task.override_id !== null">
                                        <DropdownMenuItem @click="deleteOverride(task.override_id!)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            {{ t('photoGame.restoreTask') }}
                                        </DropdownMenuItem>
                                    </template>

                                    <!-- Modified: bearbeiten + zurücksetzen -->
                                    <template v-else-if="task.state === 'modified' && task.override_id !== null">
                                        <DropdownMenuItem @click="startEditTask(task)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            {{ t('photoGame.editTask') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteOverride(task.override_id!)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                            {{ t('photoGame.resetTask') }}
                                        </DropdownMenuItem>
                                    </template>

                                    <!-- Added: bearbeiten + löschen -->
                                    <template v-else-if="task.state === 'added' && task.override_id !== null">
                                        <DropdownMenuItem @click="startEditAdded(task)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            {{ t('photoGame.editTask') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @click="askDeleteAdded(task.override_id!)">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                            {{ t('common.delete') }}
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                    </div>
                </div>

                <!-- Eigene Aufgabe hinzufügen -->
                <div class="flex gap-2 pt-1">
                    <Input
                        v-model="newTaskText"
                        :placeholder="t('photoGame.taskPlaceholder')"
                        @keydown.enter="addOwnTask"
                    />
                    <Button size="sm" @click="addOwnTask">{{ t('common.add') }}</Button>
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
                            class="absolute top-1.5 right-1.5 flex sm:hidden sm:group-hover:flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white hover:bg-red-600 transition-colors"
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
            v-model:open="confirmDeleteOverrideOpen"
            :title="t('photoGame.deleteTaskTitle')"
            :description="t('photoGame.deleteTaskDesc')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDeleteAdded"
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
