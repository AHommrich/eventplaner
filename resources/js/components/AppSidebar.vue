<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Users, SquarePen, QrCode, Images, ShieldCheck, GlassWater, ChevronsUpDown, Check, KeyRound } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref } from 'vue';

const page = usePage();
const { isMobile, state } = useSidebar();
const isAdmin = computed(() => (page.props.auth as any)?.user?.role === 'admin');
const activeEvent = computed(() => (page.props as any).active_event as { id: number; name: string; user_id?: number } | null);
const currentUserId = computed(() => (page.props.auth as any)?.user?.id);
const isEventOwner = computed(() =>
    !isAdmin.value && activeEvent.value?.user_id === currentUserId.value
);
const accessibleEvents = computed(() => (page.props as any).accessible_events as { id: number; name: string }[]);
const showSwitcher = computed(() => accessibleEvents.value?.length > 1);

const search = ref('');
const filteredEvents = computed(() => {
    if (!search.value) return accessibleEvents.value ?? [];
    return (accessibleEvents.value ?? []).filter(ev =>
        ev.name.toLowerCase().includes(search.value.toLowerCase())
    );
});

function switchEvent(eventId: number) {
    router.post('/events/switch', { event_id: eventId });
    search.value = '';
}

const mainNavItems: NavItem[] = [
    { title: 'Formulare',   href: '/dashboard',    icon: SquarePen },
    { title: 'Gäste',       href: '/table',         icon: Users },
    { title: 'Getränke',    href: '/drinks',        icon: GlassWater },
    { title: 'Einladungen', href: '/invitations',   icon: QrCode },
    { title: 'Fotos',       href: '/photos',        icon: Images },
];

const adminNavItems: NavItem[] = [
    { title: 'User-Verwaltung', href: '/admin/users', icon: ShieldCheck },
];

const eventOwnerNavItems: NavItem[] = [
    { title: 'Zugang verwalten', href: '/event/access', icon: KeyRound },
];
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

            <!-- Event-Switcher als Dropdown -->
            <SidebarMenu v-if="activeEvent">
                <SidebarMenuItem>
                    <DropdownMenu v-if="showSwitcher">
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <span class="truncate text-sm font-medium">{{ activeEvent.name }}</span>
                                <ChevronsUpDown class="ml-auto size-4 shrink-0 opacity-50" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            :side="isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'"
                            align="start"
                            :side-offset="4"
                        >
                            <!-- Suchfeld (ab 5 Events sinnvoll, immer anzeigen schadet nicht) -->
                            <div class="px-2 py-1.5">
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Event suchen..."
                                    class="w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm outline-none placeholder:text-muted-foreground"
                                    @keydown.stop
                                />
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="ev in filteredEvents"
                                :key="ev.id"
                                @click="switchEvent(ev.id)"
                                class="cursor-pointer"
                            >
                                <Check v-if="ev.id === activeEvent.id" class="mr-2 size-4" />
                                <span v-else class="mr-2 size-4" />
                                {{ ev.name }}
                            </DropdownMenuItem>
                            <p v-if="filteredEvents.length === 0" class="px-2 py-3 text-center text-xs text-muted-foreground">
                                Kein Event gefunden
                            </p>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Kein Switcher — nur Anzeige -->
                    <div v-else class="flex items-center gap-2 px-2 py-1.5">
                        <span class="truncate text-sm text-sidebar-foreground/60">{{ activeEvent.name }}</span>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain v-if="isEventOwner" :items="eventOwnerNavItems" />
            <NavMain v-if="isAdmin" :items="adminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
