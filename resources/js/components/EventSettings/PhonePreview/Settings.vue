<script setup lang="ts">
import { getDesignVariant } from '@/lib/designVariants';
import { isSoft, previewButtonRadius, previewCardStyle, previewScreenGradient, previewSheen } from '@/lib/previewStyles';
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
    cCardText: string;
    cCardButton: string;
    cCardButtonText: string;
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
// Composite card (edge-to-edge rows + dividers) → padding:false; classic renders
// bordered without a shadow, soft-luxury as glass — resolved like the app.
const cardStyle = computed(() => previewCardStyle(variant.value, props.cCardBg, props.cBorder, { padding: false }));
// Segmented control + logout button corner, resolved like the screens.
const buttonRadius = computed(() => previewButtonRadius(variant.value));
// Logout button fill: soft-luxury adds the sheen over the card-button colour.
const logoutBtnStyle = computed(() => ({
    borderRadius: buttonRadius.value,
    color: props.cCardButtonText,
    ...(isSoft(variant.value) ? { background: previewSheen(props.cCardButton) } : { backgroundColor: props.cCardButton }),
}));

function hintBgClass(hint: string): string {
    return props.activeHint === hint ? 'preview-hint-bg' : '';
}
function hintBorderClass(hint: string): string {
    return props.activeHint === hint ? 'preview-hint-border' : '';
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
                        <div class="flex flex-1 flex-col items-start px-2 pt-3">
                            <div class="w-full" :class="[hintBgClass('cardBg'), hintBorderClass('border')]" :style="cardStyle">
                                <div class="px-2 pt-2 pb-1.5">
                                    <p
                                        class="text-[5px]"
                                        :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                        :class="hintFilterClass('cardText')"
                                    >
                                        Eingeloggt als
                                    </p>
                                    <p
                                        class="text-[7px] font-semibold"
                                        :style="{ color: cCardText, fontFamily: previewFontFamily }"
                                        :class="hintFilterClass('cardText')"
                                    >
                                        Max Mustermann
                                    </p>
                                    <p
                                        class="text-[5px]"
                                        :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                        :class="hintFilterClass('cardText')"
                                    >
                                        Familie Mustermann
                                    </p>
                                </div>
                                <div class="mx-2 border-t" :style="{ borderColor: cBorder + '33' }" :class="hintBgClass('border')"></div>
                                <div class="px-2 py-1.5">
                                    <p
                                        class="mb-1 text-[5px]"
                                        :style="{ color: cCardText + '88', fontFamily: previewFontFamily }"
                                        :class="hintFilterClass('cardText')"
                                    >
                                        Sprache
                                    </p>
                                    <div
                                        class="flex overflow-hidden border"
                                        :style="{ borderColor: cBorder + '55', borderRadius: buttonRadius }"
                                        :class="hintBorderClass('border')"
                                    >
                                        <div
                                            class="flex-1 py-0.5 text-center text-[5px] font-semibold"
                                            :class="hintBgClass('cardButton')"
                                            :style="{
                                                backgroundColor: cCardButton,
                                                color: cCardButtonText,
                                                fontFamily: previewFontFamily,
                                            }"
                                        >
                                            <span :class="hintFilterClass('cardButtonText')">Deutsch</span>
                                        </div>
                                        <div
                                            class="flex-1 py-0.5 text-center text-[5px]"
                                            :style="{
                                                backgroundColor: cCardBg,
                                                color: cCardText + '66',
                                                fontFamily: previewFontFamily,
                                            }"
                                        >
                                            Englisch
                                        </div>
                                    </div>
                                </div>
                                <div class="mx-2 border-t" :style="{ borderColor: cBorder + '33' }" :class="hintBgClass('border')"></div>
                                <div
                                    class="mx-2 my-1.5 py-1 text-center text-[6px] font-semibold"
                                    :style="{ ...logoutBtnStyle, fontFamily: previewFontFamily }"
                                    :class="hintBgClass('cardButton')"
                                >
                                    <span :class="hintFilterClass('cardButtonText')">Ausloggen</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <PreviewTabBar
                                :tab-defs="tabDefs"
                                :active-index="4"
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
