<script setup lang="ts">
import { getDesignVariant } from '@/lib/designVariants';
import { isSoft, previewScreenGradient } from '@/lib/previewStyles';
import { computed } from 'vue';
import PreviewTabBar from './PreviewTabBar.vue';

interface TabDef {
    label: string;
    viewBox?: string;
    strokeWidth?: number;
    paths: string[];
}

const props = defineProps<{
    coverUrl: string | null;
    eventName: string;
    dresscode: string;
    venueDisplayMode: string | null;
    colorHomeText: string | null;
    colorHomeShadow: string | null;
    homeShadowOpacity: number | null;
    previewDate: string | null;
    previewFontFamily: string;
    previewVenueName: string;
    previewVenueAddress: string;
    previewCountdown: string | null;
    cScreenBg: string;
    cPrimary: string;
    cCardBg: string;
    cCardText: string;
    cCardButtonText: string;
    cTabTint: string;
    cBorder: string;
    cNavBg: string;
    tabDefs: TabDef[];
    activeHint: string | null;
    designPreset: string;
}>();

const variant = computed(() => getDesignVariant(props.designPreset));
const soft = computed(() => isSoft(variant.value));

// Soft-luxury no-cover background: the diagonal screen gradient.
const screenBg = computed(() => {
    const gradient = previewScreenGradient(variant.value, props.cScreenBg, props.cPrimary);
    return gradient ? { background: gradient } : { backgroundColor: props.cScreenBg };
});

// Soft-luxury typography: uppercase spaced welcome + a larger, airier title.
const welcomeExtra = computed(() => (soft.value ? { textTransform: 'uppercase' as const, letterSpacing: '0.4px', opacity: 0.9 } : {}));
const titleExtra = computed(() => (soft.value ? { fontSize: '10px', letterSpacing: '0.3px' } : {}));

// Countdown pill — the app always renders the countdown inside a pill: classic
// solid (primary / dark-on-cover), soft-luxury a frosted chip with a hairline.
const plainPill = computed(() =>
    soft.value
        ? { style: { backgroundColor: props.cCardBg + 'cc', border: `1px solid ${props.cBorder}33` }, color: props.cCardText }
        : { style: { backgroundColor: props.cPrimary }, color: props.cCardButtonText },
);
const coverPill = computed(() => {
    const homeText = props.colorHomeText || '#ffffff';
    return soft.value
        ? { style: { backgroundColor: 'rgba(0,0,0,0.35)', border: '1px solid rgba(255,255,255,0.4)' }, color: '#ffffff' }
        : { style: { backgroundColor: 'rgba(0,0,0,0.35)' }, color: homeText };
});

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
                    <!-- With cover -->
                    <div
                        v-if="coverUrl"
                        class="relative flex flex-col"
                        style="height: 244px; background-size: cover; background-position: center"
                        :style="{ backgroundImage: `url('${coverUrl}')`, fontFamily: previewFontFamily }"
                    >
                        <div
                            class="absolute inset-0"
                            :style="{
                                backgroundColor: colorHomeShadow ?? '#000000',
                                opacity: (homeShadowOpacity ?? 50) / 100,
                            }"
                        />
                        <div class="relative flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-white">
                            <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                        </div>
                        <div class="relative flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                            <p class="text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff', ...welcomeExtra }">Willkommen, Max!</p>
                            <p
                                class="mt-0.5 text-center text-[9px] leading-tight font-bold"
                                :style="{ color: colorHomeText || '#ffffff', fontFamily: previewFontFamily, ...titleExtra }"
                            >
                                {{ eventName || 'Event-Name' }}
                            </p>
                            <p class="mt-0.5 text-center text-[5.5px]" :style="{ color: colorHomeText || '#ffffff' }">
                                {{ previewDate || 'Samstag, 1. Januar 2026' }}
                            </p>
                            <!-- Venue: both → name+icon, then address without icon -->
                            <template v-if="venueDisplayMode === 'both'">
                                <span
                                    class="mt-0.5 flex items-center justify-center gap-0.5 text-center text-[6px]"
                                    :style="{ color: colorHomeText || '#ffffff' }"
                                >
                                    {{ previewVenueName }}
                                    <svg
                                        width="5"
                                        height="5"
                                        viewBox="0 0 512 512"
                                        fill="none"
                                        :stroke="colorHomeText || '#ffffff'"
                                        stroke-width="40"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                        />
                                        <circle cx="256" cy="192" r="48" />
                                    </svg>
                                </span>
                                <p class="text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff' }">
                                    {{ previewVenueAddress }}
                                </p>
                            </template>
                            <!-- Venue: name only -->
                            <span
                                v-else-if="venueDisplayMode === 'name'"
                                class="mt-0.5 flex items-center justify-center gap-0.5 text-center text-[6px]"
                                :style="{ color: colorHomeText || '#ffffff' }"
                            >
                                {{ previewVenueName }}
                                <svg
                                    width="5"
                                    height="5"
                                    viewBox="0 0 512 512"
                                    fill="none"
                                    :stroke="colorHomeText || '#ffffff'"
                                    stroke-width="40"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                    />
                                    <circle cx="256" cy="192" r="48" />
                                </svg>
                            </span>
                            <!-- Venue: address only -->
                            <p v-else class="mt-0.5 text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff' }">
                                {{ previewVenueAddress
                                }}<svg
                                    style="display: inline; vertical-align: middle; margin-left: 1px"
                                    width="5"
                                    height="5"
                                    viewBox="0 0 512 512"
                                    fill="none"
                                    :stroke="colorHomeText || '#ffffff'"
                                    stroke-width="40"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                    />
                                    <circle cx="256" cy="192" r="48" />
                                </svg>
                            </p>
                            <template v-if="dresscode">
                                <p class="mt-0.5 text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff', opacity: 0.7 }">Dresscode:</p>
                                <p class="text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff', opacity: 0.7 }">
                                    {{ dresscode }}
                                </p>
                            </template>
                            <div class="mt-1.5 flex justify-center">
                                <span class="rounded-full px-1.5 py-0.5 text-[6px] font-bold" :style="{ ...coverPill.style, color: coverPill.color }">
                                    {{ previewCountdown || 'Noch 6T 11Std 22Min 30Sek' }}
                                </span>
                            </div>
                        </div>
                        <PreviewTabBar
                            :tab-defs="tabDefs"
                            :active-index="0"
                            :design-preset="designPreset"
                            :c-screen-bg="cScreenBg"
                            :c-card-bg="cCardBg"
                            :c-tab-tint="cTabTint"
                            :c-border="cBorder"
                            :c-nav-bg="cNavBg"
                            :over-cover="true"
                            :color-home-text="colorHomeText"
                        />
                    </div>
                    <!-- Without cover: normal app colors -->
                    <div
                        v-else
                        class="flex flex-col"
                        style="height: 244px"
                        :style="{ ...screenBg, fontFamily: previewFontFamily }"
                        :class="hintBgClass('screenBg')"
                    >
                        <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                            <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                        </div>
                        <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                            <p class="text-center text-[6px]" :style="{ color: cCardText, ...welcomeExtra }">Willkommen, Max!</p>
                            <p
                                class="mt-0.5 text-center text-[9px] leading-tight font-bold"
                                :style="{ color: cCardText, fontFamily: previewFontFamily, ...titleExtra }"
                            >
                                {{ eventName || 'Event-Name' }}
                            </p>
                            <p class="mt-0.5 text-center text-[5.5px]" :style="{ color: cCardText }">
                                {{ previewDate || 'Samstag, 1. Januar 2026' }}
                            </p>
                            <!-- Venue: both → name+icon, then address without icon -->
                            <template v-if="venueDisplayMode === 'both'">
                                <span class="mt-0.5 flex items-center justify-center gap-0.5 text-center text-[6px]" :style="{ color: cCardText }">
                                    {{ previewVenueName }}
                                    <svg
                                        width="5"
                                        height="5"
                                        viewBox="0 0 512 512"
                                        fill="none"
                                        :stroke="cCardText"
                                        stroke-width="40"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                        />
                                        <circle cx="256" cy="192" r="48" />
                                    </svg>
                                </span>
                                <p class="text-center text-[6px]" :style="{ color: cCardText }">{{ previewVenueAddress }}</p>
                            </template>
                            <!-- Venue: name only -->
                            <span
                                v-else-if="venueDisplayMode === 'name'"
                                class="mt-0.5 flex items-center justify-center gap-0.5 text-center text-[6px]"
                                :style="{ color: cCardText }"
                            >
                                {{ previewVenueName }}
                                <svg
                                    width="5"
                                    height="5"
                                    viewBox="0 0 512 512"
                                    fill="none"
                                    :stroke="cCardText"
                                    stroke-width="40"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                    />
                                    <circle cx="256" cy="192" r="48" />
                                </svg>
                            </span>
                            <!-- Venue: address only -->
                            <p v-else class="mt-0.5 text-center text-[6px]" :style="{ color: cCardText }">
                                {{ previewVenueAddress
                                }}<svg
                                    style="display: inline; vertical-align: middle; margin-left: 1px"
                                    width="5"
                                    height="5"
                                    viewBox="0 0 512 512"
                                    fill="none"
                                    :stroke="cCardText"
                                    stroke-width="40"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M256 48c-79.5 0-144 61.39-144 137 0 87 96 224.87 131.25 272.49a15.77 15.77 0 0 0 25.5 0C304 409.89 400 272.07 400 185c0-75.61-64.5-137-144-137z"
                                    />
                                    <circle cx="256" cy="192" r="48" />
                                </svg>
                            </p>
                            <template v-if="dresscode">
                                <p class="mt-0.5 text-center text-[6px]" :style="{ color: cCardText, opacity: 0.7 }">Dresscode:</p>
                                <p class="text-center text-[6px]" :style="{ color: cCardText, opacity: 0.7 }">{{ dresscode }}</p>
                            </template>
                            <div class="mt-1.5 flex justify-center">
                                <span class="rounded-full px-1.5 py-0.5 text-[6px] font-bold" :style="{ ...plainPill.style, color: plainPill.color }">
                                    {{ previewCountdown || 'Noch 6T 11Std 22Min 30Sek' }}
                                </span>
                            </div>
                        </div>
                        <div :class="hintFilterClass('tabTint')">
                            <PreviewTabBar
                                :tab-defs="tabDefs"
                                :active-index="0"
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
