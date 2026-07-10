<script setup lang="ts">
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.forms'), href: '/dashboard' }];
const page = usePage();
const stats = computed(
    () =>
        page.props.stats as {
            guest_total: number;
            rsvp_accepted: number;
            rsvp_declined: number;
            rsvp_open: number;
            photo_count: number;
        },
);

interface PendingNotifications {
    revocations: number;
    event_requests: number;
    photo_reports: number;
    total: number;
}
const notifications = computed(
    () =>
        ((page.props as any).pending_notifications ?? {
            revocations: 0,
            event_requests: 0,
            photo_reports: 0,
            total: 0,
        }) as PendingNotifications,
);
const showNotifications = computed(() => notifications.value.total > 0);

function pct(n: number): string {
    const total = stats.value.guest_total;
    if (!total) return '0 %';
    return Math.round((n / total) * 100) + ' %';
}
</script>

<template>
    <Head :title="t('nav.forms')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <Card v-if="showNotifications" class="border-amber-500/50">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Bell class="size-4 text-amber-500" />
                        {{ t('dashboard.notificationsTitle') }}
                        <span class="inline-flex size-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white">{{
                            notifications.total > 99 ? '99+' : notifications.total
                        }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div v-if="notifications.photo_reports > 0" class="rounded border p-3">
                            <p class="text-2xl font-semibold">{{ notifications.photo_reports }}</p>
                            <p class="text-muted-foreground text-sm">{{ t('dashboard.notificationsPhotoReports') }}</p>
                        </div>
                        <div v-if="notifications.revocations > 0" class="rounded border p-3">
                            <p class="text-2xl font-semibold">{{ notifications.revocations }}</p>
                            <p class="text-muted-foreground text-sm">{{ t('dashboard.notificationsRevocations') }}</p>
                        </div>
                        <div v-if="notifications.event_requests > 0" class="rounded border p-3">
                            <p class="text-2xl font-semibold">{{ notifications.event_requests }}</p>
                            <p class="text-muted-foreground text-sm">{{ t('dashboard.notificationsEventRequests') }}</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <Button as-child size="sm" variant="outline">
                            <Link href="/requests">{{ t('dashboard.notificationsOpen') }}</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-muted-foreground text-sm font-medium">{{ t('dashboard.guestTotal') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.guest_total }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-muted-foreground text-sm font-medium">{{ t('dashboard.rsvpAccepted') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ stats.rsvp_accepted }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ pct(stats.rsvp_accepted) }} {{ t('dashboard.rsvpOfTotal') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-muted-foreground text-sm font-medium">{{ t('dashboard.rsvpDeclined') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ stats.rsvp_declined }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ pct(stats.rsvp_declined) }} {{ t('dashboard.rsvpOfTotal') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-muted-foreground text-sm font-medium">{{ t('dashboard.rsvpOpen') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.rsvp_open }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ pct(stats.rsvp_open) }} {{ t('dashboard.rsvpOfTotal') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-muted-foreground flex items-center gap-1.5 text-sm font-medium">
                            {{ t('dashboard.photos') }}
                            <InfoTooltip :text="t('dashboard.photosInfo')" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.photo_count }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
