<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useFloatingBar } from '@/composables/useFloatingBar';
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
    color_primary: string | null;
    color_secondary: string | null;
    color_tertiary: string | null;
    color_home_text: string | null;
    role_screen_bg: string | null;
    role_card_bg: string | null;
    role_card_text: string | null;
    role_card_button: string | null;
    role_card_button_text: string | null;
    role_tab_tint: string | null;
    role_border: string | null;
    role_fab: string | null;
    font_heading: string | null;
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
    // Palette
    color_primary:   props.event.color_primary   ?? '#7c2d3e',
    color_secondary: props.event.color_secondary ?? '#e8e3de',
    color_tertiary:  props.event.color_tertiary  ?? '#ffffff',
    color_home_text: props.event.color_home_text ?? '#ffffff',
    // Rollen
    role_screen_bg:        props.event.role_screen_bg        ?? 'secondary',
    role_card_bg:          props.event.role_card_bg          ?? 'tertiary',
    role_card_text:        props.event.role_card_text        ?? 'primary',
    role_card_button:      props.event.role_card_button      ?? 'primary',
    role_card_button_text: props.event.role_card_button_text ?? 'tertiary',
    role_tab_tint:         props.event.role_tab_tint         ?? 'primary',
    role_border:           props.event.role_border           ?? 'primary',
    role_fab:              props.event.role_fab              ?? 'primary',
    font_heading:    props.event.font_heading ?? '',
    cover: null as File | null,
});

const skipGuard = ref(false);
const isDirty   = computed(() => form.isDirty);

const { active: floatingBarActive } = useFloatingBar();
watch(isDirty, val => { floatingBarActive.value = val; }, { immediate: true });

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value && !skipGuard.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

let removeInertiaGuard: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard = router.on('before', (event) => {
        if (isDirty.value && !skipGuard.value) {
            const confirmed = window.confirm(t('drink.unsavedChangesPrompt'));
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard?.();
    floatingBarActive.value = false;
});

function discard() {
    form.reset();
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = null;
}

function submit() {
    skipGuard.value = true;
    form.post(route('event.settings.update'), {
        onSuccess: () => {
            toast.success(t('toast.eventSettingsSaved'));
            coverPreview.value = null;
            form.cover = null;
        },
        onFinish: () => { skipGuard.value = false; },
    });
}

// Cover
const coverUrl      = ref<string | null>(props.event.cover_image_url);
const coverPreview  = ref<string | null>(null);
const coverRemoving = ref(false);

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

// Palette + Rollen-Auflösung
const palette = computed(() => ({
    primary:   form.color_primary   || '#7c2d3e',
    secondary: form.color_secondary || '#e8e3de',
    tertiary:  form.color_tertiary  || '#ffffff',
}));

type PaletteKey = 'primary' | 'secondary' | 'tertiary';
const resolve = (role: string | null, fallback: PaletteKey): string =>
    palette.value[(role as PaletteKey) ?? fallback] ?? palette.value[fallback];

const cScreenBg       = computed(() => resolve(form.role_screen_bg,        'secondary'));
const cCardBg         = computed(() => resolve(form.role_card_bg,          'tertiary'));
const cCardText       = computed(() => resolve(form.role_card_text,        'primary'));
const cCardButton     = computed(() => resolve(form.role_card_button,      'primary'));
const cCardButtonText = computed(() => resolve(form.role_card_button_text, 'tertiary'));
const cTabTint        = computed(() => resolve(form.role_tab_tint,         'primary'));
const cBorder         = computed(() => resolve(form.role_border,           'primary'));
const cFab            = computed(() => resolve(form.role_fab,              'primary'));

// Optionen für Radio-Selektoren
const colorOptions = computed(() => [
    { key: 'primary',   label: t('event.colorPrimaryLabel'),   value: palette.value.primary },
    { key: 'secondary', label: t('event.colorSecondaryLabel'), value: palette.value.secondary },
    { key: 'tertiary',  label: t('event.colorTertiaryLabel'),  value: palette.value.tertiary },
]);

// Font
const fontOptions = [
    { key: 'playfair',    label: 'Playfair Display',  family: 'Playfair Display' },
    { key: 'cormorant',   label: 'Cormorant Garamond', family: 'Cormorant Garamond' },
    { key: 'cinzel',      label: 'Cinzel',             family: 'Cinzel' },
    { key: 'dancing',     label: 'Dancing Script',     family: 'Dancing Script' },
    { key: 'great_vibes', label: 'Great Vibes',        family: 'Great Vibes' },
    { key: 'raleway',     label: 'Raleway',            family: 'Raleway' },
    { key: 'lora',        label: 'Lora',               family: 'Lora' },
    { key: 'josefin',     label: 'Josefin Sans',       family: 'Josefin Sans' },
];
const previewFontFamily = computed(() =>
    fontOptions.find(f => f.key === form.font_heading)?.family ?? 'inherit'
);

// Tab-Bar Icons
const tabDefs = [
    { label: 'Home',    paths: ['M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z', 'M9 21V12h6v9'] },
    { label: 'Zusage',  paths: ['M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z', 'M8 12l3 3 5-5'] },
    { label: 'Fotos',   paths: ['M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z', 'M12 13m-4 0a4 4 0 1 0 8 0 4 4 0 1 0-8 0'] },
    { label: 'Spiel',   paths: ['M6 2h12l-2 18a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1L6 2z', 'M18 7h2.5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H18'] },
    { label: 'Einst.',  paths: ['M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z', 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'] },
];

// Hilfsfunktion für Radio-Selektor-Klasse
const radioClass = (formRole: string | null, optKey: string, fallback: PaletteKey) =>
    (formRole ?? fallback) === optKey ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground';
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

                                <!-- Schrift -->
                                <div class="grid gap-2">
                                    <Label>{{ t('event.fontHeading') }}</Label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button type="button" @click="form.font_heading = ''"
                                            class="rounded-lg border-2 px-2 py-2 text-xs transition-colors text-center"
                                            :class="!form.font_heading ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground'">
                                            {{ t('event.fontSystemDefault') }}
                                        </button>
                                        <button v-for="font in fontOptions" :key="font.key" type="button"
                                            @click="form.font_heading = font.key"
                                            class="rounded-lg border-2 px-2 py-2.5 text-sm transition-colors text-center leading-tight"
                                            :class="form.font_heading === font.key ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground'"
                                            :style="{ fontFamily: font.family }">
                                            {{ font.label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Farb-Palette -->
                                <div class="grid gap-3">
                                    <Label>{{ t('event.colorHint') }}</Label>
                                    <!-- 3 Basis-Picker -->
                                    <div class="grid grid-cols-3 gap-3">
                                        <div v-for="(key, idx) in (['color_primary', 'color_secondary', 'color_tertiary'] as const)" :key="key" class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ [t('event.colorPrimary'), t('event.colorSecondary'), t('event.colorTertiary')][idx] }}</span>
                                            <div class="flex items-center gap-1.5">
                                                <input type="color" v-model="form[key]"
                                                    class="h-9 w-10 cursor-pointer rounded border border-input bg-transparent p-0.5 shrink-0" />
                                                <Input v-model="form[key]" class="font-mono uppercase text-xs px-2" maxlength="7" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 8 Radio-Selektoren -->
                                    <div class="space-y-3 pt-1">
                                        <!-- Screen-Hintergrund -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleScreenBg') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'sb'+opt.key" type="button"
                                                    @click="form.role_screen_bg = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_screen_bg, opt.key, 'secondary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Card-Hintergrund -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleCardBg') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'cb'+opt.key" type="button"
                                                    @click="form.role_card_bg = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_card_bg, opt.key, 'tertiary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Text auf Cards -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleCardText') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'ct'+opt.key" type="button"
                                                    @click="form.role_card_text = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_card_text, opt.key, 'primary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Button auf Cards -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleCardButton') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'cbt'+opt.key" type="button"
                                                    @click="form.role_card_button = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_card_button, opt.key, 'primary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Text auf Card-Buttons -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleCardButtonText') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'cbtx'+opt.key" type="button"
                                                    @click="form.role_card_button_text = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_card_button_text, opt.key, 'tertiary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Navbar-Farbe -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleTabTint') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'tt'+opt.key" type="button"
                                                    @click="form.role_tab_tint = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_tab_tint, opt.key, 'primary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Rahmenfarbe -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleBorder') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'br'+opt.key" type="button"
                                                    @click="form.role_border = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_border, opt.key, 'primary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- FAB-Button -->
                                        <div class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.roleFab') }}</span>
                                            <div class="flex gap-2">
                                                <button v-for="opt in colorOptions" :key="'fab'+opt.key" type="button"
                                                    @click="form.role_fab = opt.key"
                                                    class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                    :class="radioClass(form.role_fab, opt.key, 'primary')">
                                                    <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                                                    <span>{{ opt.label }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Home-Screen Farbe — nur wenn Cover vorhanden -->
                                        <div v-if="displayCoverUrl" class="grid gap-1.5">
                                            <span class="text-xs text-muted-foreground">{{ t('event.colorHomeText') }}</span>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="form.color_home_text"
                                                    class="h-9 w-10 cursor-pointer rounded border border-input bg-transparent p-0.5 shrink-0" />
                                                <Input v-model="form.color_home_text" class="font-mono uppercase text-xs px-2" maxlength="7" placeholder="#ffffff" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </CardContent>
                    </Card>

                    <!-- Cover-Upload -->
                    <Card>
                        <CardHeader><CardTitle>{{ t('event.cover') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-sm text-muted-foreground">{{ t('event.coverHint') }}</p>

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

                </form>

                <!-- Rechte Spalte: Phone-Previews -->
                <div class="flex flex-col items-center gap-3">
                    <p class="text-sm font-medium text-muted-foreground">{{ t('event.phonePreview') }}</p>

                    <div class="grid grid-cols-2 gap-4">

                    <!-- ===== SCREEN 1: HOME ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Home</span>
                        <div style="width:168px;height:342px;overflow:hidden;flex-shrink:0;"><div style="transform:scale(1.4);transform-origin:top left;"><div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <!-- Mit Cover -->
                            <div v-if="displayCoverUrl" class="relative flex flex-col" style="height:244px;background-size:cover;background-position:center;" :style="{ backgroundImage: `url('${displayCoverUrl}')` }">
                                <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/5 to-black/75" />
                                <div class="relative flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-white">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="relative flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                    <p class="text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">Willkommen, Gast!</p>
                                    <p class="mt-0.5 text-center text-[9px] font-bold leading-tight" :style="{ color: form.color_home_text || '#ffffff', fontFamily: previewFontFamily }">
                                        {{ form.name || 'Event-Name' }}
                                    </p>
                                    <p class="mt-0.5 text-center text-[7px]" :style="{ color: form.color_home_text || '#ffffff' }">{{ previewDate || 'Sa., 1. Januar 2026' }}</p>
                                    <p class="mt-0.5 text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">{{ form.venue_name || 'Musterort' }}</p>
                                    <p class="text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">{{ form.venue_address || 'Musterstraße 1' }}</p>
                                    <p class="mt-1.5 text-center text-[6px] font-bold" :style="{ color: form.color_home_text || '#ffffff' }">
                                        Noch {{ previewDaysLeft ? previewDaysLeft + 'T' : '6T 11Std 22Min' }}
                                    </p>
                                </div>
                                <div class="relative flex h-[26px] w-full items-end justify-around pb-1.5 border-t border-white/15 bg-black/30">
                                    <div v-for="(tab, i) in tabDefs" :key="'h'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===0 ? (form.color_home_text||'#ffffff') : (form.color_home_text ? form.color_home_text+'77' : 'rgba(255,255,255,0.45)')">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===0 ? (form.color_home_text||'#ffffff') : (form.color_home_text ? form.color_home_text+'77' : 'rgba(255,255,255,0.45)'), fontWeight: i===0?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Ohne Cover: normale App-Farben -->
                            <div v-else class="flex flex-col" style="height:244px;" :style="{ backgroundColor: cScreenBg }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                    <p class="text-center text-[6px]" :style="{ color: cCardText }">Willkommen, Gast!</p>
                                    <p class="mt-0.5 text-center text-[9px] font-bold leading-tight" :style="{ color: cCardText, fontFamily: previewFontFamily }">
                                        {{ form.name || 'Event-Name' }}
                                    </p>
                                    <p class="mt-0.5 text-center text-[7px]" :style="{ color: cCardText }">{{ previewDate || 'Sa., 1. Januar 2026' }}</p>
                                    <p class="mt-0.5 text-center text-[6px]" :style="{ color: cCardText }">{{ form.venue_name || 'Musterort' }}</p>
                                    <p class="text-center text-[6px]" :style="{ color: cCardText }">{{ form.venue_address || 'Musterstraße 1' }}</p>
                                    <p class="mt-1.5 text-center text-[6px] font-bold" :style="{ color: cCardText }">
                                        Noch {{ previewDaysLeft ? previewDaysLeft + 'T' : '6T 11Std 22Min' }}
                                    </p>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t" :style="{ backgroundColor: cScreenBg, borderColor: cBorder+'33' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'hn'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===0 ? cTabTint : cTabTint+'55'">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===0 ? cTabTint : cTabTint+'55', fontWeight: i===0?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div></div></div>
                    </div>

                    <!-- ===== SCREEN 2: ZUSAGE ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Zusage</span>
                        <div style="width:168px;height:342px;overflow:hidden;flex-shrink:0;"><div style="transform:scale(1.4);transform-origin:top left;"><div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: cScreenBg }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col gap-1.5 overflow-hidden px-1.5 pt-1">
                                    <!-- Card 1 -->
                                    <div class="rounded-lg p-1.5 shadow-sm" :style="{ backgroundColor: cCardBg, borderWidth: '1px', borderStyle: 'solid', borderColor: cBorder+'33' }">
                                        <p class="mb-0.5 text-[5px]" :style="{ color: cCardText+'77' }">Bitte antworte bis 25. März.</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[7px] font-semibold" :style="{ color: cCardText }">Max Mustermann</span>
                                            <span class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white" style="background-color:#888888;">Zugesagt</span>
                                        </div>
                                        <div class="mt-1 flex gap-0.5">
                                            <div class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white" style="background-color:#4a7c59;">Zusagen</div>
                                            <div class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white" style="background-color:#b45a3c;">Absagen</div>
                                        </div>
                                    </div>
                                    <!-- Card 2 -->
                                    <div class="rounded-lg p-1.5 shadow-sm" :style="{ backgroundColor: cCardBg, borderWidth: '1px', borderStyle: 'solid', borderColor: cBorder+'33' }">
                                        <p class="text-[7px] font-semibold" :style="{ color: cCardText }">Deine Gruppe</p>
                                        <p class="mb-1 text-[5px]" :style="{ color: cCardText+'77' }">Du kannst für deine Gruppe antworten.</p>
                                        <div v-for="(member, mi) in [{name:'Anna M.',status:'Zugesagt'},{name:'Klaus M.',status:'Zugesagt'},{name:'Lisa M.',status:'Abgesagt',red:true}]" :key="mi"
                                            class="border-t py-0.5 first:border-t-0" :style="{ borderColor: cBorder+'22' }">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[6px] font-semibold" :style="{ color: cCardText }">{{ member.name }}</span>
                                                <div class="flex items-center gap-0.5">
                                                    <span class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white" :style="{ backgroundColor: member.red ? '#b45a3c' : '#888888' }">{{ member.status }}</span>
                                                    <span class="text-[6px]" :style="{ color: cCardText+'88' }">▼</span>
                                                </div>
                                            </div>
                                            <p class="text-[4px]" :style="{ color: cCardText+'66' }">Von dir gesetzt</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t" :style="{ backgroundColor: cScreenBg, borderColor: cBorder+'33' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'z'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===1 ? cTabTint : cTabTint+'55'">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===1 ? cTabTint : cTabTint+'55', fontWeight: i===1?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div></div></div>
                    </div>

                    <!-- ===== SCREEN 3: FOTOS ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Fotos</span>
                        <div style="width:168px;height:342px;overflow:hidden;flex-shrink:0;"><div style="transform:scale(1.4);transform-origin:top left;"><div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: cScreenBg }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="relative flex-1 px-0.5 pt-1">
                                    <div class="grid grid-cols-3 gap-0.5">
                                        <div v-for="n in 6" :key="n" class="rounded-sm" style="background-color:#d4cfc8;aspect-ratio:1;" />
                                    </div>
                                    <!-- FAB mit cFab-Farbe -->
                                    <div class="absolute bottom-3 right-2 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                        :style="{ backgroundColor: cFab }">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" :stroke="cCardButtonText" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t" :style="{ backgroundColor: cScreenBg, borderColor: cBorder+'33' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'f'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===2 ? cTabTint : cTabTint+'55'">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===2 ? cTabTint : cTabTint+'55', fontWeight: i===2?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div></div></div>
                    </div>

                    <!-- ===== SCREEN 4: EINSTELLUNGEN ===== -->
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground">Einstellungen</span>
                        <div style="width:168px;height:342px;overflow:hidden;flex-shrink:0;"><div style="transform:scale(1.4);transform-origin:top left;"><div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width:120px;">
                            <div class="flex flex-col" style="height:244px;" :style="{ backgroundColor: cScreenBg }">
                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                    <span>9:41</span><span style="font-size:6px;">▲▲ ▐</span>
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center px-2">
                                    <div class="w-full rounded-xl shadow-sm" :style="{ backgroundColor: cCardBg, borderWidth: '1px', borderStyle: 'solid', borderColor: cBorder+'33' }">
                                        <div class="px-2 pt-2 pb-1.5">
                                            <p class="text-[5px]" :style="{ color: cCardText+'88' }">Eingeloggt als</p>
                                            <p class="text-[7px] font-semibold" :style="{ color: cCardText }">Max Mustermann</p>
                                            <p class="text-[5px]" :style="{ color: cCardText+'88' }">Familie Mustermann</p>
                                        </div>
                                        <div class="border-t mx-2" :style="{ borderColor: cBorder+'33' }"></div>
                                        <div class="px-2 py-1.5">
                                            <p class="mb-1 text-[5px]" :style="{ color: cCardText+'88' }">Sprache</p>
                                            <div class="flex gap-1">
                                                <div class="flex-1 rounded-lg py-0.5 text-center text-[5px] font-semibold" :style="{ backgroundColor: cCardButton, color: cCardButtonText }">Deutsch</div>
                                                <div class="flex-1 rounded-lg border py-0.5 text-center text-[5px]" :style="{ borderColor: cBorder+'33', color: cCardText+'77' }">Englisch</div>
                                            </div>
                                        </div>
                                        <div class="border-t mx-2" :style="{ borderColor: cBorder+'33' }"></div>
                                        <div class="mx-2 my-1.5 rounded-lg py-1 text-center text-[6px] font-semibold text-white" style="background-color:#b45a3c;">
                                            Ausloggen
                                        </div>
                                    </div>
                                </div>
                                <div class="flex h-[26px] w-full items-end justify-around pb-1.5 border-t" :style="{ backgroundColor: cScreenBg, borderColor: cBorder+'33' }">
                                    <div v-for="(tab, i) in tabDefs" :key="'e'+i" class="flex flex-col items-center gap-0.5">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            :stroke="i===4 ? cTabTint : cTabTint+'55'">
                                            <path v-for="(p,pi) in tab.paths" :key="pi" :d="p" />
                                        </svg>
                                        <span class="text-[5px]" :style="{ color: i===4 ? cTabTint : cTabTint+'55', fontWeight: i===4?'700':'400' }">{{ tab.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div></div></div>
                    </div>

                    </div><!-- /grid -->
                </div>

            </div><!-- /lg:grid-cols-2 -->
        </div>

        <!-- Floating Save Bar -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="isDirty" class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 shadow-lg">
                <span class="mr-1 text-xs text-muted-foreground">{{ t('drink.unsavedChanges') }}</span>
                <Button variant="ghost" size="sm" :disabled="form.processing" @click="discard">
                    {{ t('common.cancel') }}
                </Button>
                <Button size="sm" :disabled="form.processing" @click="submit">
                    {{ form.processing ? '…' : t('common.save') }}
                </Button>
            </div>
        </Transition>
    </AppLayout>
</template>

<style>
/* Selbst gehostet via @fontsource — keine externen Requests (DSGVO) */
@import '@fontsource/playfair-display/400.css';
@import '@fontsource/playfair-display/700.css';
@import '@fontsource/cormorant-garamond/400.css';
@import '@fontsource/cormorant-garamond/700.css';
@import '@fontsource/cinzel/400.css';
@import '@fontsource/cinzel/700.css';
@import '@fontsource/dancing-script/400.css';
@import '@fontsource/dancing-script/700.css';
@import '@fontsource/great-vibes/400.css';
@import '@fontsource/raleway/400.css';
@import '@fontsource/raleway/700.css';
@import '@fontsource/lora/400.css';
@import '@fontsource/lora/700.css';
@import '@fontsource/josefin-sans/400.css';
@import '@fontsource/josefin-sans/700.css';
</style>
