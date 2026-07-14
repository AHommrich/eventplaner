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
    cTabTint: string;
    cBorder: string;
    tabDefs: TabDef[];
    activeHint: string | null;
    designPreset: string;
}>();

const isSoft = computed(() => props.designPreset === 'soft-luxury');
// Soft-luxury card look: larger radius, soft shadow, no hard border.
const softCardStyle = computed(() =>
    isSoft.value
        ? { backgroundColor: props.cCardBg, borderRadius: '14px', boxShadow: '0 4px 10px -3px rgba(90,50,55,0.28)' }
        : {
              backgroundColor: props.cCardBg,
              borderWidth: '1px',
              borderStyle: 'solid',
              borderColor: props.cBorder + '33',
          },
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

const groupMembers = [
    { name: 'Anna M.', status: 'Zugesagt' },
    { name: 'Klaus M.', status: 'Zugesagt' },
    { name: 'Lisa M.', status: 'Abgesagt', red: true },
];
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
                        <div class="flex flex-1 flex-col gap-1.5 overflow-hidden px-1.5 pt-1">
                            <!-- Card 1 -->
                            <div
                                class="rounded-lg p-1.5 shadow-sm"
                                :style="softCardStyle"
                                :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                            >
                                <p class="mb-0.5 text-[5px]" :style="{ color: cCardText + '77' }" :class="hintFilterClass('cardText')">
                                    Bitte antworte bis 25. März.
                                </p>
                                <div class="flex items-center justify-between">
                                    <span class="text-[7px] font-semibold" :style="{ color: cCardText }" :class="hintFilterClass('cardText')"
                                        >Max Mustermann</span
                                    >
                                    <span class="rounded-full px-1 py-0.5 text-[4px] font-semibold text-white" style="background-color: #4a7c59"
                                        >Zugesagt</span
                                    >
                                </div>
                                <div class="mt-1 flex gap-0.5">
                                    <div
                                        class="flex-1 py-0.5 text-center text-[5px] font-semibold text-white"
                                        :class="isSoft ? 'rounded-full' : 'rounded'"
                                        style="background-color: #4a7c59"
                                    >
                                        Zusagen
                                    </div>
                                    <div
                                        class="flex-1 py-0.5 text-center text-[5px] font-semibold text-white"
                                        :class="isSoft ? 'rounded-full' : 'rounded'"
                                        style="background-color: #b45a3c"
                                    >
                                        Absagen
                                    </div>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div
                                class="rounded-lg p-1.5 shadow-sm"
                                :style="softCardStyle"
                                :class="[hintBgClass('cardBg'), hintBorderClass('border')]"
                            >
                                <p class="text-[7px] font-semibold" :style="{ color: cCardText }" :class="hintFilterClass('cardText')">
                                    Deine Gruppe
                                </p>
                                <p class="mb-1 text-[5px]" :style="{ color: cCardText + '77' }" :class="hintFilterClass('cardText')">
                                    Du kannst für deine Gruppe antworten.
                                </p>
                                <div
                                    v-for="(member, mi) in groupMembers"
                                    :key="mi"
                                    class="border-t py-0.5 first:border-t-0"
                                    :style="{ borderColor: cBorder + '22' }"
                                    :class="hintBorderClass('border')"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[6px] font-semibold" :style="{ color: cCardText }" :class="hintFilterClass('cardText')">{{
                                            member.name
                                        }}</span>
                                        <div class="flex items-center gap-0.5">
                                            <span
                                                class="rounded-full px-1 py-0.5 text-[4px] font-semibold"
                                                :style="{
                                                    backgroundColor: member.red ? '#b45a3c' : '#4a7c59',
                                                    color: '#ffffff',
                                                }"
                                                >{{ member.status }}</span
                                            >
                                            <svg
                                                width="6"
                                                height="6"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                :stroke="cCardText + '88'"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                :class="hintFilterClass('cardText')"
                                            >
                                                <path d="M6 9l6 6 6-6" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-[4px]" :style="{ color: cCardText + '66' }" :class="hintFilterClass('cardText')">
                                        Von dir gesetzt
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div :class="hintFilterClass('tabTint')">
                            <PreviewTabBar
                                :tab-defs="tabDefs"
                                :active-index="1"
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
