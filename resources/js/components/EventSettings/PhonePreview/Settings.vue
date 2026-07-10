<script setup lang="ts">
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
}>();

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
                                class="w-full rounded-xl shadow-sm"
                                :style="{
                                    backgroundColor: cCardBg,
                                    borderWidth: '1px',
                                    borderStyle: 'solid',
                                    borderColor: cBorder + '33',
                                }"
                                :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                            >
                                <div class="px-2 pb-1.5 pt-2">
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
                                    <div class="flex rounded-lg border" :style="{ borderColor: cBorder + '55' }" :class="hintBorderClass('border')">
                                        <div
                                            class="flex-1 rounded-l-lg py-0.5 text-center text-[5px] font-semibold"
                                            :style="{
                                                backgroundColor: cCardButton,
                                                color: cCardButtonText,
                                                fontFamily: previewFontFamily,
                                            }"
                                            :class="hintBgClass('cardButton')"
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
                                    class="mx-2 my-1.5 rounded-lg py-1 text-center text-[6px] font-semibold"
                                    :style="{
                                        backgroundColor: cCardButton,
                                        color: cCardButtonText,
                                        fontFamily: previewFontFamily,
                                    }"
                                    :class="hintBgClass('cardButton')"
                                >
                                    <span :class="hintFilterClass('cardButtonText')">Ausloggen</span>
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                            :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                        >
                            <div
                                v-for="(tab, i) in tabDefs"
                                :key="'e' + i"
                                class="flex flex-col items-center gap-0.5"
                                :class="hintFilterClass('tabTint')"
                            >
                                <svg
                                    width="9"
                                    height="9"
                                    :viewBox="tab.viewBox ?? '0 0 24 24'"
                                    fill="none"
                                    :stroke-width="tab.strokeWidth ?? 2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :stroke="i === 4 ? cTabTint : cTabTint + '55'"
                                >
                                    <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                </svg>
                                <span
                                    class="text-[5px]"
                                    :style="{
                                        color: i === 4 ? cTabTint : cTabTint + '55',
                                        fontWeight: i === 4 ? '700' : '400',
                                    }"
                                    >{{ tab.label }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
