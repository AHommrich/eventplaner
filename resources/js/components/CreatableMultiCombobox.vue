<script setup lang="ts">
import { computed } from 'vue';
import Multiselect from '@vueform/multiselect';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    options: { id: number; label: string }[];
    modelValue: number[];
    placeholder?: string;
    createRoute: string;
    createField: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number[]): void;
}>();

const { t } = useI18n();

const value = computed({
    get: () => props.modelValue,
    set: (val: number[]) => emit('update:modelValue', val ?? []),
});

const multiselectOptions = computed(() =>
    props.options.map(o => ({ value: o.id, label: o.label }))
);

function handleCreate(query: string) {
    router.post(route(props.createRoute), { [props.createField]: query }, {
        preserveScroll: true,
        onSuccess: () => {
            const newItem = props.options.find(o => o.label === query);
            if (newItem) emit('update:modelValue', [...props.modelValue, newItem.id]);
        },
    });
    return false;
}
</script>

<template>
    <Multiselect
        v-model="value"
        :options="multiselectOptions"
        value-prop="value"
        label="label"
        track-by="label"
        mode="tags"
        :placeholder="placeholder ?? t('guest.foodSpecialSearchPlaceholder')"
        :create-option="true"
        :on-create="handleCreate"
        :searchable="true"
        :close-on-select="false"
        no-options-text=""
        no-results-text=""
        class="multiselect-custom"
    />
</template>
