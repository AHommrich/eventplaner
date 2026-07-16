<script setup lang="ts">
/**
 * One colour-role control: a labelled row of palette swatches (primary /
 * secondary / tertiary) picking which palette colour a UI role resolves to.
 *
 * Extracted from ColorSystemEditor so every role renders identically and a new
 * role is a single line there instead of ~24 copy-pasted markup lines. Clicking
 * its `?` asks the parent to highlight the affected element in the phone
 * preview — the "what does this change?" cue.
 */
import type { PaletteKey } from '@/lib/colorResolver';

defineProps<{
    label: string;
    /** Preview-highlight key forwarded to the parent (e.g. 'screenBg'). */
    hintKey: string;
    /** Palette key used when the role is unset, so the active state is correct. */
    fallback: PaletteKey;
    options: { key: string; label: string; value: string }[];
}>();

const model = defineModel<string>();
const emit = defineEmits<{ hint: [key: string] }>();
</script>

<template>
    <div class="grid gap-1.5">
        <div class="flex items-center justify-between">
            <span class="text-xs text-muted-foreground">{{ label }}</span>
            <button
                type="button"
                @click="emit('hint', hintKey)"
                class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
            >
                ?
            </button>
        </div>
        <div class="flex gap-2">
            <button
                v-for="opt in options"
                :key="opt.key"
                type="button"
                @click="model = opt.key"
                class="flex flex-1 flex-col items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs transition-colors"
                :class="(model ?? fallback) === opt.key ? 'border-ring bg-muted/30' : 'border-input hover:border-muted-foreground'"
            >
                <div class="h-7 w-7 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                <span>{{ opt.label }}</span>
            </button>
        </div>
    </div>
</template>
