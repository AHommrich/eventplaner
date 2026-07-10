<script setup lang="ts">
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t, te } = useI18n();

function drinkName(type: string | null | undefined, fallback: string): string {
    if (type) {
        const key = `drink.names.${type}`;
        if (te(key)) return t(key);
    }
    return fallback;
}
const breadcrumbs: BreadcrumbItem[] = [
    { title: t('drink.title'), href: '/drinks' },
    { title: t('game.title'), href: '/drinks/game' },
];

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
    is_alcoholic: boolean;
    selected_sizes: SelectedSize[];
}

const page = usePage();
const eventDrinks = computed(() => page.props.event_drinks as EventDrink[]);

const endTime = ref<string>((page.props as any).drink_game_end_time ?? '');
const savingEndTime = ref(false);

function saveEndTime() {
    savingEndTime.value = true;
    router.patch(
        route('drinks.game.update'),
        {
            drink_game_end_time: endTime.value || null,
        },
        {
            onSuccess: () => toast.success(t('toast.saved')),
            onFinish: () => {
                savingEndTime.value = false;
            },
        },
    );
}

function formatSize(liter: number): string {
    if (liter < 0.1) return `${Math.round(liter * 100)} cl`;
    return `${liter.toLocaleString('de-DE')} l`;
}
const eventTotals = computed(
    () =>
        page.props.event_totals as {
            catalog_id: number;
            type: string;
            display_name: string;
            points_each: number;
            total: number;
            points_total: number;
        }[],
);
const leaderboard = computed(
    () =>
        page.props.leaderboard as {
            catalog_id: number;
            type: string;
            display_name: string;
            points_each: number;
            top: { guest_id: number; firstname: string; lastname: string; count: number; points_total: number }[];
        }[],
);
const guestTotals = computed(
    () => page.props.guest_totals as { guest_id: number; firstname: string; lastname: string; total: number; points_total: number }[],
);
</script>

<template>
    <Head :title="t('game.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <!-- Game settings -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.settings') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-1.5">
                        <Label class="text-sm">{{ t('event.drinkGameEndTime') }}</Label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="endTime"
                                type="datetime-local"
                                class="border-input bg-background focus:ring-ring h-9 flex-1 rounded-md border px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1"
                            />
                            <Button size="sm" :disabled="savingEndTime" @click="saveEndTime">
                                {{ savingEndTime ? '…' : t('common.save') }}
                            </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">{{ t('event.drinkGameEndTimeDesc') }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Available drinks with points -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('game.availableDrinks') }}
                        <InfoTooltip :text="t('game.pointsInfo')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventDrinks.length === 0" class="text-muted-foreground text-sm">{{ t('game.empty') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="d in eventDrinks" :key="d.catalog_id" class="flex items-center justify-between gap-3 py-2.5">
                            <span class="shrink-0 text-sm font-medium">{{ drinkName(d.type, d.display_name) }}</span>
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <span
                                    v-for="s in d.selected_sizes"
                                    :key="s.id"
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                        s.points < 0
                                            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    ]"
                                >
                                    {{ formatSize(s.amount_liter) }} · {{ s.points > 0 ? '+' : '' }}{{ s.points }} {{ t('game.points') }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Overall leaderboard by points -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.leaderboard') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="guestTotals.length === 0" class="text-muted-foreground text-sm">{{ t('game.empty') }}</p>
                    <ol v-else class="divide-y">
                        <li v-for="(row, i) in guestTotals" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-muted-foreground w-6 text-lg font-bold">{{ i + 1 }}</span>
                                <span class="text-sm font-medium">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-muted-foreground text-xs">{{ row.total }}×</span>
                                <span class="text-sm font-bold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- Drinks overview -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.drinkTotals') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventTotals.length === 0" class="text-muted-foreground text-sm">{{ t('game.empty') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="row in eventTotals" :key="row.catalog_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ drinkName(row.type, row.display_name) }}</span>
                                <span class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs"
                                    >{{ row.points_each }} {{ t('game.points') }}/{{ t('game.glass') }}</span
                                >
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-muted-foreground text-xs">{{ row.total }}×</span>
                                <span class="text-sm font-semibold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Top drinkers per drink -->
            <Card v-for="drink in leaderboard" :key="drink.catalog_id">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ drinkName(drink.type, drink.display_name) }}
                        <span class="text-muted-foreground text-sm font-normal"
                            >({{ drink.points_each }} {{ t('game.points') }}/{{ t('game.glass') }})</span
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="divide-y">
                        <li v-for="(row, i) in drink.top" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-muted-foreground w-6 text-lg font-bold">{{ i + 1 }}</span>
                                <span class="text-sm">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-muted-foreground text-xs">{{ row.count }}×</span>
                                <span class="text-sm font-semibold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
