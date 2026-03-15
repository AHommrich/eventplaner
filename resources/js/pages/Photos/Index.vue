<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Photo {
    id: number;
    url: string;
    guest_name: string;
    created_at: string;
}

defineProps<{ photos: Photo[] }>();

const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({ photo: null as File | null });
const selected = ref<Photo | null>(null);

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    if (file) submitUpload();
}

function submitUpload() {
    form.post(route('photos.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function deletePhoto(id: number) {
    if (!confirm('Foto löschen?')) return;
    selected.value = null;
    router.delete(route('photos.destroy', id));
}
</script>

<template>
    <Head title="Fotos" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Fotos</h1>
                <div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png"
                        class="hidden"
                        @change="onFileChange"
                    />
                    <button
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="fileInput?.click()"
                    >
                        {{ form.processing ? 'Wird hochgeladen…' : 'Foto hochladen' }}
                    </button>
                </div>
            </div>

            <p v-if="photos.length === 0" class="text-muted-foreground text-sm">
                Noch keine Fotos vorhanden.
            </p>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <div
                    v-for="photo in photos"
                    :key="photo.id"
                    class="group relative cursor-pointer overflow-hidden rounded-lg border bg-muted"
                    @click="selected = photo"
                >
                    <img
                        :src="photo.url"
                        :alt="photo.guest_name"
                        class="aspect-square w-full object-cover"
                    />
                    <div class="absolute inset-x-0 bottom-0 bg-black/60 p-2 text-xs text-white">
                        <div class="font-medium">{{ photo.guest_name }}</div>
                        <div class="text-white/70">{{ photo.created_at }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click.self="selected = null"
        >
            <div class="relative flex w-full max-w-2xl flex-col rounded-xl bg-background shadow-xl">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div>
                        <p class="font-semibold">{{ selected.guest_name }}</p>
                        <p class="text-muted-foreground text-sm">{{ selected.created_at }}</p>
                    </div>
                    <button
                        class="text-muted-foreground hover:text-foreground text-xl leading-none"
                        @click="selected = null"
                    >
                        ✕
                    </button>
                </div>
                <img
                    :src="selected.url"
                    :alt="selected.guest_name"
                    class="max-h-[70vh] w-full rounded-b-xl object-contain"
                />
                <div class="flex justify-between border-t px-4 py-3">
                    <a
                        :href="selected.url"
                        target="_blank"
                        class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                    >
                        Öffnen
                    </a>
                    <button
                        class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                        @click="deletePhoto(selected.id)"
                    >
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
