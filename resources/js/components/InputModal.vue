<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';

withDefaults(defineProps<{
    title: string;
    description?: string;
    maxWidth?: string;
}>(), { maxWidth: 'max-w-md' });

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent :class="maxWidth">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <slot />
        </DialogContent>
    </Dialog>
</template>
