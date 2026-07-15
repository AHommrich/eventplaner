<script setup lang="ts">
import RoleSelector from '@/components/EventSettings/RoleSelector.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { contrastRatio, WCAG_AA_NORMAL } from '@/lib/colorContrast';
import { buildPalette, resolveRole, type PaletteKey } from '@/lib/colorResolver';
import { computed } from 'vue';
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
}>();

const { t } = useI18n();

const palette = computed(() => buildPalette(colorPrimary.value, colorSecondary.value, colorTertiary.value));
const resolve = (role: string | null, fallback: PaletteKey): string => resolveRole(role, fallback, palette.value);

const colorOptions = computed(() => [
    { key: 'primary', label: t('event.colorPrimaryLabel'), value: palette.value.primary },
    { key: 'secondary', label: t('event.colorSecondaryLabel'), value: palette.value.secondary },
    { key: 'tertiary', label: t('event.colorTertiaryLabel'), value: palette.value.tertiary },
]);

// WCAG AA contrast guard for the card text/background pair
const cardText = computed(() => resolve(roleCardText.value, 'primary'));
const cardBg = computed(() => resolve(roleCardBg.value, 'tertiary'));
const cardContrast = computed(() => contrastRatio(cardText.value, cardBg.value));
const cardContrastFailsAA = computed(() => cardContrast.value < WCAG_AA_NORMAL);
const cardContrastLabel = computed(() => cardContrast.value.toFixed(1));

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

        <!-- Role selectors — one RoleSelector per role, each independently set. -->
        <div class="space-y-3 pt-1">
            <RoleSelector
                :label="t('event.roleScreenBg')"
                hint-key="screenBg"
                fallback="secondary"
                :options="colorOptions"
                v-model="roleScreenBg"
                @hint="hint"
            />
            <RoleSelector
                :label="t('event.roleCardBg')"
                hint-key="cardBg"
                fallback="tertiary"
                :options="colorOptions"
                v-model="roleCardBg"
                @hint="hint"
            />
            <div>
                <RoleSelector
                    :label="t('event.roleCardText')"
                    hint-key="cardText"
                    fallback="primary"
                    :options="colorOptions"
                    v-model="roleCardText"
                    @hint="hint"
                />
                <p
                    v-if="cardContrastFailsAA"
                    role="alert"
                    class="mt-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-[11px] leading-snug text-amber-800 dark:text-amber-200"
                >
                    {{ t('event.contrastWarning', { ratio: cardContrastLabel }) }}
                </p>
            </div>
            <RoleSelector
                :label="t('event.roleCardButton')"
                hint-key="cardButton"
                fallback="primary"
                :options="colorOptions"
                v-model="roleCardButton"
                @hint="hint"
            />
            <RoleSelector
                :label="t('event.roleCardButtonText')"
                hint-key="cardButtonText"
                fallback="tertiary"
                :options="colorOptions"
                v-model="roleCardButtonText"
                @hint="hint"
            />
            <RoleSelector
                :label="t('event.roleNavBg')"
                hint-key="navBg"
                fallback="secondary"
                :options="colorOptions"
                v-model="roleNavBg"
                @hint="hint"
            />
            <RoleSelector
                :label="t('event.roleTabTint')"
                hint-key="tabTint"
                fallback="primary"
                :options="colorOptions"
                v-model="roleTabTint"
                @hint="hint"
            />
            <RoleSelector
                :label="t('event.roleBorder')"
                hint-key="border"
                fallback="primary"
                :options="colorOptions"
                v-model="roleBorder"
                @hint="hint"
            />
            <RoleSelector :label="t('event.roleFab')" hint-key="fab" fallback="primary" :options="colorOptions" v-model="roleFab" @hint="hint" />
            <RoleSelector
                :label="t('event.roleFabIcon')"
                hint-key="fabIcon"
                fallback="tertiary"
                :options="colorOptions"
                v-model="roleFabIcon"
                @hint="hint"
            />
        </div>
    </div>
</template>
