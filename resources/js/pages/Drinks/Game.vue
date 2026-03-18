<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [
    { title: t('drink.title'), href: '/drinks' },
    { title: t('game.title'), href: '/drinks/game' },
];

const page = usePage();
const eventDrinks  = computed(() => page.props.event_drinks as { id: number; display_name: string; is_alcoholic: boolean; points: number }[]);
const eventTotals  = computed(() => page.props.event_totals as { drink_id: number; display_name: string; points_each: number; total: number; points_total: number }[]);
const leaderboard  = computed(() => page.props.leaderboard as { drink_id: number; display_name: string; points_each: number; top: { guest_id: number; firstname: string; lastname: string; count: number; points_total: number }[] }[]);
const guestTotals  = computed(() => page.props.guest_totals as { guest_id: number; firstname: string; lastname: string; total: number; points_total: number }[]);
</script>

<template>
    <Head :title="t('game.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <!-- Verfügbare Getränke mit Punkten -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.availableDrinks') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventDrinks.length === 0" class="text-sm text-muted-foreground">{{ t('game.empty') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="drink in eventDrinks" :key="drink.id" class="flex items-center justify-between py-2">
                            <span class="text-sm">{{ drink.display_name }}</span>
                            <span :class="['text-sm font-semibold', drink.points < 0 ? 'text-blue-600 dark:text-blue-400' : 'text-amber-600 dark:text-amber-400']">
                                {{ drink.points > 0 ? '+' : '' }}{{ drink.points }} {{ t('game.points') }}
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Gesamt-Rangliste nach Punkten -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.leaderboard') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="guestTotals.length === 0" class="text-sm text-muted-foreground">{{ t('game.empty') }}</p>
                    <ol v-else class="divide-y">
                        <li v-for="(row, i) in guestTotals" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold text-muted-foreground w-6">{{ i + 1 }}</span>
                                <span class="text-sm font-medium">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-xs text-muted-foreground">{{ row.total }}×</span>
                                <span class="text-sm font-bold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- Getränke-Übersicht -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('game.drinkTotals') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventTotals.length === 0" class="text-sm text-muted-foreground">{{ t('game.empty') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="row in eventTotals" :key="row.drink_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ row.display_name }}</span>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ row.points_each }} {{ t('game.points') }}/{{ t('game.glass') }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-xs text-muted-foreground">{{ row.total }}×</span>
                                <span class="text-sm font-semibold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Top-Trinker pro Getränk -->
            <Card v-for="drink in leaderboard" :key="drink.drink_id">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ drink.display_name }}
                        <span class="text-sm font-normal text-muted-foreground">({{ drink.points_each }} {{ t('game.points') }}/{{ t('game.glass') }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="divide-y">
                        <li v-for="(row, i) in drink.top" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold text-muted-foreground w-6">{{ i + 1 }}</span>
                                <span class="text-sm">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <span class="text-xs text-muted-foreground">{{ row.count }}×</span>
                                <span class="text-sm font-semibold">{{ row.points_total }} {{ t('game.points') }}</span>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
