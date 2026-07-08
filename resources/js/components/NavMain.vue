<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    items: NavItem[];
}>();

const { t } = useI18n();
const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ t('nav.platform') }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="item.href === page.url" :tooltip="item.title">
                    <Link :href="item.href" class="flex items-center gap-2">
                        <component :is="item.icon" class="pointer-events-none" />
                        <span class="flex-1">{{ item.title }}</span>
                        <span
                            v-if="item.badge && item.badge > 0"
                            class="inline-flex size-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white group-data-[collapsible=icon]:hidden"
                        >
                            {{ item.badge > 99 ? '99+' : item.badge }}
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
