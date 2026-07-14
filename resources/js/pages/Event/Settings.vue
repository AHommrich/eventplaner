<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFloatingBar } from '@/composables/useFloatingBar';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface EventData {
    id: number;
    name: string;
    date: string | null;
    rsvp_deadline: string | null;
    dresscode: string | null;
    drink_game_enabled: boolean;
    photo_game_enabled: boolean;
    projector_token: string | null;
}

const props = defineProps<{ event: EventData }>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('event.settings'), href: '/event/settings' }];

const form = useForm({
    name: props.event.name ?? '',
    date: props.event.date ? props.event.date.slice(0, 16) : '',
    rsvp_deadline: props.event.rsvp_deadline ? props.event.rsvp_deadline.slice(0, 16) : '',
    dresscode: props.event.dresscode ?? '',
    drink_game_enabled: props.event.drink_game_enabled ?? false,
    photo_game_enabled: props.event.photo_game_enabled ?? false,
});

const skipGuard = ref(false);
const isDirty = computed(() => form.isDirty);

const { active: floatingBarActive } = useFloatingBar();
watch(
    isDirty,
    (val) => {
        floatingBarActive.value = val;
    },
    { immediate: true },
);

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value && !skipGuard.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

let removeInertiaGuard: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard = router.on('before', (event) => {
        if (isDirty.value && !skipGuard.value) {
            const confirmed = window.confirm(t('drink.unsavedChangesPrompt'));
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard?.();
    floatingBarActive.value = false;
});

function discard() {
    form.reset();
}

function submit() {
    skipGuard.value = true;
    form.post(route('event.settings.update'), {
        onSuccess: () => {
            toast.success(t('toast.eventSettingsSaved'));
        },
        onFinish: () => {
            skipGuard.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('event.settings')" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto w-full max-w-2xl space-y-4 p-4 pb-24">
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Basics -->
                <Card>
                    <CardHeader
                        ><CardTitle>{{ t('event.settings') }}</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p class="mb-4 text-sm text-muted-foreground">{{ t('event.settingsDesc') }}</p>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label>{{ t('event.name') }}</Label>
                                <Input v-model="form.name" required :placeholder="t('event.name')" />
                            </div>
                            <div class="grid gap-2">
                                <Label>{{ t('event.date') }}</Label>
                                <Input v-model="form.date" type="datetime-local" />
                            </div>
                            <div class="grid gap-2">
                                <Label>{{ t('event.rsvpDeadline') }}</Label>
                                <Input v-model="form.rsvp_deadline" type="datetime-local" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >{{ t('event.dresscode') }}
                                    <span class="text-xs font-normal text-muted-foreground">({{ t('event.venueOptional') }})</span></Label
                                >
                                <textarea
                                    v-model="form.dresscode"
                                    rows="2"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    :placeholder="t('event.dresscode')"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Drink game -->
                <Card>
                    <CardContent>
                        <div class="space-y-3 pt-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <Label class="text-sm font-medium">{{ t('event.drinkGameEnabled') }}</Label>
                                    <p class="text-xs text-muted-foreground">{{ t('event.drinkGameEnabledDesc') }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.drink_game_enabled"
                                    @click="form.drink_game_enabled = !form.drink_game_enabled"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                        form.drink_game_enabled ? 'bg-primary' : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                                            form.drink_game_enabled ? 'translate-x-5' : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Photo game -->
                <Card>
                    <CardContent>
                        <div class="space-y-3 pt-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <Label class="text-sm font-medium">{{ t('event.photoGameEnabled') }}</Label>
                                    <p class="text-xs text-muted-foreground">{{ t('event.photoGameEnabledDesc') }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.photo_game_enabled"
                                    @click="form.photo_game_enabled = !form.photo_game_enabled"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                        form.photo_game_enabled ? 'bg-primary' : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                                            form.photo_game_enabled ? 'translate-x-5' : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Slideshow token -->
                <Card>
                    <CardContent>
                        <div class="space-y-3 pt-4">
                            <div>
                                <Label class="text-sm font-medium">{{ t('event.projectorToken') }}</Label>
                                <p class="text-xs text-muted-foreground">{{ t('event.projectorTokenDesc') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <code v-if="props.event.projector_token" class="flex-1 truncate rounded bg-muted px-3 py-1.5 font-mono text-xs">
                                    {{ props.event.projector_token }}
                                </code>
                                <span v-else class="flex-1 text-xs text-muted-foreground italic">{{ t('event.projectorTokenNone') }}</span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        router.post(
                                            route('photos.projector-token.regenerate'),
                                            {},
                                            { onSuccess: () => toast.success(t('event.projectorTokenRegenerated')) },
                                        )
                                    "
                                >
                                    {{ props.event.projector_token ? t('event.projectorTokenRegenerate') : t('event.projectorTokenGenerate') }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>

        <!-- Floating Save Bar -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="isDirty" class="fixed right-6 bottom-6 z-50 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 shadow-lg">
                <span class="mr-1 text-xs text-muted-foreground">{{ t('drink.unsavedChanges') }}</span>
                <Button variant="ghost" size="sm" :disabled="form.processing" @click="discard">
                    {{ t('common.cancel') }}
                </Button>
                <Button size="sm" :disabled="form.processing" @click="submit">
                    {{ form.processing ? '…' : t('common.save') }}
                </Button>
            </div>
        </Transition>
    </AppLayout>
</template>
