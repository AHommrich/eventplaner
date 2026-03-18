<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('drink.title'), href: '/drinks' }];
const page = usePage();

interface CatalogEntry {
    id: number;
    category: string;
    display_name: string;
    is_alcoholic: boolean;
    points: number;
    already_added: boolean;
}
interface EventDrink {
    id: number;
    catalog_id: number;
    display_name: string;
    category: string;
    is_alcoholic: boolean;
    points: number;
}

const eventDrinks = computed(() => page.props.event_drinks as EventDrink[]);
const catalog     = computed(() => page.props.catalog as Record<string, CatalogEntry[]>);

const categoryOrder = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];
const categoryLabels: Record<string, string> = {
    beer: 'Bier', wine: 'Wein', spirit: 'Shots & Spirituosen',
    longdrink: 'Longdrinks', cocktail: 'Cocktails', softdrink: 'Softdrinks',
    water: 'Wasser', coffee: 'Kaffee & Tee',
};

const sortedCategories = computed(() =>
    categoryOrder.filter(cat => catalog.value[cat]?.length > 0)
);

// Suchfeld
const search = ref('');
const filteredCatalog = computed(() => {
    if (!search.value.trim()) return catalog.value;
    const q = search.value.toLowerCase();
    const result: Record<string, CatalogEntry[]> = {};
    for (const cat of sortedCategories.value) {
        const filtered = (catalog.value[cat] ?? []).filter(entry =>
            entry.display_name.toLowerCase().includes(q)
        );
        if (filtered.length) result[cat] = filtered;
    }
    return result;
});
const filteredCategories = computed(() => categoryOrder.filter(cat => filteredCatalog.value[cat]?.length > 0));

// Hinzufügen
const addForm = useForm({ drink_catalog_id: 0 });
function addDrink(catalogId: number) {
    addForm.drink_catalog_id = catalogId;
    addForm.post(route('drinks.store'), { onSuccess: () => toast.success(t('toast.drinkAdded')) });
}

// Löschen
const confirmOpen = ref(false);
const pendingId   = ref<number | null>(null);
function askDelete(id: number) { pendingId.value = id; confirmOpen.value = true; }
function doDelete() {
    if (pendingId.value) router.delete(route('drinks.destroy', pendingId.value), {
        onSuccess: () => toast.success(t('toast.drinkDeleted')),
    });
}
</script>

<template>
    <Head :title="t('drink.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <!-- Aktive Getränke für dieses Event -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.forEvent') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventDrinks.length === 0" class="text-sm text-muted-foreground">{{ t('drink.none') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="drink in eventDrinks" :key="drink.id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ drink.display_name }}</span>
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', drink.is_alcoholic ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400']">
                                    {{ drink.points > 0 ? '+' : '' }}{{ drink.points }} {{ t('game.points') }}
                                </span>
                            </div>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDelete(drink.id)">
                                {{ t('common.remove') }}
                            </Button>
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ categoryLabels[cat] ?? cat }}
                        </p>
                        <ul class="divide-y rounded-md border">
                            <li v-for="entry in filteredCatalog[cat]" :key="entry.id"
                                class="flex items-center justify-between px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">{{ entry.display_name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ entry.points > 0 ? '+' : '' }}{{ entry.points }} {{ t('game.points') }}
                                    </span>
                                </div>
                                <Button
                                    v-if="!entry.already_added"
                                    size="sm"
                                    variant="outline"
                                    :disabled="addForm.processing"
                                    @click="addDrink(entry.id)"
                                >
                                    {{ t('common.add') }}
                                </Button>
                                <span v-else class="text-xs text-muted-foreground">✓</span>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('drink.deleteTitle')"
            :description="t('drink.deleteDescription')"
            :confirm-label="t('common.remove')"
            destructive
            @confirm="doDelete"
        />
    </AppLayout>
</template>
