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
    color_primary: string | null;
    color_secondary: string | null;
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
    color_primary:   props.event.color_primary ?? '#7c2d3e',
    color_secondary: props.event.color_secondary ?? '#c49a6c',
});

function submit() {
    form.post(route('event.settings.update'), {
        onSuccess: () => toast.success(t('toast.eventSettingsSaved')),
    });
}

// Cover-Upload
const coverUrl       = ref<string | null>(props.event.cover_image_url);
const coverUploading = ref(false);

async function onCoverChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    coverUploading.value = true;
    try {
        const fd = new FormData();
        fd.append('cover', file);
        const res = await axios.post(route('event.settings.cover'), fd);
        coverUrl.value = res.data.cover_image_url;
        toast.success(t('toast.coverUploaded'));
    } catch {
        toast.error(t('toast.coverError'));
    } finally {
        coverUploading.value = false;
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
</script>

<template>
    <Head :title="t('event.settings')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="m-4 space-y-6">

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                <!-- Linke Spalte: Formular -->
                <div class="space-y-4">

                    <!-- Basis -->
                    <Card>
                        <CardHeader><CardTitle>{{ t('event.settings') }}</CardTitle></CardHeader>
                        <CardContent>
                            <p class="mb-4 text-sm text-muted-foreground">{{ t('event.settingsDesc') }}</p>
                            <form @submit.prevent="submit" class="space-y-4">
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
                                        <Label>{{ t('event.colorPrimary') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_primary"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_primary" class="font-mono uppercase" maxlength="7" placeholder="#7c2d3e" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.colorSecondary') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="form.color_secondary"
                                                class="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                                            <Input v-model="form.color_secondary" class="font-mono uppercase" maxlength="7" placeholder="#c49a6c" />
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ t('event.colorHint') }}</p>

                                <Button type="submit" :disabled="form.processing" class="w-full">
                                    {{ t('event.saveSettings') }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Cover-Upload -->
                    <Card>
                        <CardHeader><CardTitle>{{ t('event.cover') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-sm text-muted-foreground">{{ t('event.coverHint') }}</p>
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed px-4 py-3 transition-colors hover:bg-muted/40">
                                <input type="file" class="hidden" accept="image/jpeg,image/png,image/heic,image/heif" @change="onCoverChange" />
                                <span class="text-sm font-medium">
                                    {{ coverUploading ? t('event.coverUploading') : t('event.coverUpload') }}
                                </span>
                            </label>
                        </CardContent>
                    </Card>
                </div>

                <!-- Rechte Spalte: Phone-Preview -->
                <div class="flex flex-col items-center gap-3">
                    <p class="text-sm font-medium text-muted-foreground">{{ t('event.phonePreview') }}</p>

                    <!-- Phone-Rahmen: dunkel wie echtes Gerät -->
                    <div class="relative mx-auto w-[260px] overflow-hidden rounded-[36px] border-[10px] border-gray-800 shadow-2xl">

                        <!-- Screen: warmes Beige wie aktuelle App -->
                        <div class="flex h-[540px] w-full flex-col" style="background-color: #e8e3de;">

                            <!-- Status Bar (dunkle Icons auf hellem BG) -->
                            <div class="flex items-center justify-between px-4 pt-2.5 text-[11px] font-semibold text-gray-900">
                                <span>9:41</span>
                                <div class="flex items-center gap-1 text-[10px]">
                                    <span>▲▲▲</span>
                                    <span>⬛</span>
                                </div>
                            </div>

                            <!-- Content: zentriert wie im aktuellen Home-Screen -->
                            <div class="flex flex-1 flex-col items-center justify-center px-5 pb-2">

                                <!-- Cover als Karte (falls vorhanden) -->
                                <div v-if="coverUrl"
                                    class="mb-5 w-full overflow-hidden rounded-2xl shadow"
                                    style="height: 110px;">
                                    <img :src="coverUrl" alt="Cover" class="h-full w-full object-cover" />
                                </div>

                                <!-- Event-Name in Primary-Farbe (wie "Willkommen, Sarah!") -->
                                <h2 class="text-center text-[18px] font-bold leading-snug"
                                    :style="{ color: form.color_primary || '#7c2d3e' }">
                                    {{ form.name || 'Event-Name' }}
                                </h2>

                                <!-- Datum -->
                                <p v-if="previewDate" class="mt-2 text-center text-[12px]" style="color: #8c8880;">
                                    {{ previewDate }}
                                </p>

                                <!-- Veranstaltungsort -->
                                <p v-if="form.venue_name" class="mt-1 text-center text-[11px]" style="color: #a09890;">
                                    {{ form.venue_name }}
                                </p>

                                <!-- Countdown-Pill in Primary -->
                                <div v-if="previewDaysLeft"
                                    class="mt-4 rounded-full px-4 py-1.5 text-[11px] font-semibold text-white"
                                    :style="{ backgroundColor: form.color_primary || '#7c2d3e' }">
                                    Noch {{ previewDaysLeft }} Tage
                                </div>
                            </div>

                            <!-- Tab Bar: gleicher Beige-BG + dünner Top-Border wie App -->
                            <div class="flex h-[58px] w-full items-center justify-around border-t px-1"
                                style="background-color: #e8e3de; border-color: rgba(0,0,0,0.1);">

                                <!-- Home (aktiv) -->
                                <div class="flex flex-col items-center gap-0.5">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                                        :stroke="form.color_primary || '#7c2d3e'">
                                        <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z"/>
                                        <path d="M9 21V12h6v9"/>
                                    </svg>
                                    <span class="text-[9px] font-semibold" :style="{ color: form.color_primary || '#7c2d3e' }">Home</span>
                                </div>

                                <!-- Zusage -->
                                <div class="flex flex-col items-center gap-0.5">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9e9490" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M8.5 12.5l2.5 2.5 4.5-5"/>
                                    </svg>
                                    <span class="text-[9px]" style="color: #9e9490;">Zusage</span>
                                </div>

                                <!-- Fotos -->
                                <div class="flex flex-col items-center gap-0.5">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9e9490" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                                        <circle cx="8.5" cy="10.5" r="1.5"/>
                                        <path d="M21 15l-5-5L5 19"/>
                                    </svg>
                                    <span class="text-[9px]" style="color: #9e9490;">Fotos</span>
                                </div>

                                <!-- Einstellungen -->
                                <div class="flex flex-col items-center gap-0.5">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9e9490" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                    </svg>
                                    <span class="text-[9px]" style="color: #9e9490;">Einstellungen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
