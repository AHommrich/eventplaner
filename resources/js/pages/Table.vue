<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GuestTable from '@/components/GuestTable.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.guests'), href: '/table' }];
const page   = usePage();
const guests = computed(() => page.props.guests as any[]);
</script>

<template>
    <Head :title="t('nav.guests')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <GuestTable :guests="guests" />
        </div>
    </AppLayout>
</template>
