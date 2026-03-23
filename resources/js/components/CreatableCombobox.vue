<script setup lang="ts">
import { computed } from 'vue';
import Multiselect from '@vueform/multiselect';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    options: { id: number; label: string }[];
    modelValue: number | null;
    placeholder?: string;
    createRoute: string;
    createField: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const { t } = useI18n();

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val ?? null),
});

const multiselectOptions = computed(() =>
    props.options.map(o => ({ value: o.id, label: o.label }))
);

function handleCreate(query: string) {
    router.post(route(props.createRoute), { [props.createField]: query }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const newItem = props.options.find(o => o.label === query);
            if (newItem) emit('update:modelValue', newItem.id);
        },
    });
    return false; // don't add locally — Inertia reload liefert neues options-Array
}
</script>

<template>
    <Multiselect
        v-model="value"
        :options="multiselectOptions"
        value-prop="value"
        label="label"
        track-by="label"
        :placeholder="placeholder ?? t('guest.groupSearchPlaceholder')"
        :create-option="true"
        :on-create="handleCreate"
        :searchable="true"
        :can-clear="true"
        :can-deselect="false"
        :close-on-select="true"
        no-options-text=""
        class="multiselect-custom"
    />
</template>
