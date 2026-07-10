<script setup lang="ts">
import InputModal from '@/components/InputModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOnboardingModal } from '@/composables/useOnboardingModal';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { open } = useOnboardingModal();
const { t } = useI18n();

const form = useForm({ name: '', date: '' });
function createEvent() {
    form.post(route('events.store'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <InputModal v-model:open="open" :title="t('onboarding.create')" :description="t('onboarding.descriptionAdmin')">
        <form @submit.prevent="createEvent" class="space-y-3">
            <div>
                <Input v-model="form.name" :placeholder="t('onboarding.placeholder')" required autofocus />
                <p v-if="form.errors.name" class="text-destructive mt-1 text-xs">{{ form.errors.name }}</p>
            </div>
            <Input v-model="form.date" type="date" />
            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? '…' : t('common.create') }}
                </Button>
            </div>
        </form>
    </InputModal>
</template>
