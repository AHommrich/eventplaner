<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';
import type { BreadcrumbItemType } from '@/types';
import { useFloatingBar } from '@/composables/useFloatingBar';

const { active: floatingBarActive } = useFloatingBar();

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
    <Toaster position="bottom-right" richColors :duration="2000" :offset="floatingBarActive ? '88px' : '32px'" expand />
</template>
