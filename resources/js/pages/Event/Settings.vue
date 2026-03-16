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

                    <!-- Phone-Rahmen -->
                    <div class="relative mx-auto w-[260px] overflow-hidden rounded-[36px] border-[8px] shadow-2xl"
                        :style="{ borderColor: form.color_primary || '#7c2d3e' }">

                        <!-- Screen -->
                        <div class="relative flex h-[564px] w-full flex-col overflow-hidden">

                            <!-- Cover-Bild als Vollbild-Hintergrund -->
                            <img v-if="coverUrl" :src="coverUrl" alt="Cover"
                                class="absolute inset-0 h-full w-full object-cover" />
                            <!-- Fallback: Primary-Color -->
                            <div v-else class="absolute inset-0"
                                :style="{ backgroundColor: form.color_primary || '#7c2d3e' }" />

                            <!-- Gradient: oben für Status-Bar, unten für Content -->
                            <div class="absolute inset-0 bg-gradient-to-b from-black/45 via-black/5 to-black/82" />

                            <!-- Status Bar -->
                            <div class="relative z-10 flex items-center justify-between px-5 pt-3 text-[11px] font-medium text-white">
                                <span>9:41</span>
                                <span class="opacity-80 tracking-widest text-[8px]">● ● ●</span>
                            </div>

                            <!-- Haupt-Content -->
                            <div class="relative z-10 flex flex-1 flex-col justify-end px-5 pb-3 text-white">

                                <!-- Welcome-Label in Sekundärfarbe -->
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em]"
                                    :style="{ color: form.color_secondary || '#c49a6c' }">
                                    Herzlich Willkommen
                                </p>

                                <!-- Event-Name -->
                                <h2 class="mt-1 text-[20px] font-bold leading-tight">
                                    {{ form.name || 'Event-Name' }}
                                </h2>

                                <!-- Datum -->
                                <p v-if="previewDate" class="mt-1 text-[12px] opacity-80">
                                    {{ previewDate }}
                                </p>

                                <!-- Veranstaltungsort -->
                                <p v-if="form.venue_name" class="mt-0.5 text-[11px] opacity-60">
                                    {{ form.venue_name }}
                                </p>

                                <!-- Countdown-Badge -->
                                <div v-if="previewDaysLeft" class="mt-3 inline-flex w-fit items-center rounded-full px-3 py-1 text-[11px] font-semibold text-white"
                                    :style="{ backgroundColor: form.color_secondary || '#c49a6c' }">
                                    Noch {{ previewDaysLeft }} Tage
                                </div>
                            </div>

                            <!-- Tab Bar (wie in der echten App) -->
                            <div class="relative z-10 flex h-[52px] w-full items-center justify-around border-t border-white/15 bg-black/40">
                                <div v-for="tab in [
                                    { label: 'Home', active: true },
                                    { label: 'Zusage', active: false },
                                    { label: 'Fotos', active: false },
                                    { label: 'Einst.', active: false },
                                ]" :key="tab.label" class="flex flex-col items-center gap-0.5 px-2">
                                    <div class="h-0.5 w-4 rounded-full mb-0.5"
                                        :style="{ backgroundColor: tab.active ? (form.color_secondary || '#c49a6c') : 'transparent' }" />
                                    <span class="text-[9px] font-medium"
                                        :style="{ color: tab.active ? (form.color_secondary || '#c49a6c') : 'rgba(255,255,255,0.5)' }">
                                        {{ tab.label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
