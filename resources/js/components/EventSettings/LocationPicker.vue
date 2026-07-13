<script setup lang="ts">
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

// Leaflet default icon fix for Vite
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

interface NominatimResult {
    place_id: number;
    lat: string;
    lon: string;
    display_name: string;
    name: string;
    address: Record<string, string>;
}

// Generic location value: structured address + coordinates. Reused by the
// venue editor and (later) the schedule-station editor. Field labels reuse
// the existing event.venue* i18n keys — the street/city/country/map wording
// is location-neutral even though the key names are historical.
const name = defineModel<string>('name', { default: '' });
const lat = defineModel<number | null>('lat', { default: null });
const lng = defineModel<number | null>('lng', { default: null });
const street = defineModel<string>('street', { default: '' });
const houseNumber = defineModel<string>('houseNumber', { default: '' });
const postalCode = defineModel<string>('postalCode', { default: '' });
const city = defineModel<string>('city', { default: '' });
const state = defineModel<string>('state', { default: '' });
const country = defineModel<string>('country', { default: 'Deutschland' });
const displayMode = defineModel<string>('displayMode', { default: 'both' });

const props = withDefaults(
    defineProps<{
        // Show the optional location name field (venue name). Off for stations.
        showName?: boolean;
        // Show the home-screen display-mode selector. Venue-only.
        showDisplayMode?: boolean;
        // Per-field ↺ reset UX tied to a single-page dirty guard (Settings.vue).
        // Off for modal/inline editors that save/cancel wholesale.
        enableFieldReset?: boolean;
        // Teleport the search/country dropdowns to <body> to escape overflow
        // clipping on a scrolling page. Turn OFF inside a modal dialog, where a
        // body-teleported dropdown is treated as an outside click and can't be
        // selected — inline absolute positioning is used instead.
        teleport?: boolean;
    }>(),
    {
        showName: true,
        showDisplayMode: true,
        enableFieldReset: false,
        teleport: true,
    },
);

const { t } = useI18n();

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

// Field-level dirty detection (only active when enableFieldReset is set).
const savedAddress = reactive<Record<string, string>>({
    name: name.value,
    street: street.value,
    house_number: houseNumber.value,
    postal_code: postalCode.value,
    city: city.value,
    state: state.value,
    country: country.value,
});
type AddressField = keyof typeof savedAddress;

const fieldModelMap: Record<AddressField, { get: () => string; set: (v: string) => void }> = {
    name: { get: () => name.value, set: (v) => (name.value = v) },
    street: { get: () => street.value, set: (v) => (street.value = v) },
    house_number: { get: () => houseNumber.value, set: (v) => (houseNumber.value = v) },
    postal_code: { get: () => postalCode.value, set: (v) => (postalCode.value = v) },
    city: { get: () => city.value, set: (v) => (city.value = v) },
    state: { get: () => state.value, set: (v) => (state.value = v) },
    country: { get: () => country.value, set: (v) => (country.value = v) },
};

function isFieldDirty(field: AddressField): boolean {
    return props.enableFieldReset && fieldModelMap[field].get() !== savedAddress[field];
}
function resetField(field: AddressField) {
    fieldModelMap[field].set(savedAddress[field]);
    if (field === 'country') countryQuery.value = savedAddress.country;
}

// Exposed so a parent form can update the dirty baseline after submit onSuccess.
function resetDirtyBaseline() {
    savedAddress.name = name.value;
    savedAddress.street = street.value;
    savedAddress.house_number = houseNumber.value;
    savedAddress.postal_code = postalCode.value;
    savedAddress.city = city.value;
    savedAddress.state = state.value;
    savedAddress.country = country.value;
    countryQuery.value = country.value;
}
defineExpose({ resetDirtyBaseline, applyLocation });

// --- Country dropdown ---
const countryQuery = ref(country.value);
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

function selectCountry(c: string) {
    country.value = c;
    countryQuery.value = c;
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

// Keep countryQuery in sync when country is set programmatically (e.g. form.reset(), reverseGeocode)
watch(country, (val) => {
    if (val !== countryQuery.value) countryQuery.value = val;
});

const isGermanyForm = computed(() => {
    const c = (country.value || 'Deutschland').toLowerCase().trim();
    return c === 'deutschland' || c === 'germany' || c === 'de';
});

// --- Leaflet map picker ---
const mapContainer = ref<HTMLElement | null>(null);
const reverseGeocoding = ref(false);
let leafletMap: L.Map | null = null;
let mapMarker: L.Marker | null = null;

function initMap() {
    if (!mapContainer.value || leafletMap) return;
    const center: [number, number] = lat.value && lng.value ? [lat.value, lng.value] : [51.1657, 10.4515];
    leafletMap = L.map(mapContainer.value).setView(center, lat.value ? 15 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);
    if (lat.value && lng.value) {
        mapMarker = L.marker([lat.value, lng.value], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }
    leafletMap.on('click', (e: L.LeafletMouseEvent) => setCoords(e.latlng.lat, e.latlng.lng));
}

// Add or move the draggable marker to the given coords (no-op without a map).
function ensureMarker(mLat: number, mLng: number) {
    if (!leafletMap) return;
    if (mapMarker) {
        mapMarker.setLatLng([mLat, mLng]);
        return;
    }
    mapMarker = L.marker([mLat, mLng], { draggable: true }).addTo(leafletMap);
    mapMarker.on('dragend', (e) => {
        const ll = (e.target as L.Marker).getLatLng();
        setCoords(ll.lat, ll.lng);
    });
}

async function setCoords(newLat: number, newLng: number) {
    lat.value = newLat;
    lng.value = newLng;
    if (!leafletMap) return;
    ensureMarker(newLat, newLng);
    await reverseGeocode(newLat, newLng);
}

/**
 * Apply a full location wholesale (address + coordinates) and sync the map.
 * Used by shortcuts like "reuse the main venue" on the schedule page. Unlike
 * the map-click / search paths this also recenters, since the change is
 * programmatic and the target may be off the current view.
 */
function applyLocation(loc: {
    name?: string | null;
    street?: string | null;
    houseNumber?: string | null;
    postalCode?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    lat?: number | null;
    lng?: number | null;
}) {
    name.value = loc.name ?? '';
    street.value = loc.street ?? '';
    houseNumber.value = loc.houseNumber ?? '';
    postalCode.value = loc.postalCode ?? '';
    city.value = loc.city ?? '';
    state.value = loc.state ?? '';
    country.value = loc.country ?? 'Deutschland';
    countryQuery.value = country.value;

    if (loc.lat != null && loc.lng != null) {
        lat.value = loc.lat;
        lng.value = loc.lng;
        if (leafletMap) {
            leafletMap.setView([loc.lat, loc.lng], 16);
            ensureMarker(loc.lat, loc.lng);
        }
    } else {
        clearMapCoords();
    }
}

async function reverseGeocode(rgLat: number, rgLng: number) {
    reverseGeocoding.value = true;
    try {
        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${rgLat}&lon=${rgLng}&addressdetails=1`, {
            headers: { 'Accept-Language': 'de' },
        });
        const data = await res.json();
        const addr = data.address;
        if (!addr) return;
        street.value = addr.road ?? addr.pedestrian ?? addr.path ?? '';
        houseNumber.value = addr.house_number ?? '';
        postalCode.value = addr.postcode ?? '';
        city.value = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
        state.value = addr.state ?? '';
        const c = addr.country ?? 'Deutschland';
        country.value = c;
        countryQuery.value = c;
    } catch {
        /* silent — user can fill in manually */
    } finally {
        reverseGeocoding.value = false;
    }
}

function clearMapCoords() {
    lat.value = null;
    lng.value = null;
    if (mapMarker && leafletMap) {
        leafletMap.removeLayer(mapMarker);
        mapMarker = null;
    }
}

// --- Map search (forward geocode to center) ---
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
    const resLat = parseFloat(result.lat);
    const resLng = parseFloat(result.lon);

    // Center the map + zoom
    if (leafletMap) {
        leafletMap.setView([resLat, resLng], 17);
    }

    // Set pin
    lat.value = resLat;
    lng.value = resLng;
    if (mapMarker) {
        mapMarker.setLatLng([resLat, resLng]);
    } else if (leafletMap) {
        mapMarker = L.marker([resLat, resLng], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }

    // Populate address fields from the Nominatim result
    const addr = result.address;
    street.value = addr.road ?? addr.pedestrian ?? addr.path ?? '';
    houseNumber.value = addr.house_number ?? '';
    postalCode.value = addr.postcode ?? '';
    city.value = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
    state.value = addr.state ?? '';
    const c = addr.country ?? 'Deutschland';
    country.value = c;
    countryQuery.value = c;

    mapSearchQuery.value = '';
    mapSearchResults.value = [];
    mapSearchOpen.value = false;
}

// --- Geocode from address fields ---
const geocodingFromFields = ref(false);

async function geocodeFromFields() {
    const parts: string[] = [];
    const streetLine = [street.value, houseNumber.value].filter(Boolean).join(' ');
    if (streetLine) parts.push(streetLine);
    if (postalCode.value) parts.push(postalCode.value);
    if (city.value) parts.push(city.value);
    if (country.value && country.value !== 'Deutschland') parts.push(country.value);
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

const hasAddressInput = computed(() => !!(street.value || city.value || postalCode.value));

onMounted(() => {
    setTimeout(initMap, 50);
});

onBeforeUnmount(() => {
    if (leafletMap) {
        leafletMap.remove();
        leafletMap = null;
    }
});
</script>

<template>
    <!-- Location name (optional) -->
    <div v-if="showName" class="grid gap-2">
        <Label
            >{{ t('event.venueName') }} <span class="text-muted-foreground text-xs font-normal">({{ t('event.venueNameOptional') }})</span></Label
        >
        <div class="relative">
            <Input
                v-model="name"
                :placeholder="t('event.venueNamePlaceholder')"
                :class="isFieldDirty('name') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
            />
            <button
                v-if="isFieldDirty('name')"
                type="button"
                @click="resetField('name')"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                title="Zurücksetzen"
            >
                ↺
            </button>
        </div>
        <p class="text-muted-foreground -mt-1 text-xs">{{ t('event.venueNameHint') }}</p>
    </div>
    <!-- Venue display on the home screen -->
    <div v-if="showDisplayMode" class="grid gap-2">
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
                @click="displayMode = opt.key"
                class="flex-1 rounded-lg border-2 px-2 py-1.5 text-center text-xs transition-colors"
                :class="(displayMode ?? 'both') === opt.key ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground'"
            >
                {{ opt.label }}
            </button>
        </div>
    </div>

    <!-- Structured address -->
    <div class="border-input grid gap-3 rounded-lg border p-3">
        <!-- DE form -->
        <template v-if="isGermanyForm">
            <div class="grid grid-cols-[1fr_80px] gap-2">
                <div class="grid gap-1.5">
                    <Label class="text-xs">{{ t('event.venueStreet') }}</Label>
                    <div class="relative">
                        <Input
                            v-model="street"
                            :placeholder="t('event.venueStreet')"
                            :class="isFieldDirty('street') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('street')"
                            type="button"
                            @click="resetField('street')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                            v-model="houseNumber"
                            placeholder="26"
                            :class="isFieldDirty('house_number') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('house_number')"
                            type="button"
                            @click="resetField('house_number')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                            v-model="postalCode"
                            placeholder="56218"
                            :class="isFieldDirty('postal_code') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('postal_code')"
                            type="button"
                            @click="resetField('postal_code')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                            v-model="city"
                            :placeholder="t('event.venueCity')"
                            :class="isFieldDirty('city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('city')"
                            type="button"
                            @click="resetField('city')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                        v-model="street"
                        :placeholder="t('event.venueAddressLine1Placeholder')"
                        :class="isFieldDirty('street') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                    />
                    <button
                        v-if="isFieldDirty('street')"
                        type="button"
                        @click="resetField('street')"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                            v-model="city"
                            :placeholder="t('event.venueCity')"
                            :class="isFieldDirty('city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('city')"
                            type="button"
                            @click="resetField('city')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                            v-model="postalCode"
                            placeholder="10001"
                            :class="isFieldDirty('postal_code') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                        />
                        <button
                            v-if="isFieldDirty('postal_code')"
                            type="button"
                            @click="resetField('postal_code')"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                            title="Zurücksetzen"
                        >
                            ↺
                        </button>
                    </div>
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs"
                    >{{ t('event.venueState') }} <span class="text-muted-foreground font-normal">({{ t('event.venueOptional') }})</span></Label
                >
                <div class="relative">
                    <Input
                        v-model="state"
                        :placeholder="t('event.venueState')"
                        :class="isFieldDirty('state') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
                    />
                    <button
                        v-if="isFieldDirty('state')"
                        type="button"
                        @click="resetField('state')"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
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
                    :class="isFieldDirty('country') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
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
                    v-if="isFieldDirty('country')"
                    type="button"
                    @click="resetField('country')"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-700"
                    title="Zurücksetzen"
                >
                    ↺
                </button>
                <Teleport to="body" :disabled="!teleport">
                    <div
                        v-if="countryOpen && filteredCountries.length"
                        :class="[
                            'bg-popover z-[9999] max-h-52 overflow-y-auto rounded-md border shadow-lg',
                            teleport ? 'fixed' : 'absolute left-0 right-0 top-full mt-1',
                        ]"
                        :style="teleport ? countryDropdownStyle : undefined"
                    >
                        <button
                            v-for="c in filteredCountries"
                            :key="c"
                            type="button"
                            class="hover:bg-accent flex w-full items-center px-3 py-2 text-sm"
                            :class="country === c ? 'bg-muted font-medium' : ''"
                            @mousedown.prevent="selectCountry(c)"
                        >
                            {{ c }}
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
            <span v-if="reverseGeocoding || geocodingFromFields" class="text-muted-foreground animate-pulse text-xs">{{
                t('event.venueGeocoding')
            }}</span>
            <button v-else-if="lat && lng" type="button" class="text-muted-foreground hover:text-destructive text-xs" @click="clearMapCoords">
                {{ t('event.venueMapReset') }}
            </button>
        </div>
        <!-- Geocode from address fields -->
        <button
            v-if="hasAddressInput && !geocodingFromFields"
            type="button"
            class="border-primary/50 text-primary hover:bg-primary/5 flex items-center gap-1.5 rounded-md border border-dashed px-3 py-1.5 text-sm disabled:opacity-40"
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
            v-if="hasAddressInput && !lat && !lng"
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
        <p v-else class="text-muted-foreground -mt-1 text-xs">{{ t('event.venueMapHint') }}</p>
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
            <div v-if="mapSearching" class="absolute right-2.5 top-1/2 -translate-y-1/2">
                <svg class="text-muted-foreground h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                </svg>
            </div>
            <Teleport to="body" :disabled="!teleport">
                <div
                    v-if="mapSearchOpen && mapSearchResults.length"
                    :class="[
                        'bg-popover z-[9999] max-h-60 overflow-y-auto rounded-md border shadow-lg',
                        teleport ? 'fixed' : 'absolute left-0 right-0 top-full mt-1',
                    ]"
                    :style="teleport ? mapSearchDropdownStyle : undefined"
                >
                    <button
                        v-for="r in mapSearchResults"
                        :key="r.place_id"
                        type="button"
                        class="hover:bg-accent flex w-full flex-col px-3 py-2 text-left text-sm"
                        @mousedown.prevent="selectMapResult(r)"
                    >
                        <span class="truncate font-medium">{{ r.name || r.display_name.split(', ')[0] }}</span>
                        <span class="text-muted-foreground truncate text-xs">{{ r.display_name }}</span>
                    </button>
                </div>
            </Teleport>
        </div>
        <div ref="mapContainer" class="border-input h-56 w-full overflow-hidden rounded-lg border" style="z-index: 0" />
        <p v-if="lat && lng" class="text-muted-foreground text-xs">✓ {{ lat.toFixed(6) }}, {{ lng.toFixed(6) }}</p>
    </div>
</template>
