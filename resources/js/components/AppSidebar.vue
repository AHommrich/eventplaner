<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Users, SquarePen, QrCode, Images, ShieldCheck, GlassWater } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage();
const isAdmin = computed(() => (page.props.auth as any)?.user?.role === 'admin');
const activeEvent = computed(() => (page.props as any).active_event as { id: number; name: string } | null);
const accessibleEvents = computed(() => (page.props as any).accessible_events as { id: number; name: string }[]);
const showSwitcher = computed(() => accessibleEvents.value?.length > 1);

function switchEvent(eventId: number) {
    router.post('/events/switch', { event_id: eventId });
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

            <div v-if="activeEvent" class="px-2 pb-1 space-y-1">
                <template v-if="showSwitcher">
                    <button
                        v-for="ev in accessibleEvents"
                        :key="ev.id"
                        @click="switchEvent(ev.id)"
                        class="w-full truncate rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                        :class="ev.id === activeEvent.id
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                            : 'text-sidebar-foreground/60 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'"
                    >
                        {{ ev.name }}
                    </button>
                </template>
                <p v-else class="truncate px-1 text-xs text-sidebar-foreground/60">
                    {{ activeEvent.name }}
                </p>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain v-if="isAdmin" :items="adminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
