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

const previewRsvpDeadline = computed(() => {
    if (!form.rsvp_deadline) return null;
    try {
        return new Date(form.rsvp_deadline).toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' });
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

                <!-- Rechte Spalte: 2×2 Phone-Previews -->
                <div class="flex flex-col items-center gap-3">
                    <p class="text-sm font-medium text-muted-foreground">{{ t('event.phonePreview') }}</p>

                    <div class="grid grid-cols-2 gap-4">

                    <!-- ===== SCREEN 1: HOME ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Home</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;background-color:#e8e3de;">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                    <div v-if="coverUrl" class="mb-2 w-full overflow-hidden rounded-xl" style="height:50px;">
                                        <img :src="coverUrl" class="h-full w-full object-cover" />
                                    </div>
                                    <p class="text-center text-[9px] font-bold leading-tight" :style="{ color: form.color_primary || '#7c2d3e' }">
                                        {{ form.name || 'Event-Name' }}
                                    </p>
                                    <p v-if="previewDate" class="mt-0.5 text-center text-[7px]" style="color:#8c8880;">{{ previewDate }}</p>
                                    <p v-if="form.venue_name" class="mt-0.5 text-center text-[6px]" style="color:#a09890;">{{ form.venue_name }}</p>
                                    <div v-if="previewDaysLeft" class="mt-2 rounded-full px-2 py-0.5 text-[7px] font-semibold text-white"
                                        :style="{ backgroundColor: form.color_primary || '#7c2d3e' }">
                                        Noch {{ previewDaysLeft }} Tage
                                    </div>
                                </div>
                                <div class="flex h-[24px] w-full items-center justify-around border-t" style="background-color:#e8e3de;border-color:rgba(0,0,0,0.1);">
                                    <span v-for="(tab,i) in ['Home','Zusage','Fotos','Einst.']" :key="'h'+i" class="text-[6px]"
                                        :style="{ color: i===0 ? (form.color_primary||'#7c2d3e') : '#9e9490', fontWeight: i===0 ? '700':'400' }">{{ tab }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 2: ZUSAGE ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Zusage</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;background-color:#e8e3de;">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col gap-2 px-2 pt-2">
                                    <p v-if="previewRsvpDeadline" class="text-[6px]" style="color:#8c8880;">Bitte antworte bis {{ previewRsvpDeadline }}.</p>
                                    <div class="rounded-xl bg-white p-2 shadow-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[8px] font-semibold" :style="{ color: form.color_primary || '#7c2d3e' }">Max Mustermann</span>
                                            <span class="rounded-full px-1.5 py-0.5 text-[5px] font-semibold text-white" style="background-color:#4a7c59;">Zusagen</span>
                                        </div>
                                        <div class="mt-1.5 flex gap-1">
                                            <div class="flex-1 rounded-lg py-1 text-center text-[6px] font-semibold text-white" style="background-color:#4a7c59;">Zusagen</div>
                                            <div class="flex-1 rounded-lg border py-1 text-center text-[6px]" style="color:#666;border-color:#ccc;">Absagen</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex h-[24px] w-full items-center justify-around border-t" style="background-color:#e8e3de;border-color:rgba(0,0,0,0.1);">
                                    <span v-for="(tab,i) in ['Home','Zusage','Fotos','Einst.']" :key="'z'+i" class="text-[6px]"
                                        :style="{ color: i===1 ? (form.color_primary||'#7c2d3e') : '#9e9490', fontWeight: i===1 ? '700':'400' }">{{ tab }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 3: FOTOS ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Fotos</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;background-color:#e8e3de;">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="relative flex-1 px-0.5 pt-1">
                                    <div class="grid grid-cols-3 gap-0.5">
                                        <div v-for="n in 6" :key="n" class="rounded-sm" style="background-color:#d4cfc8;aspect-ratio:1;" />
                                    </div>
                                    <div class="absolute bottom-3 right-2 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                        :style="{ backgroundColor: form.color_secondary || '#c49a6c' }">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex h-[24px] w-full items-center justify-around border-t" style="background-color:#e8e3de;border-color:rgba(0,0,0,0.1);">
                                    <span v-for="(tab,i) in ['Home','Zusage','Fotos','Einst.']" :key="'f'+i" class="text-[6px]"
                                        :style="{ color: i===2 ? (form.color_primary||'#7c2d3e') : '#9e9490', fontWeight: i===2 ? '700':'400' }">{{ tab }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SCREEN 4: EINSTELLUNGEN ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Einstellungen</span>
                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;background-color:#e8e3de;">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center gap-2 px-2">
                                    <div class="w-full rounded-xl bg-white p-2 shadow-sm">
                                        <p class="text-[6px]" style="color:#9e9490;">Eingeloggt als</p>
                                        <p class="text-[8px] font-semibold" :style="{ color: form.color_primary || '#7c2d3e' }">Max Mustermann</p>
                                    </div>
                                    <div class="w-full rounded-xl bg-white p-2 shadow-sm">
                                        <p class="mb-1 text-[6px]" style="color:#9e9490;">Sprache</p>
                                        <div class="flex gap-1">
                                            <div class="flex-1 rounded-lg py-0.5 text-center text-[6px] font-semibold text-white" :style="{ backgroundColor: form.color_primary || '#7c2d3e' }">Deutsch</div>
                                            <div class="flex-1 rounded-lg py-0.5 text-center text-[6px]" style="background-color:#e0dbd4;color:#666;">Englisch</div>
                                        </div>
                                    </div>
                                    <div class="w-full rounded-xl border py-1 text-center text-[7px] font-medium"
                                        :style="{ borderColor: form.color_primary || '#7c2d3e', color: form.color_primary || '#7c2d3e' }">
                                        Ausloggen
                                    </div>
                                </div>
                                <div class="flex h-[24px] w-full items-center justify-around border-t" style="background-color:#e8e3de;border-color:rgba(0,0,0,0.1);">
                                    <span v-for="(tab,i) in ['Home','Zusage','Fotos','Einst.']" :key="'e'+i" class="text-[6px]"
                                        :style="{ color: i===3 ? (form.color_primary||'#7c2d3e') : '#9e9490', fontWeight: i===3 ? '700':'400' }">{{ tab }}</span>
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
