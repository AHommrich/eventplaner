<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LocationPicker from '@/components/EventSettings/LocationPicker.vue';
import VenueEditor from '@/components/EventSettings/VenueEditor.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Home, MapPin, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const page = usePage();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('schedule.title'), href: '/schedule' }];

interface ScheduleItem {
    id: number;
    title: string;
    starts_at: string | null;
    ends_at: string | null;
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

interface GroupVisibility {
    id: number;
    name: string;
    hidden_station_ids: number[];
}

const items = computed(() => page.props.items as ScheduleItem[]);
const groups = computed(() => page.props.groups as GroupVisibility[]);

// ─── Main location (venue) ────────────────────────────────────────────────────
// The venue is the event's primary station: it is the fallback shown in the
// guest app when no stations exist. Edited here so all location data lives on
// one page. Has its own save button (independent of the station editor dialog).

interface VenueData {
    venue_name: string | null;
    venue_lat: number | null;
    venue_lng: number | null;
    venue_street: string | null;
    venue_house_number: string | null;
    venue_postal_code: string | null;
    venue_city: string | null;
    venue_state: string | null;
    venue_country: string | null;
    venue_display_mode: string | null;
}

const venue = computed(() => page.props.venue as VenueData | null);
const venueEditor = ref<InstanceType<typeof VenueEditor> | null>(null);

const venueForm = useForm({
    venue_name: venue.value?.venue_name ?? '',
    venue_lat: venue.value?.venue_lat ?? (null as number | null),
    venue_lng: venue.value?.venue_lng ?? (null as number | null),
    venue_street: venue.value?.venue_street ?? '',
    venue_house_number: venue.value?.venue_house_number ?? '',
    venue_postal_code: venue.value?.venue_postal_code ?? '',
    venue_city: venue.value?.venue_city ?? '',
    venue_state: venue.value?.venue_state ?? '',
    venue_country: venue.value?.venue_country ?? 'Deutschland',
    venue_display_mode: venue.value?.venue_display_mode ?? 'both',
});

// Accordion state — the venue editor is large, so it starts collapsed.
const venueExpanded = ref(false);

// One-line summary shown in the collapsed header (name, else address).
const venueSummary = computed(() => {
    if (venueForm.venue_name) return venueForm.venue_name;
    const parts = [
        [venueForm.venue_street, venueForm.venue_house_number].filter(Boolean).join(' '),
        [venueForm.venue_postal_code, venueForm.venue_city].filter(Boolean).join(' '),
    ].filter(Boolean);
    return parts.join(', ');
});

// True once the venue has any address/coordinate data worth reusing.
const hasVenue = computed(
    () =>
        !!(
            venueForm.venue_name ||
            venueForm.venue_street ||
            venueForm.venue_city ||
            venueForm.venue_postal_code ||
            (venueForm.venue_lat && venueForm.venue_lng)
        ),
);

function saveVenue() {
    venueForm
        .transform((data) => {
            const out: Record<string, unknown> = { ...data };
            for (const key of Object.keys(out)) {
                if (out[key] === '') out[key] = null;
            }
            return out;
        })
        .patch(route('schedule.venue'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('schedule.saved'));
                // Snapshot the saved values as the new clean baseline.
                venueForm.defaults();
                venueForm.isDirty = false;
                venueEditor.value?.resetDirtyBaseline();
            },
        });
}

// ─── Station editor (create + edit share one form) ──────────────────────────

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const stationLocationPicker = ref<InstanceType<typeof LocationPicker> | null>(null);

// Copy the main venue into the station location (address + coords + map).
function useVenueForStation() {
    stationLocationPicker.value?.applyLocation({
        street: venueForm.venue_street,
        houseNumber: venueForm.venue_house_number,
        postalCode: venueForm.venue_postal_code,
        city: venueForm.venue_city,
        state: venueForm.venue_state,
        country: venueForm.venue_country,
        lat: venueForm.venue_lat,
        lng: venueForm.venue_lng,
    });
}

const form = useForm({
    title: '',
    starts_at: '',
    ends_at: '',
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
    form.ends_at = item.ends_at ?? '';
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

function timeRange(item: ScheduleItem): string {
    if (!item.starts_at) return '';
    return item.ends_at ? `${item.starts_at}–${item.ends_at}` : item.starts_at;
}

function locationSummary(item: ScheduleItem): string {
    const parts: string[] = [];
    const street = [item.location_street, item.location_house_number].filter(Boolean).join(' ');
    if (street) parts.push(street);
    const city = [item.location_postal_code, item.location_city].filter(Boolean).join(' ');
    if (city) parts.push(city);
    return parts.join(', ');
}

// ─── Per-group visibility ─────────────────────────────────────────────────────

// Each group is an accordion panel, collapsed by default.
const expandedGroups = reactive(new Set<number>());

function toggleGroupPanel(id: number) {
    if (expandedGroups.has(id)) expandedGroups.delete(id);
    else expandedGroups.add(id);
}

// A group sees every station except the ones in its hidden set. Checked = the
// group is invited to that station; unchecking hides it (default: all visible).
function toggleStationVisibility(group: GroupVisibility, stationId: number, visible: boolean) {
    const hidden = new Set(group.hidden_station_ids);
    if (visible) {
        hidden.delete(stationId);
    } else {
        hidden.add(stationId);
    }
    router.patch(
        route('groups.schedule-visibility', group.id),
        { hidden_station_ids: [...hidden] },
        { preserveScroll: true, onSuccess: () => toast.success(t('schedule.saved')) },
    );
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

            <!-- Main location (venue) — collapsed by default; the full editor is
                 large, so keep it tucked away until the organizer opens it. -->
            <Card>
                <CardContent class="p-4">
                    <button type="button" class="flex w-full items-center gap-3 text-left" @click="venueExpanded = !venueExpanded">
                        <Home class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ t('schedule.venueTitle') }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ venueSummary || t('schedule.venueNotSet') }}
                            </p>
                        </div>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                            :class="{ 'rotate-180': venueExpanded }"
                        />
                    </button>

                    <div v-if="venueExpanded" class="mt-4 space-y-3 border-t pt-4">
                        <VenueEditor
                            ref="venueEditor"
                            v-model:venue-name="venueForm.venue_name"
                            v-model:venue-lat="venueForm.venue_lat"
                            v-model:venue-lng="venueForm.venue_lng"
                            v-model:venue-street="venueForm.venue_street"
                            v-model:venue-house-number="venueForm.venue_house_number"
                            v-model:venue-postal-code="venueForm.venue_postal_code"
                            v-model:venue-city="venueForm.venue_city"
                            v-model:venue-state="venueForm.venue_state"
                            v-model:venue-country="venueForm.venue_country"
                            v-model:venue-display-mode="venueForm.venue_display_mode"
                        />
                        <div v-if="venueForm.isDirty" class="flex justify-end">
                            <Button size="sm" :disabled="venueForm.processing" @click="saveVenue">
                                {{ venueForm.processing ? '…' : t('common.save') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

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
                        <div class="w-24 shrink-0 text-center">
                            <span v-if="item.starts_at" class="font-mono text-sm font-semibold whitespace-nowrap">{{ timeRange(item) }}</span>
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

            <!-- Per-group visibility -->
            <Card class="mt-6">
                <CardContent class="space-y-3 p-4">
                    <div class="flex items-center gap-2">
                        <Users class="size-4 text-muted-foreground" />
                        <h2 class="font-semibold">{{ t('schedule.visibilityTitle') }}</h2>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ t('schedule.visibilityIntro') }}</p>

                    <p v-if="!groups.length" class="rounded-md bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        {{ t('schedule.visibilityNoGroups') }}
                    </p>
                    <p v-else-if="!items.length" class="rounded-md bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        {{ t('schedule.visibilityNeedsStations') }}
                    </p>
                    <div v-else class="divide-y">
                        <div v-for="group in groups" :key="group.id">
                            <!-- Group header (clickable) -->
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 py-2.5 text-left transition-colors hover:text-foreground"
                                @click="toggleGroupPanel(group.id)"
                            >
                                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ group.name }}</span>
                                <span v-if="group.hidden_station_ids.length" class="shrink-0 text-xs text-muted-foreground">
                                    {{ t('schedule.visibilityHiddenCount', { count: group.hidden_station_ids.length }) }}
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="{ 'rotate-180': expandedGroups.has(group.id) }"
                                />
                            </button>

                            <!-- Station checkboxes (expanded) -->
                            <div v-if="expandedGroups.has(group.id)" class="space-y-1.5 pb-3">
                                <label v-for="item in items" :key="item.id" class="flex cursor-pointer items-center gap-2.5 text-sm">
                                    <input
                                        type="checkbox"
                                        class="size-4 shrink-0 accent-primary"
                                        :checked="!group.hidden_station_ids.includes(item.id)"
                                        @change="toggleStationVisibility(group, item.id, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span class="w-12 shrink-0 font-mono text-xs text-muted-foreground">{{ item.starts_at ?? '—' }}</span>
                                    <span class="min-w-0 truncate">{{ item.title }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
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
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label>{{ t('schedule.startTime') }}</Label>
                            <Input v-model="form.starts_at" type="time" />
                            <p v-if="form.errors.starts_at" class="text-xs text-destructive">{{ form.errors.starts_at }}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label
                                >{{ t('schedule.endTime') }}
                                <span class="text-xs font-normal text-muted-foreground">({{ t('schedule.timeOptional') }})</span></Label
                            >
                            <Input v-model="form.ends_at" type="time" />
                            <p v-if="form.errors.ends_at" class="text-xs text-destructive">{{ form.errors.ends_at }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label>{{ t('schedule.location') }}</Label>
                            <button
                                v-if="hasVenue"
                                type="button"
                                class="flex items-center gap-1 rounded-md border border-dashed border-primary/50 px-2 py-1 text-xs text-primary transition-colors hover:bg-primary/5"
                                @click="useVenueForStation"
                            >
                                <Home class="size-3" />
                                {{ t('schedule.useVenue') }}
                            </button>
                        </div>
                        <LocationPicker
                            ref="stationLocationPicker"
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
                            :teleport="false"
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
