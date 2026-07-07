<script setup lang="ts">
import PhonePreviewHome from '@/components/EventSettings/PhonePreview/Home.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFloatingBar } from '@/composables/useFloatingBar';
import AppLayout from '@/layouts/AppLayout.vue';
import { contrastRatio, WCAG_AA_NORMAL } from '@/lib/colorContrast';
import { buildPalette, resolveRole, type PaletteKey } from '@/lib/colorResolver';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import heic2any from 'heic2any';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

// Leaflet default icon fix for Vite
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
delete (L.Icon.Default.prototype as any)._getIconUrl;
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
    drink_game_enabled: boolean;
    photo_game_enabled: boolean;
    projector_token: string | null;
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
    drink_game_enabled: props.event.drink_game_enabled ?? false,
    photo_game_enabled: props.event.photo_game_enabled ?? false,
    cover: null as File | null,
});

const skipGuard = ref(false);
const isDirty = computed(() => form.isDirty);

// Field-level dirty detection for address section
const savedAddress = reactive<Record<string, string>>({
    venue_name: props.event.venue_name ?? '',
    venue_street: props.event.venue_street ?? '',
    venue_house_number: props.event.venue_house_number ?? '',
    venue_postal_code: props.event.venue_postal_code ?? '',
    venue_city: props.event.venue_city ?? '',
    venue_state: props.event.venue_state ?? '',
    venue_country: props.event.venue_country ?? 'Deutschland',
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
// Unified amber overlay for all elements
function hintBgClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-bg' : '';
}
// For frames/borders: slightly lighter amber (0.5 instead of 0.3)
function hintBorderClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-border' : '';
}

function hintFilterClass(hint: string): string {
    return activeHint.value === hint ? 'preview-hint-filter' : '';
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
    // Map lazy-init: wait briefly until DOM is rendered
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
    if (leafletMap) {
        leafletMap.remove();
        leafletMap = null;
    }
    removeInertiaGuard?.();
    floatingBarActive.value = false;
    if (previewCountdownInterval) clearInterval(previewCountdownInterval);
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
            // Update dirty baseline
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
watch(
    () => props.event.cover_image_url,
    (val) => {
        coverUrl.value = val;
    },
);
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
    const isHeic = /heic|heif/i.test(file.name) || file.type === 'image/heic' || file.type === 'image/heif';
    const needsConvert = isHeic || file.type === 'image/png' || file.type === 'image/webp';

    if (isHeic) {
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
    } else if (needsConvert) {
        // PNG / WebP → JPEG via Canvas
        coverConverting.value = true;
        try {
            const bitmap = await createImageBitmap(file);
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            canvas.getContext('2d')!.drawImage(bitmap, 0, 0);
            const blob = await new Promise<Blob>((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject()), 'image/jpeg', 0.92));
            file = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' });
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
        return new Date(form.date).toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return null;
    }
});

const previewCountdown = ref<string | null>(null);
let previewCountdownInterval: ReturnType<typeof setInterval> | null = null;

function updatePreviewCountdown() {
    if (!form.date) {
        previewCountdown.value = null;
        return;
    }
    const diff = new Date(form.date).getTime() - Date.now();
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

watch(
    () => form.date,
    () => {
        updatePreviewCountdown();
        if (previewCountdownInterval) clearInterval(previewCountdownInterval);
        if (form.date) previewCountdownInterval = setInterval(updatePreviewCountdown, 1000);
    },
    { immediate: true },
);

// --- Address form ---
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

function deferCloseCountry() {
    setTimeout(() => {
        countryOpen.value = false;
    }, 150);
}

function deferCloseMapSearch() {
    setTimeout(() => {
        mapSearchOpen.value = false;
    }, 150);
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

// --- Leaflet map picker ---
const mapContainer = ref<HTMLElement | null>(null);
const reverseGeocoding = ref(false);
let leafletMap: L.Map | null = null;
let mapMarker: L.Marker | null = null;

function initMap() {
    if (!mapContainer.value || leafletMap) return;
    const center: [number, number] = form.venue_lat && form.venue_lng ? [form.venue_lat, form.venue_lng] : [51.1657, 10.4515];
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
        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`, {
            headers: { 'Accept-Language': 'de' },
        });
        const data = await res.json();
        const addr = data.address;
        if (!addr) return;
        form.venue_street = addr.road ?? addr.pedestrian ?? addr.path ?? '';
        form.venue_house_number = addr.house_number ?? '';
        form.venue_postal_code = addr.postcode ?? '';
        form.venue_city = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
        form.venue_state = addr.state ?? '';
        const country = addr.country ?? 'Deutschland';
        form.venue_country = country;
        countryQuery.value = country;
    } catch {
        /* silent — user can fill in manually */
    } finally {
        reverseGeocoding.value = false;
    }
}

function clearMapCoords() {
    form.venue_lat = null;
    form.venue_lng = null;
    if (mapMarker && leafletMap) {
        leafletMap.removeLayer(mapMarker);
        mapMarker = null;
    }
}

// --- Map search (forward geocode to center) ---
interface NominatimResult {
    place_id: number;
    lat: string;
    lon: string;
    display_name: string;
    name: string;
    address: Record<string, string>;
}

const mapSearchQuery = ref('');
const mapSearchResults = ref<NominatimResult[]>([]);
const mapSearching = ref(false);
const mapSearchOpen = ref(false);
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
    if (!val || val.length < 2) {
        mapSearchResults.value = [];
        mapSearchOpen.value = false;
        return;
    }
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
        } catch {
            mapSearchResults.value = [];
        } finally {
            mapSearching.value = false;
        }
    }, 350);
});

function selectMapResult(result: NominatimResult) {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    // Center the map + zoom
    if (leafletMap) {
        leafletMap.setView([lat, lng], 17);
    }

    // Set pin
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

    // Populate address fields from the Nominatim result
    const addr = result.address;
    form.venue_street = addr.road ?? addr.pedestrian ?? addr.path ?? '';
    form.venue_house_number = addr.house_number ?? '';
    form.venue_postal_code = addr.postcode ?? '';
    form.venue_city = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
    form.venue_state = addr.state ?? '';
    const country = addr.country ?? 'Deutschland';
    form.venue_country = country;
    countryQuery.value = country;

    mapSearchQuery.value = '';
    mapSearchResults.value = [];
    mapSearchOpen.value = false;
}

// --- Geocode from address fields ---
const geocodingFromFields = ref(false);

async function geocodeFromFields() {
    const parts: string[] = [];
    const street = [form.venue_street, form.venue_house_number].filter(Boolean).join(' ');
    if (street) parts.push(street);
    if (form.venue_postal_code) parts.push(form.venue_postal_code);
    if (form.venue_city) parts.push(form.venue_city);
    if (form.venue_country && form.venue_country !== 'Deutschland') parts.push(form.venue_country);
    if (!parts.length) return;

    geocodingFromFields.value = true;
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&limit=1&dedupe=0&q=${encodeURIComponent(parts.join(', '))}&addressdetails=1`,
            { headers: { 'Accept-Language': 'de' } },
        );
        const results: NominatimResult[] = await res.json();
        if (results.length) selectMapResult(results[0]);
    } catch {
        /* ignore */
    } finally {
        geocodingFromFields.value = false;
    }
}

const hasAddressInput = computed(() => !!(form.venue_street || form.venue_city || form.venue_postal_code));

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

// WCAG AA contrast guard for the card text/background pair — the most read
// combination in the app. Warns when the chosen palette collapses below 4.5:1.
const cardContrast = computed(() => contrastRatio(cCardText.value, cCardBg.value));
const cardContrastFailsAA = computed(() => cardContrast.value < WCAG_AA_NORMAL);
const cardContrastLabel = computed(() => cardContrast.value.toFixed(1));

// Options for radio selectors
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

// Helper for radio selector class
const radioClass = (formRole: string | null, optKey: string, fallback: PaletteKey) =>
    (formRole ?? fallback) === optKey ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground';

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
    <Head :title="t('event.settings')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <!-- Split screen: mobile = preview on top (shrink-0) / form below (scroll); desktop = form left / preview right -->
        <div class="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden lg:grid lg:h-[calc(100vh-4rem)] lg:grid-cols-2 lg:gap-6 lg:px-4 lg:pt-4">
            <!-- Form — after preview on mobile (order-last, flex-1 scroll), on the left on desktop -->
            <div class="order-last min-h-0 flex-1 overflow-y-auto px-4 pt-2 pb-24 lg:order-first lg:px-0 lg:pt-0">
                <div class="space-y-4">
                    <!-- Left column: form -->
                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Basics -->
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
                                        <Label
                                            >{{ t('event.dresscode') }}
                                            <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueOptional') }})</span></Label
                                        >
                                        <textarea
                                            v-model="form.dresscode"
                                            rows="2"
                                            class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            :placeholder="t('event.dresscode')"
                                        />
                                    </div>
                                    <!-- Venue name (optional) -->
                                    <div class="grid gap-2">
                                        <Label
                                            >{{ t('event.venueName') }}
                                            <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueNameOptional') }})</span></Label
                                        >
                                        <div class="relative">
                                            <Input
                                                v-model="form.venue_name"
                                                :placeholder="t('event.venueNamePlaceholder')"
                                                :class="isFieldDirty('venue_name') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                                            />
                                            <button
                                                v-if="isFieldDirty('venue_name')"
                                                type="button"
                                                @click="resetField('venue_name')"
                                                class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                title="Zurücksetzen"
                                            >
                                                ↺
                                            </button>
                                        </div>
                                        <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.venueNameHint') }}</p>
                                    </div>
                                    <!-- Venue display on the home screen -->
                                    <div class="grid gap-2">
                                        <div class="flex items-center gap-2">
                                            <Label>{{ t('event.venueDisplayMode') }}</Label>
                                            <InfoTooltip :text="t('event.venueDisplayModeHint')" />
                                        </div>
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
                                                :class="
                                                    (form.venue_display_mode ?? 'both') === opt.key
                                                        ? 'border-ring bg-muted/20'
                                                        : 'border-input hover:border-muted-foreground'
                                                "
                                            >
                                                {{ opt.label }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Structured address -->
                                    <div class="grid gap-3 rounded-lg border border-input p-3">
                                        <!-- DE form -->
                                        <template v-if="isGermanyForm">
                                            <div class="grid grid-cols-[1fr_80px] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueStreet') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_street"
                                                            :placeholder="t('event.venueStreet')"
                                                            :class="
                                                                isFieldDirty('venue_street') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_street')"
                                                            type="button"
                                                            @click="resetField('venue_street')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueHouseNumber') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_house_number"
                                                            placeholder="26"
                                                            :class="
                                                                isFieldDirty('venue_house_number')
                                                                    ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30'
                                                                    : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_house_number')"
                                                            type="button"
                                                            @click="resetField('venue_house_number')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-[100px_1fr] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venuePostalCode') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_postal_code"
                                                            placeholder="56218"
                                                            :class="
                                                                isFieldDirty('venue_postal_code')
                                                                    ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30'
                                                                    : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_postal_code')"
                                                            type="button"
                                                            @click="resetField('venue_postal_code')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueCity') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_city"
                                                            :placeholder="t('event.venueCity')"
                                                            :class="
                                                                isFieldDirty('venue_city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_city')"
                                                            type="button"
                                                            @click="resetField('venue_city')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- International Form -->
                                        <template v-else>
                                            <div class="grid gap-1.5">
                                                <Label class="text-xs">{{ t('event.venueAddressLine1') }}</Label>
                                                <div class="relative">
                                                    <Input
                                                        v-model="form.venue_street"
                                                        :placeholder="t('event.venueAddressLine1Placeholder')"
                                                        :class="isFieldDirty('venue_street') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                                                    />
                                                    <button
                                                        v-if="isFieldDirty('venue_street')"
                                                        type="button"
                                                        @click="resetField('venue_street')"
                                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                        title="Zurücksetzen"
                                                    >
                                                        ↺
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-[1fr_120px] gap-2">
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venueCity') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_city"
                                                            :placeholder="t('event.venueCity')"
                                                            :class="
                                                                isFieldDirty('venue_city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_city')"
                                                            type="button"
                                                            @click="resetField('venue_city')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-1.5">
                                                    <Label class="text-xs">{{ t('event.venuePostalCode') }}</Label>
                                                    <div class="relative">
                                                        <Input
                                                            v-model="form.venue_postal_code"
                                                            placeholder="10001"
                                                            :class="
                                                                isFieldDirty('venue_postal_code')
                                                                    ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30'
                                                                    : ''
                                                            "
                                                        />
                                                        <button
                                                            v-if="isFieldDirty('venue_postal_code')"
                                                            type="button"
                                                            @click="resetField('venue_postal_code')"
                                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                            title="Zurücksetzen"
                                                        >
                                                            ↺
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid gap-1.5">
                                                <Label class="text-xs"
                                                    >{{ t('event.venueState') }}
                                                    <span class="font-normal text-muted-foreground">({{ t('event.venueOptional') }})</span></Label
                                                >
                                                <div class="relative">
                                                    <Input
                                                        v-model="form.venue_state"
                                                        :placeholder="t('event.venueState')"
                                                        :class="isFieldDirty('venue_state') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                                                    />
                                                    <button
                                                        v-if="isFieldDirty('venue_state')"
                                                        type="button"
                                                        @click="resetField('venue_state')"
                                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                        title="Zurücksetzen"
                                                    >
                                                        ↺
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Country (always visible) -->
                                        <div ref="countryInputRef" class="grid gap-1.5">
                                            <Label class="text-xs">{{ t('event.venueCountry') }}</Label>
                                            <div class="relative">
                                                <Input
                                                    v-model="countryQuery"
                                                    :placeholder="t('event.venueCountry')"
                                                    autocomplete="off"
                                                    :class="isFieldDirty('venue_country') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                                                    @focus="
                                                        countryOpen = true;
                                                        updateCountryDropdownStyle();
                                                    "
                                                    @input="
                                                        countryOpen = true;
                                                        updateCountryDropdownStyle();
                                                    "
                                                    @blur="deferCloseCountry"
                                                />
                                                <button
                                                    v-if="isFieldDirty('venue_country')"
                                                    type="button"
                                                    @click="resetField('venue_country')"
                                                    class="absolute top-1/2 right-2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                                                    title="Zurücksetzen"
                                                >
                                                    ↺
                                                </button>
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
                                    <!-- Map picker -->
                                    <div class="grid gap-2">
                                        <div class="flex items-center justify-between">
                                            <Label>{{ t('event.venueMap') }}</Label>
                                            <span
                                                v-if="reverseGeocoding || geocodingFromFields"
                                                class="animate-pulse text-xs text-muted-foreground"
                                                >{{ t('event.venueGeocoding') }}</span
                                            >
                                            <button
                                                v-else-if="form.venue_lat && form.venue_lng"
                                                type="button"
                                                class="text-xs text-muted-foreground hover:text-destructive"
                                                @click="clearMapCoords"
                                            >
                                                {{ t('event.venueMapReset') }}
                                            </button>
                                        </div>
                                        <!-- Geocode from address fields -->
                                        <button
                                            v-if="hasAddressInput && !geocodingFromFields"
                                            type="button"
                                            class="flex items-center gap-1.5 rounded-md border border-dashed border-primary/50 px-3 py-1.5 text-sm text-primary hover:bg-primary/5 disabled:opacity-40"
                                            :disabled="geocodingFromFields"
                                            @click="geocodeFromFields"
                                        >
                                            <svg
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <circle cx="11" cy="11" r="8" />
                                                <path d="m21 21-4.35-4.35" />
                                            </svg>
                                            {{ t('event.venueSearchFromFields') }}
                                        </button>
                                        <!-- Hint: confirm location via map -->
                                        <div
                                            v-if="hasAddressInput && !form.venue_lat && !form.venue_lng"
                                            class="flex items-start gap-2 rounded-md border border-amber-400/50 bg-amber-50/50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
                                        >
                                            <svg
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="mt-0.5 shrink-0"
                                            >
                                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                                <line x1="12" y1="9" x2="12" y2="13" />
                                                <line x1="12" y1="17" x2="12.01" y2="17" />
                                            </svg>
                                            <span>{{ t('event.venueMapConfirmHint') }}</span>
                                        </div>
                                        <p v-else class="-mt-1 text-xs text-muted-foreground">{{ t('event.venueMapHint') }}</p>
                                        <!-- Map search -->
                                        <div ref="mapSearchInputRef" class="relative">
                                            <Input
                                                v-model="mapSearchQuery"
                                                :placeholder="t('event.venueMapSearch')"
                                                class="placeholder:text-foreground/50"
                                                autocomplete="off"
                                                @focus="mapSearchResults.length && (mapSearchOpen = true)"
                                                @blur="deferCloseMapSearch"
                                            />
                                            <div v-if="mapSearching" class="absolute top-1/2 right-2.5 -translate-y-1/2">
                                                <svg
                                                    class="h-4 w-4 animate-spin text-muted-foreground"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                                </svg>
                                            </div>
                                            <Teleport to="body">
                                                <div
                                                    v-if="mapSearchOpen && mapSearchResults.length"
                                                    class="fixed z-[9999] max-h-60 overflow-y-auto rounded-md border bg-popover shadow-lg"
                                                    :style="mapSearchDropdownStyle"
                                                >
                                                    <button
                                                        v-for="r in mapSearchResults"
                                                        :key="r.place_id"
                                                        type="button"
                                                        class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-accent"
                                                        @mousedown.prevent="selectMapResult(r)"
                                                    >
                                                        <span class="truncate font-medium">{{ r.name || r.display_name.split(', ')[0] }}</span>
                                                        <span class="truncate text-xs text-muted-foreground">{{ r.display_name }}</span>
                                                    </button>
                                                </div>
                                            </Teleport>
                                        </div>
                                        <div
                                            ref="mapContainer"
                                            class="h-56 w-full overflow-hidden rounded-lg border border-input"
                                            style="z-index: 0"
                                        />
                                        <p v-if="form.venue_lat && form.venue_lng" class="text-xs text-muted-foreground">
                                            ✓ {{ form.venue_lat.toFixed(6) }}, {{ form.venue_lng.toFixed(6) }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Cover upload -->
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

                                <!-- Home screen color — only when cover is present -->
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
                                <div v-if="displayCoverUrl" class="grid gap-2">
                                    <Label>{{ t('event.colorHomeShadow') }}</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="color"
                                            v-model="form.color_home_shadow"
                                            class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                                        />
                                        <Input v-model="form.color_home_shadow" maxlength="7" class="font-mono uppercase" placeholder="#000000" />
                                    </div>
                                </div>
                                <div v-if="displayCoverUrl" class="grid gap-2">
                                    <Label
                                        >{{ t('event.homeShadowOpacity') }}
                                        <span class="text-xs font-normal text-muted-foreground">{{ form.home_shadow_opacity }}%</span></Label
                                    >
                                    <input type="range" v-model.number="form.home_shadow_opacity" min="0" max="100" step="5" class="w-full" />
                                    <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.homeShadowOpacityHint') }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Design: font + colors -->
                        <Card>
                            <CardContent>
                                <div class="space-y-4 pt-4">
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

                                    <!-- Color palette -->
                                    <div class="grid gap-3">
                                        <div class="flex items-center gap-2">
                                            <Label>{{ t('event.colorHint') }}</Label>
                                            <InfoTooltip :text="t('event.colorSystemInfo')" />
                                        </div>
                                        <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.colorHintSub') }}</p>
                                        <!-- 3 base pickers -->
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

                                        <!-- Radio selectors -->
                                        <div class="space-y-3 pt-1">
                                            <!-- Screen background -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleScreenBg') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('screenBg')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Card background -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleCardBg') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('cardBg')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Text on cards -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleCardText') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('cardText')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                                <p
                                                    v-if="cardContrastFailsAA"
                                                    role="alert"
                                                    class="rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-[11px] leading-snug text-amber-800 dark:text-amber-200"
                                                >
                                                    {{ t('event.contrastWarning', { ratio: cardContrastLabel }) }}
                                                </p>
                                            </div>
                                            <!-- Button on cards -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleCardButton') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('cardButton')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Text on card buttons -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleCardButtonText') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('cardButtonText')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Navbar color -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleTabTint') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('tabTint')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Border color -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleBorder') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('border')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- FAB button -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleFab') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('fab')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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
                                            <!-- Icon color inside FAB -->
                                            <div class="grid gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ t('event.roleFabIcon') }}</span
                                                    ><button
                                                        type="button"
                                                        @click="showHint('fabIcon')"
                                                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                                                    >
                                                        ?
                                                    </button>
                                                </div>
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

                        <!-- Drink game -->
                        <Card>
                            <CardContent>
                                <div class="space-y-3 pt-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <Label class="text-sm font-medium">{{ t('event.drinkGameEnabled') }}</Label>
                                            <p class="text-xs text-muted-foreground">{{ t('event.drinkGameEnabledDesc') }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.drink_game_enabled"
                                            @click="form.drink_game_enabled = !form.drink_game_enabled"
                                            :class="[
                                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                                form.drink_game_enabled ? 'bg-primary' : 'bg-input',
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                                                    form.drink_game_enabled ? 'translate-x-5' : 'translate-x-0',
                                                ]"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Photo game -->
                        <Card>
                            <CardContent>
                                <div class="space-y-3 pt-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <Label class="text-sm font-medium">{{ t('event.photoGameEnabled') }}</Label>
                                            <p class="text-xs text-muted-foreground">{{ t('event.photoGameEnabledDesc') }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.photo_game_enabled"
                                            @click="form.photo_game_enabled = !form.photo_game_enabled"
                                            :class="[
                                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                                form.photo_game_enabled ? 'bg-primary' : 'bg-input',
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                                                    form.photo_game_enabled ? 'translate-x-5' : 'translate-x-0',
                                                ]"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Slideshow token -->
                        <Card>
                            <CardContent>
                                <div class="space-y-3 pt-4">
                                    <div>
                                        <Label class="text-sm font-medium">{{ t('event.projectorToken') }}</Label>
                                        <p class="text-xs text-muted-foreground">{{ t('event.projectorTokenDesc') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <code
                                            v-if="props.event.projector_token"
                                            class="flex-1 truncate rounded bg-muted px-3 py-1.5 font-mono text-xs"
                                        >
                                            {{ props.event.projector_token }}
                                        </code>
                                        <span v-else class="flex-1 text-xs text-muted-foreground italic">{{ t('event.projectorTokenNone') }}</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="
                                                router.post(
                                                    route('photos.projector-token.regenerate'),
                                                    {},
                                                    { onSuccess: () => toast.success(t('event.projectorTokenRegenerated')) },
                                                )
                                            "
                                        >
                                            {{
                                                props.event.projector_token ? t('event.projectorTokenRegenerate') : t('event.projectorTokenGenerate')
                                            }}
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
                                :event-name="form.name"
                                :dresscode="form.dresscode"
                                :venue-display-mode="form.venue_display_mode"
                                :color-home-text="form.color_home_text"
                                :color-home-shadow="form.color_home_shadow"
                                :home-shadow-opacity="form.home_shadow_opacity"
                                :preview-date="previewDate"
                                :preview-font-family="previewFontFamily"
                                :preview-venue-name="previewVenueName"
                                :preview-venue-address="previewVenueAddress"
                                :preview-countdown="previewCountdown"
                                :c-screen-bg="cScreenBg"
                                :c-card-text="cCardText"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :tab-defs="tabDefs"
                                :active-hint="activeHint"
                            />
                            <!-- ===== END HOME (extracted to PhonePreview/Home.vue) ===== -->

                            <!-- ===== SCREEN 2: ZUSAGE ===== -->
                            <div class="flex flex-col items-center gap-1.5">
                                <div class="phone-frame-outer">
                                    <div class="phone-frame-inner">
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
                                                        :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                                                    >
                                                        <p
                                                            class="mb-0.5 text-[5px]"
                                                            :style="{ color: cCardText + '77' }"
                                                            :class="hintFilterClass('cardText')"
                                                        >
                                                            Bitte antworte bis 25. März.
                                                        </p>
                                                        <div class="flex items-center justify-between">
                                                            <span
                                                                class="text-[7px] font-semibold"
                                                                :style="{ color: cCardText }"
                                                                :class="hintFilterClass('cardText')"
                                                                >Max Mustermann</span
                                                            >
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
                                                        :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                                                    >
                                                        <p
                                                            class="text-[7px] font-semibold"
                                                            :style="{ color: cCardText }"
                                                            :class="hintFilterClass('cardText')"
                                                        >
                                                            Deine Gruppe
                                                        </p>
                                                        <p
                                                            class="mb-1 text-[5px]"
                                                            :style="{ color: cCardText + '77' }"
                                                            :class="hintFilterClass('cardText')"
                                                        >
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
                                                            :class="hintBorderClass('border')"
                                                        >
                                                            <div class="flex items-center justify-between">
                                                                <span
                                                                    class="text-[6px] font-semibold"
                                                                    :style="{ color: cCardText }"
                                                                    :class="hintFilterClass('cardText')"
                                                                    >{{ member.name }}</span
                                                                >
                                                                <div class="flex items-center gap-0.5">
                                                                    <span
                                                                        class="rounded-full px-1 py-0.5 text-[4px] font-semibold"
                                                                        :style="{
                                                                            backgroundColor: member.red ? '#b45a3c' : '#4a7c59',
                                                                            color: '#ffffff',
                                                                        }"
                                                                        >{{ member.status }}</span
                                                                    >
                                                                    <svg
                                                                        width="6"
                                                                        height="6"
                                                                        viewBox="0 0 24 24"
                                                                        fill="none"
                                                                        :stroke="cCardText + '88'"
                                                                        stroke-width="2.5"
                                                                        stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        :class="hintFilterClass('cardText')"
                                                                    >
                                                                        <path d="M6 9l6 6 6-6" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                            <p
                                                                class="text-[4px]"
                                                                :style="{ color: cCardText + '66' }"
                                                                :class="hintFilterClass('cardText')"
                                                            >
                                                                Von dir gesetzt
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div
                                                        v-for="(tab, i) in tabDefs"
                                                        :key="'z' + i"
                                                        class="flex flex-col items-center gap-0.5"
                                                        :class="hintFilterClass('tabTint')"
                                                    >
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            :viewBox="tab.viewBox ?? '0 0 24 24'"
                                                            fill="none"
                                                            :stroke-width="tab.strokeWidth ?? 2"
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
                                <div class="phone-frame-outer">
                                    <div class="phone-frame-inner">
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
                                                    <!-- FAB with cFab color -->
                                                    <div
                                                        class="absolute right-2 bottom-3 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                                        :style="{ backgroundColor: cFab }"
                                                        :class="hintBgClass('fab')"
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
                                                            :class="hintFilterClass('fabIcon')"
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
                                                    <div
                                                        v-for="(tab, i) in tabDefs"
                                                        :key="'f' + i"
                                                        class="flex flex-col items-center gap-0.5"
                                                        :class="hintFilterClass('tabTint')"
                                                    >
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            :viewBox="tab.viewBox ?? '0 0 24 24'"
                                                            fill="none"
                                                            :stroke-width="tab.strokeWidth ?? 2"
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
                                <div class="phone-frame-outer">
                                    <div class="phone-frame-inner">
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
                                                        :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                                                    >
                                                        <div class="px-2 pt-2 pb-1.5">
                                                            <p
                                                                class="text-[5px]"
                                                                :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                                                :class="hintFilterClass('cardText')"
                                                            >
                                                                Eingeloggt als
                                                            </p>
                                                            <p
                                                                class="text-[7px] font-semibold"
                                                                :style="{ color: cCardText, fontFamily: previewFontFamily }"
                                                                :class="hintFilterClass('cardText')"
                                                            >
                                                                Max Mustermann
                                                            </p>
                                                            <p
                                                                class="text-[5px]"
                                                                :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                                                :class="hintFilterClass('cardText')"
                                                            >
                                                                Familie Mustermann
                                                            </p>
                                                        </div>
                                                        <div
                                                            class="mx-2 border-t"
                                                            :style="{ borderColor: cBorder + '33' }"
                                                            :class="hintBgClass('border')"
                                                        ></div>
                                                        <div class="px-2 py-1.5">
                                                            <p
                                                                class="mb-1 text-[5px]"
                                                                :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                                                :class="hintFilterClass('cardText')"
                                                            >
                                                                Sprache
                                                            </p>
                                                            <div
                                                                class="flex rounded-lg border"
                                                                :style="{ borderColor: cBorder + '55' }"
                                                                :class="hintBorderClass('border')"
                                                            >
                                                                <div
                                                                    class="flex-1 rounded-l-lg py-0.5 text-center text-[5px] font-semibold"
                                                                    :style="{
                                                                        backgroundColor: cCardButton,
                                                                        color: cCardButtonText,
                                                                        fontFamily: previewFontFamily,
                                                                    }"
                                                                    :class="hintBgClass('cardButton')"
                                                                >
                                                                    <span :class="hintFilterClass('cardButtonText')">Deutsch</span>
                                                                </div>
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
                                                        <div
                                                            class="mx-2 border-t"
                                                            :style="{ borderColor: cBorder + '33' }"
                                                            :class="hintBgClass('border')"
                                                        ></div>
                                                        <div
                                                            class="mx-2 my-1.5 rounded-lg py-1 text-center text-[6px] font-semibold"
                                                            :style="{
                                                                backgroundColor: cCardButton,
                                                                color: cCardButtonText,
                                                                fontFamily: previewFontFamily,
                                                            }"
                                                            :class="hintBgClass('cardButton')"
                                                        >
                                                            <span :class="hintFilterClass('cardButtonText')">Ausloggen</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                                                    :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                                                >
                                                    <div
                                                        v-for="(tab, i) in tabDefs"
                                                        :key="'e' + i"
                                                        class="flex flex-col items-center gap-0.5"
                                                        :class="hintFilterClass('tabTint')"
                                                    >
                                                        <svg
                                                            width="9"
                                                            height="9"
                                                            :viewBox="tab.viewBox ?? '0 0 24 24'"
                                                            fill="none"
                                                            :stroke-width="tab.strokeWidth ?? 2"
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
