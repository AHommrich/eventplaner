<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GuestTable from '@/components/GuestTable.vue';
import AddGuestModal from '@/components/AddGuestModal.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAddGuestModal } from '@/composables/useAddGuestModal';
import { UserPlus } from 'lucide-vue-next';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.guests'), href: '/guests' }];
const page   = usePage();
const guests = computed(() => page.props.guests as any[]);
const { open: addGuestOpen } = useAddGuestModal();
</script>

<template>
    <Head :title="t('nav.guests')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">
            <div class="flex justify-end">
                <Button @click="addGuestOpen = true">
                    <UserPlus class="mr-2 h-4 w-4" />
                    {{ t('guest.addGuest') }}
                </Button>
            </div>
            <GuestTable :guests="guests" />
        </div>
    </AppLayout>

    <AddGuestModal />
</template>
