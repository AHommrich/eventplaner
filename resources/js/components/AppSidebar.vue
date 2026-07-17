<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OnboardingModal from '@/components/OnboardingModal.vue';
import RequestEventModal from '@/components/RequestEventModal.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useEventRequestModal } from '@/composables/useEventRequestModal';
import { useOnboardingModal } from '@/composables/useOnboardingModal';
import { setLocale } from '@/plugins/i18n';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    Camera,
    Check,
    ChevronsUpDown,
    Clock,
    GlassWater,
    Images,
    KeyRound,
    LayoutDashboard,
    ListTodo,
    Palette,
    Plus,
    QrCode,
    Settings2,
    ShieldCheck,
    Trophy,
    Undo2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const { t, locale } = useI18n();
const page = usePage();
const { isMobile, state } = useSidebar();
const { open: requestModalOpen } = useEventRequestModal();
const { open: onboardingOpen } = useOnboardingModal();

type EventRole = 'owner' | 'event_admin' | 'event_manager' | 'superadmin' | null;

const isAdmin = computed(() => (page.props.auth as any)?.user?.role === 'admin');
const activeEvent = computed(
    () =>
        (page.props as any).active_event as {
            id: number;
            name: string;
            user_id?: number;
            my_role?: EventRole;
            drink_game_enabled?: boolean;
            photo_game_enabled?: boolean;
        } | null,
);
// Administer tier = owner ∪ event_admin ∪ superadmin. Drives which deep-config
// nav items are shown; the server policy remains authoritative.
const canAdminister = computed(() => isAdmin.value || ['owner', 'event_admin'].includes(activeEvent.value?.my_role ?? ''));
const accessibleEvents = computed(() => (page.props as any).accessible_events as { id: number; name: string; my_role?: EventRole }[]);

interface EventRequestItem {
    id: number;
    event_name: string;
    status: 'pending' | 'declined';
    created_at: string;
}
const userEventRequests = computed(() => ((page.props as any).user_event_requests ?? []) as EventRequestItem[]);
const pendingRequest = computed(() => userEventRequests.value.find((r) => r.status === 'pending'));

interface PendingNotifications {
    revocations: number;
    event_requests: number;
    photo_reports: number;
    total: number;
}
const pendingNotifications = computed(
    () =>
        ((page.props as any).pending_notifications ?? {
            revocations: 0,
            event_requests: 0,
            photo_reports: 0,
            total: 0,
        }) as PendingNotifications,
);

// Admin: always dropdown. User with events: always dropdown. User without event: no dropdown (button instead).
const showSwitcher = computed(() => isAdmin.value || accessibleEvents.value?.length > 0);

const search = ref('');
const filteredEvents = computed(() => {
    if (!search.value) return accessibleEvents.value ?? [];
    return (accessibleEvents.value ?? []).filter((ev) => ev.name.toLowerCase().includes(search.value.toLowerCase()));
});

function switchEvent(eventId: number) {
    router.post('/events/switch', { event_id: eventId });
    search.value = '';
}

const eventNavItems = computed<NavItem[]>(() => [
    { title: t('nav.forms'), href: '/dashboard', icon: LayoutDashboard },
    { title: t('nav.guests'), href: '/guests', icon: Users },
    { title: t('nav.invitations'), href: '/invitations', icon: QrCode },
    { title: t('nav.drinks'), href: '/drinks', icon: GlassWater },
    ...(activeEvent.value?.drink_game_enabled ? [{ title: t('nav.drinkGame'), href: '/drinks/game', icon: Trophy }] : []),
    { title: t('nav.photos'), href: '/photos', icon: Images },
    ...(activeEvent.value?.photo_game_enabled ? [{ title: t('nav.photoGame'), href: '/photos/game', icon: Camera }] : []),
    { title: t('nav.notes'), href: '/notes', icon: ListTodo },
    { title: t('nav.manageAccess'), href: '/event/access', icon: KeyRound },
    { title: t('nav.requests'), href: '/requests', icon: Undo2, badge: pendingNotifications.value.total },
    // Deep event settings is administer-only.
    ...(canAdminister.value ? [{ title: t('nav.eventSettings'), href: '/event/settings', icon: Settings2 }] : []),
]);

// "App" section — everything shaping the guest companion app. Administer-only.
const appNavItems = computed<NavItem[]>(() => [
    { title: t('nav.appDesign'), href: '/app/design', icon: Palette },
    { title: t('nav.schedule'), href: '/schedule', icon: CalendarClock },
]);

const platformNavItems = computed<NavItem[]>(() => [{ title: t('nav.userManagement'), href: '/admin/users', icon: ShieldCheck }]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- Admin without events: direct link to "New event" -->
            <SidebarMenu v-if="isAdmin && !activeEvent">
                <SidebarMenuItem>
                    <SidebarMenuButton :tooltip="t('event.newEvent')" @click="onboardingOpen = true">
                        <Plus class="size-4 shrink-0" />
                        <span>{{ t('event.newEvent') }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- Event dropdown (admin with events always, user if at least 1 event) -->
            <SidebarMenu v-else-if="activeEvent && showSwitcher">
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <CalendarDays class="size-4 shrink-0" />
                                <span v-if="state !== 'collapsed'" class="truncate text-sm font-medium">{{ activeEvent.name }}</span>
                                <ChevronsUpDown v-if="state !== 'collapsed'" class="ml-auto size-4 shrink-0 opacity-50" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            :side="isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'"
                            align="start"
                            :side-offset="4"
                        >
                            <div class="px-2 py-1.5">
                                <input
                                    v-model="search"
                                    type="text"
                                    :placeholder="t('event.search')"
                                    class="w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm outline-none placeholder:text-muted-foreground"
                                    @keydown.stop
                                />
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-for="ev in filteredEvents" :key="ev.id" @click="switchEvent(ev.id)" class="cursor-pointer">
                                <Check v-if="ev.id === activeEvent.id" class="mr-2 size-4" />
                                <span v-else class="mr-2 size-4" />
                                <span class="truncate">{{ ev.name }}</span>
                                <RoleBadge :role="ev.my_role ?? null" class="ml-auto" />
                            </DropdownMenuItem>
                            <p v-if="filteredEvents.length === 0" class="px-2 py-3 text-center text-xs text-muted-foreground">
                                {{ t('event.notFound') }}
                            </p>

                            <DropdownMenuSeparator />

                            <!-- Admin: create event directly -->
                            <DropdownMenuItem v-if="isAdmin" @click="onboardingOpen = true" class="cursor-pointer">
                                <Plus class="mr-2 size-4" />
                                {{ t('event.newEvent') }}
                            </DropdownMenuItem>

                            <!-- User: request event or pending status -->
                            <template v-else>
                                <DropdownMenuItem v-if="!pendingRequest" @click="requestModalOpen = true" class="cursor-pointer">
                                    <CalendarPlus class="mr-2 size-4" />
                                    {{ t('nav.requestEvent') }}
                                </DropdownMenuItem>
                                <div v-else class="flex items-center gap-2 px-2 py-1.5 text-xs text-muted-foreground">
                                    <Clock class="size-3.5 shrink-0 text-amber-500" />
                                    <span class="truncate">{{ pendingRequest.event_name }} · {{ t('onboarding.pendingShort') }}</span>
                                </div>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- User without event: show event name only (no dropdown) -->
            <SidebarMenu v-else-if="activeEvent">
                <SidebarMenuItem>
                    <div class="flex items-center gap-2 px-2 py-1.5">
                        <span class="truncate text-sm text-sidebar-foreground/60">{{ activeEvent.name }}</span>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- User without event: button opens modal -->
            <SidebarGroup v-if="!activeEvent && !isAdmin" class="px-2 py-0">
                <SidebarGroupLabel>{{ t('nav.eventSection') }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton @click="requestModalOpen = true" :tooltip="t('nav.requestEvent')">
                            <CalendarPlus class="pointer-events-none" />
                            <span>{{ t('nav.requestEvent') }}</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain v-if="activeEvent" :items="eventNavItems" label="nav.eventSection" />
            <NavMain v-if="activeEvent && canAdminister" :items="appNavItems" label="nav.appSection" />
            <NavMain v-if="isAdmin" :items="platformNavItems" label="nav.platform" />
        </SidebarContent>

        <SidebarFooter>
            <div class="flex justify-center gap-1 px-2 pb-1">
                <button
                    v-for="lang in ['de', 'en']"
                    :key="lang"
                    @click="setLocale(lang as 'de' | 'en')"
                    :class="[
                        'rounded px-2 py-0.5 text-xs font-medium transition-colors',
                        locale === lang
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground/60 hover:text-sidebar-foreground',
                    ]"
                >
                    {{ lang.toUpperCase() }}
                </button>
            </div>
            <div class="flex justify-center gap-3 px-2 pb-1 text-[10px] text-sidebar-foreground/40 group-data-[collapsible=icon]:hidden">
                <a :href="route('legal.imprint')" class="hover:text-sidebar-foreground">Impressum</a>
                <a :href="route('legal.privacy')" class="hover:text-sidebar-foreground">Datenschutz</a>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <RequestEventModal />
    <OnboardingModal />
    <slot />
</template>
