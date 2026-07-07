<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import heic2any from 'heic2any';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    initialCoverUrl: string | null;
}>();

const cover = defineModel<File | null>('cover', { default: null });
const colorHomeText = defineModel<string>('colorHomeText', { default: '#ffffff' });
const colorHomeShadow = defineModel<string>('colorHomeShadow', { default: '#000000' });
const homeShadowOpacity = defineModel<number>('homeShadowOpacity', { default: 50 });

const emit = defineEmits<{
    'update:display-cover-url': [url: string | null];
}>();

const { t } = useI18n();

const coverUrl = ref<string | null>(props.initialCoverUrl);
watch(
    () => props.initialCoverUrl,
    (val) => {
        coverUrl.value = val;
    },
);

const coverPreview = ref<string | null>(null);
const coverRemoving = ref(false);
const coverConverting = ref(false);
const isDraggingCover = ref(false);

const displayCoverUrl = computed(() => coverPreview.value ?? coverUrl.value);

// Notify parent so previews (PhonePreview/Home) can consume the current cover URL.
watch(displayCoverUrl, (val) => emit('update:display-cover-url', val), { immediate: true });

const coverFilename = computed(() => {
    if (cover.value) return cover.value.name;
    if (!coverUrl.value) return null;
    try {
        return decodeURIComponent(coverUrl.value.split('/').pop()?.split('?')[0] ?? '');
    } catch {
        return null;
    }
});

async function processCoverFile(file: File) {
    const isHeic = /heic|heif/i.test(file.name) || file.type === 'image/heic' || file.type === 'image/heif';
    const needsConvert = isHeic || file.type === 'image/png' || file.type === 'image/webp';

    if (isHeic) {
        coverConverting.value = true;
        try {
            const blob = (await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 })) as Blob;
            file = new File([blob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
        } catch {
            toast.error(t('toast.coverError'));
            return;
        } finally {
            coverConverting.value = false;
        }
    } else if (needsConvert) {
        // PNG / WebP → JPEG via Canvas
        coverConverting.value = true;
        try {
            const bitmap = await createImageBitmap(file);
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            canvas.getContext('2d')!.drawImage(bitmap, 0, 0);
            const blob = await new Promise<Blob>((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject()), 'image/jpeg', 0.92));
            file = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' });
        } catch {
            toast.error(t('toast.coverError'));
            return;
        } finally {
            coverConverting.value = false;
        }
    }

    cover.value = file;
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = URL.createObjectURL(file);
}

async function onFileSelect(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) await processCoverFile(file);
}

async function onCoverDrop(e: DragEvent) {
    isDraggingCover.value = false;
    if (coverConverting.value) return;
    const file = e.dataTransfer?.files[0];
    if (!file) return;
    await processCoverFile(file);
}

function clearSelectedFile() {
    cover.value = null;
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = null;
}

async function removeCover() {
    coverRemoving.value = true;
    try {
        await axios.delete(route('event.settings.cover.delete'));
        coverUrl.value = null;
        clearSelectedFile();
        toast.success(t('toast.coverRemoved'));
    } catch {
        toast.error(t('toast.coverError'));
    } finally {
        coverRemoving.value = false;
    }
}

// Called by the parent after form.reset() / submit onSuccess to drop any in-flight preview blob.
function resetPreview() {
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = null;
}
defineExpose({ resetPreview });

// Fallback: when the parent nulls `cover` (via form.reset()), drop the local preview blob too.
watch(cover, (val) => {
    if (val === null && coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
        coverPreview.value = null;
    }
});
</script>

<template>
    <p class="text-sm text-muted-foreground">{{ t('event.coverHint') }}</p>

    <div v-if="displayCoverUrl" class="flex items-center gap-3 rounded-lg border bg-muted/30 px-3 py-2">
        <img :src="displayCoverUrl" class="h-12 w-20 shrink-0 rounded object-cover" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium">{{ coverFilename }}</p>
            <p class="text-xs text-muted-foreground">
                {{ coverPreview ? t('event.coverSelected') : t('event.coverCurrent') }}
            </p>
        </div>
        <Button v-if="coverPreview" variant="ghost" size="sm" class="shrink-0" @click="clearSelectedFile">
            {{ t('common.remove') }}
        </Button>
        <Button
            v-else
            variant="ghost"
            size="sm"
            class="shrink-0 text-destructive hover:text-destructive"
            :disabled="coverRemoving"
            @click="removeCover"
        >
            {{ coverRemoving ? '…' : t('common.remove') }}
        </Button>
    </div>

    <label
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-all"
        :class="[
            coverConverting ? 'pointer-events-none opacity-50' : '',
            isDraggingCover ? 'scale-[1.01] border-ring bg-muted/30' : 'border-input hover:border-muted-foreground hover:bg-muted/20',
        ]"
        @dragover.prevent
        @dragenter.prevent="isDraggingCover = true"
        @dragleave.self="isDraggingCover = false"
        @drop.prevent="onCoverDrop"
    >
        <input
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/heic,image/heif"
            @change="onFileSelect"
            :disabled="coverConverting"
        />
        <svg
            class="h-6 w-6 text-muted-foreground"
            :class="{ 'animate-bounce': isDraggingCover }"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" y1="3" x2="12" y2="15" />
        </svg>
        <span class="text-sm font-medium">
            {{
                coverConverting
                    ? t('event.coverUploading')
                    : isDraggingCover
                      ? t('event.coverDragging')
                      : displayCoverUrl
                        ? t('event.coverReplace')
                        : t('event.coverUpload')
            }}
        </span>
        <span v-if="!isDraggingCover && !coverConverting" class="text-xs text-muted-foreground">
            {{ t('event.coverDragHint') }}
        </span>
    </label>
    <p class="text-xs text-muted-foreground">{{ t('event.coverSaveHint') }}</p>

    <!-- Home screen color — only when cover is present -->
    <div v-if="displayCoverUrl" class="grid gap-1.5 pt-1">
        <span class="text-xs text-muted-foreground">{{ t('event.colorHomeText') }}</span>
        <div class="flex items-center gap-2">
            <input
                type="color"
                v-model="colorHomeText"
                class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
            />
            <Input v-model="colorHomeText" class="px-2 font-mono text-xs uppercase" maxlength="7" placeholder="#ffffff" />
        </div>
    </div>
    <div v-if="displayCoverUrl" class="grid gap-2">
        <Label>{{ t('event.colorHomeShadow') }}</Label>
        <div class="flex items-center gap-2">
            <input
                type="color"
                v-model="colorHomeShadow"
                class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
            />
            <Input v-model="colorHomeShadow" maxlength="7" class="font-mono uppercase" placeholder="#000000" />
        </div>
    </div>
    <div v-if="displayCoverUrl" class="grid gap-2">
        <Label
            >{{ t('event.homeShadowOpacity') }}
            <span class="text-xs font-normal text-muted-foreground">{{ homeShadowOpacity }}%</span></Label
        >
        <input type="range" v-model.number="homeShadowOpacity" min="0" max="100" step="5" class="w-full" />
        <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.homeShadowOpacityHint') }}</p>
    </div>
</template>
