<script setup lang="ts">
import Multiselect from '@vueform/multiselect';
import axios from 'axios';
import { computed, ref } from 'vue';
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
    (e: 'created', value: { id: number; name: string }): void;
}>();

const { t } = useI18n();

const localOptions = ref<{ value: number; label: string }[]>([]);
const searchQuery = ref('');
const isOpen = ref(false);

function onKeydown(event: Event) {
    if ((event as KeyboardEvent).key === 'Escape' && isOpen.value) {
        event.stopPropagation();
    }
}

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val ?? null),
});

const multiselectOptions = computed(() => {
    const propIds = new Set(props.options.map((o) => o.id));
    return [...props.options.map((o) => ({ value: o.id, label: o.label })), ...localOptions.value.filter((o) => !propIds.has(o.value))];
});

const hasExactMatch = computed(
    () =>
        searchQuery.value.trim() !== '' &&
        multiselectOptions.value.some((o) => o.label.split(' · ')[0].toLowerCase() === searchQuery.value.trim().toLowerCase()),
);

async function handleCreate(option: { label: string }) {
    const response = await axios.post(route(props.createRoute), { [props.createField]: option.label });
    const newOption = { value: response.data.id, label: response.data.name };
    localOptions.value.push(newOption);
    emit('update:modelValue', newOption.value);
    emit('created', { id: response.data.id, name: response.data.name });
    searchQuery.value = '';
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
        @search-change="searchQuery = $event"
        @open="isOpen = true"
        @close="isOpen = false"
        @keydown="onKeydown"
    >
        <template #singlelabel="{ value }">
            <div class="multiselect-single-label">
                {{ String((value as any).label).split(' · ')[0] }}
            </div>
        </template>
        <template #option="{ option }">
            <span v-if="(option as any).__CREATE__" class="font-medium text-primary">
                {{ hasExactMatch ? t('common.createItemAgain', { name: option.label }) : t('common.createItem', { name: option.label }) }}
            </span>
            <span v-else>{{ option.label }}</span>
        </template>
    </Multiselect>
</template>
