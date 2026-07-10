<script setup lang="ts">
import Multiselect from '@vueform/multiselect';
import axios from 'axios';
import { computed, ref } from 'vue';
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

const localOptions = ref<{ value: number; label: string }[]>([]);
const isOpen = ref(false);

function onKeydown(event: Event) {
    if ((event as KeyboardEvent).key === 'Escape' && isOpen.value) {
        event.stopPropagation();
    }
}

const value = computed({
    get: () => props.modelValue,
    set: (val: number[]) => emit('update:modelValue', val ?? []),
});

const multiselectOptions = computed(() => [...props.options.map((o) => ({ value: o.id, label: o.label })), ...localOptions.value]);

async function handleCreate(option: { label: string }) {
    const response = await axios.post(route(props.createRoute), { [props.createField]: option.label });
    const newOption = { value: response.data.id, label: response.data.name };
    localOptions.value.push(newOption);
    emit('update:modelValue', [...props.modelValue, newOption.value]);
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
        @open="isOpen = true"
        @close="isOpen = false"
        @keydown="onKeydown"
    >
        <template #option="{ option }">
            <span v-if="(option as any).__CREATE__" class="text-primary font-medium">{{ t('common.createItem', { name: option.label }) }}</span>
            <span v-else>{{ option.label }}</span>
        </template>
    </Multiselect>
</template>
