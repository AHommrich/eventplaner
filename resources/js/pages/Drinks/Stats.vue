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
    { title: t('drink.stats'), href: '/drinks/stats' },
];

const page = usePage();
const eventTotals = computed(() => page.props.event_totals as { drink_id: number; drink_name: string; total: number }[]);
const leaderboard  = computed(() => page.props.leaderboard as { drink_id: number; drink_name: string; top: { guest_id: number; firstname: string; lastname: string; count: number }[] }[]);
const guestTotals  = computed(() => page.props.guest_totals as { guest_id: number; firstname: string; lastname: string; total: number }[]);
</script>

<template>
    <Head :title="t('drink.stats')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <!-- Gesamt-Rangliste Gäste -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.statsTotalGuests') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="guestTotals.length === 0" class="text-sm text-muted-foreground">{{ t('drink.statsEmpty') }}</p>
                    <ol v-else class="divide-y">
                        <li v-for="(row, i) in guestTotals" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold text-muted-foreground w-6">{{ i + 1 }}</span>
                                <span class="text-sm font-medium">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <span class="text-sm font-semibold">{{ row.total }} {{ t('drink.glasses') }}</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- Gesamt pro Getränk -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.statsTotalDrinks') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="eventTotals.length === 0" class="text-sm text-muted-foreground">{{ t('drink.statsEmpty') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="row in eventTotals" :key="row.drink_id" class="flex items-center justify-between py-2.5">
                            <span class="text-sm font-medium">{{ row.drink_name }}</span>
                            <span class="text-sm font-semibold">{{ row.total }}×</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Top-Trinker pro Getränk -->
            <Card v-for="drink in leaderboard" :key="drink.drink_id">
                <CardHeader>
                    <CardTitle>{{ drink.drink_name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="divide-y">
                        <li v-for="(row, i) in drink.top" :key="row.guest_id" class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold text-muted-foreground w-6">{{ i + 1 }}</span>
                                <span class="text-sm">{{ row.firstname }} {{ row.lastname }}</span>
                            </div>
                            <span class="text-sm font-semibold">{{ row.count }}×</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
