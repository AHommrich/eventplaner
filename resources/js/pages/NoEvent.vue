<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{ hasPendingRequest: boolean }>();

const { t } = useI18n();

const form = useForm({ event_name: '' });
function submitRequest() { form.post(route('events.request')); }
</script>

<template>
    <Head :title="t('onboarding.requestTitle')" />
    <AppLayout>
        <div class="flex min-h-[60vh] items-center justify-center p-6">
            <div class="w-full max-w-md space-y-4">

                <div class="text-center pb-2">
                    <h1 class="text-2xl font-bold">{{ t('onboarding.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('onboarding.descriptionUser') }}</p>
                </div>

                <!-- Anfrage noch nicht gestellt -->
                <Card v-if="!hasPendingRequest">
                    <CardHeader><CardTitle class="text-sm">{{ t('onboarding.requestTitle') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="mb-3 text-sm text-muted-foreground">{{ t('onboarding.requestDesc') }}</p>
                        <form @submit.prevent="submitRequest" class="space-y-3">
                            <div class="grid gap-1.5">
                                <Input v-model="form.event_name" :placeholder="t('onboarding.placeholder')" required />
                                <p v-if="form.errors.event_name" class="text-xs text-destructive">{{ form.errors.event_name }}</p>
                            </div>
                            <Button type="submit" :disabled="form.processing" class="w-full">
                                {{ form.processing ? '…' : t('onboarding.request') }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Anfrage bereits eingereicht -->
                <Card v-else>
                    <CardHeader><CardTitle class="text-sm">{{ t('onboarding.pendingTitle') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">{{ t('onboarding.pendingDesc') }}</p>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
