<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

interface ProjectorPhoto { id: number; url: string; label: string | null; }

const props = defineProps<{
    event: { name: string };
    photos: ProjectorPhoto[];
    token: string;
}>();

const allPhotos = ref<ProjectorPhoto[]>([...props.photos]);
const currentIndex = ref(0);
const visible = ref(true);
const infoVisible = ref(true);

const currentPhoto = computed(() => allPhotos.value[currentIndex.value] ?? null);

let slideInterval: ReturnType<typeof setInterval> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;

function nextSlide() {
    if (allPhotos.value.length === 0) return;
    visible.value = false;
    setTimeout(() => {
        currentIndex.value = (currentIndex.value + 1) % allPhotos.value.length;
        visible.value = true;
    }, 600);
}

async function pollPhotos() {
    try {
        const res = await axios.get(route('projector.photos', props.token));
        const incoming: ProjectorPhoto[] = res.data.data ?? [];
        if (incoming.length !== allPhotos.value.length) {
            allPhotos.value = incoming;
            if (currentIndex.value >= allPhotos.value.length) {
                currentIndex.value = 0;
            }
        }
    } catch {
        // ignore
    }
}

onMounted(() => {
    if (allPhotos.value.length > 0) {
        slideInterval = setInterval(nextSlide, 5000);
    }
    pollInterval = setInterval(pollPhotos, 10000);
    setTimeout(() => { infoVisible.value = false; }, 4000);
});

onUnmounted(() => {
    if (slideInterval) clearInterval(slideInterval);
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head :title="event.name + ' – Projektor'" />
    <div class="fixed inset-0 bg-black flex items-center justify-center overflow-hidden">

        <Transition name="crossfade">
            <img
                v-if="currentPhoto && visible"
                :key="currentPhoto.id"
                :src="currentPhoto.url"
                :alt="event.name"
                class="absolute inset-0 w-full h-full object-contain"
            />
        </Transition>

        <div v-if="allPhotos.length === 0" class="text-white/40 text-2xl select-none">
            Noch keine Fotos vorhanden
        </div>

        <!-- Kontext-Label (Gastname / Beschreibung / Aufgabe) -->
        <Transition name="crossfade">
            <div
                v-if="currentPhoto?.label && visible"
                :key="'label-' + currentPhoto.id"
                class="absolute bottom-16 left-0 right-0 flex justify-center px-8 pointer-events-none"
            >
                <span class="bg-black/60 backdrop-blur-sm text-white text-xl font-medium px-6 py-2 rounded-full max-w-2xl text-center truncate">
                    {{ currentPhoto.label }}
                </span>
            </div>
        </Transition>

        <!-- Foto-Counter -->
        <div v-if="allPhotos.length > 0" class="absolute top-4 right-6 text-white/30 text-xs select-none">
            {{ currentIndex + 1 }} / {{ allPhotos.length }}
        </div>

        <!-- Info-Overlay (blendet sich nach 4s aus) -->
        <Transition name="fade-slow">
            <div v-if="infoVisible" class="absolute top-4 left-6 text-white/50 text-xs select-none space-y-0.5">
                <p>Automatischer Wechsel alle 5 Sekunden</p>
                <p>Neue Fotos werden alle 10 Sekunden geladen</p>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.crossfade-enter-active,
.crossfade-leave-active {
    transition: opacity 0.6s ease;
}
.crossfade-enter-from,
.crossfade-leave-to {
    opacity: 0;
}

.fade-slow-leave-active {
    transition: opacity 1.5s ease;
}
.fade-slow-leave-to {
    opacity: 0;
}
</style>
