<script setup lang="ts">
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
    cCardText: string;
    cTabTint: string;
    cBorder: string;
    tabDefs: TabDef[];
    activeHint: string | null;
}>();

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
                            <p class="text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff' }">Willkommen, Max!</p>
                            <p
                                class="mt-0.5 text-center text-[9px] leading-tight font-bold"
                                :style="{ color: colorHomeText || '#ffffff', fontFamily: previewFontFamily }"
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
                                <p class="mt-0.5 text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff', opacity: 0.7 }">
                                    Dresscode:
                                </p>
                                <p class="text-center text-[6px]" :style="{ color: colorHomeText || '#ffffff', opacity: 0.7 }">
                                    {{ dresscode }}
                                </p>
                            </template>
                            <p class="mt-1.5 text-center text-[6px] font-bold" :style="{ color: colorHomeText || '#ffffff' }">
                                {{ previewCountdown || 'Noch 6T 11Std 22Min 30Sek' }}
                            </p>
                        </div>
                        <div class="relative flex h-[26px] w-full items-end justify-around border-t border-white/15 bg-black/30 pb-1.5">
                            <div v-for="(tab, i) in tabDefs" :key="'h' + i" class="flex flex-col items-center gap-0.5">
                                <svg
                                    width="9"
                                    height="9"
                                    :viewBox="tab.viewBox ?? '0 0 24 24'"
                                    fill="none"
                                    :stroke-width="tab.strokeWidth ?? 2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :stroke="
                                        i === 0
                                            ? colorHomeText || '#ffffff'
                                            : colorHomeText
                                              ? colorHomeText + '77'
                                              : 'rgba(255,255,255,0.45)'
                                    "
                                >
                                    <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                </svg>
                                <span
                                    class="text-[5px]"
                                    :style="{
                                        color:
                                            i === 0
                                                ? colorHomeText || '#ffffff'
                                                : colorHomeText
                                                  ? colorHomeText + '77'
                                                  : 'rgba(255,255,255,0.45)',
                                        fontWeight: i === 0 ? '700' : '400',
                                    }"
                                    >{{ tab.label }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <!-- Without cover: normal app colors -->
                    <div
                        v-else
                        class="flex flex-col"
                        style="height: 244px"
                        :style="{ backgroundColor: cScreenBg, fontFamily: previewFontFamily }"
                        :class="hintBgClass('screenBg')"
                    >
                        <div class="flex items-center justify-between px-2 pt-1.5 text-[7px] font-semibold text-gray-900">
                            <span>9:41</span><span style="font-size: 6px">▲▲ ▐</span>
                        </div>
                        <div class="flex flex-1 flex-col items-center justify-center px-2.5 pb-1">
                            <p class="text-center text-[6px]" :style="{ color: cCardText }">Willkommen, Max!</p>
                            <p class="mt-0.5 text-center text-[9px] leading-tight font-bold" :style="{ color: cCardText, fontFamily: previewFontFamily }">
                                {{ eventName || 'Event-Name' }}
                            </p>
                            <p class="mt-0.5 text-center text-[5.5px]" :style="{ color: cCardText }">
                                {{ previewDate || 'Samstag, 1. Januar 2026' }}
                            </p>
                            <!-- Venue: both → name+icon, then address without icon -->
                            <template v-if="venueDisplayMode === 'both'">
                                <span
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
                            <p class="mt-1.5 text-center text-[6px] font-bold" :style="{ color: cCardText }">
                                {{ previewCountdown || 'Noch 6T 11Std 22Min 30Sek' }}
                            </p>
                        </div>
                        <div
                            class="flex h-[26px] w-full items-end justify-around border-t pb-1.5"
                            :style="{ backgroundColor: cScreenBg, borderColor: cBorder + '33' }"
                        >
                            <div
                                v-for="(tab, i) in tabDefs"
                                :key="'hn' + i"
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
                                    :stroke="i === 0 ? cTabTint : cTabTint + '55'"
                                >
                                    <path v-for="(p, pi) in tab.paths" :key="pi" :d="p" />
                                </svg>
                                <span
                                    class="text-[5px]"
                                    :style="{
                                        color: i === 0 ? cTabTint : cTabTint + '55',
                                        fontWeight: i === 0 ? '700' : '400',
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
