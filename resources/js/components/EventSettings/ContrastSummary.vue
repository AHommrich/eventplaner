<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { contrastRatio, findBrightnessContrastCandidate, WCAG_AA_LARGE, WCAG_AA_NORMAL } from '@/lib/colorContrast';
import type { PaletteKey } from '@/lib/colorResolver';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Roles = Record<'cardBg' | 'cardText' | 'cardButton' | 'cardButtonText' | 'navBg' | 'tabTint' | 'fab' | 'fabIcon', PaletteKey>;

const props = defineProps<{
    palette: Record<PaletteKey, string>;
    roles: Roles;
}>();

const emit = defineEmits<{
    apply: [key: PaletteKey, value: string];
}>();

const { t } = useI18n();

const roleLabels: Record<keyof Roles, string> = {
    cardBg: 'event.roleCardBg',
    cardText: 'event.roleCardText',
    cardButton: 'event.roleCardButton',
    cardButtonText: 'event.roleCardButtonText',
    navBg: 'event.roleNavBg',
    tabTint: 'event.roleTabTint',
    fab: 'event.roleFab',
    fabIcon: 'event.roleFabIcon',
};

const pairs = [
    { id: 'card', foreground: 'cardText', background: 'cardBg', threshold: WCAG_AA_NORMAL },
    { id: 'button', foreground: 'cardButtonText', background: 'cardButton', threshold: WCAG_AA_NORMAL },
    { id: 'navigation', foreground: 'tabTint', background: 'navBg', threshold: WCAG_AA_NORMAL },
    { id: 'fab', foreground: 'fabIcon', background: 'fab', threshold: WCAG_AA_LARGE },
] as const;

type ContrastIssue = (typeof pairs)[number] & { ratio: number; foregroundKey: PaletteKey; backgroundKey: PaletteKey };

const issues = computed<ContrastIssue[]>(() =>
    pairs.flatMap((pair) => {
        const foregroundKey = props.roles[pair.foreground];
        const backgroundKey = props.roles[pair.background];
        const ratio = contrastRatio(props.palette[foregroundKey], props.palette[backgroundKey]);
        return ratio < pair.threshold ? [{ ...pair, ratio, foregroundKey, backgroundKey }] : [];
    }),
);

const selectedIssue = ref<ContrastIssue | null>(null);
const selectedKey = ref<PaletteKey | null>(null);

const selectedCandidate = computed(() => {
    if (!selectedIssue.value || !selectedKey.value) return null;
    const againstKey =
        selectedKey.value === selectedIssue.value.foregroundKey ? selectedIssue.value.backgroundKey : selectedIssue.value.foregroundKey;
    const value = findBrightnessContrastCandidate(props.palette[selectedKey.value], props.palette[againstKey], selectedIssue.value.threshold);
    if (!value) return null;
    return { key: selectedKey.value, value, ratio: contrastRatio(value, props.palette[againstKey]) };
});

const affectedRoles = computed(() => {
    if (!selectedKey.value) return [];
    return (Object.keys(props.roles) as Array<keyof Roles>)
        .filter((role) => props.roles[role] === selectedKey.value)
        .map((role) => t(roleLabels[role]));
});

const introducedIssues = computed(() => {
    if (!selectedCandidate.value || !selectedIssue.value) return [];
    const proposedPalette = { ...props.palette, [selectedCandidate.value.key]: selectedCandidate.value.value };
    return pairs
        .filter((pair) => pair.id !== selectedIssue.value?.id)
        .filter(
            (pair) => contrastRatio(proposedPalette[props.roles[pair.foreground]], proposedPalette[props.roles[pair.background]]) < pair.threshold,
        )
        .map((pair) => t(`event.contrastPair.${pair.id}`));
});

function openIssue(issue: ContrastIssue) {
    selectedIssue.value = issue;
    selectedKey.value = issue.foregroundKey === issue.backgroundKey ? null : issue.foregroundKey;
}

function closeDialog() {
    selectedIssue.value = null;
    selectedKey.value = null;
}

function applyCandidate() {
    if (!selectedCandidate.value) return;
    emit('apply', selectedCandidate.value.key, selectedCandidate.value.value);
    closeDialog();
}
</script>

<template>
    <div v-if="issues.length" class="space-y-2" aria-live="polite">
        <div
            v-for="issue in issues"
            :key="issue.id"
            class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="leading-snug">
                    {{ t('event.contrastWarning', { ratio: issue.ratio.toFixed(1), target: issue.threshold.toFixed(1) }) }}
                    <span class="block pt-0.5 font-medium">{{ t(`event.contrastPair.${issue.id}`) }}</span>
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="h-7 shrink-0 border-amber-500/50 bg-transparent text-xs"
                    @click="openIssue(issue)"
                >
                    {{ t('event.contrastCheckSuggestion') }}
                </Button>
            </div>
        </div>
    </div>
    <p v-else class="text-xs text-muted-foreground">{{ t('event.contrastAllGood') }}</p>

    <Dialog :open="Boolean(selectedIssue)" @update:open="!$event && closeDialog()">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('event.contrastDialogTitle') }}</DialogTitle>
                <DialogDescription v-if="selectedIssue">
                    {{
                        t('event.contrastDialogDescription', {
                            pair: t(`event.contrastPair.${selectedIssue.id}`),
                            ratio: selectedIssue.ratio.toFixed(1),
                            target: selectedIssue.threshold.toFixed(1),
                        })
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedIssue" class="space-y-4 text-sm">
                <p v-if="selectedIssue.foregroundKey === selectedIssue.backgroundKey" class="text-muted-foreground">
                    {{ t('event.contrastSamePaletteKey') }}
                </p>
                <template v-else>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="key in [selectedIssue.foregroundKey, selectedIssue.backgroundKey]"
                            :key="key"
                            type="button"
                            class="flex items-center gap-2 rounded-md border border-input p-2 text-left transition-colors hover:bg-muted"
                            :class="selectedKey === key && 'ring-2 ring-ring ring-offset-2 ring-offset-background'"
                            @click="selectedKey = key"
                        >
                            <span class="h-6 w-6 shrink-0 rounded border" :style="{ backgroundColor: palette[key] }" />
                            <span>
                                <span class="block font-medium">{{ t(`event.color${key.charAt(0).toUpperCase()}${key.slice(1)}Label`) }}</span>
                                <span class="font-mono text-xs text-muted-foreground">{{ palette[key].toUpperCase() }}</span>
                            </span>
                        </button>
                    </div>

                    <div v-if="selectedCandidate" class="rounded-md border p-3">
                        <p class="font-medium">{{ t('event.contrastSuggestion') }}</p>
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <span class="h-6 w-6 rounded border" :style="{ backgroundColor: palette[selectedCandidate.key] }" />
                            <span class="font-mono">{{ palette[selectedCandidate.key].toUpperCase() }}</span>
                            <span aria-hidden="true">→</span>
                            <span class="h-6 w-6 rounded border" :style="{ backgroundColor: selectedCandidate.value }" />
                            <span class="font-mono">{{ selectedCandidate.value }}</span>
                            <span class="text-muted-foreground">({{ selectedCandidate.ratio.toFixed(1) }}:1)</span>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">{{ t('event.contrastBrightnessOnly') }}</p>
                        <p v-if="affectedRoles.length" class="mt-2 text-xs text-muted-foreground">
                            {{ t('event.contrastAffectedRoles', { roles: affectedRoles.join(', ') }) }}
                        </p>
                        <p v-if="introducedIssues.length" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                            {{ t('event.contrastIntroducedIssues', { pairs: introducedIssues.join(', ') }) }}
                        </p>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">{{ t('event.contrastNoSuggestion') }}</p>
                </template>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="closeDialog">{{ t('confirm.cancel') }}</Button>
                <Button type="button" :disabled="!selectedCandidate" @click="applyCandidate">{{ t('event.contrastApply') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
