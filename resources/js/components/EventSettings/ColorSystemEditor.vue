<script setup lang="ts">
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

const radioClass = (formRole: string | null, optKey: string, fallback: PaletteKey) =>
    (formRole ?? fallback) === optKey ? 'border-ring bg-muted/20' : 'border-input hover:border-muted-foreground';
</script>

<template>
    <!-- Color palette -->
    <div class="grid gap-3">
        <div class="flex items-center gap-2">
            <Label>{{ t('event.colorHint') }}</Label>
            <InfoTooltip :text="t('event.colorSystemInfo')" />
        </div>
        <p class="text-muted-foreground -mt-1 text-xs">{{ t('event.colorHintSub') }}</p>
        <!-- 3 base pickers -->
        <div class="grid grid-cols-3 gap-3">
            <div class="grid gap-1.5">
                <span class="text-muted-foreground text-xs">{{ t('event.colorPrimary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorPrimary"
                        class="border-input h-9 w-10 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                    />
                    <Input v-model="colorPrimary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <span class="text-muted-foreground text-xs">{{ t('event.colorSecondary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorSecondary"
                        class="border-input h-9 w-10 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                    />
                    <Input v-model="colorSecondary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <span class="text-muted-foreground text-xs">{{ t('event.colorTertiary') }}</span>
                <div class="flex items-center gap-1.5">
                    <input
                        type="color"
                        v-model="colorTertiary"
                        class="border-input h-9 w-10 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                    />
                    <Input v-model="colorTertiary" class="px-2 font-mono text-xs uppercase" maxlength="7" />
                </div>
            </div>
        </div>

        <!-- Radio selectors -->
        <div class="space-y-3 pt-1">
            <!-- Screen background -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleScreenBg') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'screenBg')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'sb' + opt.key"
                        type="button"
                        @click="roleScreenBg = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleScreenBg, opt.key, 'secondary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Card background -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleCardBg') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'cardBg')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'cb' + opt.key"
                        type="button"
                        @click="roleCardBg = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleCardBg, opt.key, 'tertiary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Text on cards -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleCardText') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'cardText')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'ct' + opt.key"
                        type="button"
                        @click="roleCardText = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleCardText, opt.key, 'primary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
                <p
                    v-if="cardContrastFailsAA"
                    role="alert"
                    class="rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-[11px] leading-snug text-amber-800 dark:text-amber-200"
                >
                    {{ t('event.contrastWarning', { ratio: cardContrastLabel }) }}
                </p>
            </div>
            <!-- Button on cards -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleCardButton') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'cardButton')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'cbt' + opt.key"
                        type="button"
                        @click="roleCardButton = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleCardButton, opt.key, 'primary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Text on card buttons -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleCardButtonText') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'cardButtonText')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'cbtx' + opt.key"
                        type="button"
                        @click="roleCardButtonText = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleCardButtonText, opt.key, 'tertiary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Navbar background -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleNavBg') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'navBg')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'nav' + opt.key"
                        type="button"
                        @click="roleNavBg = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleNavBg, opt.key, 'secondary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Navbar icons/text -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleTabTint') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'tabTint')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'tt' + opt.key"
                        type="button"
                        @click="roleTabTint = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleTabTint, opt.key, 'primary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Border color -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleBorder') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'border')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'br' + opt.key"
                        type="button"
                        @click="roleBorder = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleBorder, opt.key, 'primary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- FAB button -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleFab') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'fab')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'fab' + opt.key"
                        type="button"
                        @click="roleFab = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleFab, opt.key, 'primary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
            <!-- Icon color inside FAB -->
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-xs">{{ t('event.roleFabIcon') }}</span
                    ><button
                        type="button"
                        @click="emit('show-hint', 'fabIcon')"
                        class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-full border border-amber-500/60 text-[11px] font-bold text-amber-500 hover:bg-amber-500/10"
                    >
                        ?
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="opt in colorOptions"
                        :key="'fabi' + opt.key"
                        type="button"
                        @click="roleFabIcon = opt.key"
                        class="flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs transition-colors"
                        :class="radioClass(roleFabIcon, opt.key, 'tertiary')"
                    >
                        <div class="h-6 w-6 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: opt.value }" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
