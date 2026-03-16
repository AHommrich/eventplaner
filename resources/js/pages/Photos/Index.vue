<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Photo { id: number; url: string; guest_name: string; created_at: string; }
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
        <div class="m-4 space-y-4">

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Fotos</h1>
                <div>
                    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/heic" class="hidden" @change="onFileChange" />
                    <Button :disabled="form.processing" @click="fileInput?.click()">
                        {{ form.processing ? 'Wird hochgeladen…' : 'Foto hochladen' }}
                    </Button>
                </div>
            </div>

            <p v-if="photos.length === 0" class="text-sm text-muted-foreground">Noch keine Fotos vorhanden.</p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <div
                    v-for="photo in photos"
                    :key="photo.id"
                    class="group relative cursor-pointer overflow-hidden rounded-lg border bg-muted aspect-square"
                    @click="selected = photo"
                >
                    <img :src="photo.url" :alt="photo.guest_name" class="h-full w-full object-cover transition-transform group-hover:scale-105" />
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 text-xs text-white">
                        <div class="font-medium truncate">{{ photo.guest_name }}</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Detail Dialog -->
        <Dialog :open="!!selected" @update:open="val => { if (!val) selected = null }">
            <DialogContent class="max-w-2xl p-0 overflow-hidden">
                <DialogHeader class="px-4 py-3 border-b">
                    <DialogTitle>{{ selected?.guest_name }}</DialogTitle>
                    <p class="text-sm text-muted-foreground">{{ selected?.created_at }}</p>
                </DialogHeader>
                <img v-if="selected" :src="selected.url" :alt="selected.guest_name" class="max-h-[65vh] w-full object-contain" />
                <div class="flex justify-between border-t px-4 py-3">
                    <Button variant="outline" as="a" :href="selected?.url" target="_blank">Öffnen</Button>
                    <Button variant="destructive" @click="selected && deletePhoto(selected.id)">Löschen</Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
