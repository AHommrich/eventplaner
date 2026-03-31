<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CreatableCombobox from './CreatableCombobox.vue';
import CreatableMultiCombobox from './CreatableMultiCombobox.vue';
import { computed, ref } from 'vue';
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

type GuestFormData = {
    firstname: string;
    lastname: string;
    group_id: number | null;
    food_specials: number[];
};

const props = defineProps<{
    groups: { id: number; name: string; guests?: { id: number; firstname: string }[] }[];
    foodSpecials: { id: number; name: string; translation_key: string | null }[];
    initialForm?: Partial<GuestFormData>;
    submitLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'submit', form: InertiaForm<GuestFormData>): void;
}>();

const form = useForm<GuestFormData>({
    firstname:     props.initialForm?.firstname     ?? '',
    lastname:      props.initialForm?.lastname      ?? '',
    group_id:      (props.initialForm?.group_id as number | null | undefined) ?? null,
    food_specials: props.initialForm?.food_specials ?? [],
});

const { t, te } = useI18n();

const localGroups = ref<{ id: number; name: string; guests: { id: number; firstname: string }[] }[]>([]);

const allGroups = computed(() => [...props.groups, ...localGroups.value]);

const duplicateGroupNames = computed(() => {
    const names = allGroups.value.map(g => g.name);
    return new Set(names.filter((n, i) => names.indexOf(n) !== i));
});

function onGroupCreated(created: { id: number; name: string }) {
    localGroups.value.push({
        id: created.id,
        name: created.name,
        guests: form.firstname ? [{ id: -1, firstname: form.firstname }] : [],
    });
}

function submit() { emit('submit', form); }
</script>

<template>
    <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">

        <div class="grid gap-1.5">
            <Label>{{ t('guest.firstName') }}</Label>
            <Input v-model="form.firstname" :placeholder="t('guest.firstName')" />
            <p v-if="form.errors.firstname" class="text-xs text-destructive">{{ form.errors.firstname }}</p>
        </div>

        <div class="grid gap-1.5">
            <Label>{{ t('guest.lastName') }}</Label>
            <Input v-model="form.lastname" :placeholder="t('guest.lastName')" />
            <p v-if="form.errors.lastname" class="text-xs text-destructive">{{ form.errors.lastname }}</p>
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label>{{ t('guest.group') }}</Label>
            <CreatableCombobox
                v-model="form.group_id"
                :options="allGroups.map(g => ({
                    id: g.id,
                    label: duplicateGroupNames.has(g.name) && g.guests?.length
                        ? `${g.name} · ${g.guests.map(m => m.firstname).join(', ')}`
                        : g.name
                }))"
                :placeholder="t('guest.groupSearchPlaceholder')"
                create-route="groups.store"
                create-field="name"
                @created="onGroupCreated"
            />
            <p v-if="form.errors.group_id" class="text-xs text-destructive">{{ form.errors.group_id }}</p>
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label>{{ t('guest.foodSpecials') }}</Label>
            <CreatableMultiCombobox
                v-model="form.food_specials"
                :options="foodSpecials.map(f => ({ id: f.id, label: f.translation_key && te('foodSpecial.catalog.' + f.translation_key) ? t('foodSpecial.catalog.' + f.translation_key) : f.name }))"
                :placeholder="t('guest.foodSpecialSearchPlaceholder')"
                create-route="foodspecials.store"
                create-field="name"
            />
            <p v-if="form.errors.food_specials" class="text-xs text-destructive">{{ form.errors.food_specials }}</p>
        </div>

        <div class="sm:col-span-2 flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel ?? t('common.save') }}
            </Button>
        </div>
    </form>
</template>
