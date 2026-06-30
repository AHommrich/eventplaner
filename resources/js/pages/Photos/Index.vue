<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Photo {
    id: number;
    url: string;
    guest_name: string | null;
    organizer_role: 'owner' | 'co_organizer' | null;
    description: string | null;
    created_at: string;
}

interface Album {
    id: number;
    slug: string;
    name: string;
    sort_order: number;
    photos: Photo[];
}

const props = defineProps<{
    albums: Album[];
    projectorUrl: string | null;
    projectorAlbumId: number | null;
    projectorNameMode: 'full' | 'first' | 'none';
}>();

const { t } = useI18n();

// --- Collapsible sections ---
const slideshowOpen = ref(false);
const albumsOpen = ref(false);

// --- Tabs ---
const activeTab = ref<string>(props.albums[0]?.slug ?? 'presentation');
const currentAlbum = computed(() => props.albums.find((a) => a.slug === activeTab.value));

// --- Sortierung ---
const sortOrder = ref<'newest' | 'oldest'>('newest');
const sortedPhotos = computed(() => {
    const photos = [...(currentAlbum.value?.photos ?? [])];
    return sortOrder.value === 'newest' ? photos : photos.reverse();
});

// --- Upload ---
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({ photo: null as File | null, album_id: null as number | null, description: null as string | null });

// Beschreibungs-Dialog für Präsentation-Uploads
const uploadDescriptionOpen = ref(false);
const uploadDescriptionText = ref('');

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    form.photo = file;
    if (activeTab.value === 'presentation') {
        uploadDescriptionText.value = '';
        uploadDescriptionOpen.value = true;
    } else {
        submitUpload();
    }
}

function submitUpload() {
    form.album_id = currentAlbum.value?.id ?? null;
    form.post(route('photos.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
            uploadDescriptionOpen.value = false;
            uploadDescriptionText.value = '';
            toast.success(t('toast.photoUploaded'));
        },
    });
}

function submitUploadWithDescription() {
    form.description = uploadDescriptionText.value.trim() || null;
    submitUpload();
}

function cancelUpload() {
    form.reset();
    if (fileInput.value) fileInput.value.value = '';
    uploadDescriptionOpen.value = false;
}

// --- Foto-Viewer ---
const selected = ref<Photo | null>(null);

function openPhoto(photo: Photo) {
    selected.value = photo;
}

function navigatePhoto(dir: 1 | -1) {
    if (!selected.value) return;
    const photos = sortedPhotos.value;
    const idx = photos.findIndex((p) => p.id === selected.value!.id);
    const next = photos[idx + dir];
    if (next) selected.value = next;
}

// --- Einzellöschen ---
const confirmDeleteOpen = ref(false);
const pendingDeleteId = ref<number | null>(null);

function askDelete(id: number) {
    pendingDeleteId.value = id;
    confirmDeleteOpen.value = true;
}

function doDelete() {
    if (pendingDeleteId.value === null) return;
    selected.value = null;
    router.delete(route('photos.destroy', pendingDeleteId.value), {
        onSuccess: () => toast.success(t('toast.photoDeleted')),
    });
}

// --- Mehrfachauswahl ---
const selectionMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

function toggleSelection(id: number) {
    const s = new Set(selectedIds.value);
    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }
    selectedIds.value = s;
}

function toggleSelectAll() {
    if (selectedIds.value.size === sortedPhotos.value.length) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(sortedPhotos.value.map((p) => p.id));
    }
}

function exitSelectionMode() {
    selectionMode.value = false;
    selectedIds.value = new Set();
}

// --- Batch-Delete ---
const confirmBatchOpen = ref(false);

function doBatchDelete() {
    if (selectedIds.value.size === 0) return;
    router.delete(route('photos.destroy-batch'), {
        data: { ids: [...selectedIds.value] },
        onSuccess: () => {
            toast.success(t('photo.batchDeleted', { count: selectedIds.value.size }));
            exitSelectionMode();
        },
    });
}

// --- Projektor ---
const projectorAlbumId = ref<string>(String(props.projectorAlbumId ?? ''));
const projectorNameMode = ref<string>(props.projectorNameMode ?? 'first');

function copyProjectorUrl() {
    if (props.projectorUrl) {
        navigator.clipboard.writeText(props.projectorUrl);
        toast.success(t('photo.projectorCopied'));
    }
}

function updateProjectorAlbum(albumId: string) {
    projectorAlbumId.value = albumId;
    router.patch(
        route('photos.projector-album'),
        { album_id: Number(albumId) },
        {
            onSuccess: () => toast.success(t('photo.projectorAlbumSaved')),
        },
    );
}

function updateProjectorNameMode(mode: string) {
    projectorNameMode.value = mode;
    router.patch(
        route('photos.projector-name-mode'),
        { name_mode: mode },
        {
            onSuccess: () => toast.success(t('photo.projectorAlbumSaved')),
        },
    );
}
</script>

<template>
    <Head :title="t('photo.title')" />
    <AppLayout>
        <div class="m-4 space-y-4">
            <!-- Header -->
            <h1 class="text-xl font-semibold">{{ t('photo.title') }}</h1>

            <!-- Diashow-Section (collapsible) -->
            <div v-if="projectorUrl" class="overflow-hidden rounded-lg border">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 transition-colors hover:bg-muted/50"
                    @click="slideshowOpen = !slideshowOpen"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">{{ t('photo.slideshowTitle') }}</span>
                        <InfoTooltip :text="t('photo.slideshowInfo')" />
                    </div>
                    <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': slideshowOpen }" />
                </button>
                <div v-show="slideshowOpen" class="space-y-3 border-t px-4 py-3">
                    <div class="flex items-center gap-2">
                        <code class="flex-1 truncate rounded bg-muted px-3 py-2 font-mono text-xs">{{ projectorUrl }}</code>
                        <Button variant="outline" size="sm" @click="copyProjectorUrl">{{ t('photo.projectorCopy') }}</Button>
                        <Button variant="outline" size="sm" as="a" :href="projectorUrl" target="_blank">{{ t('common.open') }}</Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-muted-foreground">{{ t('photo.projectorAlbum') }}:</span>
                        <select
                            :value="projectorAlbumId"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-1 focus:ring-ring focus:outline-none"
                            @change="updateProjectorAlbum(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="album in albums" :key="album.id" :value="String(album.id)">
                                {{ album.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Alben-Section (collapsible) -->
            <div class="overflow-hidden rounded-lg border">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 transition-colors hover:bg-muted/50"
                    @click="albumsOpen = !albumsOpen"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">{{ t('photo.albumsTitle') }}</span>
                        <InfoTooltip :text="t('photo.albumsInfo')" />
                    </div>
                    <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': albumsOpen }" />
                </button>

                <div v-show="albumsOpen" class="border-t">
                    <!-- Album-Tabs + Aktionen in einer Zeile -->
                    <div class="flex items-end justify-between gap-2 border-b">
                        <div class="flex gap-1">
                            <button
                                v-for="album in albums"
                                :key="album.slug"
                                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                                :class="
                                    activeTab === album.slug
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                "
                                @click="
                                    activeTab = album.slug;
                                    exitSelectionMode();
                                "
                            >
                                {{ album.name }}
                                <span class="ml-1 text-xs text-muted-foreground">({{ album.photos.length }})</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 pr-2 pb-1">
                            <Button variant="outline" size="sm" @click="sortOrder = sortOrder === 'newest' ? 'oldest' : 'newest'">
                                {{ sortOrder === 'newest' ? t('photo.sortNewest') : t('photo.sortOldest') }}
                            </Button>
                            <Button v-if="!selectionMode" variant="outline" size="sm" @click="selectionMode = true">
                                {{ t('photo.select') }}
                            </Button>
                            <template v-else>
                                <Button variant="outline" size="sm" @click="toggleSelectAll">
                                    {{ selectedIds.size === sortedPhotos.length ? t('photo.deselectAll') : t('photo.selectAll') }}
                                </Button>
                                <Button v-if="selectedIds.size > 0" variant="destructive" size="sm" @click="confirmBatchOpen = true">
                                    {{ t('photo.deleteSelected', { count: selectedIds.size }) }}
                                </Button>
                                <Button variant="ghost" size="sm" @click="exitSelectionMode">{{ t('common.cancel') }}</Button>
                            </template>
                            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/heic" class="hidden" @change="onFileChange" />
                            <Button :disabled="form.processing" @click="fileInput?.click()">
                                {{ form.processing ? t('photo.uploading') : t('photo.upload') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Tab-Beschreibung -->
                    <div class="space-y-2 px-4 py-3">
                        <div class="flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                            <span class="mt-px shrink-0 text-base leading-none">ℹ</span>
                            <div>
                                <template v-if="activeTab === 'presentation'">
                                    <p>{{ t('photo.descPresentation') }}</p>
                                    <p class="mt-0.5 text-xs opacity-75">{{ t('photo.descPresentationUploadHint') }}</p>
                                </template>
                                <template v-else-if="activeTab === 'app_gallery'">{{ t('photo.descAppGallery') }}</template>
                                <template v-else-if="activeTab === 'photo_game'">{{ t('photo.descPhotoGame') }}</template>
                            </div>
                        </div>

                        <!-- Namensanzeige-Einstellung nur bei App-Galerie -->
                        <div v-if="activeTab === 'app_gallery'" class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ t('photo.projectorNameMode') }}:</span>
                            <select
                                :value="projectorNameMode"
                                class="h-8 rounded-md border border-input bg-background px-2 py-0.5 text-xs shadow-sm focus:ring-1 focus:ring-ring focus:outline-none"
                                @change="updateProjectorNameMode(($event.target as HTMLSelectElement).value)"
                            >
                                <option value="first">{{ t('photo.projectorNameFirst') }}</option>
                                <option value="full">{{ t('photo.projectorNameFull') }}</option>
                                <option value="none">{{ t('photo.projectorNameNone') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Foto-Grid -->
                    <p v-if="sortedPhotos.length === 0" class="px-4 pb-4 text-sm text-muted-foreground">{{ t('photo.none') }}</p>

                    <div v-else class="grid grid-cols-2 gap-3 px-4 pb-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <div
                            v-for="photo in sortedPhotos"
                            :key="photo.id"
                            class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg border bg-muted"
                            :class="{ 'ring-2 ring-primary ring-offset-1': selectedIds.has(photo.id) }"
                            @click="selectionMode ? toggleSelection(photo.id) : openPhoto(photo)"
                        >
                            <img
                                :src="photo.url"
                                :alt="photo.guest_name"
                                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 text-xs text-white">
                                <div class="truncate font-medium">
                                    <template v-if="activeTab === 'presentation' && photo.description">{{ photo.description }}</template>
                                    <template v-else-if="photo.guest_name">
                                        {{ photo.guest_name
                                        }}<template v-if="photo.organizer_role === 'owner'"> ({{ t('photo.organizer') }})</template
                                        ><template v-else-if="photo.organizer_role === 'co_organizer'"> ({{ t('photo.coOrganizer') }})</template>
                                    </template>
                                    <template v-else>{{ t('photo.uploadedByOrganizer') }}</template>
                                </div>
                            </div>
                            <!-- Checkbox im Auswahlmodus -->
                            <div
                                v-if="selectionMode"
                                class="absolute top-2 left-2 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white transition-colors"
                                :class="selectedIds.has(photo.id) ? 'border-primary bg-primary' : 'bg-black/30'"
                            >
                                <svg
                                    v-if="selectedIds.has(photo.id)"
                                    class="h-3 w-3 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="3"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Foto-Viewer Dialog -->
        <Dialog
            :open="!!selected"
            @update:open="
                (val) => {
                    if (!val) selected = null;
                }
            "
        >
            <DialogContent class="max-w-2xl overflow-hidden p-0">
                <DialogHeader class="border-b px-4 py-3">
                    <DialogTitle>
                        {{ selected?.guest_name ?? t('photo.uploadedByOrganizer')
                        }}<template v-if="selected?.organizer_role === 'owner'"> ({{ t('photo.organizer') }})</template
                        ><template v-else-if="selected?.organizer_role === 'co_organizer'"> ({{ t('photo.coOrganizer') }})</template>
                    </DialogTitle>
                    <p v-if="selected?.description" class="text-sm font-normal">{{ selected.description }}</p>
                    <p class="text-sm text-muted-foreground">{{ selected?.created_at }}</p>
                </DialogHeader>
                <img v-if="selected" :src="selected.url" :alt="selected.guest_name" class="max-h-[65vh] w-full object-contain" />
                <div class="flex justify-between border-t px-4 py-3">
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!sortedPhotos.find((p, i) => p.id === selected?.id && i > 0)"
                            @click="navigatePhoto(-1)"
                        >
                            ← {{ t('photo.prev') }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!sortedPhotos.find((p, i) => p.id === selected?.id && i < sortedPhotos.length - 1)"
                            @click="navigatePhoto(1)"
                        >
                            {{ t('photo.next') }} →
                        </Button>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" as="a" :href="selected?.url" target="_blank">{{ t('common.open') }}</Button>
                        <Button variant="destructive" @click="selected && askDelete(selected.id)">{{ t('common.delete') }}</Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Beschreibungs-Dialog für Präsentation-Upload -->
        <Dialog v-model:open="uploadDescriptionOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ t('photo.upload') }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium">{{ t('photo.uploadDescription') }}</label>
                        <Input
                            v-model="uploadDescriptionText"
                            :placeholder="t('photo.uploadDescriptionPlaceholder')"
                            @keydown.enter="submitUploadWithDescription"
                            autofocus
                        />
                        <p class="text-xs text-muted-foreground">{{ t('photo.uploadDescriptionHint') }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button variant="ghost" @click="cancelUpload">{{ t('common.cancel') }}</Button>
                    <Button :disabled="form.processing" @click="submitUploadWithDescription">
                        {{ form.processing ? t('photo.uploading') : t('photo.upload') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Einzellöschen Confirm -->
        <ConfirmDialog
            v-model:open="confirmDeleteOpen"
            :title="t('photo.deleteTitle')"
            :description="t('photo.deleteDescription')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDelete"
        />

        <!-- Batch-Delete Confirm -->
        <ConfirmDialog
            v-model:open="confirmBatchOpen"
            :title="t('photo.batchDeleteTitle')"
            :description="t('photo.batchDeleteDescription', { count: selectedIds.size })"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doBatchDelete"
        />
    </AppLayout>
</template>
