<script setup lang="ts">
import ColorSystemEditor from '@/components/EventSettings/ColorSystemEditor.vue';
import CoverUpload from '@/components/EventSettings/CoverUpload.vue';
import PhonePreviewHome from '@/components/EventSettings/PhonePreview/Home.vue';
import PhonePreviewPhotos from '@/components/EventSettings/PhonePreview/Photos.vue';
import PhonePreviewRsvp from '@/components/EventSettings/PhonePreview/Rsvp.vue';
import PhonePreviewSettings from '@/components/EventSettings/PhonePreview/Settings.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFloatingBar } from '@/composables/useFloatingBar';
import AppLayout from '@/layouts/AppLayout.vue';
import { buildPalette, resolveRole, type PaletteKey } from '@/lib/colorResolver';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

// Read-only preview context — these fields are edited on other pages (event
// settings / schedule) but the phone preview still renders them for realism.
interface EventData {
    id: number;
    name: string;
    date: string | null;
    dresscode: string | null;
    cover_image_url: string | null;
    venue_display_mode: string | null;
    venue_name: string | null;
    venue_street: string | null;
    venue_house_number: string | null;
    venue_postal_code: string | null;
    venue_city: string | null;
    venue_country: string | null;
    // Design fields:
    color_primary: string | null;
    color_secondary: string | null;
    color_tertiary: string | null;
    color_home_text: string | null;
    color_home_shadow: string | null;
    home_shadow_opacity: number | null;
    role_screen_bg: string | null;
    role_card_bg: string | null;
    role_card_text: string | null;
    role_card_button: string | null;
    role_card_button_text: string | null;
    role_tab_tint: string | null;
    role_border: string | null;
    role_fab: string | null;
    role_fab_icon: string | null;
    font_heading: string | null;
    design_preset: string | null;
}

interface StylePreset {
    id: number;
    name: string;
    color_primary: string | null;
    color_secondary: string | null;
    color_tertiary: string | null;
    color_home_text: string | null;
    color_home_shadow: string | null;
    home_shadow_opacity: number | null;
    role_screen_bg: string | null;
    role_card_bg: string | null;
    role_card_text: string | null;
    role_card_button: string | null;
    role_card_button_text: string | null;
    role_tab_tint: string | null;
    role_border: string | null;
    role_fab: string | null;
    role_fab_icon: string | null;
    font_heading: string | null;
}

const props = defineProps<{ event: EventData; stylePresets: StylePreset[] }>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('nav.appDesign'), href: '/app/design' }];

const form = useForm({
    // Palette
    color_primary: props.event.color_primary ?? '#7c2d3e',
    color_secondary: props.event.color_secondary ?? '#e8e3de',
    color_tertiary: props.event.color_tertiary ?? '#ffffff',
    color_home_text: props.event.color_home_text ?? '#ffffff',
    color_home_shadow: props.event.color_home_shadow ?? '#000000',
    home_shadow_opacity: props.event.home_shadow_opacity ?? 50,
    // Roles
    role_screen_bg: props.event.role_screen_bg ?? 'secondary',
    role_card_bg: props.event.role_card_bg ?? 'tertiary',
    role_card_text: props.event.role_card_text ?? 'primary',
    role_card_button: props.event.role_card_button ?? 'primary',
    role_card_button_text: props.event.role_card_button_text ?? 'tertiary',
    role_tab_tint: props.event.role_tab_tint ?? 'primary',
    role_border: props.event.role_border ?? 'primary',
    role_fab: props.event.role_fab ?? 'primary',
    role_fab_icon: props.event.role_fab_icon ?? 'tertiary',
    font_heading: props.event.font_heading ?? '',
    design_preset: props.event.design_preset ?? 'classic',
    cover: null as File | null,
});

// App design preset — the "form language" (radius, glass, shadows, animations)
// the guest app renders on top of the colours. Orthogonal to the palette.
const designPresets = [
    {
        key: 'classic',
        label: 'Classic',
        description: 'Klare Karten, dezente Ränder — der bisherige Look.',
    },
    {
        key: 'soft-luxury',
        label: 'Soft Luxury',
        description: 'Weiche Rundungen, Milchglas, Verläufe & Animationen.',
    },
] as const;

const skipGuard = ref(false);
const isDirty = computed(() => form.isDirty);

// Hint system
const activeHint = ref<string | null>(null);
const previewCollapsed = ref(false);
let hintTimer: ReturnType<typeof setTimeout> | null = null;
function showHint(section: string) {
    if (hintTimer) clearTimeout(hintTimer);
    // Briefly set to null so the CSS animation restarts on subsequent clicks
    activeHint.value = null;
    requestAnimationFrame(() => {
        activeHint.value = section;
        hintTimer = setTimeout(() => {
            activeHint.value = null;
        }, 2500);
    });
}
const { active: floatingBarActive } = useFloatingBar();
watch(
    isDirty,
    (val) => {
        floatingBarActive.value = val;
    },
    { immediate: true },
);

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
    if (previewCountdownInterval) clearInterval(previewCountdownInterval);
});

function discard() {
    form.reset();
    coverUpload.value?.resetPreview();
}

function submit() {
    skipGuard.value = true;
    form.post(route('app.design.update'), {
        onSuccess: () => {
            toast.success(t('toast.eventSettingsSaved'));
            coverUpload.value?.resetPreview();
            form.cover = null;
        },
        onFinish: () => {
            skipGuard.value = false;
        },
    });
}

// Cover — logic lives in CoverUpload.vue. Parent keeps a ref to the child's
// displayCoverUrl so the PhonePreview stays in sync, plus a component ref so
// discard()/submit() onSuccess can drop the in-flight preview blob.
const coverUpload = ref<InstanceType<typeof CoverUpload> | null>(null);
const displayCoverUrl = ref<string | null>(props.event.cover_image_url);

// Preview — name/date/dresscode/venue are read-only context from the event.
const previewDate = computed(() => {
    if (!props.event.date) return null;
    try {
        return new Date(props.event.date).toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return null;
    }
});

const previewCountdown = ref<string | null>(null);
let previewCountdownInterval: ReturnType<typeof setInterval> | null = null;

function updatePreviewCountdown() {
    if (!props.event.date) {
        previewCountdown.value = null;
        return;
    }
    const diff = new Date(props.event.date).getTime() - Date.now();
    if (diff <= 0) {
        previewCountdown.value = null;
        return;
    }
    const totalSec = Math.floor(diff / 1000);
    const d = Math.floor(totalSec / 86400);
    const h = Math.floor((totalSec % 86400) / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    previewCountdown.value = `Noch ${d}T ${h}Std ${m}Min ${s}Sek`;
}

onMounted(() => {
    updatePreviewCountdown();
    if (props.event.date) previewCountdownInterval = setInterval(updatePreviewCountdown, 1000);
});

const isGermanyForm = computed(() => {
    const c = (props.event.venue_country || 'Deutschland').toLowerCase().trim();
    return c === 'deutschland' || c === 'germany' || c === 'de';
});

const previewVenueName = computed(() => props.event.venue_name || props.event.venue_city || 'Musterort');
const previewVenueAddress = computed(() => {
    if (!props.event.venue_street && !props.event.venue_city) return 'Musterstraße 1';
    if (isGermanyForm.value) {
        const street = [props.event.venue_street, props.event.venue_house_number].filter(Boolean).join(' ');
        const city = [props.event.venue_postal_code, props.event.venue_city].filter(Boolean).join(' ');
        return [street, city].filter(Boolean).join(', ');
    }
    return [props.event.venue_street, props.event.venue_city].filter(Boolean).join(', ');
});

// Palette + role resolution — pure logic in resources/js/lib/colorResolver.ts
const palette = computed(() => buildPalette(form.color_primary, form.color_secondary, form.color_tertiary));
const resolve = (role: string | null, fallback: PaletteKey): string => resolveRole(role, fallback, palette.value);

const cScreenBg = computed(() => resolve(form.role_screen_bg, 'secondary'));
const cCardBg = computed(() => resolve(form.role_card_bg, 'tertiary'));
const cCardText = computed(() => resolve(form.role_card_text, 'primary'));
const cCardButton = computed(() => resolve(form.role_card_button, 'primary'));
const cCardButtonText = computed(() => resolve(form.role_card_button_text, 'tertiary'));
const cTabTint = computed(() => resolve(form.role_tab_tint, 'primary'));
const cBorder = computed(() => resolve(form.role_border, 'primary'));
const cFab = computed(() => resolve(form.role_fab, 'primary'));
const cFabIcon = computed(() => resolve(form.role_fab_icon, 'tertiary'));

// Font
const fontOptions = [
    { key: 'playfair', label: 'Playfair Display', family: 'Playfair Display' },
    { key: 'cormorant', label: 'Cormorant Garamond', family: 'Cormorant Garamond' },
    { key: 'cinzel', label: 'Cinzel', family: 'Cinzel' },
    { key: 'dancing', label: 'Dancing Script', family: 'Dancing Script' },
    { key: 'great_vibes', label: 'Great Vibes', family: 'Great Vibes' },
    { key: 'raleway', label: 'Raleway', family: 'Raleway' },
    { key: 'lora', label: 'Lora', family: 'Lora' },
    { key: 'josefin', label: 'Josefin Sans', family: 'Josefin Sans' },
];
const previewFontFamily = computed(() => fontOptions.find((f) => f.key === form.font_heading)?.family ?? 'inherit');

// Tab bar icons
// Ionicons outline — exact paths (viewBox 0 0 512 512, stroke-based)
const tabDefs = [
    {
        label: 'Home',
        viewBox: '0 0 512 512',
        strokeWidth: 40,
        paths: [
            'M80 212v236a16 16 0 0 0 16 16h96V328a24 24 0 0 1 24-24h80a24 24 0 0 1 24 24v136h96a16 16 0 0 0 16-16V212',
            'M480 256 266.89 52c-5-5.28-16.69-5.34-21.78 0L32 256',
            'M400 179V64h-48v69',
        ],
    },
    {
        label: 'Zusage',
        viewBox: '0 0 512 512',
        strokeWidth: 40,
        paths: ['M448 256c0-106-86-192-192-192S64 150 64 256s86 192 192 192 192-86 192-192z', 'M352 176 217.6 336 160 272'],
    },
    {
        label: 'Fotos',
        viewBox: '0 0 512 512',
        strokeWidth: 40,
        paths: [
            'M432 112V96a48 48 0 0 0-48-48H64a48 48 0 0 0-48 48v256a48 48 0 0 0 64 48h16',
            'M142 128h308a46 46 0 0 1 46 46v244a46 46 0 0 1-46 46H142a46 46 0 0 1-46-46V174a46 46 0 0 1 46-46z',
            'M342.15 219.64a30.77 30.55 0 1 0 61.54 0 30.77 30.55 0 1 0-61.54 0',
            'M342.15 372.17 255 285.78a31 31 0 0 0-42.18-1.21L96 387.64',
            'M265.23 464l118.59-117.73a31 31 0 0 1 41.46-1.87L496 402.91',
        ],
    },
    {
        label: 'Spiel',
        viewBox: '0 0 512 512',
        strokeWidth: 40,
        paths: [
            'M352 200v240a40 40 0 0 1-40 40H136a40 40 0 0 1-40-40V224',
            'M352 224h40a56 56 0 0 1 56 56v80a56 56 0 0 1-56 56h-40',
            'M320 112a48 48 0 0 1 0 96c-13.25 0-29.31-7.31-38-16H160c-8 22-27 32-48 32a48 48 0 0 1 0-96 47.9 47.9 0 0 1 26 9',
        ],
    },
    {
        label: 'Einst.',
        viewBox: '0 0 512 512',
        strokeWidth: 40,
        paths: [
            'M262.29 192.31a64 64 0 1 0 57.4 57.4 64.13 64.13 0 0 0-57.4-57.4M416.39 256a154 154 0 0 1-1.53 20.79l45.21 35.46a10.81 10.81 0 0 1 2.45 13.75l-42.77 74a10.81 10.81 0 0 1-13.14 4.59l-44.9-18.08a16.11 16.11 0 0 0-15.17 1.75A164.5 164.5 0 0 1 325 400.8a15.94 15.94 0 0 0-8.82 12.14l-6.73 47.89a11.08 11.08 0 0 1-10.68 9.17h-85.54a11.11 11.11 0 0 1-10.69-8.87l-6.72-47.82a16.07 16.07 0 0 0-9-12.22 155 155 0 0 1-21.46-12.57 16 16 0 0 0-15.11-1.71l-44.89 18.07a10.81 10.81 0 0 1-13.14-4.58l-42.77-74a10.8 10.8 0 0 1 2.45-13.75l38.21-30a16.05 16.05 0 0 0 6-14.08c-.36-4.17-.58-8.33-.58-12.5s.21-8.27.58-12.35a16 16 0 0 0-6.07-13.94l-38.19-30A10.81 10.81 0 0 1 49.48 186l42.77-74a10.81 10.81 0 0 1 13.14-4.59l44.9 18.08a16.11 16.11 0 0 0 15.17-1.75A164.5 164.5 0 0 1 187 111.2a15.94 15.94 0 0 0 8.82-12.14l6.73-47.89A11.08 11.08 0 0 1 213.23 42h85.54a11.11 11.11 0 0 1 10.69 8.87l6.72 47.82a16.07 16.07 0 0 0 9 12.22 155 155 0 0 1 21.46 12.57 16 16 0 0 0 15.11 1.71l44.89-18.07a10.81 10.81 0 0 1 13.14 4.58l42.77 74a10.8 10.8 0 0 1-2.45 13.75l-38.21 30a16.05 16.05 0 0 0-6.05 14.08c.33 4.14.55 8.3.55 12.47',
        ],
    },
];

// Style presets
const presetNameInput = ref('');
const showPresetInput = ref(false);

function loadPreset(preset: StylePreset) {
    form.color_primary = preset.color_primary ?? form.color_primary;
    form.color_secondary = preset.color_secondary ?? form.color_secondary;
    form.color_tertiary = preset.color_tertiary ?? form.color_tertiary;
    form.color_home_text = preset.color_home_text ?? form.color_home_text;
    form.color_home_shadow = preset.color_home_shadow ?? form.color_home_shadow;
    form.home_shadow_opacity = preset.home_shadow_opacity ?? form.home_shadow_opacity;
    form.role_screen_bg = preset.role_screen_bg ?? form.role_screen_bg;
    form.role_card_bg = preset.role_card_bg ?? form.role_card_bg;
    form.role_card_text = preset.role_card_text ?? form.role_card_text;
    form.role_card_button = preset.role_card_button ?? form.role_card_button;
    form.role_card_button_text = preset.role_card_button_text ?? form.role_card_button_text;
    form.role_tab_tint = preset.role_tab_tint ?? form.role_tab_tint;
    form.role_border = preset.role_border ?? form.role_border;
    form.role_fab = preset.role_fab ?? form.role_fab;
    form.role_fab_icon = preset.role_fab_icon ?? form.role_fab_icon;
    form.font_heading = preset.font_heading ?? form.font_heading;
}

const presetForm = useForm({
    name: '',
    color_primary: '',
    color_secondary: '',
    color_tertiary: '',
    color_home_text: '',
    color_home_shadow: '',
    home_shadow_opacity: 50 as number,
    role_screen_bg: '',
    role_card_bg: '',
    role_card_text: '',
    role_card_button: '',
    role_card_button_text: '',
    role_tab_tint: '',
    role_border: '',
    role_fab: '',
    role_fab_icon: '',
    font_heading: '',
});

function savePreset() {
    presetForm.name = presetNameInput.value.trim();
    presetForm.color_primary = form.color_primary;
    presetForm.color_secondary = form.color_secondary;
    presetForm.color_tertiary = form.color_tertiary;
    presetForm.color_home_text = form.color_home_text;
    presetForm.color_home_shadow = form.color_home_shadow;
    presetForm.home_shadow_opacity = form.home_shadow_opacity;
    presetForm.role_screen_bg = form.role_screen_bg;
    presetForm.role_card_bg = form.role_card_bg;
    presetForm.role_card_text = form.role_card_text;
    presetForm.role_card_button = form.role_card_button;
    presetForm.role_card_button_text = form.role_card_button_text;
    presetForm.role_tab_tint = form.role_tab_tint;
    presetForm.role_border = form.role_border;
    presetForm.role_fab = form.role_fab;
    presetForm.role_fab_icon = form.role_fab_icon;
    presetForm.font_heading = form.font_heading;
    presetForm.post(route('event.style-presets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            presetNameInput.value = '';
            showPresetInput.value = false;
            toast.success('Stil gespeichert');
        },
    });
}

function deletePreset(id: number) {
    router.delete(route('event.style-presets.destroy', id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Stil gelöscht'),
    });
}

const STYLE_FIELDS = [
    'color_primary',
    'color_secondary',
    'color_tertiary',
    'color_home_text',
    'color_home_shadow',
    'home_shadow_opacity',
    'role_screen_bg',
    'role_card_bg',
    'role_card_text',
    'role_card_button',
    'role_card_button_text',
    'role_tab_tint',
    'role_border',
    'role_fab',
    'role_fab_icon',
    'font_heading',
] as const;

function exportStyle(preset?: StylePreset) {
    const data: Record<string, unknown> = { _version: 1 };
    if (preset) {
        data._name = preset.name;
        for (const field of STYLE_FIELDS) {
            data[field] = preset[field as keyof StylePreset];
        }
    } else {
        data._name = props.event.name;
        for (const field of STYLE_FIELDS) {
            data[field] = (form as unknown as Record<string, unknown>)[field];
        }
    }
    const slug = String(data._name).replace(/\s+/g, '-').toLowerCase();
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `stil-${slug}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
}

const importFileInput = ref<HTMLInputElement | null>(null);

function importStyle(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        try {
            const data = JSON.parse(reader.result as string);
            // Save directly as preset
            presetForm.name = String(data._name ?? file.name.replace(/\.json$/i, ''));
            const f = presetForm as unknown as Record<string, unknown>;
            for (const field of STYLE_FIELDS) {
                if (data[field] !== undefined) f[field] = data[field];
            }
            presetForm.post(route('event.style-presets.store'), {
                preserveScroll: true,
                onSuccess: () => toast.success(`Stil „${presetForm.name}" importiert`),
                onError: () => toast.error('Importieren fehlgeschlagen'),
            });
        } catch {
            toast.error('Ungültige Datei');
        }
        if (importFileInput.value) importFileInput.value.value = '';
    };
    reader.readAsText(file);
}
</script>

<template>
    <Head :title="t('nav.appDesign')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <!-- Split screen: mobile = preview on top (shrink-0) / form below (scroll); desktop = form left / preview right -->
        <div class="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden lg:grid lg:h-[calc(100vh-4rem)] lg:grid-cols-2 lg:gap-6 lg:px-4 lg:pt-4">
            <!-- Form — after preview on mobile (order-last, flex-1 scroll), on the left on desktop -->
            <div class="order-last min-h-0 flex-1 overflow-y-auto px-4 pt-2 pb-24 lg:order-first lg:px-0 lg:pt-0">
                <div class="space-y-4">
                    <!-- Left column: form -->
                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Cover upload -->
                        <Card>
                            <CardContent class="space-y-3 pt-6">
                                <CoverUpload
                                    ref="coverUpload"
                                    :initial-cover-url="props.event.cover_image_url"
                                    v-model:cover="form.cover"
                                    v-model:color-home-text="form.color_home_text"
                                    v-model:color-home-shadow="form.color_home_shadow"
                                    v-model:home-shadow-opacity="form.home_shadow_opacity"
                                    @update:display-cover-url="displayCoverUrl = $event"
                                />
                            </CardContent>
                        </Card>

                        <!-- Design: preset + font + colors -->
                        <Card>
                            <CardContent>
                                <div class="space-y-4 pt-4">
                                    <!-- Design preset (form language) -->
                                    <div class="grid gap-2">
                                        <Label>App-Design</Label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                v-for="preset in designPresets"
                                                :key="preset.key"
                                                type="button"
                                                @click="form.design_preset = preset.key"
                                                class="rounded-lg border-2 px-3 py-3 text-left transition-colors"
                                                :class="
                                                    form.design_preset === preset.key
                                                        ? 'border-ring bg-muted/20'
                                                        : 'border-input hover:border-muted-foreground'
                                                "
                                            >
                                                <div class="text-sm font-semibold">{{ preset.label }}</div>
                                                <div class="mt-0.5 text-xs leading-tight text-muted-foreground">
                                                    {{ preset.description }}
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Font -->
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.fontHeading') }}</Label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <button
                                                type="button"
                                                @click="form.font_heading = ''"
                                                class="rounded-lg border-2 px-2 py-2 text-center text-xs transition-colors"
                                                :class="!form.font_heading ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground'"
                                            >
                                                {{ t('event.fontSystemDefault') }}
                                            </button>
                                            <button
                                                v-for="font in fontOptions"
                                                :key="font.key"
                                                type="button"
                                                @click="form.font_heading = font.key"
                                                class="rounded-lg border-2 px-2 py-2.5 text-center text-sm leading-tight transition-colors"
                                                :class="
                                                    form.font_heading === font.key
                                                        ? 'border-ring bg-muted/20'
                                                        : 'border-input hover:border-muted-foreground'
                                                "
                                                :style="{ fontFamily: font.family }"
                                            >
                                                {{ font.label }}
                                            </button>
                                        </div>
                                    </div>
                                    <ColorSystemEditor
                                        v-model:color-primary="form.color_primary"
                                        v-model:color-secondary="form.color_secondary"
                                        v-model:color-tertiary="form.color_tertiary"
                                        v-model:role-screen-bg="form.role_screen_bg"
                                        v-model:role-card-bg="form.role_card_bg"
                                        v-model:role-card-text="form.role_card_text"
                                        v-model:role-card-button="form.role_card_button"
                                        v-model:role-card-button-text="form.role_card_button_text"
                                        v-model:role-tab-tint="form.role_tab_tint"
                                        v-model:role-border="form.role_border"
                                        v-model:role-fab="form.role_fab"
                                        v-model:role-fab-icon="form.role_fab_icon"
                                        @show-hint="showHint"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Style presets -->
                        <Card>
                            <CardContent class="pt-4">
                                <!-- Header -->
                                <div class="mb-3 flex items-center justify-between">
                                    <Label>Stile</Label>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Aktuellen Stil als Datei exportieren"
                                            @click="exportStyle()"
                                        >
                                            ↓ Export
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Stil aus Datei importieren & speichern"
                                            @click="importFileInput?.click()"
                                        >
                                            ↑ Import
                                        </button>
                                        <input
                                            ref="importFileInput"
                                            type="file"
                                            accept=".json,application/json"
                                            class="hidden"
                                            @change="importStyle"
                                        />
                                    </div>
                                </div>

                                <!-- Preset list -->
                                <div class="space-y-1.5">
                                    <div
                                        v-for="preset in stylePresets"
                                        :key="preset.id"
                                        class="flex items-center gap-2 rounded-lg border border-input bg-muted/20 px-3 py-2"
                                    >
                                        <div class="flex shrink-0 gap-0.5">
                                            <div
                                                class="h-3.5 w-3.5 rounded-full border border-black/10"
                                                :style="{ backgroundColor: preset.color_primary ?? '#7c2d3e' }"
                                            />
                                            <div
                                                class="h-3.5 w-3.5 rounded-full border border-black/10"
                                                :style="{ backgroundColor: preset.color_secondary ?? '#e8e3de' }"
                                            />
                                            <div
                                                class="h-3.5 w-3.5 rounded-full border border-black/10"
                                                :style="{ backgroundColor: preset.color_tertiary ?? '#ffffff' }"
                                            />
                                        </div>
                                        <span class="flex-1 truncate text-sm">{{ preset.name }}</span>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Exportieren"
                                            @click="exportStyle(preset)"
                                        >
                                            ↓
                                        </button>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded border border-input px-2 py-0.5 text-xs transition-colors hover:bg-muted"
                                            @click="loadPreset(preset)"
                                        >
                                            Laden
                                        </button>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            @click="deletePreset(preset.id)"
                                        >
                                            ✕
                                        </button>
                                    </div>

                                    <p v-if="!stylePresets.length" class="py-1 text-xs text-muted-foreground">Noch keine Stile gespeichert.</p>
                                </div>

                                <!-- Save new style -->
                                <div class="mt-3 border-t border-input pt-3">
                                    <div v-if="!showPresetInput">
                                        <button
                                            type="button"
                                            class="w-full rounded-lg border border-dashed border-input py-2 text-xs text-muted-foreground transition-colors hover:border-ring hover:text-foreground"
                                            @click="showPresetInput = true"
                                        >
                                            + Aktuellen Stil speichern
                                        </button>
                                    </div>
                                    <div v-else class="flex gap-2">
                                        <Input
                                            v-model="presetNameInput"
                                            placeholder="Name des Stils…"
                                            class="h-8 flex-1 text-sm"
                                            @keyup.enter="presetNameInput.trim() && savePreset()"
                                            @keyup.esc="
                                                showPresetInput = false;
                                                presetNameInput = '';
                                            "
                                            autofocus
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            class="h-8"
                                            :disabled="!presetNameInput.trim() || presetForm.processing"
                                            @click="savePreset"
                                        >
                                            Speichern
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8"
                                            @click="
                                                showPresetInput = false;
                                                presetNameInput = '';
                                            "
                                        >
                                            ✕
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>
            </div>

            <!-- Preview — top on mobile (order-first), right on desktop (order-last) -->
            <div class="order-first flex-shrink-0 lg:order-last lg:h-full lg:overflow-y-auto lg:pb-4">
                <!-- Mobile: collapsible header -->
                <button
                    type="button"
                    class="flex w-full items-center justify-between border-b px-4 py-3 lg:hidden"
                    @click="previewCollapsed = !previewCollapsed"
                >
                    <span class="text-sm font-semibold">{{ t('event.phonePreview') }}</span>
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': !previewCollapsed }"
                    >
                        <path d="M18 15l-6-6-6 6" />
                    </svg>
                </button>

                <div v-show="!previewCollapsed" class="flex flex-col items-center gap-2 pt-2 lg:pt-0">
                    <!-- Phones: mobile horizontal scroll, desktop 2×2 grid -->
                    <div class="w-full overflow-x-auto lg:overflow-x-visible">
                        <div class="flex gap-4 px-4 pb-4 lg:grid lg:grid-cols-2 lg:px-0">
                            <!-- ===== SCREEN 1: HOME ===== -->
                            <PhonePreviewHome
                                :cover-url="displayCoverUrl"
                                :event-name="props.event.name"
                                :dresscode="props.event.dresscode ?? ''"
                                :venue-display-mode="props.event.venue_display_mode"
                                :color-home-text="form.color_home_text"
                                :color-home-shadow="form.color_home_shadow"
                                :home-shadow-opacity="form.home_shadow_opacity"
                                :preview-date="previewDate"
                                :preview-font-family="previewFontFamily"
                                :preview-venue-name="previewVenueName"
                                :preview-venue-address="previewVenueAddress"
                                :preview-countdown="previewCountdown"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-card-text="cCardText"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :tab-defs="tabDefs"
                                :active-hint="activeHint"
                                :design-preset="form.design_preset"
                            />
                            <!-- ===== END HOME (extracted to PhonePreview/Home.vue) ===== -->

                            <!-- ===== SCREEN 2: ZUSAGE ===== -->
                            <PhonePreviewRsvp
                                :preview-font-family="previewFontFamily"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-card-text="cCardText"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :tab-defs="tabDefs"
                                :active-hint="activeHint"
                                :design-preset="form.design_preset"
                            />

                            <!-- ===== SCREEN 3: FOTOS ===== -->
                            <PhonePreviewPhotos
                                :preview-font-family="previewFontFamily"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-fab="cFab"
                                :c-fab-icon="cFabIcon"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :tab-defs="tabDefs"
                                :active-hint="activeHint"
                                :design-preset="form.design_preset"
                            />

                            <!-- ===== SCREEN 4: EINSTELLUNGEN ===== -->
                            <PhonePreviewSettings
                                :preview-font-family="previewFontFamily"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-card-text="cCardText"
                                :c-card-button="cCardButton"
                                :c-card-button-text="cCardButtonText"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :tab-defs="tabDefs"
                                :active-hint="activeHint"
                                :design-preset="form.design_preset"
                            />
                        </div>
                        <!-- /flex or grid -->
                    </div>
                    <!-- /overflow-x-auto -->
                </div>
            </div>
        </div>
        <!-- /split screen -->

        <!-- Floating Save Bar -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="isDirty" class="fixed right-6 bottom-6 z-50 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 shadow-lg">
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
/* Self-hosted via @fontsource — no external requests (GDPR) */
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

/* Phone preview sizes */
.phone-frame-outer {
    width: 228px;
    height: 464px;
    overflow: hidden;
    flex-shrink: 0;
}
.phone-frame-inner {
    transform: scale(1.9);
    transform-origin: top left;
}
@media (max-width: 1023px) {
    .phone-frame-outer {
        width: 171px;
        height: 348px;
    }
    .phone-frame-inner {
        transform: scale(1.425);
    }
}

/* Unified amber overlay for all hint elements */
@keyframes preview-hint-bg {
    0%,
    100% {
        box-shadow: inset 0 0 0 1000px rgba(251, 191, 36, 0);
    }
    50% {
        box-shadow: inset 0 0 0 1000px rgba(251, 191, 36, 0.3);
    }
}
.preview-hint-bg {
    animation: preview-hint-bg 0.4s ease-in-out 5;
}
/* Border: outer amber ring */
@keyframes preview-hint-border {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(251, 191, 36, 0);
    }
    50% {
        box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.9);
    }
}
.preview-hint-border {
    animation: preview-hint-border 0.4s ease-in-out 5;
}
/* Text/icons/SVGs: amber via filter */
@keyframes preview-hint-filter {
    0%,
    100% {
        filter: none;
    }
    50% {
        filter: sepia(1) saturate(15) hue-rotate(-5deg);
    }
}
.preview-hint-filter {
    animation: preview-hint-filter 0.4s ease-in-out 5;
}
</style>
