<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ guests: any[] }>();
const emit  = defineEmits<{ (e: 'deleted', id: number): void }>();

const { t } = useI18n();

// --- Filter ---
const categoryFilter = ref('');
const filteredGuests = computed(() => {
    if (!categoryFilter.value) return props.guests;
    return props.guests.filter((g) => g.category?.title === categoryFilter.value);
});

// --- Labels / Klassen ---
const likelihoodLabel = computed<Record<string, string>>(() => ({
    sure: t('guest.sure'), likely: t('guest.likely'), maybe: t('guest.maybe'),
    unlikely: t('guest.unlikely'), no: t('guest.no'),
}));
const likelihoodClass: Record<string, string> = {
    sure:     'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    likely:   'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
    maybe:    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    unlikely: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    no:       'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const rsvpLabel = computed<Record<string, string>>(() => ({
    accepted_pending:     t('guest.rsvpAcceptedPending'),
    accepted:             t('guest.rsvpAccepted'),
    declined_pending:     t('guest.rsvpDeclinedPending'),
    declined:             t('guest.rsvpDeclined'),
    revocation_requested: t('guest.rsvpRevocationRequested'),
}));
const rsvpClass: Record<string, string> = {
    accepted_pending:     'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
    accepted:             'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    declined_pending:     'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    declined:             'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    revocation_requested: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

// --- Delete ---
const confirmOpen = ref(false);
const pendingId   = ref<number | null>(null);

function askDelete(id: number) {
    pendingId.value = id;
    confirmOpen.value = true;
}
function doDelete() {
    if (!pendingId.value) return;
    router.delete(route('guests.destroy', pendingId.value), {
        onSuccess: () => {
            toast.success(t('toast.guestDeleted'));
            emit('deleted', pendingId.value!);
        },
    });
}
</script>

<template>
    <Card>
        <CardContent class="p-0">
            <!-- Filter-Leiste -->
            <div class="flex items-center gap-3 border-b px-6 py-3">
                <span class="text-sm text-muted-foreground">{{ t('category.filter') }}</span>
                <select
                    v-model="categoryFilter"
                    class="flex h-8 rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                >
                    <option value="">{{ t('common.all') }}</option>
                    <option
                        v-for="cat in [...new Set(guests.map((g) => g.category?.title).filter(Boolean))]"
                        :key="cat"
                        :value="cat"
                    >{{ cat }}</option>
                </select>
                <span class="ml-auto text-xs text-muted-foreground">{{ t('guest.count', { count: filteredGuests.length }) }}</span>
            </div>

            <!-- Tabelle -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.firstName') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.lastName') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.category') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.group') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.likelihood') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.rsvpStatus') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.invite') }}</th>
                            <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">{{ t('guest.food') }}</th>
                            <th class="h-10 px-6"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="guest in filteredGuests"
                            :key="guest.id"
                            class="border-b transition-colors hover:bg-muted/50 cursor-pointer last:border-0"
                            @click="router.visit(route('guests.edit', guest.id))"
                        >
                            <td class="px-6 py-3 font-medium">{{ guest.firstname }}</td>
                            <td class="px-6 py-3 text-muted-foreground">{{ guest.lastname }}</td>
                            <td class="px-6 py-3 text-muted-foreground">{{ guest.category?.title ?? '–' }}</td>
                            <td class="px-6 py-3 text-muted-foreground">{{ guest.group?.name ?? '–' }}</td>
                            <td class="px-6 py-3">
                                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', likelihoodClass[guest.likelihood] ?? '']">
                                    {{ likelihoodLabel[guest.likelihood] ?? guest.likelihood }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span v-if="guest.rsvp_status" :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', rsvpClass[guest.rsvp_status] ?? '']">
                                    {{ rsvpLabel[guest.rsvp_status] ?? guest.rsvp_status }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground">–</span>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">{{ guest.invite ? t('common.yes') : t('common.no') }}</td>
                            <td class="px-6 py-3 text-muted-foreground text-xs">{{ guest.food_specials?.map((fs: any) => fs.name).join(', ') || '–' }}</td>
                            <td class="px-6 py-3 text-right">
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click.stop="askDelete(guest.id)">
                                    {{ t('common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="filteredGuests.length === 0">
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-muted-foreground">{{ t('guest.none') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="t('guest.deleteTitle')"
        :description="t('guest.deleteDescription')"
        :confirm-label="t('common.delete')"
        destructive
        @confirm="doDelete"
    />
</template>
