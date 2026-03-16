<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter, DialogClose,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    destructive?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ title ?? 'Bist du sicher?' }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Abbrechen</Button>
                </DialogClose>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    @click="emit('confirm'); emit('update:open', false)"
                >
                    {{ confirmLabel ?? 'Bestätigen' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
