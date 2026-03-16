<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import FoodSpecialMultiSelect from './FoodSpecialMultiSelect.vue';
import { useI18n } from 'vue-i18n';

type GuestFormData = {
    firstname: string;
    lastname: string;
    category_id: string | number;
    group_id: string | number;
    likelihood: 'sure' | 'likely' | 'maybe' | 'unlikely' | 'no';
    invite: boolean;
    food_specials: number[];
};

const props = defineProps<{
    categories: { id: number; title: string }[];
    groups: { id: number; name: string }[];
    foodSpecials: { id: number; name: string }[];
    initialForm?: Partial<GuestFormData>;
    submitLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'submit', form: InertiaForm<GuestFormData>): void;
}>();

const form = useForm<GuestFormData>({
    firstname:     props.initialForm?.firstname     ?? '',
    lastname:      props.initialForm?.lastname      ?? '',
    category_id:   props.initialForm?.category_id   ?? '',
    group_id:      props.initialForm?.group_id      ?? '',
    likelihood:    props.initialForm?.likelihood    ?? 'maybe',
    invite:        props.initialForm?.invite        ?? false,
    food_specials: props.initialForm?.food_specials ?? [],
});

const { t } = useI18n();

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

        <div class="grid gap-1.5">
            <Label>{{ t('guest.category') }}</Label>
            <select v-model="form.category_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                <option disabled value="">{{ t('guest.selectCategory') }}</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <p v-if="form.errors.category_id" class="text-xs text-destructive">{{ form.errors.category_id }}</p>
        </div>

        <div class="grid gap-1.5">
            <Label>{{ t('guest.group') }}</Label>
            <select v-model="form.group_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                <option value="">{{ t('guest.noGroup') }}</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <p v-if="form.errors.group_id" class="text-xs text-destructive">{{ form.errors.group_id }}</p>
        </div>

        <div class="grid gap-1.5">
            <Label>{{ t('guest.likelihood') }}</Label>
            <select v-model="form.likelihood" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                <option value="sure">{{ t('guest.sure') }}</option>
                <option value="likely">{{ t('guest.likely') }}</option>
                <option value="maybe">{{ t('guest.maybe') }}</option>
                <option value="unlikely">{{ t('guest.unlikely') }}</option>
                <option value="no">{{ t('guest.no') }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2 pt-5">
            <input id="invite" type="checkbox" v-model="form.invite" class="h-4 w-4 rounded border-input accent-primary" />
            <Label for="invite">{{ t('guest.inviteNeeded') }}</Label>
        </div>

        <div class="sm:col-span-2">
            <FoodSpecialMultiSelect v-model="form.food_specials" :options="foodSpecials" :label="t('guest.foodSpecials')" :placeholder="t('guest.pleaseSelect')" />
        </div>

        <div class="sm:col-span-2 flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel ?? t('common.save') }}
            </Button>
        </div>
    </form>
</template>
