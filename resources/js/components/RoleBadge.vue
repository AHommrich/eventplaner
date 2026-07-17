<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Role = 'owner' | 'event_admin' | 'event_manager' | 'superadmin' | null;

const props = defineProps<{ role: Role }>();

const { t } = useI18n();

// Superadmin is an internal tier — show it as plain Owner in user-facing badges.
const label = computed(() => {
    switch (props.role) {
        case 'owner':
        case 'superadmin':
            return t('access.roleOwner');
        case 'event_admin':
            return t('access.roleAdmin');
        case 'event_manager':
            return t('access.roleManager');
        default:
            return '';
    }
});

const tone = computed(() => {
    switch (props.role) {
        case 'owner':
        case 'superadmin':
            return 'bg-primary/10 text-primary';
        case 'event_admin':
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
        default:
            return 'bg-muted text-muted-foreground';
    }
});
</script>

<template>
    <span v-if="label" :class="['rounded-full px-2 py-0.5 text-xs font-medium', tone]">{{ label }}</span>
</template>
