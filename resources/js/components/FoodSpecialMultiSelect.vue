<script setup lang="ts">
import { Listbox, ListboxButton, ListboxLabel, ListboxOption, ListboxOptions } from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number[];
    options: { id: number; name: string }[];
    label?: string;
    placeholder?: string;
}>();

const emit = defineEmits(['update:modelValue']);

// Local copy of the value
const internalValue = ref<number[]>([...props.modelValue]);

// Sync internalValue → parent (only on user change)
watch(internalValue, (val) => {
    emit('update:modelValue', val);
});
</script>

<template>
    <Listbox v-model="internalValue" multiple>
        <ListboxLabel class="mb-2 block font-medium text-gray-900 dark:text-gray-100">
            {{ label }}
        </ListboxLabel>
        <div class="relative mt-2">
            <!-- Button -->
            <ListboxButton
                class="relative w-full cursor-default rounded-md bg-white py-2 pr-10 pl-3 text-left text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 focus:outline-none sm:text-sm dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-700"
            >
                <span class="block truncate">
                    {{
                        internalValue.length > 0
                            ? options
                                  .filter((opt) => internalValue.includes(opt.id))
                                  .map((opt) => opt.name)
                                  .join(', ')
                            : placeholder
                    }}
                </span>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <ChevronUpDownIcon class="h-5 w-5 text-gray-400 dark:text-gray-300" aria-hidden="true" />
                </span>
            </ListboxButton>

            <!-- Options -->
            <transition leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <ListboxOptions
                    class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm dark:bg-gray-900 dark:ring-white/10"
                >
                    <ListboxOption v-for="option in options" :key="option.id" :value="option.id" v-slot="{ active, selected }">
                        <li
                            :class="[
                                active ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-100',
                                'relative cursor-default py-2 pr-9 pl-3 select-none',
                            ]"
                        >
                            <span :class="[selected ? 'font-semibold' : 'font-normal', 'block truncate']">
                                {{ option.name }}
                            </span>
                            <span v-if="selected" class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 dark:text-indigo-400">
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                    </ListboxOption>
                </ListboxOptions>
            </transition>
        </div>
    </Listbox>
</template>
