<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputModal from '@/components/InputModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const open = ref(false);

const form = useForm({ name: '', date: '' });
function createEvent() {
    form.post(route('events.store'));
}

function onClose() {
    router.back();
}

onMounted(() => { open.value = true; });
</script>

<template>
    <Head :title="t('onboarding.create')" />
    <AppLayout>
        <InputModal
            v-model:open="open"
            :title="t('onboarding.create')"
            :description="t('onboarding.descriptionAdmin')"
            @update:open="(v) => { if (!v) onClose(); }"
        >
            <form @submit.prevent="createEvent" class="space-y-3">
                <div>
                    <Input v-model="form.name" :placeholder="t('onboarding.placeholder')" required autofocus />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <Input v-model="form.date" type="date" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="onClose">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? '…' : t('common.create') }}
                    </Button>
                </div>
            </form>
        </InputModal>
    </AppLayout>
</template>
