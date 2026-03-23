<script setup lang="ts">
import InputModal from '@/components/InputModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useEventRequestModal } from '@/composables/useEventRequestModal';
import { Clock, XCircle } from 'lucide-vue-next';

const { open } = useEventRequestModal();
const { t } = useI18n();
const page = usePage();

interface EventRequestItem {
    id: number;
    event_name: string;
    status: 'pending' | 'declined';
    created_at: string;
}

const userRequests = computed(() =>
    ((page.props as any).user_event_requests ?? []) as EventRequestItem[]
);
const pendingRequest  = computed(() => userRequests.value.find(r => r.status === 'pending'));
const declinedRequest = computed(() => !pendingRequest.value ? userRequests.value.find(r => r.status === 'declined') : null);

const form = useForm({ event_name: '' });
function submit() {
    form.post(route('events.request'), {
        onSuccess: () => { form.reset(); },
    });
}
</script>

<template>
    <InputModal v-model:open="open" :title="t('onboarding.requestTitle')">

        <!-- Anfrage läuft -->
        <template v-if="pendingRequest">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 p-4 dark:bg-amber-950/30">
                <Clock class="mt-0.5 size-4 shrink-0 text-amber-600" />
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-400">{{ t('onboarding.pendingTitle') }}</p>
                    <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-500">
                        {{ t('onboarding.pendingEventName') }}: <span class="font-medium">{{ pendingRequest.event_name }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-600">{{ t('onboarding.pendingDesc') }}</p>
                </div>
            </div>
            <div class="flex justify-end">
                <Button variant="outline" @click="open = false">{{ t('common.close') }}</Button>
            </div>
        </template>

        <!-- Anfrage abgelehnt oder neu -->
        <template v-else>
            <div v-if="declinedRequest" class="flex items-start gap-3 rounded-lg bg-red-50 p-4 dark:bg-red-950/30">
                <XCircle class="mt-0.5 size-4 shrink-0 text-red-500" />
                <div>
                    <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ t('onboarding.declinedTitle') }}</p>
                    <p class="mt-0.5 text-sm text-red-600 dark:text-red-500">
                        {{ t('onboarding.declinedEventName') }}: <span class="font-medium">{{ declinedRequest.event_name }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-red-500">{{ t('onboarding.declinedDesc') }}</p>
                </div>
            </div>

            <p class="text-sm text-muted-foreground">{{ t('onboarding.requestDesc') }}</p>

            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <Input v-model="form.event_name" :placeholder="t('onboarding.placeholder')" required autofocus />
                    <p v-if="form.errors.event_name" class="mt-1 text-xs text-destructive">{{ form.errors.event_name }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? '…' : t('onboarding.request') }}
                    </Button>
                </div>
            </form>
        </template>

    </InputModal>
</template>
