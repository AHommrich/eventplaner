<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, reactive, watch, onMounted, onBeforeUnmount } from 'vue';
import { useFloatingBar } from '@/composables/useFloatingBar';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('drink.title'), href: '/drinks' }];
const page = usePage();

interface CatalogEntry {
    id: number;
    category: string;
    type: string;
    display_name: string;
    amount_liter: number;
    is_alcoholic: boolean;
    points: number;
    event_drink_id: number | null;
}
interface EventDrink {
    id: number;
    catalog_id: number;
    type: string;
    display_name: string;
    amount_liter: number;
    category: string;
    is_alcoholic: boolean;
    points: number;
}

const eventDrinks = computed(() => page.props.event_drinks as EventDrink[]);
const catalog     = computed(() => page.props.catalog as Record<string, CatalogEntry[]>);

// Event-Drinks nach type gruppieren: { type: EventDrink[] }
const eventDrinksByType = computed(() => {
    const map: Record<string, EventDrink[]> = {};
    for (const d of eventDrinks.value) {
        if (!map[d.type]) map[d.type] = [];
        map[d.type].push(d);
    }
    return map;
});

// Initialer Server-Stand: catalogId → eventDrinkId
const initialAddedMap = computed(() => {
    const map: Record<number, number> = {};
    for (const entries of Object.values(catalog.value)) {
        for (const entry of entries) {
            if (entry.event_drink_id) map[entry.id] = entry.event_drink_id;
        }
    }
    return map;
});

// Lokaler Zwischenstand (noch nicht gespeichert)
const localAdded   = reactive(new Set<number>()); // catalog IDs die der User hinzufügen will
const localRemoved = reactive(new Set<number>()); // catalog IDs die der User entfernen will

const isDirty = computed(() => localAdded.size > 0 || localRemoved.size > 0);

const { active: floatingBarActive } = useFloatingBar();
watch(isDirty, val => { floatingBarActive.value = val; }, { immediate: true });
onBeforeUnmount(() => { floatingBarActive.value = false; });

function isSelected(catalogId: number): boolean {
    if (localAdded.has(catalogId)) return true;
    const originallyAdded = initialAddedMap.value[catalogId] != null;
    return originallyAdded && !localRemoved.has(catalogId);
}

function toggleBadge(entry: CatalogEntry) {
    if (isSelected(entry.id)) {
        if (localAdded.has(entry.id)) {
            localAdded.delete(entry.id);         // pending add rückgängig
        } else {
            localRemoved.add(entry.id);          // für Löschung markieren
        }
    } else {
        if (localRemoved.has(entry.id)) {
            localRemoved.delete(entry.id);       // pending remove rückgängig
        } else {
            localAdded.add(entry.id);            // für Hinzufügen markieren
        }
    }
}

function reset() {
    localAdded.clear();
    localRemoved.clear();
}

const saving = ref(false);
const skipGuard = ref(false);

function save() {
    skipGuard.value = true;
    const removeEventDrinkIds = Array.from(localRemoved)
        .map(catalogId => initialAddedMap.value[catalogId])
        .filter(Boolean) as number[];

    saving.value = true;
    router.post(route('drinks.batch'), {
        add:    Array.from(localAdded),
        remove: removeEventDrinkIds,
    }, {
        onSuccess: () => {
            localAdded.clear();
            localRemoved.clear();
            toast.success(t('toast.drinksSaved'));
        },
        onFinish: () => { saving.value = false; skipGuard.value = false; },
    });
}

// Navigation Guard: warnen wenn ungespeicherte Änderungen
function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value && !skipGuard.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

// Inertia Navigation Guard
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
});

// Katalog-Hilfsfunktionen
const categoryOrder = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];
const categoryLabels: Record<string, string> = {
    beer: 'Bier', wine: 'Wein', spirit: 'Shots & Spirituosen',
    longdrink: 'Longdrinks', cocktail: 'Cocktails', softdrink: 'Softdrinks',
    water: 'Wasser', coffee: 'Kaffee & Tee',
};

const sortedCategories = computed(() =>
    categoryOrder.filter(cat => catalog.value[cat]?.length > 0)
);

// Eingeklappte Kategorien (standard: alle eingeklappt)
const collapsed = reactive(new Set<string>(categoryOrder));

function toggleCategory(cat: string) {
    if (collapsed.has(cat)) collapsed.delete(cat);
    else collapsed.add(cat);
}

const search = ref('');
const filteredCatalog = computed(() => {
    if (!search.value.trim()) return catalog.value;
    // Beim Suchen alle gefundenen Kategorien aufklappen
    for (const cat of categoryOrder) collapsed.delete(cat);
    const q = search.value.toLowerCase();
    const result: Record<string, CatalogEntry[]> = {};
    for (const cat of sortedCategories.value) {
        const filtered = (catalog.value[cat] ?? []).filter(e =>
            e.display_name.toLowerCase().includes(q)
        );
        if (filtered.length) result[cat] = filtered;
    }
    return result;
});
const filteredCategories = computed(() =>
    categoryOrder.filter(cat => filteredCatalog.value[cat]?.length > 0)
);

const groupedByType = computed(() => {
    const result: Record<string, Record<string, CatalogEntry[]>> = {};
    for (const cat of filteredCategories.value) {
        result[cat] = {};
        for (const entry of (filteredCatalog.value[cat] ?? [])) {
            if (!result[cat][entry.type]) result[cat][entry.type] = [];
            result[cat][entry.type].push(entry);
        }
    }
    return result;
});

function baseName(entry: CatalogEntry): string {
    // Entfernt Größenangabe am Ende: "Pils 0,5 l" → "Pils", "Vodka 2 cl" → "Vodka"
    return entry.display_name.replace(/\s+\d[\d,.]*\s*(l|cl)$/i, '').trim();
}

function formatSize(liter: number): string {
    if (liter < 0.1) return `${Math.round(liter * 100)} cl`;
    return `${liter.toLocaleString('de-DE')} l`;
}
</script>

<template>
    <Head :title="t('drink.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Padding unten damit der floating bar nichts überdeckt -->
        <div class="m-4 space-y-4" :class="{ 'pb-24': isDirty }">

            <!-- Aktive Getränke für dieses Event -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.forEvent') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventDrinks.length === 0" class="text-sm text-muted-foreground">{{ t('drink.none') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="(drinks, type) in eventDrinksByType" :key="type"
                            class="flex items-center justify-between gap-3 px-0 py-2.5">
                            <span class="text-sm font-medium shrink-0">
                                {{ drinks[0].display_name.replace(/\s+\d[\d,.]*\s*(l|cl)$/i, '').trim() }}
                            </span>
                            <div class="flex flex-wrap gap-1.5 justify-end">
                                <span
                                    v-for="d in drinks"
                                    :key="d.id"
                                    class="inline-flex items-center gap-1 rounded-full border border-green-400 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                    {{ formatSize(d.amount_liter) }}
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
                            <li v-for="(entries, type) in groupedByType[cat]" :key="type"
                                class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <span class="text-sm font-medium shrink-0">{{ baseName(entries[0]) }}</span>
                                <div class="flex flex-wrap gap-1.5 justify-end">
                                    <button
                                        v-for="entry in entries"
                                        :key="entry.id"
                                        :disabled="saving"
                                        @click="toggleBadge(entry)"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors',
                                            isSelected(entry.id)
                                                ? 'border-green-400 bg-green-100 text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400'
                                                : 'border-border bg-muted text-muted-foreground hover:bg-muted/70'
                                        ]"
                                    >
                                        <svg v-if="isSelected(entry.id)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6L9 17l-5-5" />
                                        </svg>
                                        {{ formatSize(entry.amount_liter) }}
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

        </div>

        <!-- Floating Save/Reset Bar -->
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
                <Button variant="ghost" size="sm" :disabled="saving" @click="reset">
                    {{ t('common.cancel') }}
                </Button>
                <Button size="sm" :disabled="saving" @click="save">
                    {{ saving ? '…' : t('common.save') }}
                </Button>
            </div>
        </Transition>

    </AppLayout>
</template>
