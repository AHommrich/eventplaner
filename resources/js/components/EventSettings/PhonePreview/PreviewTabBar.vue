<script setup lang="ts">
/**
 * Shared bottom tab bar for the phone previews. Mirrors the guest app's two
 * presets so the web preview stays in parity with the RN app:
 *   - classic ...... docked, hairline top border, plain icons.
 *   - soft-luxury .. floating rounded "frosted" bar (inset, shadow) with the
 *                    active icon in a filled circle chip + label below.
 *
 * `overCover` = rendered on top of the Home cover photo (light-on-dark tints).
 */
import { getDesignVariant } from '@/lib/designVariants';
import { previewShadow, previewSheen, radiusPx } from '@/lib/previewStyles';
import { computed } from 'vue';

interface TabDef {
    label: string;
    viewBox?: string;
    strokeWidth?: number;
    paths: string[];
}

const props = defineProps<{
    tabDefs: TabDef[];
    activeIndex: number;
    designPreset: string;
    cScreenBg: string;
    cNavBg: string;
    cCardBg: string;
    cTabTint: string;
    cBorder: string;
    overCover?: boolean;
    colorHomeText?: string | null;
    activeHint?: string | null;
}>();

const variant = computed(() => getDesignVariant(props.designPreset));
const isSheet = computed(() => variant.value.tabBar === 'sheet');
const sheetRadius = computed(() => radiusPx(variant.value.tabBarRadius));
const sheetShadow = computed(() => previewShadow(variant.value.card.shadow));
const activeColor = computed(() => (props.overCover ? props.colorHomeText || '#ffffff' : props.cTabTint));
// Inactive tint: the sheet bar rides at ~0x99 (like SoftTabBar), the docked bar
// at ~0x66 (like the classic bar); over a cover both go translucent white.
const mutedColor = computed(() => {
    if (props.overCover) return props.colorHomeText ? props.colorHomeText + '77' : 'rgba(255,255,255,0.45)';
    return props.cTabTint + (isSheet.value ? '99' : '66');
});
// Active highlight disc (soft-luxury): over a cover it's a white ring on the
// frost; elsewhere a tinted disc with a sheen gradient + soft glow.
const activeDiscStyle = computed(() =>
    props.overCover
        ? {
              width: '13px',
              height: '13px',
              borderRadius: '9999px',
              backgroundColor: 'rgba(255,255,255,0.18)',
              border: '1px solid rgba(255,255,255,0.9)',
          }
        : {
              width: '13px',
              height: '13px',
              borderRadius: '9999px',
              background: previewSheen(props.cTabTint),
              boxShadow: `0 1px 3px ${props.cTabTint}73`,
          },
);
// Active icon sits on the tint disc as a knockout revealing the bar behind it,
// so it takes the nav_bg colour (white ring over a cover → white icon).
const activeIconStroke = computed(() => (props.overCover ? '#ffffff' : props.cNavBg));
// The "Navbar icons/text" hint (tab_tint) highlights ONLY the icons + labels —
// not the whole bar (that's the nav_bg hint) — so the two controls stay
// visually distinguishable in the editor.
const hintIconText = computed(() => (props.activeHint === 'tabTint' ? 'preview-hint-filter' : ''));
</script>

<template>
    <!-- Sheet tab bar (soft-luxury): floating rounded bar with an active circle chip. -->
    <div v-if="isSheet" class="flex h-[30px] w-full items-center px-1.5 pb-1">
        <div
            class="flex w-full items-center justify-around px-0.5 py-0.5"
            :class="activeHint === 'navBg' ? 'preview-hint-bg' : ''"
            :style="{
                borderRadius: sheetRadius,
                backgroundColor: overCover ? 'rgba(255,255,255,0.16)' : cNavBg,
                border: overCover ? '1px solid rgba(255,255,255,0.4)' : '1px solid rgba(255,255,255,0.5)',
                boxShadow: overCover ? 'none' : sheetShadow,
            }"
        >
            <div v-for="(tab, i) in tabDefs" :key="'sl' + i" class="flex flex-col items-center gap-px">
                <div
                    class="flex items-center justify-center overflow-hidden"
                    :style="i === activeIndex ? activeDiscStyle : { width: '13px', height: '13px' }"
                >
                    <svg
                        width="8"
                        height="8"
                        :viewBox="tab.viewBox ?? '0 0 24 24'"
                        fill="none"
                        :stroke-width="tab.strokeWidth ?? 2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :class="hintIconText"
                        :stroke="i === activeIndex ? activeIconStroke : mutedColor"
                    >
                        <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                    </svg>
                </div>
                <span
                    class="text-[4px]"
                    :class="hintIconText"
                    :style="{ color: i === activeIndex ? activeColor : mutedColor, fontWeight: i === activeIndex ? '700' : '400' }"
                    >{{ tab.label }}</span
                >
            </div>
        </div>
    </div>

    <!-- Classic: docked bar with a hairline top border. -->
    <div
        v-else
        class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
        :class="activeHint === 'navBg' ? 'preview-hint-bg' : ''"
        :style="
            overCover
                ? { backgroundColor: 'rgba(0,0,0,0.3)', borderColor: 'rgba(255,255,255,0.15)' }
                : { backgroundColor: cNavBg, borderColor: cBorder + '33' }
        "
    >
        <div v-for="(tab, i) in tabDefs" :key="'cl' + i" class="flex flex-col items-center gap-0.5">
            <svg
                width="9"
                height="9"
                :viewBox="tab.viewBox ?? '0 0 24 24'"
                fill="none"
                :stroke-width="tab.strokeWidth ?? 2"
                stroke-linecap="round"
                stroke-linejoin="round"
                :class="hintIconText"
                :stroke="i === activeIndex ? activeColor : mutedColor"
            >
                <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
            </svg>
            <span
                class="text-[5px]"
                :class="hintIconText"
                :style="{ color: i === activeIndex ? activeColor : mutedColor, fontWeight: i === activeIndex ? '700' : '400' }"
                >{{ tab.label }}</span
            >
        </div>
    </div>
</template>
