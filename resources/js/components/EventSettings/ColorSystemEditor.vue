<script setup lang="ts">
import ContrastSummary from '@/components/EventSettings/ContrastSummary.vue';
import RoleGroupEditor from '@/components/EventSettings/RoleGroupEditor.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { buildPalette, type PaletteKey } from '@/lib/colorResolver';
import { recommendDesignRoles } from '@/lib/designRecommendations';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const colorPrimary = defineModel<string>('colorPrimary', { default: '#7c2d3e' });
const colorSecondary = defineModel<string>('colorSecondary', { default: '#e8e3de' });
const colorTertiary = defineModel<string>('colorTertiary', { default: '#ffffff' });
const roleScreenBg = defineModel<string>('roleScreenBg', { default: 'secondary' });
const roleCardBg = defineModel<string>('roleCardBg', { default: 'tertiary' });
const roleCardText = defineModel<string>('roleCardText', { default: 'primary' });
const roleCardButton = defineModel<string>('roleCardButton', { default: 'primary' });
const roleCardButtonText = defineModel<string>('roleCardButtonText', { default: 'tertiary' });
const roleTabTint = defineModel<string>('roleTabTint', { default: 'primary' });
const roleBorder = defineModel<string>('roleBorder', { default: 'primary' });
const roleFab = defineModel<string>('roleFab', { default: 'primary' });
const roleFabIcon = defineModel<string>('roleFabIcon', { default: 'tertiary' });
const roleNavBg = defineModel<string>('roleNavBg', { default: 'secondary' });

const emit = defineEmits<{
    'show-hint': [hint: string];
    'before-confirmed-change': [];
}>();

const { t } = useI18n();
const advancedOpen = ref(false);

const palette = computed(() => buildPalette(colorPrimary.value, colorSecondary.value, colorTertiary.value));
const colorOptions = computed(() => [
    { key: 'primary', label: t('event.colorPrimaryLabel'), value: palette.value.primary },
    { key: 'secondary', label: t('event.colorSecondaryLabel'), value: palette.value.secondary },
    { key: 'tertiary', label: t('event.colorTertiaryLabel'), value: palette.value.tertiary },
]);

const contrastRoles = computed(() => ({
    cardBg: roleCardBg.value as PaletteKey,
    cardText: roleCardText.value as PaletteKey,
    cardButton: roleCardButton.value as PaletteKey,
    cardButtonText: roleCardButtonText.value as PaletteKey,
    navBg: roleNavBg.value as PaletteKey,
    tabTint: roleTabTint.value as PaletteKey,
    fab: roleFab.value as PaletteKey,
    fabIcon: roleFabIcon.value as PaletteKey,
}));

const editableRoles = computed(() => ({
    screenBg: roleScreenBg.value as PaletteKey,
    cardBg: roleCardBg.value as PaletteKey,
    cardText: roleCardText.value as PaletteKey,
    cardButton: roleCardButton.value as PaletteKey,
    cardButtonText: roleCardButtonText.value as PaletteKey,
    navBg: roleNavBg.value as PaletteKey,
    tabTint: roleTabTint.value as PaletteKey,
    border: roleBorder.value as PaletteKey,
    fab: roleFab.value as PaletteKey,
    fabIcon: roleFabIcon.value as PaletteKey,
}));
const roleRecommendations = computed(() => recommendDesignRoles(palette.value));

function applyContrastCandidate(key: PaletteKey, value: string) {
    emit('before-confirmed-change');
    if (key === 'primary') colorPrimary.value = value;
    if (key === 'secondary') colorSecondary.value = value;
    if (key === 'tertiary') colorTertiary.value = value;
}

function updateRole(key: keyof typeof editableRoles.value, value: string) {
    if (key === 'screenBg') roleScreenBg.value = value;
    if (key === 'cardBg') roleCardBg.value = value;
    if (key === 'cardText') roleCardText.value = value;
    if (key === 'cardButton') roleCardButton.value = value;
    if (key === 'cardButtonText') roleCardButtonText.value = value;
    if (key === 'navBg') roleNavBg.value = value;
    if (key === 'tabTint') roleTabTint.value = value;
    if (key === 'border') roleBorder.value = value;
    if (key === 'fab') roleFab.value = value;
    if (key === 'fabIcon') roleFabIcon.value = value;
}

// Forward a role control's highlight request up to Design.vue's hint system.
function hint(key: string) {
    emit('show-hint', key);
}
</script>

<template>
    <!-- Color palette -->
    <div class="grid gap-3">
        <div class="flex items-center gap-2">
            <Label>{{ t('event.colorHint') }}</Label>
            <InfoTooltip :text="t('event.colorSystemInfo')" />
        </div>
        <p class="-mt-1 text-xs text-muted-foreground">{{ t('event.colorHintSub') }}</p>
        <!-- 3 base pickers -->
        <div class="grid grid-cols-3 gap-3">
            <div class="grid gap-1.5">
                <span class="text-xs text-muted-foreground">{{ t('event.colorPrimary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorPrimary"
                        class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                    />
                    <Input v-model="colorPrimary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <span class="text-xs text-muted-foreground">{{ t('event.colorSecondary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorSecondary"
                        class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                    />
                    <Input v-model="colorSecondary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <span class="text-xs text-muted-foreground">{{ t('event.colorTertiary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorTertiary"
                        class="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-0.5"
                    />
                    <Input v-model="colorTertiary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
        </div>

        <ContrastSummary :palette="palette" :roles="contrastRoles" @apply="applyContrastCandidate" />

        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md border border-input px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-muted/50"
            :aria-expanded="advancedOpen"
            @click="advancedOpen = !advancedOpen"
        >
            <span>{{ t('event.advancedAdjustment') }}</span>
            <span aria-hidden="true" class="text-muted-foreground">{{ advancedOpen ? '−' : '+' }}</span>
        </button>

        <div v-if="advancedOpen" class="space-y-3 pt-1">
            <p class="text-xs text-muted-foreground">{{ t('event.roleGroupHint') }}</p>
            <RoleGroupEditor
                :roles="editableRoles"
                :recommendations="roleRecommendations"
                :options="colorOptions"
                @update-role="updateRole"
                @before-group-reset="emit('before-confirmed-change')"
                @hint="hint"
            />
        </div>
    </div>
</template>
