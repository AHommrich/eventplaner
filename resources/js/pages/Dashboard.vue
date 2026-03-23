<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.forms'), href: '/dashboard' }];
const page  = usePage();
const stats = computed(() => page.props.stats as {
    guest_total:   number;
    rsvp_accepted: number;
    rsvp_declined: number;
    rsvp_open:     number;
    photo_count:   number;
});

function pct(n: number): string {
    const total = stats.value.guest_total;
    if (!total) return '0 %';
    return Math.round((n / total) * 100) + ' %';
}
</script>

<template>
    <Head :title="t('nav.forms')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">

                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">{{ t('dashboard.guestTotal') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.guest_total }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">{{ t('dashboard.rsvpAccepted') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ stats.rsvp_accepted }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ pct(stats.rsvp_accepted) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">{{ t('dashboard.rsvpDeclined') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ stats.rsvp_declined }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ pct(stats.rsvp_declined) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">{{ t('dashboard.rsvpOpen') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.rsvp_open }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ pct(stats.rsvp_open) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">{{ t('dashboard.photos') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.photo_count }}</p>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
