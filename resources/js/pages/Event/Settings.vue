<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFloatingBar } from '@/composables/useFloatingBar';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import heic2any from 'heic2any';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

// Leaflet default icon fix für Vite
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

interface EventData {
    id: number;
    name: string;
    date: string | null;
    rsvp_deadline: string | null;
    cover_image_url: string | null;
    venue_name: string | null;
    venue_lat: number | null;
    venue_lng: number | null;
    venue_street: string | null;
    venue_house_number: string | null;
    venue_postal_code: string | null;
    venue_city: string | null;
    venue_state: string | null;
    venue_country: string | null;
    venue_display_mode: string | null;
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
    role_fab_icon: string | null;
    font_heading: string | null;
}

const props = defineProps<{ event: EventData }>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('event.settings'), href: '/event/settings' }];

const form = useForm({
    name: props.event.name ?? '',
    date: props.event.date ? props.event.date.slice(0, 16) : '',
    rsvp_deadline: props.event.rsvp_deadline ? props.event.rsvp_deadline.slice(0, 16) : '',
    venue_name: props.event.venue_name ?? '',
    venue_lat: props.event.venue_lat ?? (null as number | null),
    venue_lng: props.event.venue_lng ?? (null as number | null),
    venue_street: props.event.venue_street ?? '',
    venue_house_number: props.event.venue_house_number ?? '',
    venue_postal_code: props.event.venue_postal_code ?? '',
    venue_city: props.event.venue_city ?? '',
    venue_state: props.event.venue_state ?? '',
    venue_country: props.event.venue_country ?? 'Deutschland',
    venue_display_mode: props.event.venue_display_mode ?? 'both',
    dresscode: props.event.dresscode ?? '',
    schedule: props.event.schedule ?? '',
    // Palette
    color_primary: props.event.color_primary ?? '#7c2d3e',
    color_secondary: props.event.color_secondary ?? '#e8e3de',
    color_tertiary: props.event.color_tertiary ?? '#ffffff',
    color_home_text: props.event.color_home_text ?? '#ffffff',
    // Rollen
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
    cover: null as File | null,
});

const skipGuard = ref(false);
const isDirty = computed(() => form.isDirty);

// Field-level dirty detection for address section
const savedAddress = reactive<Record<string, string>>({
    venue_name:         props.event.venue_name         ?? '',
    venue_street:       props.event.venue_street       ?? '',
    venue_house_number: props.event.venue_house_number ?? '',
    venue_postal_code:  props.event.venue_postal_code  ?? '',
    venue_city:         props.event.venue_city         ?? '',
    venue_state:        props.event.venue_state        ?? '',
    venue_country:      props.event.venue_country      ?? 'Deutschland',
});
type AddressField = keyof typeof savedAddress;
const formAny = form as unknown as Record<string, string | null>;
function isFieldDirty(field: AddressField): boolean {
    return (formAny[field] ?? '') !== savedAddress[field];
}
function resetField(field: AddressField) {
    formAny[field] = savedAddress[field];
    if (field === 'venue_country') countryQuery.value = savedAddress.venue_country;
}

// Hint-System
const activeHint = ref<string | null>(null);
let hintTimer: ReturnType<typeof setTimeout> | null = null;
function showHint(section: string) {
    if (hintTimer) clearTimeout(hintTimer);
    // Kurz auf null setzen, damit die CSS-Animation bei erneutem Klick neu startet
    activeHint.value = null;
    requestAnimationFrame(() => {
        activeHint.value = section;
        hintTimer = setTimeout(() => { activeHint.value = null; }, 2500);
    });
}
function hintClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint' : '';
}
function hintTextClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-text' : '';
}
function hintBgClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-bg' : '';
}
function hintSepClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-sep' : '';
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
    // Map lazy-init: kurz warten bis DOM gerendert
    setTimeout(initMap, 50);
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
    if (leafletMap) { leafletMap.remove(); leafletMap = null; }
    removeInertiaGuard?.();
    floatingBarActive.value = false;
});

function discard() {
    form.reset();
    countryQuery.value = props.event.venue_country ?? 'Deutschland';
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
            // Dirty-Baseline aktualisieren
            for (const key of Object.keys(savedAddress) as AddressField[]) {
                savedAddress[key] = (formAny[key] ?? '') as string;
            }
        },
        onFinish: () => {
            skipGuard.value = false;
        },
    });
}

// Cover
const coverUrl = ref<string | null>(props.event.cover_image_url);
const coverPreview = ref<string | null>(null);
const coverRemoving = ref(false);

const displayCoverUrl = computed(() => coverPreview.value ?? coverUrl.value);

const coverFilename = computed(() => {
    if (form.cover) return form.cover.name;
    if (!coverUrl.value) return null;
    try {
        return decodeURIComponent(coverUrl.value.split('/').pop()?.split('?')[0] ?? '');
    } catch {
        return null;
    }
});

const coverConverting = ref(false);
const isDraggingCover = ref(false);

async function processCoverFile(file: File) {
    if (/heic|heif/i.test(file.name) || file.type === 'image/heic' || file.type === 'image/heif') {
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
    }
    form.cover = file;
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
    } catch {
        return null;
    }
});

const previewDaysLeft = computed(() => {
    if (!form.date) return null;
    try {
        const diff = Math.ceil((new Date(form.date).getTime() - Date.now()) / 86_400_000);
        return diff > 0 ? diff : null;
    } catch {
        return null;
    }
});

// --- Adressform ---
const COUNTRIES = [
    'Deutschland',
    'Österreich',
    'Schweiz',
    'Frankreich',
    'Italien',
    'Spanien',
    'Portugal',
    'Niederlande',
    'Belgien',
    'Luxemburg',
    'Polen',
    'Tschechien',
    'Slowakei',
    'Ungarn',
    'Rumänien',
    'Bulgarien',
    'Griechenland',
    'Kroatien',
    'Slowenien',
    'Serbien',
    'Albanien',
    'Montenegro',
    'Nordmazedonien',
    'Kosovo',
    'Dänemark',
    'Schweden',
    'Norwegen',
    'Finnland',
    'Island',
    'Vereinigtes Königreich',
    'Irland',
    'Vereinigte Staaten',
    'Kanada',
    'Mexiko',
    'Brasilien',
    'Argentinien',
    'Chile',
    'Kolumbien',
    'Peru',
    'Türkei',
    'Russland',
    'Ukraine',
    'Litauen',
    'Lettland',
    'Estland',
    'Japan',
    'China',
    'Südkorea',
    'Indien',
    'Thailand',
    'Vietnam',
    'Singapur',
    'Indonesien',
    'Malaysia',
    'Philippinen',
    'Australien',
    'Neuseeland',
    'Südafrika',
    'Ägypten',
    'Marokko',
    'Israel',
    'Vereinigte Arabische Emirate',
    'Saudi-Arabien',
];

const countryQuery = ref(props.event.venue_country ?? 'Deutschland');
const countryOpen = ref(false);
const countryInputRef = ref<HTMLElement | null>(null);
const countryDropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });

const filteredCountries = computed(() => {
    const q = countryQuery.value.toLowerCase().trim();
    return q ? COUNTRIES.filter((c) => c.toLowerCase().includes(q)) : COUNTRIES;
});

function updateCountryDropdownStyle() {
    if (!countryInputRef.value) return;
    const rect = countryInputRef.value.getBoundingClientRect();
    countryDropdownStyle.value = { top: `${rect.bottom + 4}px`, left: `${rect.left}px`, width: `${rect.width}px` };
}

function selectCountry(country: string) {
    form.venue_country = country;
    countryQuery.value = country;
    countryOpen.value = false;
}

const isGermanyForm = computed(() => {
    const c = (form.venue_country || 'Deutschland').toLowerCase().trim();
    return c === 'deutschland' || c === 'germany' || c === 'de';
});

const previewVenueName = computed(() => form.venue_name || form.venue_city || 'Musterort');
const previewVenueAddress = computed(() => {
    if (!form.venue_street && !form.venue_city) return 'Musterstraße 1';
    if (isGermanyForm.value) {
        const street = [form.venue_street, form.venue_house_number].filter(Boolean).join(' ');
        const city = [form.venue_postal_code, form.venue_city].filter(Boolean).join(' ');
        return [street, city].filter(Boolean).join(', ');
    }
    return [form.venue_street, form.venue_city].filter(Boolean).join(', ');
});

// --- Leaflet Map-Picker ---
const mapContainer     = ref<HTMLElement | null>(null);
const reverseGeocoding = ref(false);
let leafletMap: L.Map | null = null;
let mapMarker: L.Marker | null = null;

function initMap() {
    if (!mapContainer.value || leafletMap) return;
    const center: [number, number] = (form.venue_lat && form.venue_lng)
        ? [form.venue_lat, form.venue_lng] : [51.1657, 10.4515];
    leafletMap = L.map(mapContainer.value).setView(center, form.venue_lat ? 15 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);
    if (form.venue_lat && form.venue_lng) {
        mapMarker = L.marker([form.venue_lat, form.venue_lng], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }
    leafletMap.on('click', (e: L.LeafletMouseEvent) => setCoords(e.latlng.lat, e.latlng.lng));
}

async function setCoords(lat: number, lng: number) {
    form.venue_lat = lat;
    form.venue_lng = lng;
    if (!leafletMap) return;
    if (mapMarker) {
        mapMarker.setLatLng([lat, lng]);
    } else {
        mapMarker = L.marker([lat, lng], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }
    await reverseGeocode(lat, lng);
}

async function reverseGeocode(lat: number, lng: number) {
    reverseGeocoding.value = true;
    try {
        const res  = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`,
            { headers: { 'Accept-Language': 'de' } },
        );
        const data = await res.json();
        const addr = data.address;
        if (!addr) return;
        form.venue_street       = addr.road ?? addr.pedestrian ?? addr.path ?? '';
        form.venue_house_number = addr.house_number ?? '';
        form.venue_postal_code  = addr.postcode ?? '';
        form.venue_city         = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
        form.venue_state        = addr.state ?? '';
        const country           = addr.country ?? 'Deutschland';
        form.venue_country      = country;
        countryQuery.value      = country;
    } catch { /* silent — User kann manuell befüllen */ }
    finally { reverseGeocoding.value = false; }
}

function clearMapCoords() {
    form.venue_lat = null;
    form.venue_lng = null;
    if (mapMarker && leafletMap) { leafletMap.removeLayer(mapMarker); mapMarker = null; }
}

// --- Karten-Suche (forward geocode zum Zentrieren) ---
interface NominatimResult {
    place_id: number;
    lat: string;
    lon: string;
    display_name: string;
    name: string;
    address: Record<string, string>;
}

const mapSearchQuery   = ref('');
const mapSearchResults = ref<NominatimResult[]>([]);
const mapSearching     = ref(false);
const mapSearchOpen    = ref(false);
const mapSearchInputRef = ref<HTMLElement | null>(null);
const mapSearchDropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });
let mapSearchDebounce: ReturnType<typeof setTimeout> | null = null;

function updateMapSearchDropdownStyle() {
    if (!mapSearchInputRef.value) return;
    const rect = mapSearchInputRef.value.getBoundingClientRect();
    mapSearchDropdownStyle.value = { top: `${rect.bottom + 4}px`, left: `${rect.left}px`, width: `${rect.width}px` };
}

watch(mapSearchQuery, (val) => {
    if (mapSearchDebounce) clearTimeout(mapSearchDebounce);
    if (!val || val.length < 2) { mapSearchResults.value = []; mapSearchOpen.value = false; return; }
    mapSearchDebounce = setTimeout(async () => {
        mapSearching.value = true;
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&limit=6&dedupe=0&q=${encodeURIComponent(val)}&addressdetails=1`,
                { headers: { 'Accept-Language': 'de' } },
            );
            mapSearchResults.value = await res.json();
            if (mapSearchResults.value.length > 0) {
                updateMapSearchDropdownStyle();
                mapSearchOpen.value = true;
            } else {
                mapSearchOpen.value = false;
            }
        } catch { mapSearchResults.value = []; }
        finally { mapSearching.value = false; }
    }, 350);
});

function selectMapResult(result: NominatimResult) {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    // Karte zentrieren + Zoom
    if (leafletMap) {
        leafletMap.setView([lat, lng], 17);
    }

    // Pin setzen
    form.venue_lat = lat;
    form.venue_lng = lng;
    if (mapMarker) {
        mapMarker.setLatLng([lat, lng]);
    } else if (leafletMap) {
        mapMarker = L.marker([lat, lng], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }

    // Adressfelder aus Nominatim-Ergebnis befüllen
    const addr = result.address;
    form.venue_street       = addr.road ?? addr.pedestrian ?? addr.path ?? '';
    form.venue_house_number = addr.house_number ?? '';
    form.venue_postal_code  = addr.postcode ?? '';
    form.venue_city         = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
    form.venue_state        = addr.state ?? '';
    const country           = addr.country ?? 'Deutschland';
    form.venue_country      = country;
    countryQuery.value      = country;

    mapSearchQuery.value   = '';
    mapSearchResults.value = [];
    mapSearchOpen.value    = false;
}

// Palette + Rollen-Auflösung
const palette = computed(() => ({
    primary: form.color_primary || '#7c2d3e',
    secondary: form.color_secondary || '#e8e3de',
    tertiary: form.color_tertiary || '#ffffff',
}));

type PaletteKey = 'primary' | 'secondary' | 'tertiary';
const resolve = (role: string | null, fallback: PaletteKey): string => palette.value[(role as PaletteKey) ?? fallback] ?? palette.value[fallback];

const cScreenBg = computed(() => resolve(form.role_screen_bg, 'secondary'));
const cCardBg = computed(() => resolve(form.role_card_bg, 'tertiary'));
const cCardText = computed(() => resolve(form.role_card_text, 'primary'));
const cCardButton = computed(() => resolve(form.role_card_button, 'primary'));
const cCardButtonText = computed(() => resolve(form.role_card_button_text, 'tertiary'));
const cTabTint = computed(() => resolve(form.role_tab_tint, 'primary'));
const cBorder = computed(() => resolve(form.role_border, 'primary'));
const cFab = computed(() => resolve(form.role_fab, 'primary'));
const cFabIcon = computed(() => resolve(form.role_fab_icon, 'tertiary'));

// Optionen für Radio-Selektoren
const colorOptions = computed(() => [
    { key: 'primary', label: t('event.colorPrimaryLabel'), value: palette.value.primary },
    { key: 'secondary', label: t('event.colorSecondaryLabel'), value: palette.value.secondary },
    { key: 'tertiary', label: t('event.colorTertiaryLabel'), value: palette.value.tertiary },
]);

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

// Tab-Bar Icons
// Ionicons outline — exakte Pfade (viewBox 0 0 24 24, stroke-based)
const tabDefs = [
    {
        // home-outline
        label: 'Home',
        paths: [
            'M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z',
            'M9 21V12h6v9',
        ],
    },
    {
        // checkmark-circle-outline
        label: 'Zusage',
        paths: [
            'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
            'M7.5 12l3 3 6-6',
        ],
    },
    {
        // images-outline (zwei überlappende Fotorahmen)
        label: 'Fotos',
        paths: [
            'M20 5H9a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z',
            'M4 8H3a1 1 0 0 0-1 1v10a2 2 0 0 0 2 2h10a1 1 0 0 0 1-1v-1',
        ],
    },
    {
        // beer-outline (Krug mit Henkel)
        label: 'Spiel',
        paths: [
            'M6 2h10l-1.5 17a1.5 1.5 0 0 1-1.5 1.4H9a1.5 1.5 0 0 1-1.5-1.4L6 2z',
            'M17 7.5h2a1.5 1.5 0 0 1 0 3h-2',
            'M6 7h10',
        ],
    },
    {
        // settings-outline (Zahnrad)
        label: 'Einst.',
        paths: [
            'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z',
            'M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41',
        ],
    },
];

// Hilfsfunktion für Radio-Selektor-Klasse
const radioClass = (formRole: string | null, optKey: string, fallback: PaletteKey) =>
    (formRole ?? fallback) === optKey ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground';
</script>

<template>
    <Head :title="t('event.settings')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <!-- Split-Screen: Mobile = oben Preview / unten Form; Desktop = links Form / rechts Preview -->
        <div class="flex h-[calc(100vh-4rem)] flex-col overflow-hidden lg:grid lg:grid-cols-2 lg:gap-6 lg:px-4 lg:pt-4">
            <!-- Form — unten auf Mobile (order-last), links auf Desktop (order-first) -->
            <div class="order-last overflow-y-auto px-4 pt-2 pb-24 lg:order-first lg:px-0 lg:pt-0">
                <div class="space-y-4">
                    <!-- Linke Spalte: Formular -->
                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Basis -->
                        <Card>
                            <CardHeader
                                ><CardTitle>{{ t('event.settings') }}</CardTitle></CardHeader
                            >
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
                                        <Label>{{ t('event.dresscode') }} <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueOptional') }})</span></Label>
                                        <textarea
                                            v-model="form.dresscode"
                                            rows="2"
                                            class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            :placeholder="t('event.dresscode')"
                                        />
                                    </div>
                                    <!-- Veranstaltungsort-Name (optional) -->
                                    <div class="grid gap-2">
                                        <Label
                                            >{{ t('event.venueName') }}
                                            <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueNameOptional') }})</span></Label
                                        >
                                        <div class="relative">
                                            <Input v-model="form.venue_name" :placeholder="t('event.venueNamePlaceholder')" :class="isFieldDirty('venue_name') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                            <button v-if="isFieldDirty('venue_name')" type="button" @click="resetField('venue_name')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                        </div>
                                        <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.venueNameHint') }}</p>
                                    </div>
                                    <!-- Venue-Anzeige auf dem Home-Screen -->
                                    <div class="grid gap-2">
                                        <Label>{{ t('event.venueDisplayMode') }}</Label>
                                        <div class="flex gap-2">
                                            <button
                                                v-for="opt in [
                                                    { key: 'address', label: t('event.venueDisplayModeAddress') },
                                                    { key: 'name', label: t('event.venueDisplayModeName') },
                                                    { key: 'both', label: t('event.venueDisplayModeBoth') },
                                                ]"
                                                :key="opt.key"
                                                type="button"
                                                @click="form.venue_display_mode = opt.key"
                                                class="flex-1 rounded-lg border-2 px-2 py-1.5 text-center text-xs transition-colors"
                                                :class="(form.venue_display_mode ?? 'both') === opt.key ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground'"
                                            >
                                                {{ opt.label }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Strukturierte Adresse -->
                                    <div class="grid gap-3 rounded-lg border border-input p-3">
                                        <!-- DE-Form -->
                                        <template v-if="isGermanyForm">
                                            <div class="grid grid-cols-[1fr_80px] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueStreet') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_street" :placeholder="t('event.venueStreet')" :class="isFieldDirty('venue_street') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_street')" type="button" @click="resetField('venue_street')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueHouseNumber') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_house_number" placeholder="26" :class="isFieldDirty('venue_house_number') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_house_number')" type="button" @click="resetField('venue_house_number')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-[100px_1fr] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venuePostalCode') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_postal_code" placeholder="56218" :class="isFieldDirty('venue_postal_code') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_postal_code')" type="button" @click="resetField('venue_postal_code')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueCity') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_city" :placeholder="t('event.venueCity')" :class="isFieldDirty('venue_city') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_city')" type="button" @click="resetField('venue_city')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- International Form -->
                                        <template v-else>
                                            <div class="grid gap-1.5">
                                                <Label class="text-xs">{{ t('event.venueAddressLine1') }}</Label>
                                                <div class="relative">
                                                    <Input v-model="form.venue_street" :placeholder="t('event.venueAddressLine1Placeholder')" :class="isFieldDirty('venue_street') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                    <button v-if="isFieldDirty('venue_street')" type="button" @click="resetField('venue_street')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-[1fr_120px] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueCity') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_city" :placeholder="t('event.venueCity')" :class="isFieldDirty('venue_city') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_city')" type="button" @click="resetField('venue_city')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venuePostalCode') }}</Label>
                                                    <div class="relative">
                                                        <Input v-model="form.venue_postal_code" placeholder="10001" :class="isFieldDirty('venue_postal_code') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                        <button v-if="isFieldDirty('venue_postal_code')" type="button" @click="resetField('venue_postal_code')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid gap-1.5">
                                                <Label class="text-xs"
                                                    >{{ t('event.venueState') }}
                                                    <span class="font-normal text-muted-foreground">({{ t('event.venueOptional') }})</span></Label
                                                >
                                                <div class="relative">
                                                    <Input v-model="form.venue_state" :placeholder="t('event.venueState')" :class="isFieldDirty('venue_state') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''" />
                                                    <button v-if="isFieldDirty('venue_state')" type="button" @click="resetField('venue_state')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Land (immer sichtbar) -->
                                        <div ref="countryInputRef" class="grid gap-1.5">
                                            <Label class="text-xs">{{ t('event.venueCountry') }}</Label>
                                            <div class="relative">
                                                <Input
                                                    v-model="countryQuery"
                                                    :placeholder="t('event.venueCountry')"
                                                    autocomplete="off"
                                                    :class="isFieldDirty('venue_country') ? 'border-amber-400 ring-2 ring-amber-400/30 pr-8' : ''"
                                                    @focus="
                                                        countryOpen = true;
                                                        updateCountryDropdownStyle();
                                                    "
                                                    @input="
                                                        countryOpen = true;
                                                        updateCountryDropdownStyle();
                                                    "
                                                    @blur="
                                                        setTimeout(() => {
                                                            countryOpen = false;
                                                        }, 150)
                                                    "
                                                />
                                                <button v-if="isFieldDirty('venue_country')" type="button" @click="resetField('venue_country')" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700" title="Zurücksetzen">↺</button>
                                                <Teleport to="body">
                                                    <div
                                                        v-if="countryOpen && filteredCountries.length"
                                                        class="fixed z-[9999] max-h-52 overflow-y-auto rounded-md border bg-popover shadow-lg"
                                                        :style="countryDropdownStyle"
                                                    >
                                                        <button
                                                            v-for="country in filteredCountries"
                                                            :key="country"
                                                            type="button"
                                                            class="flex w-full items-center px-3 py-2 text-sm hover:bg-accent"
                                                            :class="form.venue_country === country ? 'bg-muted font-medium' : ''"
                                                            @mousedown.prevent="selectCountry(country)"
                                                        >
                                                            {{ country }}
                                                        </button>
                                                    </div>
                                                </Teleport>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Map-Picker -->
                                    <div class="grid gap-2">
                                        <div class="flex items-center justify-between">
                                            <Label>{{ t('event.venueMap') }}</Label>
                                            <span v-if="reverseGeocoding" class="text-xs text-muted-foreground animate-pulse">{{ t('event.venueGeocoding') }}</span>
                                            <button v-else-if="form.venue_lat && form.venue_lng"
                                                type="button" class="text-xs text-muted-foreground hover:text-destructive"
                                                @click="clearMapCoords">
                                                {{ t('event.venueMapClear') }}
                                            </button>
                                        </div>
                                        <p class="text-xs text-muted-foreground -mt-1">{{ t('event.venueMapHint') }}</p>
                                        <!-- Map search -->
                                        <div ref="mapSearchInputRef" class="relative">
                                            <Input
                                                v-model="mapSearchQuery"
                                                :placeholder="t('event.venueMapSearch')"
                                                autocomplete="off"
                                                @focus="mapSearchResults.length && (mapSearchOpen = true)"
                                                @blur="setTimeout(() => { mapSearchOpen = false }, 150)"
                                            />
                                            <div v-if="mapSearching" class="absolute right-2.5 top-1/2 -translate-y-1/2">
                                                <svg class="h-4 w-4 animate-spin text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                                </svg>
                                            </div>
                                            <Teleport to="body">
                                                <div v-if="mapSearchOpen && mapSearchResults.length"
                                                    class="fixed z-[9999] max-h-60 overflow-y-auto rounded-md border bg-popover shadow-lg"
                                                    :style="mapSearchDropdownStyle">
                                                    <button v-for="r in mapSearchResults" :key="r.place_id"
                                                        type="button"
                                                        class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-accent"
                                                        @mousedown.prevent="selectMapResult(r)">
                                                        <span class="font-medium truncate">{{ r.name || r.display_name.split(', ')[0] }}</span>
                                                        <span class="text-xs text-muted-foreground truncate">{{ r.display_name }}</span>
                                                    </button>
                                                </div>
                                            </Teleport>
                                        </div>
                                        <div ref="mapContainer" class="h-56 w-full overflow-hidden rounded-lg border border-input" style="z-index:0;" />
                                        <p v-if="form.venue_lat && form.venue_lng" class="text-xs text-muted-foreground">
                                            {{ form.venue_lat.toFixed(6) }}, {{ form.venue_lng.toFixed(6) }}
                                        </p>
                                    </div>

                                </div>
                            </CardContent>
                        </Card>

                        <!-- Cover-Upload -->
                        <Card>
                            <CardHeader
                                ><CardTitle>{{ t('event.cover') }}</CardTitle></CardHeader
                            >
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
                                        isDraggingCover
                                            ? 'scale-[1.01] border-ring bg-muted/30'
                                            : 'border-input hover:border-muted-foreground hover:bg-muted/20',
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

                                <!-- Home-Screen Farbe — nur wenn Cover vorhanden -->
                                <div v-if="displayCoverUrl" class="grid gap-1.5 pt-1">
                                    <span class="text-xs text-muted-foreground">{{ t('event.colorHomeText') }}</span>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="color"
                                            v-model="form.color_home_text"
                                            class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                                        />
                                        <Input
                                            v-model="form.color_home_text"
                                            class="px-2 font-mono text-xs uppercase"
                                            maxlength="7"
                                            placeholder="#ffffff"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Design: Schrift + Farben -->
                        <Card>
                            <CardContent>
                                <div class="space-y-4 pt-4">
                                    <!-- Schrift -->
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

                                    <!-- Farb-Palette -->
                                    <div class="grid gap-3">
                                        <Label>{{ t('event.colorHint') }}</Label>
                                        <!-- 3 Basis-Picker -->
                                        <div class="grid grid-cols-3 gap-3">
                                            <div
                                                v-for="(key, idx) in ['color_primary', 'color_secondary', 'color_tertiary'] as const"
                                                :key="key"
                                                class="grid gap-1.5"
                                            >
                                                <span class="text-xs text-muted-foreground">{{
                                                    [t('event.colorPrimary'), t('event.colorSecondary'), t('event.colorTertiary')][idx]
                                                }}</span>
                                                <div class="flex items-center gap-1.5">
                                                    <input
                                                        type="color"
                                                        v-model="form[key]"
                                                        class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                                                    />
                                                    <Input v-model="form[key]" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Radio-Selektoren -->
                                        <div class="space-y-3 pt-1">
                                            <!-- Screen-Hintergrund -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleScreenBg') }}</span><button type="button" @click="showHint('screenBg')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'sb' + opt.key"
                                                        type="button"
                                                        @click="form.role_screen_bg = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_screen_bg, opt.key, 'secondary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Card-Hintergrund -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleCardBg') }}</span><button type="button" @click="showHint('cardBg')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'cb' + opt.key"
                                                        type="button"
                                                        @click="form.role_card_bg = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_card_bg, opt.key, 'tertiary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Text auf Cards -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleCardText') }}</span><button type="button" @click="showHint('cardText')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'ct' + opt.key"
                                                        type="button"
                                                        @click="form.role_card_text = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_card_text, opt.key, 'primary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Button auf Cards -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleCardButton') }}</span><button type="button" @click="showHint('cardButton')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'cbt' + opt.key"
                                                        type="button"
                                                        @click="form.role_card_button = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_card_button, opt.key, 'primary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Text auf Card-Buttons -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleCardButtonText') }}</span><button type="button" @click="showHint('cardButtonText')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'cbtx' + opt.key"
                                                        type="button"
                                                        @click="form.role_card_button_text = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_card_button_text, opt.key, 'tertiary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Navbar-Farbe -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleTabTint') }}</span><button type="button" @click="showHint('tabTint')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'tt' + opt.key"
                                                        type="button"
                                                        @click="form.role_tab_tint = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_tab_tint, opt.key, 'primary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Rahmenfarbe -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleBorder') }}</span><button type="button" @click="showHint('border')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'br' + opt.key"
                                                        type="button"
                                                        @click="form.role_border = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_border, opt.key, 'primary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- FAB-Button -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleFab') }}</span><button type="button" @click="showHint('fab')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'fab' + opt.key"
                                                        type="button"
                                                        @click="form.role_fab = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_fab, opt.key, 'primary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Icon-Farbe im FAB -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between"><span class="text-xs text-muted-foreground">{{ t('event.roleFabIcon') }}</span><button type="button" @click="showHint('fabIcon')" class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10">?</button></div>
                                                <div class="flex gap-2">
                                                    <button
                                                        v-for="opt in colorOptions"
                                                        :key="'fabi' + opt.key"
                                                        type="button"
                                                        @click="form.role_fab_icon = opt.key"
                                                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                                                        :class="radioClass(form.role_fab_icon, opt.key, 'tertiary')"
                                                    >
                                                        <div
                                                            class="h-6 w-6 rounded-full border border-black/10 shadow-sm"
                                                            :style="{ backgroundColor: opt.value }"
                                                        />
                                                        <span>{{ opt.label }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>
            </div>

            <!-- Preview — oben auf Mobile (order-first), rechts auf Desktop (order-last) -->
            <div class="order-first h-[50vh] overflow-hidden lg:order-last lg:h-full lg:overflow-y-auto lg:pb-4">
                <div class="flex flex-col items-center gap-2 pt-3 lg:pt-0">
                    <p class="text-sm font-medium text-muted-foreground">{{ t('event.phonePreview') }}</p>

                    <!-- Mobile: horizontal scroll, 2 sichtbar; Desktop: 2×2 Grid -->
                    <div class="w-full overflow-x-auto lg:overflow-x-visible">
                        <div class="flex gap-4 px-4 pb-4 lg:grid lg:grid-cols-2 lg:px-0">
                            <!-- ===== SCREEN 1: HOME ===== -->
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="text-[11px] font-medium text-muted-foreground">Home</span>
                                <div style="width: 240px; height: 488px; overflow: hidden; flex-shrink: 0">
                                    <div style="transform: scale(2); transform-origin: top left">
                                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width: 120px">
                                            <!-- Mit Cover -->
                                            <div
                                                v-if="displayCoverUrl"
                                                class="relative flex flex-col"
                                                style="height: 244px; background-size: cover; background-position: center"
                                                :style="{ backgroundImage: `url('${displayCoverUrl}')`, fontFamily: previewFontFamily }"
                                            >
                                                <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/5 to-black/75" />
                                                <div
                                                    class="relative flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-white"
                                                >
                                                    <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                                                </div>
                                                <div class="relative flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                                    <p class="text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">
                                                        Willkommen, Max!
                                                    </p>
                                                    <p
                                                        class="mt-0.5 text-center text-[9px] leading-tight font-bold"
                                                        :style="{ color: form.color_home_text || '#ffffff', fontFamily: previewFontFamily }"
                                                    >
                                                        {{ form.name || 'Event-Name' }}
                                                    </p>
                                                    <p class="mt-0.5 text-center text-[7px]" :style="{ color: form.color_home_text || '#ffffff' }">
                                                        {{ previewDate || 'Sa., 1. Januar 2026' }}
                                                    </p>
                                                    <p v-if="form.venue_display_mode !== 'address'" class="mt-0.5 text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">
                                                        {{ previewVenueName }}
                                                    </p>
                                                    <p v-if="form.venue_display_mode !== 'name'" class="text-center text-[6px]" :style="{ color: form.color_home_text || '#ffffff' }">
                                                        {{ previewVenueAddress }}
                                                    </p>
                                                    <p
                                                        class="mt-1.5 text-center text-[6px] font-bold"
                                                        :style="{ color: form.color_home_text || '#ffffff' }"
                                                    >
                                                        Noch {{ previewDaysLeft ? previewDaysLeft + 'T' : '6T 11Std 22Min' }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="relative flex h-[26px] w-full items-end justify-around border-t border-white/15 bg-black/30 pb-1.5"
                                                >
                                                    <div v-for="(tab, i) in tabDefs" :key="'h' + i" class="flex flex-col items-center gap-0.5">
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :stroke="
                                                                i === 0
                                                                    ? form.color_home_text || '#ffffff'
                                                                    : form.color_home_text
                                                                      ? form.color_home_text + '77'
                                                                      : 'rgba(255,255,255,0.45)'
                                                            "
                                                        >
                                                            <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                                        </svg>
                                                        <span
                                                            class="text-[5px]"
                                                            :style="{
                                                                color:
                                                                    i === 0
                                                                        ? form.color_home_text || '#ffffff'
                                                                        : form.color_home_text
                                                                          ? form.color_home_text + '77'
                                                                          : 'rgba(255,255,255,0.45)',
                                                                fontWeight: i === 0 ? '700' : '400',
                                                            }"
                                                            >{{ tab.label }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Ohne Cover: normale App-Farben -->
                                            <div
                                                v-else
                                                class="flex flex-col"
                                                style="height: 244px"
                                                :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                                                :class="hintBgClass('screenBg')"
                                            >
                                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                                    <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                                                </div>
                                                <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                                                    <p class="text-center text-[6px]" :style="{ color: cCardText }">Willkommen, Max!</p>
                                                    <p
                                                        class="mt-0.5 text-center text-[9px] leading-tight font-bold"
                                                        :style="{ color: cCardText, fontFamily: previewFontFamily }"
                                                    >
                                                        {{ form.name || 'Event-Name' }}
                                                    </p>
                                                    <p class="mt-0.5 text-center text-[7px]" :style="{ color: cCardText }">
                                                        {{ previewDate || 'Sa., 1. Januar 2026' }}
                                                    </p>
                                                    <p v-if="form.venue_display_mode !== 'address'" class="mt-0.5 text-center text-[6px]" :style="{ color: cCardText }">
                                                        {{ previewVenueName }}
                                                    </p>
                                                    <p v-if="form.venue_display_mode !== 'name'" class="text-center text-[6px]" :style="{ color: cCardText }">
                                                        {{ previewVenueAddress }}
                                                    </p>
                                                    <p class="mt-1.5 text-center text-[6px] font-bold" :style="{ color: cCardText }">
                                                        Noch {{ previewDaysLeft ? previewDaysLeft + 'T' : '6T 11Std 22Min' }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div v-for="(tab, i) in tabDefs" :key="'hn' + i" class="flex flex-col items-center gap-0.5" :class="hintTextClass('tabTint')">
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :stroke="i === 0 ? cTabTint : cTabTint + '55'"
                                                        >
                                                            <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                                        </svg>
                                                        <span
                                                            class="text-[5px]"
                                                            :style="{
                                                                color: i === 0 ? cTabTint : cTabTint + '55',
                                                                fontWeight: i === 0 ? '700' : '400',
                                                            }"
                                                            >{{ tab.label }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ===== SCREEN 2: ZUSAGE ===== -->
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="text-[11px] font-medium text-muted-foreground">Zusage</span>
                                <div style="width: 240px; height: 488px; overflow: hidden; flex-shrink: 0">
                                    <div style="transform: scale(2); transform-origin: top left">
                                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width: 120px">
                                            <div
                                                class="flex flex-col"
                                                style="height: 244px"
                                                :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                                                :class="hintBgClass('screenBg')"
                                            >
                                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                                    <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                                                </div>
                                                <div class="flex flex-1 flex-col gap-1.5 overflow-hidden px-1.5 pt-1">
                                                    <!-- Card 1 -->
                                                    <div
                                                        class="rounded-lg p-1.5 shadow-sm"
                                                        :style="{
                                                            backgroundColor: cCardBg,
                                                            borderWidth: '1px',
                                                            borderStyle: 'solid',
                                                            borderColor: cBorder + '33',
                                                        }"
                                                        :class="[hintBgClass('cardBg'), hintClass('border')]"
                                                    >
                                                        <p class="mb-0.5 text-[5px]" :style="{ color: cCardText + '77' }" :class="hintTextClass('cardText')">
                                                            Bitte antworte bis 25. März.
                                                        </p>
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[7px] font-semibold" :style="{ color: cCardText }" :class="hintTextClass('cardText')">Max Mustermann</span>
                                                            <span
                                                                class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white"
                                                                style="background-color: #4a7c59"
                                                                >Zugesagt</span
                                                            >
                                                        </div>
                                                        <div class="mt-1 flex gap-0.5">
                                                            <div
                                                                class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white"
                                                                style="background-color: #4a7c59"
                                                            >
                                                                Zusagen
                                                            </div>
                                                            <div
                                                                class="flex-1 rounded py-0.5 text-center text-[5px] font-semibold text-white"
                                                                style="background-color: #b45a3c"
                                                            >
                                                                Absagen
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Card 2 -->
                                                    <div
                                                        class="rounded-lg p-1.5 shadow-sm"
                                                        :style="{
                                                            backgroundColor: cCardBg,
                                                            borderWidth: '1px',
                                                            borderStyle: 'solid',
                                                            borderColor: cBorder + '33',
                                                        }"
                                                        :class="[hintBgClass('cardBg'), hintClass('border')]"
                                                    >
                                                        <p class="text-[7px] font-semibold" :style="{ color: cCardText }" :class="hintTextClass('cardText')">Deine Gruppe</p>
                                                        <p class="mb-1 text-[5px]" :style="{ color: cCardText + '77' }" :class="hintTextClass('cardText')">
                                                            Du kannst für deine Gruppe antworten.
                                                        </p>
                                                        <div
                                                            v-for="(member, mi) in [
                                                                { name: 'Anna M.', status: 'Zugesagt' },
                                                                { name: 'Klaus M.', status: 'Zugesagt' },
                                                                { name: 'Lisa M.', status: 'Abgesagt', red: true },
                                                            ]"
                                                            :key="mi"
                                                            class="border-t py-0.5 first:border-t-0"
                                                            :style="{ borderColor: cBorder + '22' }"
                                                            :class="hintSepClass('border')"
                                                        >
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-[6px] font-semibold" :style="{ color: cCardText }" :class="hintTextClass('cardText')">{{
                                                                    member.name
                                                                }}</span>
                                                                <div class="flex items-center gap-0.5">
                                                                    <span
                                                                        class="rounded-full px-1 py-0.5 text-[4px] font-semibold"
                                                                        :style="{ backgroundColor: member.red ? '#b45a3c' : '#4a7c59', color: '#ffffff' }"
                                                                        >{{ member.status }}</span
                                                                    >
                                                                    <svg width="6" height="6" viewBox="0 0 24 24" fill="none" :stroke="cCardText + '88'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                                                </div>
                                                            </div>
                                                            <p class="text-[4px]" :style="{ color: cCardText + '66' }" :class="hintTextClass('cardText')">Von dir gesetzt</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div v-for="(tab, i) in tabDefs" :key="'z' + i" class="flex flex-col items-center gap-0.5" :class="hintTextClass('tabTint')">
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :stroke="i === 1 ? cTabTint : cTabTint + '55'"
                                                        >
                                                            <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                                        </svg>
                                                        <span
                                                            class="text-[5px]"
                                                            :style="{
                                                                color: i === 1 ? cTabTint : cTabTint + '55',
                                                                fontWeight: i === 1 ? '700' : '400',
                                                            }"
                                                            >{{ tab.label }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ===== SCREEN 3: FOTOS ===== -->
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="text-[11px] font-medium text-muted-foreground">Fotos</span>
                                <div style="width: 240px; height: 488px; overflow: hidden; flex-shrink: 0">
                                    <div style="transform: scale(2); transform-origin: top left">
                                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width: 120px">
                                            <div
                                                class="flex flex-col"
                                                style="height: 244px"
                                                :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                                                :class="hintBgClass('screenBg')"
                                            >
                                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                                    <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                                                </div>
                                                <div class="relative flex-1 px-0.5 pt-1">
                                                    <div class="grid grid-cols-3 gap-0.5">
                                                        <div
                                                            v-for="n in 6"
                                                            :key="n"
                                                            class="rounded-sm"
                                                            style="background-color: #d4cfc8; aspect-ratio: 1"
                                                        />
                                                    </div>
                                                    <!-- FAB mit cFab-Farbe -->
                                                    <div
                                                        class="absolute right-2 bottom-3 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                                        :style="{ backgroundColor: cFab }"
                                                        :class="hintTextClass('fab')"
                                                    >
                                                        <svg
                                                            width="12"
                                                            height="12"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            :stroke="cFabIcon"
                                                            stroke-width="2.2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :class="hintTextClass('fabIcon')"
                                                        >
                                                            <path
                                                                d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"
                                                            />
                                                            <circle cx="12" cy="13" r="4" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div v-for="(tab, i) in tabDefs" :key="'f' + i" class="flex flex-col items-center gap-0.5" :class="hintTextClass('tabTint')">
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :stroke="i === 2 ? cTabTint : cTabTint + '55'"
                                                        >
                                                            <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                                        </svg>
                                                        <span
                                                            class="text-[5px]"
                                                            :style="{
                                                                color: i === 2 ? cTabTint : cTabTint + '55',
                                                                fontWeight: i === 2 ? '700' : '400',
                                                            }"
                                                            >{{ tab.label }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ===== SCREEN 4: EINSTELLUNGEN ===== -->
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="text-[11px] font-medium text-muted-foreground">Einstellungen</span>
                                <div style="width: 240px; height: 488px; overflow: hidden; flex-shrink: 0">
                                    <div style="transform: scale(2); transform-origin: top left">
                                        <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width: 120px">
                                            <div
                                                class="flex flex-col"
                                                style="height: 244px"
                                                :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                                                :class="hintBgClass('screenBg')"
                                            >
                                                <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                                                    <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                                                </div>
                                                <div class="flex flex-1 flex-col items-start px-2 pt-3">
                                                    <div
                                                        class="w-full rounded-xl shadow-sm"
                                                        :style="{
                                                            backgroundColor: cCardBg,
                                                            borderWidth: '1px',
                                                            borderStyle: 'solid',
                                                            borderColor: cBorder + '33',
                                                        }"
                                                        :class="[hintBgClass('cardBg'), hintClass('border')]"
                                                    >
                                                        <div class="px-2 pt-2 pb-1.5">
                                                            <p class="text-[5px]" :style="{ color: cCardText + '88', fontFamily: previewFontFamily }" :class="hintTextClass('cardText')">
                                                                Eingeloggt als
                                                            </p>
                                                            <p
                                                                class="text-[7px] font-semibold"
                                                                :style="{ color: cCardText, fontFamily: previewFontFamily }"
                                                                :class="hintTextClass('cardText')"
                                                            >
                                                                Max Mustermann
                                                            </p>
                                                            <p class="text-[5px]" :style="{ color: cCardText + '88', fontFamily: previewFontFamily }" :class="hintTextClass('cardText')">
                                                                Familie Mustermann
                                                            </p>
                                                        </div>
                                                        <div class="mx-2 border-t" :style="{ borderColor: cBorder + '33' }" :class="hintTextClass('border')"></div>
                                                        <div class="px-2 py-1.5">
                                                            <p
                                                                class="mb-1 text-[5px]"
                                                                :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                                                :class="hintTextClass('cardText')"
                                                            >
                                                                Sprache
                                                            </p>
                                                            <div class="flex rounded-lg border" :style="{ borderColor: cBorder + '55' }" :class="hintClass('border')">
                                                                <div
                                                                    class="flex-1 rounded-l-lg py-0.5 text-center text-[5px] font-semibold"
                                                                    :style="{
                                                                        backgroundColor: cCardButton,
                                                                        color: cCardButtonText,
                                                                        fontFamily: previewFontFamily,
                                                                    }"
                                                                    :class="hintBgClass('cardButton')"
                                                                ><span :class="hintTextClass('cardButtonText')">Deutsch</span></div>
                                                                <div
                                                                    class="flex-1 py-0.5 text-center text-[5px]"
                                                                    :style="{
                                                                        backgroundColor: cCardBg,
                                                                        color: cCardText + '66',
                                                                        fontFamily: previewFontFamily,
                                                                    }"
                                                                >
                                                                    Englisch
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="mx-2 border-t" :style="{ borderColor: cBorder + '33' }" :class="hintTextClass('border')"></div>
                                                        <div
                                                            class="mx-2 my-1.5 rounded-lg py-1 text-center text-[6px] font-semibold"
                                                            :style="{
                                                                backgroundColor: cCardButton,
                                                                color: cCardButtonText,
                                                                fontFamily: previewFontFamily,
                                                            }"
                                                            :class="hintBgClass('cardButton')"
                                                        ><span :class="hintTextClass('cardButtonText')">Ausloggen</span></div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div v-for="(tab, i) in tabDefs" :key="'e' + i" class="flex flex-col items-center gap-0.5" :class="hintTextClass('tabTint')">
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            :stroke="i === 4 ? cTabTint : cTabTint + '55'"
                                                        >
                                                            <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                                        </svg>
                                                        <span
                                                            class="text-[5px]"
                                                            :style="{
                                                                color: i === 4 ? cTabTint : cTabTint + '55',
                                                                fontWeight: i === 4 ? '700' : '400',
                                                            }"
                                                            >{{ tab.label }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /flex oder grid -->
                    </div>
                    <!-- /overflow-x-auto -->
                </div>
            </div>
        </div>
        <!-- /split-screen -->

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

@keyframes preview-hint {
    0%, 100% { box-shadow: inset 0 0 0 0px #f59e0b; }
    50% { box-shadow: inset 0 0 0 2px #f59e0b; }
}
.preview-hint {
    animation: preview-hint 0.4s ease-in-out 5;
}
@keyframes preview-hint-text {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.15; }
}
.preview-hint-text {
    animation: preview-hint-text 0.4s ease-in-out 5;
}
@keyframes preview-hint-bg {
    0%, 100% { box-shadow: inset 0 0 0 1000px rgba(251, 191, 36, 0); }
    50% { box-shadow: inset 0 0 0 1000px rgba(251, 191, 36, 0.3); }
}
.preview-hint-bg {
    animation: preview-hint-bg 0.4s ease-in-out 5;
}
@keyframes preview-hint-sep {
    0%, 100% { box-shadow: inset 0 1px 0 0 rgba(251, 191, 36, 0); }
    50% { box-shadow: inset 0 1px 0 0 rgba(251, 191, 36, 0.9); }
}
.preview-hint-sep {
    animation: preview-hint-sep 0.4s ease-in-out 5;
}
</style>
