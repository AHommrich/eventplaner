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
    cCardBg: string;
    cTabTint: string;
    cBorder: string;
    overCover?: boolean;
    colorHomeText?: string | null;
}>();

const isSoft = computed(() => props.designPreset === 'soft-luxury');
const activeColor = computed(() => (props.overCover ? props.colorHomeText || '#ffffff' : props.cTabTint));
const mutedColor = computed(() =>
    props.overCover ? (props.colorHomeText ? props.colorHomeText + '77' : 'rgba(255,255,255,0.45)') : props.cTabTint + '55',
);
</script>

<template>
    <!-- Soft-luxury: floating frosted rounded bar with an active circle chip. -->
    <div v-if="isSoft" class="flex h-[30px] w-full items-center px-1.5 pb-1">
        <div
            class="flex w-full items-center justify-around rounded-[10px] px-0.5 py-0.5"
            :style="{
                backgroundColor: overCover ? 'rgba(255,255,255,0.16)' : cCardBg + 'e6',
                border: overCover ? '1px solid rgba(255,255,255,0.4)' : `1px solid ${cBorder}22`,
                boxShadow: '0 3px 6px -2px rgba(90,50,55,0.3)',
            }"
        >
            <div v-for="(tab, i) in tabDefs" :key="'sl' + i" class="flex flex-col items-center gap-px">
                <div
                    class="flex items-center justify-center"
                    :style="
                        i === activeIndex
                            ? { width: '13px', height: '13px', borderRadius: '9999px', backgroundColor: cTabTint }
                            : { width: '13px', height: '13px' }
                    "
                >
                    <svg
                        width="8"
                        height="8"
                        :viewBox="tab.viewBox ?? '0 0 24 24'"
                        fill="none"
                        :stroke-width="tab.strokeWidth ?? 2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :stroke="i === activeIndex ? cCardBg : mutedColor"
                    >
                        <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                    </svg>
                </div>
                <span
                    class="text-[4px]"
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
        :style="
            overCover
                ? { backgroundColor: 'rgba(0,0,0,0.3)', borderColor: 'rgba(255,255,255,0.15)' }
                : { backgroundColor: cScreenBg, borderColor: cBorder + '33' }
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
                :stroke="i === activeIndex ? activeColor : mutedColor"
            >
                <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
            </svg>
            <span
                class="text-[5px]"
                :style="{ color: i === activeIndex ? activeColor : mutedColor, fontWeight: i === activeIndex ? '700' : '400' }"
                >{{ tab.label }}</span
            >
        </div>
    </div>
</template>
