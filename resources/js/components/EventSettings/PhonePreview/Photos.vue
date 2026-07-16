<script setup lang="ts">
import { getDesignVariant } from '@/lib/designVariants';
import { isSoft, previewScreenGradient, previewSheen, previewTileRadius } from '@/lib/previewStyles';
import { computed } from 'vue';
import PreviewTabBar from './PreviewTabBar.vue';

interface TabDef {
    label: string;
    viewBox?: string;
    strokeWidth?: number;
    paths: string[];
}

const props = defineProps<{
    previewFontFamily: string;
    cScreenBg: string;
    cPrimary: string;
    cCardBg: string;
    cFab: string;
    cFabIcon: string;
    cTabTint: string;
    cBorder: string;
    cNavBg: string;
    tabDefs: TabDef[];
    activeHint: string | null;
    designPreset: string;
}>();

const variant = computed(() => getDesignVariant(props.designPreset));
// Soft-luxury gets the diagonal screen gradient; classic stays flat.
const screenBg = computed(() => {
    const gradient = previewScreenGradient(variant.value, props.cScreenBg, props.cPrimary);
    return gradient ? { background: gradient } : { backgroundColor: props.cScreenBg };
});
// Tile radius: soft = rounded (radius.tile), classic = near-square (app hardcodes 2).
const tileRadius = computed(() => previewTileRadius(variant.value));
// Sheet tab bar floats higher → lift the FAB; soft-luxury also gives the FAB a
// coloured glow + sheen fill instead of a flat drop shadow.
const isSheet = computed(() => variant.value.tabBar === 'sheet');
const fabStyle = computed(() =>
    isSoft(variant.value) ? { background: previewSheen(props.cFab), boxShadow: `0 4px 10px -2px ${props.cFab}` } : { backgroundColor: props.cFab },
);

function hintBgClass(hint: string): string {
    return props.activeHint === hint ? 'preview-hint-bg' : '';
}
function hintFilterClass(hint: string): string {
    return props.activeHint === hint ? 'preview-hint-filter' : '';
}
</script>

<template>
    <div class="flex flex-col items-center gap-1.5">
        <div class="phone-frame-outer">
            <div class="phone-frame-inner">
                <div class="overflow-hidden rounded-[20px] border-[5px] border-gray-800 shadow-md" style="width: 120px">
                    <div
                        class="flex flex-col"
                        style="height: 244px"
                        :style="{ ...screenBg, fontFamily: previewFontFamily }"
                        :class="hintBgClass('screenBg')"
                    >
                        <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                            <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                        </div>
                        <div class="relative flex-1 px-0.5 pt-1" :class="{ 'px-1': isSheet }">
                            <div class="grid grid-cols-3" :class="isSheet ? 'gap-1' : 'gap-0.5'">
                                <div v-for="n in 6" :key="n" :style="{ borderRadius: tileRadius, backgroundColor: '#d4cfc8', aspectRatio: '1' }" />
                            </div>
                            <!-- FAB with cFab color -->
                            <div
                                class="absolute right-2 flex h-7 w-7 items-center justify-center rounded-full shadow-md"
                                :class="[hintBgClass('fab'), isSheet ? 'bottom-9' : 'bottom-3']"
                                :style="fabStyle"
                            >
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    :stroke="cFabIcon"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :class="hintFilterClass('fabIcon')"
                                >
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <PreviewTabBar
                                :tab-defs="tabDefs"
                                :active-index="2"
                                :design-preset="designPreset"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                                :c-nav-bg="cNavBg"
                                :active-hint="activeHint"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
