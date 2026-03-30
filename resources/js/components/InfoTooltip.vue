<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { TooltipProvider } from 'reka-ui';

defineProps<{ text: string }>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-muted-foreground/40 text-muted-foreground/70 text-[10px] font-semibold leading-none shrink-0 hover:border-muted-foreground hover:text-muted-foreground transition-colors"
                    @click.stop
                >
                    ?
                </button>
            </TooltipTrigger>
            <TooltipContent :side="'top'" class="max-w-xs text-xs leading-relaxed">
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
