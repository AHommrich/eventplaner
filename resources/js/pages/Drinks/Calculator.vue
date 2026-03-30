<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useFloatingBar } from '@/composables/useFloatingBar';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.drinks'), href: '/drinks' },
    { title: t('nav.calculator'), href: '/drinks/calculator' },
];
const page = usePage();

// ─── Interfaces ───────────────────────────────────────────────────────────────

interface SelectedSize {
    id: number;
    drink_id: number;
    amount_liter: number;
    is_default: boolean;
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
interface PurchaseConfig { bottleSize: number; caseCount: number; price: number | null }
interface CalculatorSettings {
    guestBase?: 'total' | 'confirmed';
    bufferMode?: 'absolute' | 'percent';
    bufferAbs?: number;
    bufferPct?: number;
    alkPct?: number;
    categoryPct?: Record<string, number>;
    drinkShareWithinCat?: Record<string, Record<string, number>>;
    purchaseConfig?: Record<string, PurchaseConfig>;
}

// ─── Page Props ───────────────────────────────────────────────────────────────

const props = defineProps<{
    event_drinks: EventDrink[];
    guest_stats: GuestStats;
    calculator_settings: CalculatorSettings | null;
}>();

const eventDrinks      = computed(() => props.event_drinks);
const guestStats       = computed(() => props.guest_stats);
const drinkGameEnabled = computed(() => (page.props as any).active_event?.drink_game_enabled === true);

// ─── Dirty-Tracking ───────────────────────────────────────────────────────────

const isDirty       = ref(false);
const saving        = ref(false);
const skipGuard     = ref(false);
const isInitialized = ref(false);

function markDirty() {
    if (isInitialized.value) isDirty.value = true;
}

const { active: floatingBarActive } = useFloatingBar();
watch(isDirty, val => { floatingBarActive.value = val; }, { immediate: true });
onBeforeUnmount(() => { floatingBarActive.value = false; });

// ─── Eingabe-Refs ─────────────────────────────────────────────────────────────

const rsvpDeadlinePassed = computed(() =>
    !!guestStats.value?.rsvp_deadline &&
    new Date(guestStats.value.rsvp_deadline) < new Date()
);

const saved = props.calculator_settings ?? {};

const guestBase  = ref<'total' | 'confirmed'>(saved.guestBase ?? 'total');
const bufferMode = ref<'absolute' | 'percent'>(saved.bufferMode ?? 'absolute');
const bufferAbs  = ref(saved.bufferAbs ?? 10);
const bufferPct  = ref(saved.bufferPct ?? 10);
const alkPct     = ref(saved.alkPct ?? 70);

// Nach Stichtag: 'confirmed' vorauswählen (nur wenn nicht gespeichert)
watch(rsvpDeadlinePassed, (passed) => {
    if (passed && !saved.guestBase) guestBase.value = 'confirmed';
}, { immediate: true });

watch([guestBase, bufferMode, bufferAbs, bufferPct, alkPct], markDirty);

// ─── Kategorie-Verteilung ─────────────────────────────────────────────────────

const categoryOrder = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];
const categoryLabels: Record<string, string> = {
    beer: 'Bier', wine: 'Wein', spirit: 'Shots & Spirituosen',
    longdrink: 'Longdrinks', cocktail: 'Cocktails', softdrink: 'Softdrinks',
    water: 'Wasser', coffee: 'Kaffee & Tee',
};

const categoryPct = ref<Record<string, number>>({});

const presentAllCategories = computed(() => {
    const present = new Set(
        eventDrinks.value
            .filter(d => !(d.category === 'wine' && d.type === 'sekt'))
            .map(d => d.category)
    );
    return categoryOrder
        .filter(cat => present.has(cat))
        .concat([...present].filter(cat => !categoryOrder.includes(cat)));
});

watch(presentAllCategories, (cats) => {
    const updated = { ...categoryPct.value };
    if (cats.length === 0) { categoryPct.value = {}; return; }
    const share = Math.round(100 / cats.length);
    cats.forEach((cat, i) => {
        if (!(cat in updated)) {
            updated[cat] = i === cats.length - 1 ? 100 - share * (cats.length - 1) : share;
        }
    });
    for (const k of Object.keys(updated)) {
        if (!cats.includes(k)) delete updated[k];
    }
    categoryPct.value = updated;
    markDirty();
}, { immediate: true });

const categorySum = computed(() => Object.values(categoryPct.value).reduce((a, b) => a + b, 0));
const lockedCategories = reactive(new Set<string>());

function toggleLock(cat: string) {
    if (lockedCategories.has(cat)) lockedCategories.delete(cat);
    else lockedCategories.add(cat);
}

function setCategoryPct(cat: string, newVal: number) {
    const cats = presentAllCategories.value;
    const lockedSum = cats
        .filter(c => c !== cat && lockedCategories.has(c))
        .reduce((s, c) => s + (categoryPct.value[c] ?? 0), 0);

    const maxAllowed = Math.max(0, 100 - lockedSum);
    const clamped = Math.max(0, Math.min(maxAllowed, Math.round(newVal)));
    if (clamped === (categoryPct.value[cat] ?? 0)) return;

    const adjustable = cats.filter(c => c !== cat && !lockedCategories.has(c));
    const newPct: Record<string, number> = { ...categoryPct.value, [cat]: clamped };
    const remainingBudget = 100 - clamped - lockedSum;

    if (adjustable.length > 0) {
        const totalAdjustable = adjustable.reduce((s, c) => s + (categoryPct.value[c] ?? 0), 0);
        if (totalAdjustable > 0) {
            let allocated = 0;
            adjustable.forEach((c, i) => {
                if (i === adjustable.length - 1) {
                    newPct[c] = Math.max(0, remainingBudget - allocated);
                } else {
                    const share = Math.round(((categoryPct.value[c] ?? 0) / totalAdjustable) * remainingBudget);
                    newPct[c] = Math.max(0, share);
                    allocated += share;
                }
            });
        } else {
            const share = Math.floor(Math.max(0, remainingBudget) / adjustable.length);
            adjustable.forEach((c, i) => {
                newPct[c] = i === adjustable.length - 1
                    ? Math.max(0, remainingBudget - share * (adjustable.length - 1))
                    : share;
            });
        }
    }

    categoryPct.value = newPct;
    markDirty();
}

const normalizedPct = computed(() => {
    const sum = categorySum.value;
    if (sum === 0) return {} as Record<string, number>;
    return Object.fromEntries(Object.entries(categoryPct.value).map(([k, v]) => [k, v / sum]));
});

// ─── Drink-Share innerhalb Kategorie ─────────────────────────────────────────

const drinkShareWithinCat = reactive<Record<string, Record<string, number>>>({});
const lockedDrinks = reactive(new Set<string>());
const expandedCatDetails = reactive(new Set<string>());

const allDrinksByCat = computed(() => {
    const result: Record<string, EventDrink[]> = {};
    for (const d of eventDrinks.value) {
        if (d.category === 'wine' && d.type === 'sekt') continue;
        if (!result[d.category]) result[d.category] = [];
        result[d.category].push(d);
    }
    return result;
});

const alcoholicDrinksByCat = computed(() => {
    const result: Record<string, EventDrink[]> = {};
    for (const d of eventDrinks.value) {
        if (!d.is_alcoholic || (d.category === 'wine' && d.type === 'sekt')) continue;
        if (!result[d.category]) result[d.category] = [];
        result[d.category].push(d);
    }
    return result;
});

function categoryIsAlcoholic(cat: string): boolean {
    return (alcoholicDrinksByCat.value[cat]?.length ?? 0) > 0;
}

function initSharesForCategory(cat: string, drinks: EventDrink[]) {
    if (!drinkShareWithinCat[cat]) drinkShareWithinCat[cat] = {};
    const catShares = drinkShareWithinCat[cat];
    const types = drinks.map(d => d.type);
    const share = Math.round(100 / types.length);
    for (let i = 0; i < types.length; i++) {
        const type = types[i];
        if (!(type in catShares)) {
            catShares[type] = i === types.length - 1
                ? 100 - share * (types.length - 1)
                : share;
        }
    }
    for (const type of Object.keys(catShares)) {
        if (!types.includes(type)) delete catShares[type];
    }
}

watch(allDrinksByCat, (byCategory) => {
    for (const [cat, drinks] of Object.entries(byCategory)) {
        initSharesForCategory(cat, drinks);
    }
    for (const cat of Object.keys(drinkShareWithinCat)) {
        if (!byCategory[cat]) delete drinkShareWithinCat[cat];
    }
    markDirty();
}, { immediate: true, deep: true });

function toggleCatDetail(cat: string) {
    if (expandedCatDetails.has(cat)) expandedCatDetails.delete(cat);
    else expandedCatDetails.add(cat);
}

function drinkShareSum(cat: string): number {
    const shares = drinkShareWithinCat[cat];
    if (!shares) return 100;
    return Object.values(shares).reduce((a, b) => a + b, 0);
}

function drinkShareNorm(cat: string, type: string): number {
    const sum = drinkShareSum(cat);
    return sum > 0 ? (drinkShareWithinCat[cat]?.[type] ?? 0) / sum : 0;
}

function setDrinkShare(cat: string, type: string, newVal: number) {
    const catShares = drinkShareWithinCat[cat];
    if (!catShares) return;
    const types = Object.keys(catShares);

    const lockedSum = types
        .filter(t => t !== type && lockedDrinks.has(`${cat}:${t}`))
        .reduce((s, t) => s + (catShares[t] ?? 0), 0);

    const maxAllowed = Math.max(0, 100 - lockedSum);
    const clamped = Math.max(0, Math.min(maxAllowed, Math.round(newVal)));
    if (clamped === (catShares[type] ?? 0)) return;

    const adjustable = types.filter(t => t !== type && !lockedDrinks.has(`${cat}:${t}`));
    const remainingBudget = 100 - clamped - lockedSum;

    catShares[type] = clamped;

    if (adjustable.length > 0) {
        const totalAdjustable = adjustable.reduce((s, t) => s + (catShares[t] ?? 0), 0);
        if (totalAdjustable > 0) {
            let allocated = 0;
            adjustable.forEach((t, i) => {
                if (i === adjustable.length - 1) {
                    catShares[t] = Math.max(0, remainingBudget - allocated);
                } else {
                    const share = Math.round(((catShares[t] ?? 0) / totalAdjustable) * remainingBudget);
                    catShares[t] = Math.max(0, share);
                    allocated += share;
                }
            });
        } else {
            const share = Math.floor(Math.max(0, remainingBudget) / adjustable.length);
            adjustable.forEach((t, i) => {
                catShares[t] = i === adjustable.length - 1
                    ? Math.max(0, remainingBudget - share * (adjustable.length - 1))
                    : share;
            });
        }
    }
    markDirty();
}

// ─── Farben ───────────────────────────────────────────────────────────────────

const categoryBgColor: Record<string, string> = {
    beer: 'bg-amber-400', wine: 'bg-red-500', spirit: 'bg-purple-500',
    longdrink: 'bg-cyan-500', cocktail: 'bg-pink-500', softdrink: 'bg-green-500',
    water: 'bg-blue-400', coffee: 'bg-amber-800',
};
const categoryHexColor: Record<string, string> = {
    beer: '#fbbf24', wine: '#ef4444', spirit: '#a855f7',
    longdrink: '#06b6d4', cocktail: '#ec4899', softdrink: '#22c55e',
    water: '#60a5fa', coffee: '#92400e',
};
const categoryEmoji: Record<string, string> = {
    beer: '🍺', wine: '🍷', spirit: '🥃', longdrink: '🍹', cocktail: '🍸',
    softdrink: '🥤', water: '💧', coffee: '☕',
};

// ─── Planungsgäste ────────────────────────────────────────────────────────────

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
const sektDrinks   = computed(() => eventDrinks.value.filter(d => d.type === 'sekt'));
const sektFlaschen = computed(() => Math.floor(planGuests.value * 1.25 / 6));

// ─── Ergebnis-Computed ────────────────────────────────────────────────────────

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

const DEFAULT_GLASSES_BY_CAT: Record<string, number> = {
    beer: 3, wine: 3, spirit: 3, longdrink: 2, cocktail: 2,
    water: 6, softdrink: 3, coffee: 1,
};

function defaultLiter(d: EventDrink): number {
    return d.selected_sizes.find(s => s.is_default)?.amount_liter ?? d.selected_sizes[0]?.amount_liter ?? 0;
}

function formatLiters(l: number): string {
    return l.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' L';
}

function formatSize(liter: number): string {
    if (liter < 0.1) return `${Math.round(liter * 100)} cl`;
    return `${liter.toLocaleString('de-DE')} l`;
}

const drinkResults = computed((): DrinkResult[] => {
    if (!eventDrinks.value.length) return [];
    const g = planGuests.value;
    if (g <= 0) return [];
    const results: DrinkResult[] = [];

    const sektTypes = [...new Map(sektDrinks.value.map(d => [d.type, d])).values()];
    for (const sd of sektTypes) {
        const fl = Math.ceil(sektFlaschen.value / Math.max(sektTypes.length, 1));
        results.push({
            type: sd.type, display_name: sd.display_name, category: sd.category,
            is_alcoholic: sd.is_alcoholic, amount_liter: defaultLiter(sd),
            liters: fl * 0.75,
            basisText: `${g} Gäste × 1,25 Gläser / 6 Gläser je Fl.`,
            isSekt: true,
        });
    }

    for (const d of eventDrinks.value.filter(d => !(d.category === 'wine' && d.type === 'sekt'))) {
        const glasses = DEFAULT_GLASSES_BY_CAT[d.category] ?? 2;
        const serving = defaultLiter(d);
        let liters = 0;
        if (d.is_alcoholic) {
            const catShare = normalizedPct.value[d.category] ?? 0;
            const catDrinks = alcoholicDrinksByCat.value[d.category] ?? [];
            const shareFactor = catDrinks.length > 1 ? drinkShareNorm(d.category, d.type) : 1;
            const drinkers = Math.round(alkTrinker.value * catShare * shareFactor);
            liters = alkTrinker.value * catShare * shareFactor * glasses * serving;
            const basisParts = [`~${drinkers} Pers.`, `${glasses} Gl.`, formatSize(serving)];
            if (catDrinks.length > 1) basisParts.splice(1, 0, `${Math.round(drinkShareNorm(d.category, d.type) * 100)} %`);
            results.push({
                type: d.type, display_name: d.display_name, category: d.category,
                is_alcoholic: d.is_alcoholic, amount_liter: serving, liters,
                basisText: basisParts.join(' × '),
            });
        } else {
            const catShare = normalizedPct.value[d.category] ?? 0;
            const catDrinks = allDrinksByCat.value[d.category] ?? [];
            const shareFactor = catDrinks.length > 1 ? drinkShareNorm(d.category, d.type) : 1;
            liters = g * catShare * shareFactor * glasses * serving;
            const basisParts = [`${g} Pers.`, `${glasses} Gl.`, formatSize(serving)];
            if (catDrinks.length > 1) basisParts.splice(1, 0, `${Math.round(shareFactor * 100)} %`);
            results.push({
                type: d.type, display_name: d.display_name, category: d.category,
                is_alcoholic: d.is_alcoholic, amount_liter: serving, liters,
                basisText: basisParts.join(' × '),
            });
        }
    }
    return results;
});

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

// ─── Kaufeinheiten ────────────────────────────────────────────────────────────

const BOTTLE_FALLBACK: Record<string, number> = {
    wine: 0.75, spirit: 0.7, longdrink: 0.75, cocktail: 0.75,
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

const purchaseConfig = ref<Record<string, PurchaseConfig>>(
    saved.purchaseConfig ? { ...saved.purchaseConfig } : {}
);

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
    markDirty();
}

function setCaseCount(type: string, val: string) {
    const v = parseInt(val, 10);
    const cur = purchaseConfig.value[type];
    if (!cur) return;
    purchaseConfig.value = { ...purchaseConfig.value, [type]: { ...cur, caseCount: v > 0 ? v : 1 } };
    markDirty();
}

function setPrice(type: string, val: string) {
    const price = val === '' ? null : parseFloat(val);
    const cur = purchaseConfig.value[type];
    if (!cur) return;
    purchaseConfig.value = { ...purchaseConfig.value, [type]: { ...cur, price: price && price > 0 ? price : null } };
    markDirty();
}

function calcUnits(liters: number, cfg: PurchaseConfig): number {
    const lpu = cfg.bottleSize * cfg.caseCount;
    return lpu > 0 ? Math.ceil(liters / lpu) : 0;
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

const resultCategories = computed(() => {
    const cats = [...new Set(drinkResults.value.map(r => r.category))];
    return categoryOrder.filter(c => cats.includes(c)).concat(cats.filter(c => !categoryOrder.includes(c)));
});

// ─── Save / Load ──────────────────────────────────────────────────────────────

function save() {
    const settings: CalculatorSettings = {
        guestBase:            guestBase.value,
        bufferMode:           bufferMode.value,
        bufferAbs:            bufferAbs.value,
        bufferPct:            bufferPct.value,
        alkPct:               alkPct.value,
        categoryPct:          { ...categoryPct.value },
        drinkShareWithinCat:  JSON.parse(JSON.stringify(drinkShareWithinCat)),
        purchaseConfig:       { ...purchaseConfig.value },
    };
    saving.value = true;
    skipGuard.value = true;
    router.patch(route('drinks.calculator.save'), { settings }, {
        onSuccess: () => {
            isDirty.value = false;
            toast.success(t('toast.eventSettingsSaved'));
        },
        onFinish: () => { saving.value = false; skipGuard.value = false; },
    });
}

function reset() {
    isDirty.value = false;
    router.reload();
}

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value && !skipGuard.value) { e.preventDefault(); e.returnValue = ''; }
}
let removeInertiaGuard: (() => void) | null = null;

onMounted(() => {
    // Gespeicherte Werte in die reaktiven Strukturen laden (nach der initialen Watch-Initialisierung)
    if (props.calculator_settings) {
        const s = props.calculator_settings;
        if (s.categoryPct) {
            categoryPct.value = { ...categoryPct.value, ...s.categoryPct };
        }
        if (s.drinkShareWithinCat) {
            for (const [cat, shares] of Object.entries(s.drinkShareWithinCat)) {
                if (drinkShareWithinCat[cat]) {
                    Object.assign(drinkShareWithinCat[cat], shares);
                }
            }
        }
    }

    nextTick(() => { isInitialized.value = true; });

    window.addEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard = router.on('before', (event) => {
        if (isDirty.value && !skipGuard.value) {
            const confirmed = window.confirm(t('drink.unsavedChangesPrompt'));
            if (!confirmed) { event.preventDefault(); return false; }
        }
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard?.();
});
</script>

<template>
    <Head :title="t('nav.calculator')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4" :class="{ 'pb-24': isDirty }">

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

                    <!-- Kategorie-Verteilung (alle Kategorien unified) -->
                    <div v-if="presentAllCategories.length > 0" class="space-y-3">
                        <label class="text-sm font-medium">Kategorien-Verteilung</label>

                        <!-- Proportionalbalken -->
                        <div class="flex rounded-full overflow-hidden h-3 gap-px">
                            <div
                                v-for="cat in presentAllCategories"
                                :key="cat"
                                :class="[categoryBgColor[cat] ?? 'bg-muted', 'transition-all duration-300']"
                                :style="{ flexGrow: Math.max(categoryPct[cat] ?? 1, 1) }"
                            />
                        </div>

                        <!-- Vertikale Zeilen -->
                        <div class="space-y-1">
                            <template v-for="cat in presentAllCategories" :key="cat">
                                <div class="flex items-center gap-2">
                                    <div class="h-2.5 w-2.5 rounded-full shrink-0" :class="categoryBgColor[cat] ?? 'bg-muted'" />
                                    <span class="text-xs w-28 shrink-0 truncate" :style="{ color: categoryHexColor[cat] ?? 'inherit' }">
                                        {{ categoryEmoji[cat] }} {{ categoryLabels[cat] ?? cat }}
                                        <span class="text-muted-foreground/70 font-normal">
                                            (~{{ Math.round((categoryIsAlcoholic(cat) ? alkTrinker : planGuests) * (normalizedPct[cat] ?? 0)) }})
                                        </span>
                                    </span>
                                    <input
                                        type="range" min="0" max="100" step="1"
                                        :value="categoryPct[cat] ?? 0"
                                        @input="setCategoryPct(cat, Number(($event.target as HTMLInputElement).value))"
                                        class="flex-1 min-w-0"
                                        :style="{ accentColor: categoryHexColor[cat] ?? 'var(--primary)' }"
                                    />
                                    <input
                                        type="number" min="0" max="100"
                                        :value="categoryPct[cat] ?? 0"
                                        @change="setCategoryPct(cat, Number(($event.target as HTMLInputElement).value))"
                                        class="w-12 rounded border bg-background px-1.5 py-0.5 text-xs text-foreground text-right shrink-0"
                                        :style="{ outlineColor: categoryHexColor[cat] ?? '' }"
                                    />
                                    <span class="text-xs text-muted-foreground shrink-0">%</span>
                                    <button
                                        type="button"
                                        @click="toggleLock(cat)"
                                        :title="lockedCategories.has(cat) ? 'Entsperren' : 'Fixieren'"
                                        class="shrink-0 rounded p-0.5 transition-colors"
                                        :class="lockedCategories.has(cat) ? 'text-foreground' : 'text-muted-foreground/40 hover:text-muted-foreground'"
                                    >
                                        <svg v-if="lockedCategories.has(cat)" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        <svg v-else class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                                        </svg>
                                    </button>
                                    <button
                                        v-if="(allDrinksByCat[cat]?.length ?? 0) > 1"
                                        type="button"
                                        @click="toggleCatDetail(cat)"
                                        :title="expandedCatDetails.has(cat) ? 'Details einklappen' : 'Getränke-Aufteilung anpassen'"
                                        class="shrink-0 rounded p-0.5 transition-colors text-muted-foreground hover:text-foreground"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5 transition-transform duration-200"
                                            :class="{ 'rotate-180': expandedCatDetails.has(cat) }"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        >
                                            <path d="M6 9l6 6 6-6" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Aufklappbare Getränke-Aufteilung -->
                                <div
                                    v-if="expandedCatDetails.has(cat) && (allDrinksByCat[cat]?.length ?? 0) > 1"
                                    class="ml-5 mt-1 mb-2 space-y-2 rounded-md border border-dashed px-3 py-2"
                                    :style="{ borderColor: categoryHexColor[cat] + '50' }"
                                >
                                    <div class="flex rounded-full overflow-hidden h-1.5 gap-px">
                                        <div
                                            v-for="drink in allDrinksByCat[cat]"
                                            :key="drink.type"
                                            class="transition-all duration-300"
                                            :class="categoryBgColor[drink.category] ?? 'bg-muted'"
                                            :style="{
                                                flexGrow: Math.max(drinkShareWithinCat[cat]?.[drink.type] ?? 1, 1),
                                                opacity: 0.5 + 0.5 * ((drinkShareWithinCat[cat]?.[drink.type] ?? 0) / Math.max(drinkShareSum(cat), 1)),
                                            }"
                                        />
                                    </div>
                                    <div
                                        v-for="drink in allDrinksByCat[cat]"
                                        :key="drink.type"
                                        class="flex items-center gap-2"
                                    >
                                        <span class="text-xs w-28 shrink-0 truncate text-muted-foreground">
                                            {{ drink.display_name }}
                                            <span class="text-muted-foreground/60">
                                                ({{ formatLiters(drinkResults.find(r => r.type === drink.type)?.liters ?? 0) }})
                                            </span>
                                        </span>
                                        <input
                                            type="range" min="0" max="100" step="1"
                                            :value="drinkShareWithinCat[cat]?.[drink.type] ?? 0"
                                            @input="setDrinkShare(cat, drink.type, Number(($event.target as HTMLInputElement).value))"
                                            class="flex-1 min-w-0"
                                            :style="{ accentColor: categoryHexColor[drink.category] ?? 'var(--primary)' }"
                                        />
                                        <input
                                            type="number" min="0" max="100"
                                            :value="drinkShareWithinCat[cat]?.[drink.type] ?? 0"
                                            @change="setDrinkShare(cat, drink.type, Number(($event.target as HTMLInputElement).value))"
                                            class="w-12 rounded border bg-background px-1.5 py-0.5 text-xs text-foreground text-right shrink-0"
                                        />
                                        <span class="text-xs text-muted-foreground shrink-0">%</span>
                                        <button
                                            type="button"
                                            @click="lockedDrinks.has(`${cat}:${drink.type}`) ? lockedDrinks.delete(`${cat}:${drink.type}`) : lockedDrinks.add(`${cat}:${drink.type}`)"
                                            :title="lockedDrinks.has(`${cat}:${drink.type}`) ? 'Entsperren' : 'Fixieren'"
                                            class="shrink-0 rounded p-0.5 transition-colors"
                                            :class="lockedDrinks.has(`${cat}:${drink.type}`) ? 'text-foreground' : 'text-muted-foreground/40 hover:text-muted-foreground'"
                                        >
                                            <svg v-if="lockedDrinks.has(`${cat}:${drink.type}`)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                            <svg v-else class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-end gap-1.5 border-t pt-1">
                                        <span class="text-xs text-muted-foreground">Gesamt</span>
                                        <span class="text-xs font-semibold tabular-nums" :class="drinkShareSum(cat) === 100 ? 'text-green-500 dark:text-green-400' : 'text-amber-500 dark:text-amber-400'">{{ drinkShareSum(cat) }} %</span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-end gap-1.5 pt-1.5 border-t">
                            <span class="text-xs text-muted-foreground">Gesamt</span>
                            <span class="text-xs font-semibold tabular-nums" :class="categorySum === 100 ? 'text-green-500 dark:text-green-400' : 'text-amber-500 dark:text-amber-400'">{{ categorySum }} %</span>
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

                    <div class="flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                        <span class="mt-px shrink-0">ℹ</span>
                        <p>{{ t('drink.calc.resultsNote') }}</p>
                    </div>

                    <div v-if="sanityWarning" class="flex items-start gap-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                        <span class="mt-px shrink-0">⚠</span>
                        <p>{{ t('drink.calc.sanityWarning', { n: sanityWarning }) }}</p>
                    </div>

                    <!-- Leerstate -->
                    <div v-if="eventDrinks.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                        <p>{{ t('drink.calc.emptyDrinks') }}</p>
                        <a href="/drinks" class="mt-2 inline-block text-primary underline underline-offset-2 text-sm">
                            → {{ t('nav.drinks') }}
                        </a>
                    </div>

                    <!-- Ergebnistabelle -->
                    <template v-else>
                        <div v-for="cat in resultCategories" :key="cat" class="space-y-2">
                            <div class="flex items-center gap-2 pt-2">
                                <span class="text-base">{{ categoryEmoji[cat] ?? '?' }}</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ categoryLabels[cat] ?? cat }}
                                </span>
                            </div>

                            <div
                                v-for="result in drinkResults.filter(r => r.category === cat)"
                                :key="result.type"
                                class="rounded-md border bg-muted/30 px-3 py-2.5 space-y-2"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-medium">{{ result.display_name }}</span>
                                    <span v-if="result.liters > 0" class="text-sm font-semibold text-primary shrink-0">
                                        {{ formatLiters(result.liters) }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground shrink-0">{{ t('drink.calc.noModel') }}</span>
                                </div>

                                <p v-if="result.basisText && result.liters > 0" class="text-xs text-muted-foreground">
                                    {{ t('drink.calc.basis') }}: {{ result.basisText }}
                                </p>
                                <p v-if="result.isSekt" class="text-xs text-muted-foreground italic">
                                    {{ t('drink.calc.sektNote') }}
                                </p>

                                <div v-if="result.liters > 0 && result.category !== 'coffee'" class="space-y-1.5 pt-0.5">
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

                            <div v-if="!categoryOrder.includes(cat)" class="rounded-md border bg-muted/30 px-3 py-2.5">
                                <span class="text-xs text-muted-foreground">{{ t('drink.calc.noModel') }}</span>
                            </div>
                        </div>

                        <div v-if="totalCost !== null" class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2.5 flex items-center justify-between mt-2">
                            <span class="text-sm font-medium">{{ t('drink.calc.totalCost') }}</span>
                            <span class="text-sm font-semibold text-primary">
                                {{ totalCost.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
                            </span>
                        </div>

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
                <span class="text-xs text-muted-foreground mr-1">{{ t('drink.unsavedChanges') }}</span>
                <Button variant="ghost" size="sm" :disabled="saving" @click="reset">{{ t('common.cancel') }}</Button>
                <Button size="sm" :disabled="saving" @click="save">{{ saving ? '…' : t('common.save') }}</Button>
            </div>
        </Transition>

    </AppLayout>
</template>
