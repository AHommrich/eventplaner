<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { TooltipProvider } from 'reka-ui';
import { ref } from 'vue';

defineProps<{ text: string }>();

const isOpen = ref(false);
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip :open="isOpen" @update:open="isOpen = $event">
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-muted-foreground/40 text-muted-foreground/70 text-[10px] font-semibold leading-none shrink-0 hover:border-muted-foreground hover:text-muted-foreground transition-colors"
                    @click.stop="isOpen = !isOpen"
                >
                    ?
                </button>
            </TooltipTrigger>
            <TooltipContent side="top" class="max-w-xs text-xs leading-relaxed">
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
