<script setup lang="ts">
import AddGuestModal from '@/components/AddGuestModal.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import GuestTable from '@/components/GuestTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAddGuestModal } from '@/composables/useAddGuestModal';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Trash2, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.guests'), href: '/guests' }];
const page = usePage();
const guests = computed(() => page.props.guests as any[]);
const unusedGroups = computed(() => (page.props as any).unused_groups as { id: number; name: string }[]);
const { open: addGuestOpen } = useAddGuestModal();

const confirmOpen = ref(false);
const pendingGroupId = ref<number | null>(null);

function askDeleteGroup(id: number) {
    pendingGroupId.value = id;
    confirmOpen.value = true;
}
function doDeleteGroup() {
    if (!pendingGroupId.value) return;
    router.delete(route('groups.destroy', pendingGroupId.value), {
        onSuccess: () => toast.success(t('toast.groupDeleted')),
    });
}
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

            <Card v-if="unusedGroups.length > 0">
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">{{ t('group.unused') }}</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-for="group in unusedGroups" :key="group.id" class="flex items-center justify-between border-b px-6 py-2 last:border-0">
                        <span class="text-sm">{{ group.name }}</span>
                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDeleteGroup(group.id)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>

    <AddGuestModal />

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="t('group.deleteTitle')"
        :description="t('group.deleteDescription')"
        :confirm-label="t('common.delete')"
        destructive
        @confirm="doDeleteGroup"
    />
</template>
