<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LocationPicker from '@/components/EventSettings/LocationPicker.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, MapPin, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const page = usePage();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('schedule.title'), href: '/schedule' }];

interface ScheduleItem {
    id: number;
    title: string;
    starts_at: string | null;
    sort_order: number;
    location_name: string | null;
    location_street: string | null;
    location_house_number: string | null;
    location_postal_code: string | null;
    location_city: string | null;
    location_state: string | null;
    location_country: string | null;
    location_lat: number | null;
    location_lng: number | null;
}

const items = computed(() => page.props.items as ScheduleItem[]);

// ─── Station editor (create + edit share one form) ──────────────────────────

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    starts_at: '',
    location_name: '',
    location_street: '',
    location_house_number: '',
    location_postal_code: '',
    location_city: '',
    location_state: '',
    location_country: 'Deutschland',
    location_lat: null as number | null,
    location_lng: null as number | null,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(item: ScheduleItem) {
    editingId.value = item.id;
    form.title = item.title;
    form.starts_at = item.starts_at ?? '';
    form.location_name = item.location_name ?? '';
    form.location_street = item.location_street ?? '';
    form.location_house_number = item.location_house_number ?? '';
    form.location_postal_code = item.location_postal_code ?? '';
    form.location_city = item.location_city ?? '';
    form.location_state = item.location_state ?? '';
    form.location_country = item.location_country ?? 'Deutschland';
    form.location_lat = item.location_lat;
    form.location_lng = item.location_lng;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    // Empty inputs should persist as NULL, not "" — keeps the address clean.
    form.transform((data) => {
        const out: Record<string, unknown> = { ...data };
        for (const key of Object.keys(out)) {
            if (out[key] === '') out[key] = null;
        }
        return out;
    });

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('schedule.saved'));
            dialogOpen.value = false;
            editingId.value = null;
            form.reset();
        },
    };

    if (editingId.value) {
        form.patch(route('schedule.update', editingId.value), opts);
    } else {
        form.post(route('schedule.store'), opts);
    }
}

// ─── Reorder ────────────────────────────────────────────────────────────────

function move(index: number, dir: 'up' | 'down') {
    const ids = items.value.map((i) => i.id);
    const j = dir === 'up' ? index - 1 : index + 1;
    if (j < 0 || j >= ids.length) return;
    [ids[index], ids[j]] = [ids[j], ids[index]];
    router.patch(route('schedule.reorder'), { ids }, { preserveScroll: true });
}

// ─── Delete ─────────────────────────────────────────────────────────────────

const pendingDelete = ref<ScheduleItem | null>(null);

function askDelete(item: ScheduleItem) {
    pendingDelete.value = item;
}

function doDelete() {
    if (!pendingDelete.value) return;
    router.delete(route('schedule.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('schedule.deleted')),
    });
    pendingDelete.value = null;
}

// ─── Display helpers ────────────────────────────────────────────────────────

function locationSummary(item: ScheduleItem): string {
    const parts: string[] = [];
    const street = [item.location_street, item.location_house_number].filter(Boolean).join(' ');
    if (street) parts.push(street);
    const city = [item.location_postal_code, item.location_city].filter(Boolean).join(' ');
    if (city) parts.push(city);
    return parts.join(', ');
}
</script>

<template>
    <Head :title="t('schedule.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-4 p-4">
            <!-- Intro -->
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ t('schedule.title') }}</h1>
                    <InfoTooltip :text="t('schedule.infoHint')" />
                </div>
                <p class="text-sm text-muted-foreground">{{ t('schedule.intro') }}</p>
            </div>

            <!-- Station list -->
            <div v-if="items.length" class="space-y-2">
                <Card v-for="(item, index) in items" :key="item.id">
                    <CardContent class="flex items-center gap-3 p-3">
                        <!-- Reorder -->
                        <div class="flex flex-col">
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                :disabled="index === 0"
                                :title="t('schedule.moveUp')"
                                @click="move(index, 'up')"
                            >
                                <ChevronUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                :disabled="index === items.length - 1"
                                :title="t('schedule.moveDown')"
                                @click="move(index, 'down')"
                            >
                                <ChevronDown class="size-4" />
                            </button>
                        </div>

                        <!-- Time -->
                        <div class="w-14 shrink-0 text-center">
                            <span v-if="item.starts_at" class="font-mono text-sm font-semibold">{{ item.starts_at }}</span>
                            <span v-else class="text-xs text-muted-foreground">{{ t('schedule.noTime') }}</span>
                        </div>

                        <!-- Title + location -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ item.title }}</p>
                            <p v-if="locationSummary(item)" class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                <MapPin class="size-3 shrink-0" />
                                {{ locationSummary(item) }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground/70">{{ t('schedule.noLocation') }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex shrink-0 items-center gap-1">
                            <Button variant="ghost" size="icon" :title="t('common.edit')" @click="openEdit(item)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-muted-foreground hover:text-destructive"
                                :title="t('common.delete')"
                                @click="askDelete(item)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                {{ t('schedule.empty') }}
            </div>

            <!-- Add -->
            <Button variant="outline" class="w-full" @click="openCreate">
                <Plus class="mr-2 size-4" />
                {{ t('schedule.addStation') }}
            </Button>
        </div>

        <!-- Station editor dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? t('schedule.editStation') : t('schedule.newStation') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>{{ t('schedule.stationTitle') }}</Label>
                        <Input v-model="form.title" :placeholder="t('schedule.stationTitlePlaceholder')" required />
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label
                            >{{ t('schedule.time') }}
                            <span class="text-xs font-normal text-muted-foreground">({{ t('schedule.timeOptional') }})</span></Label
                        >
                        <Input v-model="form.starts_at" type="time" class="w-40" />
                        <p v-if="form.errors.starts_at" class="text-xs text-destructive">{{ form.errors.starts_at }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('schedule.location') }}</Label>
                        <LocationPicker
                            v-model:street="form.location_street"
                            v-model:house-number="form.location_house_number"
                            v-model:postal-code="form.location_postal_code"
                            v-model:city="form.location_city"
                            v-model:state="form.location_state"
                            v-model:country="form.location_country"
                            v-model:lat="form.location_lat"
                            v-model:lng="form.location_lng"
                            :show-name="false"
                            :show-display-mode="false"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">{{ t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- Delete confirmation -->
        <ConfirmDialog
            :open="pendingDelete !== null"
            :title="t('schedule.deleteTitle')"
            :description="pendingDelete ? t('schedule.deleteConfirm', { title: pendingDelete.title }) : ''"
            destructive
            @update:open="(v) => !v && (pendingDelete = null)"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
