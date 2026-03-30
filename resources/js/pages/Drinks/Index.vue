<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, reactive, onMounted, onBeforeUnmount } from 'vue';
import { useFloatingBar } from '@/composables/useFloatingBar';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('drink.title'), href: '/drinks' }];
const page = usePage();

// ─── Interfaces ───────────────────────────────────────────────────────────────

interface CatalogSize {
    id: number;
    amount_liter: number;
    is_default: boolean;
    points: number;
    event_drink_id: number | null;
}
interface SelectedSize {
    id: number;          // size_id
    drink_id: number;    // drinks.id (für remove)
    amount_liter: number;
    is_default: boolean;
    points: number;
}
interface CatalogEntry {
    id: number;
    category: string;
    type: string;
    display_name: string;
    is_alcoholic: boolean;
    sizes: CatalogSize[];
    points: number;
}
interface EventDrink {
    catalog_id: number;
    type: string;
    display_name: string;
    category: string;
    is_alcoholic: boolean;
    selected_sizes: SelectedSize[];
}
interface GuestStats {
    total: number;
    confirmed: number;
    rsvp_deadline: string | null;
    event_date: string | null;
}

// ─── Page Props ───────────────────────────────────────────────────────────────

const eventDrinks      = computed(() => page.props.event_drinks as EventDrink[]);
const catalog          = computed(() => page.props.catalog as Record<string, CatalogEntry[]>);
const drinkGameEnabled = computed(() => (page.props as any).active_event?.drink_game_enabled === true);
const guestStats       = computed(() => (page.props as any).guest_stats as GuestStats);

// ─── Tabs ─────────────────────────────────────────────────────────────────────

const activeTab = ref<'catalog' | 'calculator'>('catalog');
const tabs = computed(() => [
    { id: 'catalog',    label: t('drink.tabCatalog') },
    { id: 'calculator', label: t('drink.tabCalculator') },
]);

// ─── Katalog: Grouped view ────────────────────────────────────────────────────

// initialAddedMap: size_id → drink_id (für Löschen per Badge)
const initialAddedMap = computed(() => {
    const map: Record<number, number> = {};
    for (const entries of Object.values(catalog.value)) {
        for (const entry of entries) {
            for (const s of entry.sizes) {
                if (s.event_drink_id) map[s.id] = s.event_drink_id;
            }
        }
    }
    return map;
});

const localAdded   = reactive(new Set<number>()); // size_ids
const localRemoved = reactive(new Set<number>()); // size_ids
const isDirty = computed(() => localAdded.size > 0 || localRemoved.size > 0);

const { active: floatingBarActive } = useFloatingBar();
watch(isDirty, val => { floatingBarActive.value = val; }, { immediate: true });
onBeforeUnmount(() => { floatingBarActive.value = false; });

function isSelected(sizeId: number): boolean {
    if (localAdded.has(sizeId)) return true;
    const originallyAdded = initialAddedMap.value[sizeId] != null;
    return originallyAdded && !localRemoved.has(sizeId);
}

function toggleSize(size: CatalogSize) {
    if (isSelected(size.id)) {
        if (localAdded.has(size.id)) localAdded.delete(size.id);
        else localRemoved.add(size.id);
    } else {
        if (localRemoved.has(size.id)) localRemoved.delete(size.id);
        else localAdded.add(size.id);
    }
}

function reset() { localAdded.clear(); localRemoved.clear(); }

const saving = ref(false);
const skipGuard = ref(false);

function save() {
    skipGuard.value = true;
    const removeEventDrinkIds = Array.from(localRemoved)
        .map(sizeId => initialAddedMap.value[sizeId])
        .filter(Boolean) as number[];
    saving.value = true;
    router.post(route('drinks.batch'), {
        add:    Array.from(localAdded),
        remove: removeEventDrinkIds,
    }, {
        onSuccess: () => { localAdded.clear(); localRemoved.clear(); toast.success(t('toast.drinksSaved')); },
        onFinish: () => { saving.value = false; skipGuard.value = false; },
    });
}

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value && !skipGuard.value) { e.preventDefault(); e.returnValue = ''; }
}
let removeInertiaGuard: (() => void) | null = null;
onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard = router.on('before', (event) => {
        if (isDirty.value && !skipGuard.value) {
            const confirmed = window.confirm(t('drink.unsavedChangesPrompt'));
            if (!confirmed) { event.preventDefault(); return false; }
        }
    });
});
onBeforeUnmount(() => { window.removeEventListener('beforeunload', handleBeforeUnload); removeInertiaGuard?.(); });

const categoryOrder = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];
const categoryLabels: Record<string, string> = {
    beer: 'Bier', wine: 'Wein', spirit: 'Shots & Spirituosen',
    longdrink: 'Longdrinks', cocktail: 'Cocktails', softdrink: 'Softdrinks',
    water: 'Wasser', coffee: 'Kaffee & Tee',
};

const sortedCategories = computed(() => categoryOrder.filter(cat => catalog.value[cat]?.length > 0));
const collapsed = reactive(new Set<string>(categoryOrder));
function toggleCategory(cat: string) {
    if (collapsed.has(cat)) collapsed.delete(cat);
    else collapsed.add(cat);
}

const search = ref('');
const filteredCatalog = computed(() => {
    if (!search.value.trim()) return catalog.value;
    for (const cat of categoryOrder) collapsed.delete(cat);
    const q = search.value.toLowerCase();
    const result: Record<string, CatalogEntry[]> = {};
    for (const cat of sortedCategories.value) {
        const filtered = (catalog.value[cat] ?? []).filter(e => e.display_name.toLowerCase().includes(q));
        if (filtered.length) result[cat] = filtered;
    }
    return result;
});
const filteredCategories = computed(() => categoryOrder.filter(cat => filteredCatalog.value[cat]?.length > 0));
function formatSize(liter: number): string {
    if (liter < 0.1) return `${Math.round(liter * 100)} cl`;
    return `${liter.toLocaleString('de-DE')} l`;
}

// ─── Rechner: Eingabe-Refs ────────────────────────────────────────────────────

const rsvpDeadlinePassed = computed(() =>
    !!guestStats.value?.rsvp_deadline &&
    new Date(guestStats.value.rsvp_deadline) < new Date()
);

const guestBase     = ref<'total' | 'confirmed'>('total');
const bufferMode    = ref<'absolute' | 'percent'>('absolute');
const bufferAbs     = ref(10);
const bufferPct     = ref(10);
const alkPct        = ref(70);

// Nach Stichtag: 'confirmed' vorauswählen
watch(rsvpDeadlinePassed, (passed) => {
    if (passed) guestBase.value = 'confirmed';
}, { immediate: true });

// ─── Rechner: Kategorie-Verteilung ───────────────────────────────────────────

const categoryPct = ref<Record<string, number>>({});

const presentAlcoholicCategories = computed(() =>
    [...new Set(eventDrinks.value.filter(d => d.is_alcoholic).map(d => d.category))]
);

watch(presentAlcoholicCategories, (cats) => {
    const updated = { ...categoryPct.value };
    if (cats.length === 0) { categoryPct.value = {}; return; }
    const share = Math.round(100 / cats.length);
    cats.forEach((cat, i) => {
        if (!(cat in updated)) {
            updated[cat] = i === cats.length - 1 ? 100 - share * (cats.length - 1) : share;
        }
    });
    // Entferne Kategorien die nicht mehr vorhanden sind
    for (const k of Object.keys(updated)) {
        if (!cats.includes(k)) delete updated[k];
    }
    categoryPct.value = updated;
}, { immediate: true });

const categorySum = computed(() => Object.values(categoryPct.value).reduce((a, b) => a + b, 0));

const normalizedPct = computed(() => {
    const sum = categorySum.value;
    if (sum === 0) return {} as Record<string, number>;
    return Object.fromEntries(Object.entries(categoryPct.value).map(([k, v]) => [k, v / sum]));
});

// ─── Rechner: Gläser/Getränk (pro Typ) ──────────────────────────────────────

const DEFAULT_GLASSES_BY_CAT: Record<string, number> = {
    beer: 3, wine: 3, spirit: 3, longdrink: 2, cocktail: 2,
    water: 6, softdrink: 3, coffee: 1,
};

const glassesPerType = reactive<Record<string, number>>({});

watch(() => eventDrinks.value.map(d => d.type), () => {
    for (const drink of eventDrinks.value) {
        if (!(drink.type in glassesPerType)) {
            glassesPerType[drink.type] = DEFAULT_GLASSES_BY_CAT[drink.category] ?? 2;
        }
    }
    const types = eventDrinks.value.map(d => d.type);
    for (const k of Object.keys(glassesPerType)) {
        if (!types.includes(k)) delete glassesPerType[k];
    }
}, { immediate: true });

const categoryBgColor: Record<string, string> = {
    beer:      'bg-amber-400',
    wine:      'bg-red-500',
    spirit:    'bg-purple-500',
    longdrink: 'bg-cyan-500',
    cocktail:  'bg-pink-500',
    softdrink: 'bg-green-500',
    water:     'bg-blue-400',
    coffee:    'bg-amber-800',
};

const categoryHexColor: Record<string, string> = {
    beer:      '#fbbf24',
    wine:      '#ef4444',
    spirit:    '#a855f7',
    longdrink: '#06b6d4',
    cocktail:  '#ec4899',
    softdrink: '#22c55e',
    water:     '#60a5fa',
    coffee:    '#92400e',
};

// ─── Rechner: Planungsgäste ───────────────────────────────────────────────────

const baseCount = computed(() => {
    const gs = guestStats.value;
    if (!gs) return 0;
    return rsvpDeadlinePassed.value && guestBase.value === 'confirmed' ? gs.confirmed : gs.total;
});

const planGuests = computed(() =>
    bufferMode.value === 'absolute'
        ? Math.max(0, baseCount.value) + bufferAbs.value
        : Math.ceil(Math.max(0, baseCount.value) * (1 + bufferPct.value / 100))
);

const alkTrinker = computed(() => planGuests.value * (alkPct.value / 100));


// Sekt separat
const sektDrinks    = computed(() => eventDrinks.value.filter(d => d.type === 'sekt'));
const sektFlaschen  = computed(() => Math.floor(planGuests.value * 1.25 / 6));
const nonSektWine   = computed(() => eventDrinks.value.filter(d => d.category === 'wine' && d.type !== 'sekt'));

// ─── Rechner: Ergebnis-Computed ───────────────────────────────────────────────

interface DrinkResult {
    type: string;
    display_name: string;
    category: string;
    is_alcoholic: boolean;
    amount_liter: number;
    liters: number;
    basisText: string;
    isSekt?: boolean;
}

function defaultLiter(d: EventDrink): number {
    return d.selected_sizes.find(s => s.is_default)?.amount_liter ?? d.selected_sizes[0]?.amount_liter ?? 0;
}

const drinkResults = computed((): DrinkResult[] => {
    if (!eventDrinks.value.length) return [];
    const g = planGuests.value;
    if (g <= 0) return [];
    const results: DrinkResult[] = [];

    // Sekt separat: pauschal 1,25 Gläser à 125 ml pro Gast
    const sektTypes = [...new Map(sektDrinks.value.map(d => [d.type, d])).values()];
    for (const sd of sektTypes) {
        const fl = Math.ceil(sektFlaschen.value / Math.max(sektTypes.length, 1));
        results.push({
            type: sd.type,
            display_name: sd.display_name,
            category: sd.category,
            is_alcoholic: sd.is_alcoholic,
            amount_liter: defaultLiter(sd),
            liters: fl * 0.75,
            basisText: `${g} Gäste × 1,25 Gläser / 6 Gläser je Fl.`,
            isSekt: true,
        });
    }

    // Alle anderen: pro Typ direkt berechnen
    for (const d of eventDrinks.value.filter(d => !(d.category === 'wine' && d.type === 'sekt'))) {
        const glasses = glassesPerType[d.type] ?? DEFAULT_GLASSES_BY_CAT[d.category] ?? 2;
        const serving = defaultLiter(d);
        let liters = 0;
        let drinkers = 0;
        if (d.is_alcoholic) {
            const catShare = normalizedPct.value[d.category] ?? 0;
            drinkers = Math.round(alkTrinker.value * catShare);
            liters = alkTrinker.value * catShare * glasses * serving;
        } else {
            drinkers = g;
            liters = g * glasses * serving;
        }
        results.push({
            type: d.type,
            display_name: d.display_name,
            category: d.category,
            is_alcoholic: d.is_alcoholic,
            amount_liter: serving,
            liters,
            basisText: `~${drinkers} Pers. × ${glasses} Gl. × ${formatSize(serving)}`,
        });
    }
    return results;
});

// Sanity Check: > 10L Alkohol pro alkohol-trinkender Person
const SANITY_CAP_L = 10;
const sanityWarning = computed(() => {
    if (alkTrinker.value <= 0) return null;
    const alcCats = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail'];
    const total = drinkResults.value
        .filter(r => alcCats.includes(r.category) && !r.isSekt)
        .reduce((s, r) => s + r.liters, 0);
    const perPerson = total / alkTrinker.value;
    return perPerson > SANITY_CAP_L ? Math.round(perPerson) : null;
});

// ─── Rechner: Kaufeinheiten ───────────────────────────────────────────────────
//
// amount_liter im Katalog = Ausschankgröße (Glas/Portion), NICHT Kaufbehälter.
// bottleSize + caseCount werden explizit eingegeben — editierbare Defaults, kein Heuristic-Zoo.
//
// Formel: litersPerPurchaseUnit = bottleSize × caseCount
//         qty = Math.ceil(requiredLiters / litersPerPurchaseUnit)

// Kategorie-Fallback für Ausschankgrößen < 0,25 L (Gläser/Shots → Kaufflasche)
const BOTTLE_FALLBACK: Record<string, number> = {
    wine:      0.75,
    spirit:    0.7,
    longdrink: 0.75,
    cocktail:  0.75,
};

function defaultBottleSize(r: { amount_liter: number; category: string }): number {
    if (r.amount_liter < 0.25 && r.category in BOTTLE_FALLBACK)
        return BOTTLE_FALLBACK[r.category];
    return r.amount_liter;
}

function defaultCaseCount(size: number): number {
    if (Math.abs(size - 0.25) < 0.01) return 24;
    if (Math.abs(size - 0.33) < 0.02) return 24;
    if (Math.abs(size - 0.5)  < 0.01) return 20;
    return 1;
}

interface PurchaseConfig { bottleSize: number; caseCount: number; price: number | null }
const purchaseConfig = ref<Record<string, PurchaseConfig>>({});

function getConfig(result: DrinkResult): PurchaseConfig {
    if (!purchaseConfig.value[result.type]) {
        const bs = defaultBottleSize(result);
        purchaseConfig.value = {
            ...purchaseConfig.value,
            [result.type]: { bottleSize: bs, caseCount: defaultCaseCount(bs), price: null },
        };
    }
    return purchaseConfig.value[result.type];
}

function setBottleSize(type: string, val: string) {
    const v = parseFloat(val);
    const cur = purchaseConfig.value[type];
    if (!cur) return;
    purchaseConfig.value = { ...purchaseConfig.value, [type]: { ...cur, bottleSize: v > 0 ? v : cur.bottleSize } };
}

function setCaseCount(type: string, val: string) {
    const v = parseInt(val, 10);
    const cur = purchaseConfig.value[type];
    if (!cur) return;
    purchaseConfig.value = { ...purchaseConfig.value, [type]: { ...cur, caseCount: v > 0 ? v : 1 } };
}

function setPrice(type: string, val: string) {
    const price = val === '' ? null : parseFloat(val);
    const cur = purchaseConfig.value[type];
    if (!cur) return;
    purchaseConfig.value = { ...purchaseConfig.value, [type]: { ...cur, price: price && price > 0 ? price : null } };
}

function calcUnits(liters: number, cfg: PurchaseConfig): number {
    const lpu = cfg.bottleSize * cfg.caseCount;
    return lpu > 0 ? Math.ceil(liters / lpu) : 0;
}

function formatLiters(l: number): string {
    return l.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' L';
}

const totalCost = computed(() => {
    let sum = 0, hasAny = false;
    for (const r of drinkResults.value) {
        const cfg = purchaseConfig.value[r.type];
        if (cfg?.price != null && cfg.price > 0) {
            sum += calcUnits(r.liters, cfg) * cfg.price;
            hasAny = true;
        }
    }
    return hasAny ? sum : null;
});

// Kategorien für Ergebnisanzeige sortiert
const resultCategories = computed(() => {
    const cats = [...new Set(drinkResults.value.map(r => r.category))];
    return categoryOrder.filter(c => cats.includes(c)).concat(cats.filter(c => !categoryOrder.includes(c)));
});

const categoryEmoji: Record<string, string> = {
    beer: '🍺', wine: '🍷', spirit: '🥃', longdrink: '🍹', cocktail: '🍸',
    softdrink: '🥤', water: '💧', coffee: '☕',
};
</script>

<template>
    <Head :title="t('drink.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4" :class="{ 'pb-24': isDirty }">

            <!-- Tab-Navigation -->
            <div class="flex gap-1 border-b">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
                    :class="activeTab === tab.id
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab.id"
                >{{ tab.label }}</button>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- Tab: Getränke-Katalog                                          -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <template v-if="activeTab === 'catalog'">

                <!-- Aktive Getränke für dieses Event -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('drink.forEvent') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-3 flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                            <span class="mt-px shrink-0 text-base leading-none">ℹ</span>
                            <p>{{ t('drink.catalogHint') }}<template v-if="drinkGameEnabled"> {{ t('drink.gameHint') }}</template></p>
                        </div>
                        <p v-if="eventDrinks.length === 0" class="text-sm text-muted-foreground">{{ t('drink.none') }}</p>
                        <ul v-else class="divide-y">
                            <li v-for="d in eventDrinks" :key="d.catalog_id"
                                class="flex items-center justify-between gap-3 px-0 py-2.5">
                                <span class="text-sm font-medium shrink-0">{{ d.display_name }}</span>
                                <div class="flex flex-wrap gap-1.5 justify-end">
                                    <span
                                        v-for="s in d.selected_sizes"
                                        :key="s.id"
                                        class="inline-flex items-center gap-1 rounded-full border border-green-400 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6L9 17l-5-5" />
                                        </svg>
                                        {{ formatSize(s.amount_liter) }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Katalog -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('drink.catalog') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Input v-model="search" :placeholder="t('drink.catalogSearch')" />

                        <div v-if="filteredCategories.length === 0" class="text-sm text-muted-foreground">
                            {{ t('drink.catalogEmpty') }}
                        </div>

                        <div v-for="cat in filteredCategories" :key="cat" class="space-y-1">
                            <button
                                type="button"
                                @click="toggleCategory(cat)"
                                class="flex w-full items-center justify-between py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <span>{{ categoryLabels[cat] ?? cat }}</span>
                                <svg
                                    class="h-3.5 w-3.5 transition-transform duration-200"
                                    :class="{ 'rotate-180': !collapsed.has(cat) }"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                >
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </button>

                            <ul v-if="!collapsed.has(cat)" class="divide-y rounded-md border">
                                <li v-for="entry in filteredCatalog[cat]" :key="entry.id"
                                    class="flex items-center justify-between gap-3 px-3 py-2.5">
                                    <span class="text-sm font-medium shrink-0">{{ entry.display_name }}</span>
                                    <div class="flex flex-wrap gap-1.5 justify-end">
                                        <button
                                            v-for="s in entry.sizes"
                                            :key="s.id"
                                            :disabled="saving"
                                            @click="toggleSize(s)"
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors',
                                                isSelected(s.id)
                                                    ? 'border-green-400 bg-green-100 text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'border-border bg-muted text-muted-foreground hover:bg-muted/70'
                                            ]"
                                        >
                                            <svg v-if="isSelected(s.id)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5" />
                                            </svg>
                                            {{ formatSize(s.amount_liter) }}
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

            </template>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- Tab: Mengenrechner                                             -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <template v-else>

                <!-- Card 1: Parameter -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <CardTitle>{{ t('drink.calc.params') }}</CardTitle>
                            <InfoTooltip :text="t('drink.calc.paramsTooltip')" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">

                        <!-- Gästebasis -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ t('drink.calc.guestBase') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    @click="guestBase = 'total'"
                                    :class="[
                                        'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                        guestBase === 'total'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-muted text-muted-foreground hover:text-foreground'
                                    ]"
                                >{{ t('drink.calc.guestBaseAll', { n: guestStats?.total ?? 0 }) }}</button>
                                <button
                                    v-if="rsvpDeadlinePassed"
                                    @click="guestBase = 'confirmed'"
                                    :class="[
                                        'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                        guestBase === 'confirmed'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-muted text-muted-foreground hover:text-foreground'
                                    ]"
                                >{{ t('drink.calc.guestBaseConfirmed', { n: guestStats?.confirmed ?? 0 }) }}</button>
                            </div>
                            <p v-if="!rsvpDeadlinePassed" class="text-xs text-muted-foreground">
                                {{ t('drink.calc.rsvpNotPassed') }}
                            </p>
                        </div>

                        <!-- Puffer -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ t('drink.calc.buffer') }}</label>
                            <div class="flex items-center gap-3">
                                <div class="flex rounded-md border overflow-hidden">
                                    <button
                                        @click="bufferMode = 'absolute'"
                                        :class="['px-3 py-1.5 text-sm transition-colors', bufferMode === 'absolute' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground']"
                                    >{{ t('drink.calc.bufferAbs') }}</button>
                                    <button
                                        @click="bufferMode = 'percent'"
                                        :class="['px-3 py-1.5 text-sm transition-colors', bufferMode === 'percent' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground']"
                                    >{{ t('drink.calc.bufferPct') }}</button>
                                </div>
                                <input
                                    v-if="bufferMode === 'absolute'"
                                    v-model.number="bufferAbs"
                                    type="number" min="0" max="200"
                                    class="w-20 rounded-md border px-2 py-1.5 text-sm"
                                />
                                <div v-else class="flex items-center gap-1">
                                    <input v-model.number="bufferPct" type="range" min="0" max="50" step="5" class="w-28" />
                                    <span class="text-sm w-8 text-right">{{ bufferPct }} %</span>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-primary">
                                → {{ t('drink.calc.planGuests', { n: planGuests }) }}
                            </p>
                        </div>

                        <!-- Alkoholtrinker -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ t('drink.calc.alkPct') }}</label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="alkPct" type="range" min="0" max="100" step="5" class="w-40" />
                                <span class="text-sm w-12">{{ alkPct }} %</span>
                            </div>
                        </div>

                        <!-- Kategorie-Verteilung: Proportionalbalken + horizontale Slider -->
                        <div v-if="presentAlcoholicCategories.length > 0" class="space-y-3">
                            <label class="text-sm font-medium">{{ t('drink.calc.distribution') }}</label>

                            <!-- Proportionalbalken -->
                            <div class="flex rounded-md overflow-hidden h-8 gap-px">
                                <div
                                    v-for="cat in presentAlcoholicCategories"
                                    :key="cat"
                                    :class="[categoryBgColor[cat] ?? 'bg-muted', 'flex items-center justify-center min-w-0 transition-all duration-300 overflow-hidden']"
                                    :style="{ flexGrow: Math.max(categoryPct[cat] ?? 1, 1) }"
                                >
                                    <span class="text-xs font-medium text-white truncate px-1.5 leading-none">
                                        {{ categoryEmoji[cat] }} {{ Math.round((normalizedPct[cat] ?? 0) * 100) }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Slider horizontal nebeneinander, in Kategoriefarbe -->
                            <div class="grid gap-x-3 gap-y-1" :style="{ gridTemplateColumns: `repeat(${presentAlcoholicCategories.length}, 1fr)` }">
                                <template v-for="cat in presentAlcoholicCategories" :key="cat">
                                    <input
                                        :value="categoryPct[cat] ?? 0"
                                        @input="categoryPct = { ...categoryPct, [cat]: Number(($event.target as HTMLInputElement).value) }"
                                        type="range" min="0" max="100" step="5"
                                        class="w-full"
                                        :style="{ accentColor: categoryHexColor[cat] ?? 'var(--primary)' }"
                                    />
                                </template>
                                <template v-for="cat in presentAlcoholicCategories" :key="'lbl-' + cat">
                                    <span class="text-xs text-center truncate" :style="{ color: categoryHexColor[cat] ?? 'inherit' }">
                                        {{ categoryEmoji[cat] }} {{ categoryLabels[cat] ?? cat }}
                                    </span>
                                </template>
                            </div>

                            <p v-if="categorySum !== 100" class="text-xs text-muted-foreground">
                                {{ t('drink.calc.distributionNormNote', { n: categorySum }) }}
                            </p>
                        </div>

                        <!-- Gläser pro Getränk (pro Typ, nach Kategorie geordnet) -->
                        <div v-if="eventDrinks.length > 0" class="space-y-4">
                            <label class="text-sm font-medium">{{ t('drink.calc.glassesPerType') }}</label>

                            <!-- Alkoholische Kategorien -->
                            <div v-for="cat in presentAlcoholicCategories" :key="'g-' + cat" class="space-y-1.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm">{{ categoryEmoji[cat] }}</span>
                                    <span class="text-xs font-medium text-muted-foreground">
                                        {{ categoryLabels[cat] }}
                                        · ~{{ Math.round(alkTrinker * (normalizedPct[cat] ?? 0)) }} {{ t('drink.calc.drinkers') }}
                                    </span>
                                </div>
                                <div
                                    v-for="drink in eventDrinks.filter(d => d.category === cat && !(d.category === 'wine' && d.type === 'sekt'))"
                                    :key="drink.type"
                                    class="flex items-center gap-3 pl-5"
                                >
                                    <span class="text-sm min-w-0 truncate w-28 shrink-0">{{ drink.display_name }}</span>
                                    <input
                                        type="range" min="0" max="10" step="0.5"
                                        :value="glassesPerType[drink.type] ?? DEFAULT_GLASSES_BY_CAT[drink.category] ?? 2"
                                        @input="glassesPerType[drink.type] = Number(($event.target as HTMLInputElement).value)"
                                        class="flex-1 min-w-0"
                                        :style="{ accentColor: categoryHexColor[drink.category] ?? 'var(--primary)' }"
                                    />
                                    <span class="text-sm font-medium w-10 text-right shrink-0">
                                        {{ glassesPerType[drink.type] ?? DEFAULT_GLASSES_BY_CAT[drink.category] ?? 2 }} Gl.
                                    </span>
                                    <span class="text-xs text-muted-foreground w-14 shrink-0">× {{ formatSize(defaultLiter(drink)) }}</span>
                                </div>
                            </div>

                            <!-- Nicht-alkoholische Getränke -->
                            <div v-if="eventDrinks.some(d => !d.is_alcoholic)" class="space-y-1.5">
                                <span class="text-xs font-medium text-muted-foreground">{{ t('drink.calc.nonAlcoholic') }} · {{ planGuests }} {{ t('drink.calc.guestsAll') }}</span>
                                <div
                                    v-for="drink in eventDrinks.filter(d => !d.is_alcoholic)"
                                    :key="drink.type"
                                    class="flex items-center gap-3 pl-5"
                                >
                                    <span class="text-sm min-w-0 truncate w-28 shrink-0">{{ categoryEmoji[drink.category] ?? '' }} {{ drink.display_name }}</span>
                                    <input
                                        type="range" min="0" max="10" step="0.5"
                                        :value="glassesPerType[drink.type] ?? DEFAULT_GLASSES_BY_CAT[drink.category] ?? 2"
                                        @input="glassesPerType[drink.type] = Number(($event.target as HTMLInputElement).value)"
                                        class="flex-1 min-w-0"
                                        :style="{ accentColor: categoryHexColor[drink.category] ?? 'var(--primary)' }"
                                    />
                                    <span class="text-sm font-medium w-10 text-right shrink-0">
                                        {{ glassesPerType[drink.type] ?? DEFAULT_GLASSES_BY_CAT[drink.category] ?? 2 }} Gl.
                                    </span>
                                    <span class="text-xs text-muted-foreground w-14 shrink-0">× {{ formatSize(defaultLiter(drink)) }}</span>
                                </div>
                            </div>
                        </div>


                    </CardContent>
                </Card>

                <!-- Card 2: Ergebnisse -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('drink.calc.results') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">

                        <!-- Richtwert-Hinweis -->
                        <div class="flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                            <span class="mt-px shrink-0">ℹ</span>
                            <p>{{ t('drink.calc.resultsNote') }}</p>
                        </div>

                        <!-- Sanity-Warnung -->
                        <div v-if="sanityWarning" class="flex items-start gap-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                            <span class="mt-px shrink-0">⚠</span>
                            <p>{{ t('drink.calc.sanityWarning', { n: sanityWarning }) }}</p>
                        </div>

                        <!-- Leerstate -->
                        <div v-if="eventDrinks.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                            <p>{{ t('drink.calc.emptyDrinks') }}</p>
                            <button @click="activeTab = 'catalog'" class="mt-2 text-primary underline underline-offset-2 text-sm">
                                → {{ t('drink.tabCatalog') }}
                            </button>
                        </div>

                        <!-- Ergebnistabelle -->
                        <template v-else>
                            <div v-for="cat in resultCategories" :key="cat" class="space-y-2">
                                <!-- Kategorie-Header -->
                                <div class="flex items-center gap-2 pt-2">
                                    <span class="text-base">{{ categoryEmoji[cat] ?? '?' }}</span>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ categoryLabels[cat] ?? cat }}
                                    </span>
                                </div>

                                <!-- Drinks in dieser Kategorie -->
                                <div
                                    v-for="result in drinkResults.filter(r => r.category === cat)"
                                    :key="result.type"
                                    class="rounded-md border bg-muted/30 px-3 py-2.5 space-y-2"
                                >
                                    <!-- Drink-Name + Liter -->
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-sm font-medium">{{ result.display_name }}</span>
                                        <span v-if="result.liters > 0" class="text-sm font-semibold text-primary shrink-0">
                                            {{ formatLiters(result.liters) }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground shrink-0">{{ t('drink.calc.noModel') }}</span>
                                    </div>

                                    <!-- Basis-Text -->
                                    <p v-if="result.basisText && result.liters > 0" class="text-xs text-muted-foreground">
                                        {{ t('drink.calc.basis') }}: {{ result.basisText }}
                                    </p>
                                    <p v-if="result.isSekt" class="text-xs text-muted-foreground italic">
                                        {{ t('drink.calc.sektNote') }}
                                    </p>

                                    <!-- Kaufeinheit-Eingabe + Preis (nicht für coffee) -->
                                    <div v-if="result.liters > 0 && result.category !== 'coffee'" class="space-y-1.5 pt-0.5">
                                        <!-- Flaschengröße + Kasten à N Fl. -->
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground">
                                            <label class="flex items-center gap-1">
                                                {{ t('drink.calc.unitSize') }}
                                                <input
                                                    type="number" min="0.01" step="0.01"
                                                    :value="getConfig(result).bottleSize"
                                                    @change="setBottleSize(result.type, ($event.target as HTMLInputElement).value)"
                                                    class="w-16 rounded border bg-background px-1.5 py-0.5 text-foreground ml-1"
                                                /> L
                                            </label>
                                            <label class="flex items-center gap-1">
                                                {{ t('drink.calc.caseSize') }}
                                                <input
                                                    type="number" min="1" step="1"
                                                    :value="getConfig(result).caseCount"
                                                    @change="setCaseCount(result.type, ($event.target as HTMLInputElement).value)"
                                                    class="w-14 rounded border bg-background px-1.5 py-0.5 text-foreground ml-1"
                                                /> {{ t('drink.calc.caseSizeUnit') }}
                                            </label>
                                        </div>

                                        <!-- Ergebnis + Preis -->
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                            <span class="font-medium text-foreground">
                                                <template v-if="getConfig(result).caseCount > 1">
                                                    = {{ calcUnits(result.liters, getConfig(result)) }} {{ t('drink.calc.cases') }}
                                                    ({{ calcUnits(result.liters, getConfig(result)) * getConfig(result).caseCount }} {{ t('drink.calc.bottles') }})
                                                </template>
                                                <template v-else>
                                                    = {{ calcUnits(result.liters, getConfig(result)) }} {{ t('drink.calc.bottles') }}
                                                </template>
                                            </span>
                                            <input
                                                type="number" min="0" step="0.01"
                                                :placeholder="getConfig(result).caseCount > 1 ? t('drink.calc.pricePerCase') : t('drink.calc.pricePerUnit')"
                                                :value="purchaseConfig[result.type]?.price ?? ''"
                                                @input="setPrice(result.type, ($event.target as HTMLInputElement).value)"
                                                class="w-24 rounded border bg-background px-1.5 py-0.5 text-foreground"
                                            />
                                            <span v-if="purchaseConfig[result.type]?.price" class="text-muted-foreground">
                                                = {{ (calcUnits(result.liters, getConfig(result)) * (purchaseConfig[result.type]?.price ?? 0)).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unbekannte Kategorie -->
                                <div
                                    v-if="!categoryOrder.includes(cat)"
                                    class="rounded-md border bg-muted/30 px-3 py-2.5"
                                >
                                    <span class="text-xs text-muted-foreground">{{ t('drink.calc.noModel') }}</span>
                                </div>
                            </div>

                            <!-- Gesamtkosten (wenn mind. ein Preis eingegeben) -->
                            <div v-if="totalCost !== null" class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2.5 flex items-center justify-between mt-2">
                                <span class="text-sm font-medium">{{ t('drink.calc.totalCost') }}</span>
                                <span class="text-sm font-semibold text-primary">
                                    {{ totalCost.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
                                </span>
                            </div>

                            <!-- Drink-Game-Hinweis -->
                            <div v-if="drinkGameEnabled" class="flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground mt-2">
                                <span class="mt-px shrink-0">🎮</span>
                                <p>{{ t('drink.calc.gameHint') }}</p>
                            </div>
                        </template>

                    </CardContent>
                </Card>

                <!-- Disclaimer -->
                <Card>
                    <CardContent class="pt-4">
                        <div class="flex items-start gap-2 text-sm text-muted-foreground">
                            <span class="mt-px shrink-0 text-base">ℹ</span>
                            <div class="space-y-1.5">
                                <p class="font-medium text-foreground">{{ t('drink.calc.disclaimer') }}</p>
                                <ul class="space-y-1 list-none">
                                    <li>• {{ t('drink.calc.disclaimerAlcohol') }}</li>
                                    <li>• {{ t('drink.calc.disclaimerWeather') }}</li>
                                    <li>• {{ t('drink.calc.disclaimerCatering') }}</li>
                                    <li>• {{ t('drink.calc.disclaimerSelection') }}</li>
                                    <li>• {{ t('drink.calc.disclaimerBuffer') }}</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>

            </template>

        </div>

        <!-- Floating Save/Reset Bar (Katalog-Tab) -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="isDirty" class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 shadow-lg">
                <span class="text-xs text-muted-foreground mr-1">{{ t('drink.unsavedChanges') }}</span>
                <Button variant="ghost" size="sm" :disabled="saving" @click="reset">{{ t('common.cancel') }}</Button>
                <Button size="sm" :disabled="saving" @click="save">{{ saving ? '…' : t('common.save') }}</Button>
            </div>
        </Transition>

    </AppLayout>
</template>
