<script setup lang="ts">
import RoleBadge from '@/components/RoleBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Role = 'owner' | 'event_admin' | 'event_manager' | 'superadmin' | null;
interface AccessibleEvent {
    id: number;
    name: string;
    my_role: Role;
}

const { t } = useI18n();
const page = usePage();

const events = computed(() => ((page.props as any).accessible_events ?? []) as AccessibleEvent[]);
const activeEventId = computed(() => (page.props as any).active_event?.id ?? null);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Meine Events', href: '/settings/events' }];

function switchTo(id: number) {
    router.post('/events/switch', { event_id: id });
}
</script>

<template>
    <Head :title="t('settings.eventsTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <header>
                    <h2 class="text-lg font-medium">{{ t('settings.eventsTitle') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ t('settings.eventsDesc') }}</p>
                </header>

                <ul class="divide-y rounded-md border">
                    <li v-for="event in events" :key="event.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <Check v-if="event.id === activeEventId" class="size-4 shrink-0 text-primary" />
                            <span v-else class="size-4 shrink-0" />
                            <span class="truncate text-sm font-medium">{{ event.name }}</span>
                            <RoleBadge :role="event.my_role" />
                        </div>
                        <Button v-if="event.id !== activeEventId" variant="outline" size="sm" class="shrink-0" @click="switchTo(event.id)">
                            {{ t('settings.switchTo') }}
                        </Button>
                        <span v-else class="shrink-0 text-xs text-muted-foreground">{{ t('settings.activeEvent') }}</span>
                    </li>
                    <li v-if="events.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        {{ t('settings.noEvents') }}
                    </li>
                </ul>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
