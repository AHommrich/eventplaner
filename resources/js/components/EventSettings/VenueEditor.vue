<script setup lang="ts">
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
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

const venueName = defineModel<string>('venueName', { default: '' });
const venueLat = defineModel<number | null>('venueLat', { default: null });
const venueLng = defineModel<number | null>('venueLng', { default: null });
const venueStreet = defineModel<string>('venueStreet', { default: '' });
const venueHouseNumber = defineModel<string>('venueHouseNumber', { default: '' });
const venuePostalCode = defineModel<string>('venuePostalCode', { default: '' });
const venueCity = defineModel<string>('venueCity', { default: '' });
const venueState = defineModel<string>('venueState', { default: '' });
const venueCountry = defineModel<string>('venueCountry', { default: 'Deutschland' });
const venueDisplayMode = defineModel<string>('venueDisplayMode', { default: 'both' });

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

// Field-level dirty detection
const savedAddress = reactive<Record<string, string>>({
    venue_name: venueName.value,
    venue_street: venueStreet.value,
    venue_house_number: venueHouseNumber.value,
    venue_postal_code: venuePostalCode.value,
    venue_city: venueCity.value,
    venue_state: venueState.value,
    venue_country: venueCountry.value,
});
type AddressField = keyof typeof savedAddress;

const fieldModelMap: Record<AddressField, { get: () => string; set: (v: string) => void }> = {
    venue_name: { get: () => venueName.value, set: (v) => (venueName.value = v) },
    venue_street: { get: () => venueStreet.value, set: (v) => (venueStreet.value = v) },
    venue_house_number: { get: () => venueHouseNumber.value, set: (v) => (venueHouseNumber.value = v) },
    venue_postal_code: { get: () => venuePostalCode.value, set: (v) => (venuePostalCode.value = v) },
    venue_city: { get: () => venueCity.value, set: (v) => (venueCity.value = v) },
    venue_state: { get: () => venueState.value, set: (v) => (venueState.value = v) },
    venue_country: { get: () => venueCountry.value, set: (v) => (venueCountry.value = v) },
};

function isFieldDirty(field: AddressField): boolean {
    return fieldModelMap[field].get() !== savedAddress[field];
}
function resetField(field: AddressField) {
    fieldModelMap[field].set(savedAddress[field]);
    if (field === 'venue_country') countryQuery.value = savedAddress.venue_country;
}

// Exposed so Settings.vue can update the dirty baseline after submit onSuccess.
function resetDirtyBaseline() {
    savedAddress.venue_name = venueName.value;
    savedAddress.venue_street = venueStreet.value;
    savedAddress.venue_house_number = venueHouseNumber.value;
    savedAddress.venue_postal_code = venuePostalCode.value;
    savedAddress.venue_city = venueCity.value;
    savedAddress.venue_state = venueState.value;
    savedAddress.venue_country = venueCountry.value;
    countryQuery.value = venueCountry.value;
}
defineExpose({ resetDirtyBaseline });

// --- Country dropdown ---
const countryQuery = ref(venueCountry.value);
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
    venueCountry.value = country;
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

// Keep countryQuery in sync when venueCountry is set programmatically (e.g. form.reset(), reverseGeocode)
watch(venueCountry, (val) => {
    if (val !== countryQuery.value) countryQuery.value = val;
});

const isGermanyForm = computed(() => {
    const c = (venueCountry.value || 'Deutschland').toLowerCase().trim();
    return c === 'deutschland' || c === 'germany' || c === 'de';
});

// --- Leaflet map picker ---
const mapContainer = ref<HTMLElement | null>(null);
const reverseGeocoding = ref(false);
let leafletMap: L.Map | null = null;
let mapMarker: L.Marker | null = null;

function initMap() {
    if (!mapContainer.value || leafletMap) return;
    const center: [number, number] = venueLat.value && venueLng.value ? [venueLat.value, venueLng.value] : [51.1657, 10.4515];
    leafletMap = L.map(mapContainer.value).setView(center, venueLat.value ? 15 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);
    if (venueLat.value && venueLng.value) {
        mapMarker = L.marker([venueLat.value, venueLng.value], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', (e) => {
            const ll = (e.target as L.Marker).getLatLng();
            setCoords(ll.lat, ll.lng);
        });
    }
    leafletMap.on('click', (e: L.LeafletMouseEvent) => setCoords(e.latlng.lat, e.latlng.lng));
}

async function setCoords(lat: number, lng: number) {
    venueLat.value = lat;
    venueLng.value = lng;
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
        venueStreet.value = addr.road ?? addr.pedestrian ?? addr.path ?? '';
        venueHouseNumber.value = addr.house_number ?? '';
        venuePostalCode.value = addr.postcode ?? '';
        venueCity.value = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
        venueState.value = addr.state ?? '';
        const country = addr.country ?? 'Deutschland';
        venueCountry.value = country;
        countryQuery.value = country;
    } catch {
        /* silent — user can fill in manually */
    } finally {
        reverseGeocoding.value = false;
    }
}

function clearMapCoords() {
    venueLat.value = null;
    venueLng.value = null;
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
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    // Center the map + zoom
    if (leafletMap) {
        leafletMap.setView([lat, lng], 17);
    }

    // Set pin
    venueLat.value = lat;
    venueLng.value = lng;
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
    venueStreet.value = addr.road ?? addr.pedestrian ?? addr.path ?? '';
    venueHouseNumber.value = addr.house_number ?? '';
    venuePostalCode.value = addr.postcode ?? '';
    venueCity.value = addr.city ?? addr.town ?? addr.village ?? addr.municipality ?? addr.county ?? '';
    venueState.value = addr.state ?? '';
    const country = addr.country ?? 'Deutschland';
    venueCountry.value = country;
    countryQuery.value = country;

    mapSearchQuery.value = '';
    mapSearchResults.value = [];
    mapSearchOpen.value = false;
}

// --- Geocode from address fields ---
const geocodingFromFields = ref(false);

async function geocodeFromFields() {
    const parts: string[] = [];
    const street = [venueStreet.value, venueHouseNumber.value].filter(Boolean).join(' ');
    if (street) parts.push(street);
    if (venuePostalCode.value) parts.push(venuePostalCode.value);
    if (venueCity.value) parts.push(venueCity.value);
    if (venueCountry.value && venueCountry.value !== 'Deutschland') parts.push(venueCountry.value);
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

const hasAddressInput = computed(() => !!(venueStreet.value || venueCity.value || venuePostalCode.value));

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
    <!-- Venue name (optional) -->
    <div class="grid gap-2">
        <Label
            >{{ t('event.venueName') }}
            <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueNameOptional') }})</span></Label
        >
        <div class="relative">
            <Input
                v-model="venueName"
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
                @click="venueDisplayMode = opt.key"
                class="flex-1 rounded-lg border-2 px-2 py-1.5 text-center text-xs transition-colors"
                :class="
                    (venueDisplayMode ?? 'both') === opt.key
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
                            v-model="venueStreet"
                            :placeholder="t('event.venueStreet')"
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
                <div class="grid gap-1.5">
                    <Label class="text-xs">{{ t('event.venueHouseNumber') }}</Label>
                    <div class="relative">
                        <Input
                            v-model="venueHouseNumber"
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
                            v-model="venuePostalCode"
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
                            v-model="venueCity"
                            :placeholder="t('event.venueCity')"
                            :class="isFieldDirty('venue_city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
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
                        v-model="venueStreet"
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
                            v-model="venueCity"
                            :placeholder="t('event.venueCity')"
                            :class="isFieldDirty('venue_city') ? 'border-amber-400 pr-8 ring-2 ring-amber-400/30' : ''"
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
                            v-model="venuePostalCode"
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
                        v-model="venueState"
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
                            :class="venueCountry === country ? 'bg-muted font-medium' : ''"
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
            <span v-if="reverseGeocoding || geocodingFromFields" class="animate-pulse text-xs text-muted-foreground">{{
                t('event.venueGeocoding')
            }}</span>
            <button
                v-else-if="venueLat && venueLng"
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            {{ t('event.venueSearchFromFields') }}
        </button>
        <!-- Hint: confirm location via map -->
        <div
            v-if="hasAddressInput && !venueLat && !venueLng"
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
                <svg class="h-4 w-4 animate-spin text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
        <div ref="mapContainer" class="h-56 w-full overflow-hidden rounded-lg border border-input" style="z-index: 0" />
        <p v-if="venueLat && venueLng" class="text-xs text-muted-foreground">✓ {{ venueLat.toFixed(6) }}, {{ venueLng.toFixed(6) }}</p>
    </div>
</template>
