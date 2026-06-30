<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useFloatingBar } from '@/composables/useFloatingBar';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t, te } = useI18n();
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
    id: number; // size_id
    drink_id: number; // drinks.id (für remove)
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
// ─── Page Props ───────────────────────────────────────────────────────────────

const eventDrinks = computed(() => page.props.event_drinks as EventDrink[]);
const catalog = computed(() => page.props.catalog as Record<string, CatalogEntry[]>);
const drinkGameEnabled = computed(() => (page.props as any).active_event?.drink_game_enabled === true);

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

const localAdded = reactive(new Set<number>()); // size_ids
const localRemoved = reactive(new Set<number>()); // size_ids
const isDirty = computed(() => localAdded.size > 0 || localRemoved.size > 0);

const { active: floatingBarActive } = useFloatingBar();
watch(
    isDirty,
    (val) => {
        floatingBarActive.value = val;
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    floatingBarActive.value = false;
});

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

function reset() {
    localAdded.clear();
    localRemoved.clear();
}

const saving = ref(false);
const skipGuard = ref(false);

function save() {
    skipGuard.value = true;
    const removeEventDrinkIds = Array.from(localRemoved)
        .map((sizeId) => initialAddedMap.value[sizeId])
        .filter(Boolean) as number[];
    saving.value = true;
    router.post(
        route('drinks.batch'),
        {
            add: Array.from(localAdded),
            remove: removeEventDrinkIds,
        },
        {
            onSuccess: () => {
                localAdded.clear();
                localRemoved.clear();
                toast.success(t('toast.drinksSaved'));
            },
            onFinish: () => {
                saving.value = false;
                skipGuard.value = false;
            },
        },
    );
}

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
});

const categoryOrder = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];

function categoryLabel(cat: string): string {
    const key = `drink.categories.${cat}`;
    return te(key) ? t(key) : cat;
}

function drinkName(type: string, fallback: string): string {
    const key = `drink.names.${type}`;
    return te(key) ? t(key) : fallback;
}

const sortedCategories = computed(() => categoryOrder.filter((cat) => catalog.value[cat]?.length > 0));
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
        const filtered = (catalog.value[cat] ?? []).filter(
            (e) => drinkName(e.type, e.display_name).toLowerCase().includes(q) || e.display_name.toLowerCase().includes(q),
        );
        if (filtered.length) result[cat] = filtered;
    }
    return result;
});
const filteredCategories = computed(() => categoryOrder.filter((cat) => filteredCatalog.value[cat]?.length > 0));
function formatSize(liter: number): string {
    if (liter < 0.1) return `${Math.round(liter * 100)} cl`;
    return `${liter.toLocaleString('de-DE')} l`;
}
</script>

<template>
    <Head :title="t('drink.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4" :class="{ 'pb-24': isDirty }">
            <!-- Aktive Getränke für dieses Event -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.forEvent') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="mb-3 flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                        <span class="mt-px shrink-0 text-base leading-none">ℹ</span>
                        <p>
                            {{ t('drink.catalogHint') }}<template v-if="drinkGameEnabled"> {{ t('drink.gameHint') }}</template>
                        </p>
                    </div>
                    <p v-if="eventDrinks.length === 0" class="text-sm text-muted-foreground">{{ t('drink.none') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="d in eventDrinks" :key="d.catalog_id" class="flex items-center justify-between gap-3 px-0 py-2.5">
                            <span class="shrink-0 text-sm font-medium">{{ drinkName(d.type, d.display_name) }}</span>
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <span
                                    v-for="s in d.selected_sizes"
                                    :key="s.id"
                                    class="inline-flex items-center gap-1 rounded-full border border-green-400 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    <svg
                                        class="h-3 w-3"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
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
                            class="flex w-full items-center justify-between py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                        >
                            <span>{{ categoryLabel(cat) }}</span>
                            <svg
                                class="h-3.5 w-3.5 transition-transform duration-200"
                                :class="{ 'rotate-180': !collapsed.has(cat) }"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <ul v-if="!collapsed.has(cat)" class="divide-y rounded-md border">
                            <li v-for="entry in filteredCatalog[cat]" :key="entry.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <span class="shrink-0 text-sm font-medium">{{ drinkName(entry.type, entry.display_name) }}</span>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button
                                        v-for="s in entry.sizes"
                                        :key="s.id"
                                        :disabled="saving"
                                        @click="toggleSize(s)"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors',
                                            isSelected(s.id)
                                                ? 'border-green-400 bg-green-100 text-green-700 dark:border-green-600 dark:bg-green-900/30 dark:text-green-400'
                                                : 'border-border bg-muted text-muted-foreground hover:bg-muted/70',
                                        ]"
                                    >
                                        <svg
                                            v-if="isSelected(s.id)"
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
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
            <div v-if="isDirty" class="fixed right-6 bottom-6 z-50 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 shadow-lg">
                <span class="mr-1 text-xs text-muted-foreground">{{ t('drink.unsavedChanges') }}</span>
                <Button variant="ghost" size="sm" :disabled="saving" @click="reset">{{ t('common.cancel') }}</Button>
                <Button size="sm" :disabled="saving" @click="save">{{ saving ? '…' : t('common.save') }}</Button>
            </div>
        </Transition>
    </AppLayout>
</template>
