<script setup lang="ts">
import RoleSelector from '@/components/EventSettings/RoleSelector.vue';
import { Button } from '@/components/ui/button';
import type { PaletteKey } from '@/lib/colorResolver';
import type { DesignRoleKey, DesignRoleRecommendation } from '@/lib/designRecommendations';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type RoleKey = DesignRoleKey;

type GroupKey = 'screen' | 'cards' | 'buttons' | 'navigation' | 'fab';

const props = defineProps<{
    roles: Record<RoleKey, PaletteKey>;
    recommendations: DesignRoleRecommendation;
    options: { key: string; label: string; value: string }[];
}>();

const emit = defineEmits<{
    'update-role': [key: RoleKey, value: string];
    'before-group-reset': [];
    hint: [key: string];
}>();

const { t } = useI18n();

const groups: Array<{ key: GroupKey; roles: RoleKey[] }> = [
    { key: 'screen', roles: ['screenBg'] },
    { key: 'cards', roles: ['cardBg', 'cardText', 'border'] },
    { key: 'buttons', roles: ['cardButton', 'cardButtonText'] },
    { key: 'navigation', roles: ['navBg', 'tabTint'] },
    { key: 'fab', roles: ['fab', 'fabIcon'] },
];

const roleMeta: Record<RoleKey, { label: string; fallback: PaletteKey }> = {
    screenBg: { label: 'event.roleScreenBg', fallback: 'secondary' },
    cardBg: { label: 'event.roleCardBg', fallback: 'tertiary' },
    cardText: { label: 'event.roleCardText', fallback: 'primary' },
    cardButton: { label: 'event.roleCardButton', fallback: 'primary' },
    cardButtonText: { label: 'event.roleCardButtonText', fallback: 'tertiary' },
    navBg: { label: 'event.roleNavBg', fallback: 'secondary' },
    tabTint: { label: 'event.roleTabTint', fallback: 'primary' },
    border: { label: 'event.roleBorder', fallback: 'primary' },
    fab: { label: 'event.roleFab', fallback: 'primary' },
    fabIcon: { label: 'event.roleFabIcon', fallback: 'tertiary' },
};

const isRecommended = computed(
    () =>
        Object.fromEntries(
            groups.map((group) => [group.key, group.roles.every((role) => props.roles[role] === props.recommendations[role])]),
        ) as Record<GroupKey, boolean>,
);
const hasAdjustedGroup = computed(() => groups.some((group) => !isRecommended.value[group.key]));

function resetGroup(group: (typeof groups)[number]) {
    emit('before-group-reset');
    group.roles.forEach((role) => emit('update-role', role, props.recommendations[role]));
}

function resetAllGroups() {
    emit('before-group-reset');
    groups.flatMap((group) => group.roles).forEach((role) => emit('update-role', role, props.recommendations[role]));
}

function colour(key: PaletteKey) {
    return props.options.find((option) => option.key === key)?.value ?? '#000000';
}
</script>

<template>
    <div class="space-y-3">
        <div v-if="hasAdjustedGroup" class="flex justify-end">
            <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="resetAllGroups">
                {{ t('event.roleGroup.resetAll') }}
            </Button>
        </div>
        <section v-for="group in groups" :key="group.key" class="rounded-lg border border-input p-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-medium">{{ t(`event.roleGroup.${group.key}.title`) }}</h3>
                        <span
                            class="text-xs"
                            :class="isRecommended[group.key] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                        >
                            {{ t(isRecommended[group.key] ? 'event.roleGroup.recommended' : 'event.roleGroup.adjusted') }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ t(`event.roleGroup.${group.key}.description`) }}</p>
                </div>
                <Button
                    v-if="!isRecommended[group.key]"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 shrink-0 px-2 text-xs"
                    @click="resetGroup(group)"
                >
                    {{ t('event.roleGroup.reset') }}
                </Button>
            </div>

            <div class="mt-3 flex items-center gap-1.5" aria-hidden="true">
                <template v-if="group.key === 'cards'">
                    <span class="h-7 w-12 rounded border" :style="{ backgroundColor: colour(roles.cardBg), borderColor: colour(roles.border) }" />
                    <span class="h-2 w-7 rounded" :style="{ backgroundColor: colour(roles.cardText) }" />
                </template>
                <template v-else-if="group.key === 'buttons'">
                    <span
                        class="rounded-full px-3 py-1 text-[10px] font-medium"
                        :style="{ backgroundColor: colour(roles.cardButton), color: colour(roles.cardButtonText) }"
                        >Aa</span
                    >
                </template>
                <template v-else-if="group.key === 'navigation'">
                    <span
                        class="flex h-7 w-20 items-center justify-center rounded"
                        :style="{ backgroundColor: colour(roles.navBg), color: colour(roles.tabTint) }"
                        >◌&nbsp; ◌&nbsp; ◌</span
                    >
                </template>
                <template v-else-if="group.key === 'fab'">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full"
                        :style="{ backgroundColor: colour(roles.fab), color: colour(roles.fabIcon) }"
                        >◉</span
                    >
                </template>
                <template v-else>
                    <span class="h-8 w-20 rounded" :style="{ backgroundColor: colour(roles.screenBg) }" />
                </template>
            </div>

            <div class="mt-3 space-y-3">
                <RoleSelector
                    v-for="role in group.roles"
                    :key="role"
                    :label="t(roleMeta[role].label)"
                    :hint-key="role"
                    :fallback="roleMeta[role].fallback"
                    :options="options"
                    :model-value="roles[role]"
                    @update:model-value="(value) => emit('update-role', role, value ?? roleMeta[role].fallback)"
                    @hint="emit('hint', $event)"
                />
            </div>
        </section>
    </div>
</template>
