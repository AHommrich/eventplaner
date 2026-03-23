<script setup lang="ts">
import { computed, ref } from 'vue';
import Multiselect from '@vueform/multiselect';
import axios from 'axios';
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

const localOptions = ref<{ value: number; label: string }[]>([]);

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val ?? null),
});

const multiselectOptions = computed(() => [
    ...props.options.map(o => ({ value: o.id, label: o.label })),
    ...localOptions.value,
]);

async function handleCreate(query: string) {
    const response = await axios.post(route(props.createRoute), { [props.createField]: query });
    const newOption = { value: response.data.id, label: response.data.name };
    localOptions.value.push(newOption);
    emit('update:modelValue', newOption.value);
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
        :placeholder="placeholder ?? t('guest.groupSearchPlaceholder')"
        :create-option="true"
        :on-create="handleCreate"
        :searchable="true"
        :can-clear="true"
        :can-deselect="false"
        :close-on-select="true"
        no-options-text=""
        class="multiselect-custom"
    >
        <template #option="{ option }">
            <span v-if="(option as any).__CREATE__" class="text-primary font-medium">{{ t('common.createItem', { name: option.label }) }}</span>
            <span v-else>{{ option.label }}</span>
        </template>
    </Multiselect>
</template>
