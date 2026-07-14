<script setup lang="ts">
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
    cCardBg: string;
    cCardText: string;
    cCardButton: string;
    cCardButtonText: string;
    cTabTint: string;
    cBorder: string;
    tabDefs: TabDef[];
    activeHint: string | null;
    designPreset: string;
}>();

const isSoft = computed(() => props.designPreset === 'soft-luxury');
const softCardStyle = computed(() =>
    isSoft.value
        ? { backgroundColor: props.cCardBg, boxShadow: '0 4px 10px -3px rgba(90,50,55,0.28)' }
        : { backgroundColor: props.cCardBg, borderWidth: '1px', borderStyle: 'solid', borderColor: props.cBorder + '33' },
);

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
                        :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                        :class="hintBgClass('screenBg')"
                    >
                        <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                            <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                        </div>
                        <div class="flex flex-1 flex-col items-start px-2 pt-3">
                            <div
                                class="w-full shadow-sm"
                                :class="[hintBgClass('cardBg'), hintBorderClass('border'), isSoft ? 'rounded-[16px]' : 'rounded-xl']"
                                :style="softCardStyle"
                            >
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
                                        :style="{ borderColor: cBorder + '55' }"
                                        :class="[hintBorderClass('border'), isSoft ? 'rounded-full' : 'rounded-lg']"
                                    >
                                        <div
                                            class="flex-1 py-0.5 text-center text-[5px] font-semibold"
                                            :class="[hintBgClass('cardButton'), isSoft ? '' : 'rounded-l-lg']"
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
                                    :style="{
                                        backgroundColor: cCardButton,
                                        color: cCardButtonText,
                                        fontFamily: previewFontFamily,
                                    }"
                                    :class="[hintBgClass('cardButton'), isSoft ? 'rounded-full' : 'rounded-lg']"
                                >
                                    <span :class="hintFilterClass('cardButtonText')">Ausloggen</span>
                                </div>
                            </div>
                        </div>
                        <div :class="hintFilterClass('tabTint')">
                            <PreviewTabBar
                                :tab-defs="tabDefs"
                                :active-index="4"
                                :design-preset="designPreset"
                                :c-screen-bg="cScreenBg"
                                :c-card-bg="cCardBg"
                                :c-tab-tint="cTabTint"
                                :c-border="cBorder"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
