<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import heic2any from 'heic2any';

interface EventData {
    id: number;
    name: string;
    date: string | null;
    rsvp_deadline: string | null;
    cover_image_url: string | null;
    venue_name: string | null;
    venue_address: string | null;
    dresscode: string | null;
    schedule: string | null;
    color_accent: string | null;
    color_background: string | null;
    color_card: string | null;
    color_home_text: string | null;
}

const props = defineProps<{ event: EventData }>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('event.settings'), href: '/event/settings' },
];

const form = useForm({
    name:            props.event.name ?? '',
    date:            props.event.date ? props.event.date.slice(0, 16) : '',
    rsvp_deadline:   props.event.rsvp_deadline ? props.event.rsvp_deadline.slice(0, 16) : '',
    venue_name:      props.event.venue_name ?? '',
    venue_address:   props.event.venue_address ?? '',
    dresscode:       props.event.dresscode ?? '',
    schedule:        props.event.schedule ?? '',
    color_accent:     props.event.color_accent     ?? '#7c2d3e',
    color_background: props.event.color_background ?? '#e8e3de',
    color_card:       props.event.color_card        ?? '#ffffff',
    color_home_text:  props.event.color_home_text   ?? '#ffffff',
    cover: null as File | null,
});

function submit() {
    form.post(route('event.settings.update'), {
        onSuccess: () => {
            toast.success(t('toast.eventSettingsSaved'));
            coverPreview.value = null;
            form.cover = null;
        },
    });
}

// Cover
const coverUrl     = ref<string | null>(props.event.cover_image_url);
const coverPreview = ref<string | null>(null);
const coverRemoving = ref(false);

// Für Preview-Panels: neue lokale Vorschau hat Vorrang vor gespeicherter URL
const displayCoverUrl = computed(() => coverPreview.value ?? coverUrl.value);

const coverFilename = computed(() => {
    if (form.cover) return form.cover.name;
    if (!coverUrl.value) return null;
    try { return decodeURIComponent(coverUrl.value.split('/').pop()?.split('?')[0] ?? ''); }
    catch { return null; }
});

const coverConverting = ref(false);

async function onFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    let file = input.files?.[0];
    if (!file) return;

    if (/heic|heif/i.test(file.name) || file.type === 'image/heic' || file.type === 'image/heif') {
        coverConverting.value = true;
        try {
            const blob = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 }) as Blob;
            file = new File([blob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
        } catch {
            toast.error(t('toast.coverError'));
            coverConverting.value = false;
            input.value = '';
            return;
        } finally {
            coverConverting.value = false;
        }
    }

    form.cover = file;
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = URL.createObjectURL(file);
}

function clearSelectedFile() {
    form.cover = null;
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

// Preview
const previewDate = computed(() => {
    if (!form.date) return null;
    try {
        return new Date(form.date).toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' });
    } catch { return null; }
});

const previewDaysLeft = computed(() => {
    if (!form.date) return null;
    try {
        const diff = Math.ceil((new Date(form.date).getTime() - Date.now()) / 86_400_000);
        return diff > 0 ? diff : null;
    } catch { return null; }
});

const previewRsvpDeadline = computed(() => {
    if (!form.rsvp_deadline) return null;
    try {
        return new Date(form.rsvp_deadline).toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' });
    } catch { return null; }
});

// SVG-Pfade für Tab-Bar Icons: [Pfad1, Pfad2?]
const tabDefs = [
    { label: 'Home',     paths: ['M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z', 'M9 21V12h6v9'] },
    { label: 'Zusage',   paths: ['M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z', 'M8 12l3 3 5-5'] },
    { label: 'Fotos',    paths: ['M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z', 'M12 13m-4 0a4 4 0 1 0 8 0 4 4 0 1 0-8 0'] },
    { label: 'Einst.',   paths: ['M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z', 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'] },
];
</script>

<template>
    <Head :title="t('event.settings')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="m-4 space-y-6">

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                <!-- Linke Spalte: Formular -->
                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Basis -->
                    <Card>
                        <CardHeader><CardTitle>{{ t('event.settings') }}</CardTitle></CardHeader>
                        <CardContent>
                            <p class="mb-4 text-sm text-muted-foreground">{{ t('event.settingsDesc') }}</p>
                            <div class="space-y-4">
                                <div class="grid gap-2">
                                    <Label>{{ t('event.name') }}</Label>
                                    <Input v-model="form.name" required :placeholder="t('event.name')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.date') }}</Label>
                                    <Input v-model="form.date" type="datetime-local" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.rsvpDeadline') }}</Label>
                                    <Input v-model="form.rsvp_deadline" type="datetime-local" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.venueName') }}</Label>
                                    <Input v-model="form.venue_name" :placeholder="t('event.venueName')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.venueAddress') }}</Label>
                                    <Input v-model="form.venue_address" :placeholder="t('event.venueAddress')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.dresscode') }}</Label>
                                    <textarea v-model="form.dresscode" rows="2"
                                        class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground"
                                        :placeholder="t('event.dresscode')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('event.schedule') }}</Label>
                                    <textarea v-model="form.schedule" rows="4"
                                        class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground"
                                        :placeholder="t('event.schedule')" />
                                </div>

                                <!-- Farben -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.colorAccent') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_accent"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_accent" class="font-mono uppercase" maxlength="7" placeholder="#7c2d3e" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.colorBackground') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_background"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_background" class="font-mono uppercase" maxlength="7" placeholder="#e8e3de" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.colorCard') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_card"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_card" class="font-mono uppercase" maxlength="7" placeholder="#ffffff" />
                                        </div>
                                    </div>
                                    <div v-if="displayCoverUrl" class="grid gap-2">
                                        <Label>{{ t('event.colorHomeText') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_home_text"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_home_text" class="font-mono uppercase" maxlength="7" placeholder="#ffffff" />
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ t('event.colorHint') }}</p>


                            </div>
                        </CardContent>
                    </Card>

                    <!-- Cover-Upload -->
                    <Card>
                        <CardHeader><CardTitle>{{ t('event.cover') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-sm text-muted-foreground">{{ t('event.coverHint') }}</p>

                            <!-- Gespeichertes Cover oder neue Auswahl -->
                            <div v-if="displayCoverUrl" class="flex items-center gap-3 rounded-lg border bg-muted/30 px-3 py-2">
                                <img :src="displayCoverUrl" class="h-12 w-20 shrink-0 rounded object-cover" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium">{{ coverFilename }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ coverPreview ? t('event.coverSelected') : t('event.coverCurrent') }}
                                    </p>
                                </div>
                                <Button v-if="coverPreview" variant="ghost" size="sm" class="shrink-0"
                                    @click="clearSelectedFile">
                                    {{ t('common.remove') }}
                                </Button>
                                <Button v-else variant="ghost" size="sm" class="shrink-0 text-destructive hover:text-destructive"
                                    :disabled="coverRemoving" @click="removeCover">
                                    {{ coverRemoving ? '…' : t('common.remove') }}
                                </Button>
                            </div>

                            <!-- Datei auswählen (kein sofortiger Upload) -->
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed px-4 py-3 transition-colors hover:bg-muted/40"
                                :class="{ 'opacity-50 pointer-events-none': coverConverting }">
                                <input type="file" class="hidden" accept="image/jpeg,image/png,image/heic,image/heif" @change="onFileSelect" :disabled="coverConverting" />
                                <span class="text-sm font-medium">
                                    {{ coverConverting ? t('event.coverUploading') : displayCoverUrl ? t('event.coverReplace') : t('event.coverUpload') }}
                                </span>
                            </label>
                            <p class="text-xs text-muted-foreground">{{ t('event.coverSaveHint') }}</p>
                        </CardContent>
                    </Card>

                    <Button type="submit" :disabled="form.processing" class="w-full">
                        {{ t('event.saveSettings') }}
                    </Button>
                </form>

                <!-- Rechte Spalte: 2×2 Phone-Previews -->
                <div class="flex flex-col items-center gap-3">
                    <p class="text-sm font-medium text-muted-foreground">{{ t('event.phonePreview') }}</p>

                    <div class="grid grid-cols-2 gap-4">

                    <!-- ===== SCREEN 1: HOME ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Home</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <!-- Mit Cover: Vollbild-Bild + Gradient + home_text_color -->
                            <div v-if="displayCoverUrl" class="relative flex flex-col" style="height:244px;background-size:cover;background-position:center;" :style="{ backgroundImage: `url('${displayCoverUrl}')` }">
                                <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/5 to-black/75" />
                                <div class="relative flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-white">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="relative flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                    <p class="text-center text-[9px] font-bold leading-tight" :style="{ color: form.color_home_text || '#ffffff' }">
                                        {{ form.name || 'Event-Name' }}
                                    </p>
                                    <p v-if="previewDate" class="mt-0.5 text-center text-[7px]" :style="{ color: (form.color_home_text || '#ffffff') + 'bb' }">{{ previewDate }}</p>
                                    <p v-if="form.venue_name" class="mt-0.5 text-center text-[6px]" :style="{ color: (form.color_home_text || '#ffffff') + '88' }">{{ form.venue_name }}</p>
                                    <div v-if="previewDaysLeft" class="mt-2 rounded-full px-2 py-0.5 text-[7px] font-semibold"
                                        :style="{ backgroundColor: form.color_accent || '#7c2d3e', color: form.color_home_text || '#ffffff' }">
                                        Noch {{ previewDaysLeft }} Tage
                                    </div>
                                </div>
                                <div class="relative flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-white/15 bg-black/30">
                                    <div v-for="(tab, i) in tabDefs" :key="'h'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===0 ? (form.color_home_text||'#ffffff') : 'rgba(255,255,255,0.45)'">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===0 ? (form.color_home_text||'#ffffff') : 'rgba(255,255,255,0.45)', fontWeight: i===0?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Ohne Cover: Secondary BG + Primary Text -->
                            <div v-else class="flex flex-col" style="height:244px;" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                    <p class="text-center text-[9px] font-bold leading-tight" :style="{ color: form.color_accent || '#7c2d3e' }">
                                        {{ form.name || 'Event-Name' }}
                                    </p>
                                    <p v-if="previewDate" class="mt-0.5 text-center text-[7px]" :style="{ color: form.color_accent ? form.color_accent+'99' : '#8c8880' }">{{ previewDate }}</p>
                                    <div v-if="previewDaysLeft" class="mt-2 rounded-full px-2 py-0.5 text-[7px] font-semibold text-white"
                                        :style="{ backgroundColor: form.color_accent || '#7c2d3e' }">
                                        Noch {{ previewDaysLeft }} Tage
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-black/10" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'hn'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===0 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490')">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===0 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490'), fontWeight: i===0?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 2: ZUSAGE ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Zusage</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col gap-1.5 overflow-hidden px-1.5 pt-1">
                                    <!-- Card 1: eigener Gast -->
                                    <div class="rounded-lg p-1.5 shadow-sm" :style="{ backgroundColor: form.color_card || '#ffffff' }">
                                        <p class="mb-0.5 text-[5px]" :style="{ color: form.color_accent ? form.color_accent+'77' : '#8c8880' }">Bitte antworte bis 25. März.</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[7px] font-semibold" :style="{ color: form.color_accent || '#7c2d3e' }">Max Mustermann</span>
                                            <span class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white" style="background-color:#888888;">Zugesagt</span>
                                        </div>
                                        <div class="mt-1 flex gap-0.5">
                                            <div class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white" style="background-color:#4a7c59;">Zusagen</div>
                                            <div class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white" style="background-color:#b45a3c;">Absagen</div>
                                        </div>
                                    </div>
                                    <!-- Card 2: Gruppe -->
                                    <div class="rounded-lg p-1.5 shadow-sm" :style="{ backgroundColor: form.color_card || '#ffffff' }">
                                        <p class="text-[7px] font-semibold" :style="{ color: form.color_accent || '#7c2d3e' }">Deine Gruppe</p>
                                        <p class="mb-1 text-[5px]" :style="{ color: form.color_accent ? form.color_accent+'77' : '#8c8880' }">Du kannst für deine Gruppe antworten.</p>
                                        <div v-for="(member, mi) in [{name:'Anna M.',status:'Zugesagt'},{name:'Klaus M.',status:'Zugesagt'},{name:'Lisa M.',status:'Abgesagt',red:true}]" :key="mi"
                                            class="border-t py-0.5 first:border-t-0" style="border-color:rgba(0,0,0,0.08)">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[6px] font-semibold" :style="{ color: form.color_accent || '#7c2d3e' }">{{ member.name }}</span>
                                                <div class="flex items-center gap-0.5">
                                                    <span class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white" :style="{ backgroundColor: member.red ? '#b45a3c' : '#888888' }">{{ member.status }}</span>
                                                    <span class="text-[6px]" :style="{ color: form.color_accent ? form.color_accent+'88' : '#aaa' }">▼</span>
                                                </div>
                                            </div>
                                            <p class="text-[4px]" :style="{ color: form.color_accent ? form.color_accent+'66' : '#aaa' }">Von dir gesetzt</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-black/10" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'z'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===1 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490')">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===1 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490'), fontWeight: i===1?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 3: FOTOS ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Fotos</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="relative flex-1 px-0.5 pt-1">
                                    <div class="grid grid-cols-3 gap-0.5">
                                        <div v-for="n in 6" :key="n" class="rounded-sm" style="background-color:#d4cfc8;aspect-ratio:1;" />
                                    </div>
                                    <div class="absolute bottom-3 right-2 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                        :style="{ backgroundColor: form.color_accent || '#7c2d3e' }">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-black/10" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'f'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===2 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490')">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===2 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490'), fontWeight: i===2?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 4: EINSTELLUNGEN ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Einstellungen</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center px-2">
                                    <!-- Eine Card mit allem drin -->
                                    <div class="w-full rounded-xl shadow-sm" :style="{ backgroundColor: form.color_card || '#ffffff' }">
                                        <!-- User-Info -->
                                        <div class="px-2 pt-2 pb-1.5">
                                            <p class="text-[5px]" :style="{ color: form.color_accent ? form.color_accent+'88' : '#9e9490' }">Eingeloggt als</p>
                                            <p class="text-[7px] font-semibold" :style="{ color: form.color_accent || '#7c2d3e' }">Max Mustermann</p>
                                            <p class="text-[5px]" :style="{ color: form.color_accent ? form.color_accent+'88' : '#9e9490' }">Familie Mustermann</p>
                                        </div>
                                        <!-- Divider -->
                                        <div class="border-t mx-2" style="border-color:rgba(0,0,0,0.08);"></div>
                                        <!-- Sprache -->
                                        <div class="px-2 py-1.5">
                                            <p class="mb-1 text-[5px]" :style="{ color: form.color_accent ? form.color_accent+'88' : '#9e9490' }">Sprache</p>
                                            <div class="flex gap-1">
                                                <div class="flex-1 rounded-lg py-0.5 text-center text-[5px] font-semibold text-white" :style="{ backgroundColor: form.color_accent || '#7c2d3e' }">Deutsch</div>
                                                <div class="flex-1 rounded-lg border py-0.5 text-center text-[5px]" style="border-color:rgba(0,0,0,0.15);color:#888;">Englisch</div>
                                            </div>
                                        </div>
                                        <!-- Divider -->
                                        <div class="border-t mx-2" style="border-color:rgba(0,0,0,0.08);"></div>
                                        <!-- Ausloggen innerhalb der Card -->
                                        <div class="mx-2 my-1.5 rounded-lg py-1 text-center text-[6px] font-semibold text-white" style="background-color:#b45a3c;">
                                            Ausloggen
                                        </div>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-black/10" :style="{ backgroundColor: form.color_background || '#e8e3de' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'e'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===3 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490')">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===3 ? (form.color_accent||'#7c2d3e') : (form.color_accent ? form.color_accent+'55' : '#9e9490'), fontWeight: i===3?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div><!-- /grid -->

                </div>

            </div><!-- /lg:grid-cols-2 -->
        </div>
    </AppLayout>
</template>
