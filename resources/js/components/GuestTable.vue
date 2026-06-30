<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{ guests: any[] }>();
const emit = defineEmits<{ (e: 'deleted', id: number): void }>();

const { t, te } = useI18n();

function foodSpecialLabel(fs: { name: string; translation_key?: string | null }): string {
    if (fs.translation_key) {
        const key = `foodSpecial.catalog.${fs.translation_key}`;
        if (te(key)) return t(key);
    }
    return fs.name;
}

const rsvpLabel = computed<Record<string, string>>(() => ({
    accepted_pending: t('guest.rsvpAcceptedPending'),
    accepted: t('guest.rsvpAccepted'),
    declined_pending: t('guest.rsvpDeclinedPending'),
    declined: t('guest.rsvpDeclined'),
    revocation_requested: t('guest.rsvpRevocationRequested'),
}));
const rsvpClass: Record<string, string> = {
    accepted_pending: 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
    accepted: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    declined_pending: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    declined: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    revocation_requested: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

// --- Gruppierung ---
const grouped = computed(() => {
    const groups: { id: number | null; name: string; guests: any[] }[] = [];
    const map = new Map<number | null, (typeof groups)[0]>();

    for (const guest of props.guests) {
        const key = guest.group_id ?? null;
        if (!map.has(key)) {
            map.set(key, { id: key, name: guest.group?.name ?? '', guests: [] });
            groups.push(map.get(key)!);
        }
        map.get(key)!.guests.push(guest);
    }

    return groups.sort((a, b) => {
        if (a.id === null) return 1;
        if (b.id === null) return -1;
        return a.name.localeCompare(b.name);
    });
});

// --- Suche & Collapse ---
const search = ref('');

// expanded = Set von Gruppen-Keys die aufgeklappt sind (Standard: alle zu)
const expanded = reactive(new Set<string>());

function groupKey(id: number | null) {
    return id === null ? 'null' : String(id);
}

function toggleGroup(id: number | null) {
    const key = groupKey(id);
    if (expanded.has(key)) expanded.delete(key);
    else expanded.add(key);
}

const filteredGrouped = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return grouped.value;

    // Bei Suche: alle Gruppen mit Treffern aufklappen
    return grouped.value
        .map((group) => {
            const filtered = group.guests.filter((g) => `${g.firstname} ${g.lastname}`.toLowerCase().includes(q));
            if (filtered.length) {
                expanded.add(groupKey(group.id));
                return { ...group, guests: filtered };
            }
            return null;
        })
        .filter(Boolean) as typeof grouped.value;
});

// --- Delete ---
const confirmOpen = ref(false);
const pendingId = ref<number | null>(null);

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
        <CardHeader class="pb-3">
            <CardTitle>{{ t('guest.count', { count: props.guests.length }) }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 pt-0">
            <Input v-model="search" :placeholder="t('guest.searchPlaceholder')" />

            <p v-if="props.guests.length === 0" class="text-sm text-muted-foreground">
                {{ t('guest.none') }}
            </p>
            <p v-else-if="filteredGrouped.length === 0" class="text-sm text-muted-foreground">
                {{ t('common.noResults') }}
            </p>

            <div v-for="group in filteredGrouped" :key="group.id ?? 'null'" class="space-y-0">
                <!-- Gruppen-Header (klickbar) -->
                <button
                    type="button"
                    class="flex w-full items-center justify-between py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                    @click="toggleGroup(group.id)"
                >
                    <span>
                        {{ group.id !== null ? group.name : t('guest.noGroup') }}
                        <span class="ml-1 font-normal normal-case">({{ group.guests.length }})</span>
                    </span>
                    <ChevronDown class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': expanded.has(groupKey(group.id)) }" />
                </button>

                <!-- Gäste-Tabelle (ausgeklappt) -->
                <div v-if="expanded.has(groupKey(group.id))" class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr>
                                <th class="h-8 px-4 text-left align-middle text-xs font-medium text-muted-foreground">
                                    {{ t('guest.firstName') }} / {{ t('guest.lastName') }}
                                </th>
                                <th class="h-8 px-4 text-left align-middle text-xs font-medium text-muted-foreground">{{ t('guest.rsvpStatus') }}</th>
                                <th class="h-8 px-4 text-left align-middle text-xs font-medium text-muted-foreground">{{ t('guest.food') }}</th>
                                <th class="h-8 px-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="guest in group.guests"
                                :key="guest.id"
                                class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                @click="router.visit(route('guests.edit', guest.id))"
                            >
                                <td class="px-4 py-2.5 font-medium">{{ guest.firstname }} {{ guest.lastname }}</td>
                                <td class="px-4 py-2.5">
                                    <span
                                        v-if="guest.rsvp_status"
                                        :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', rsvpClass[guest.rsvp_status] ?? '']"
                                    >
                                        {{ rsvpLabel[guest.rsvp_status] ?? guest.rsvp_status }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">–</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-muted-foreground">
                                    {{ guest.food_specials?.map((fs: any) => foodSpecialLabel(fs)).join(', ') || '–' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click.stop="askDelete(guest.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
