<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({ name: '', date: '' });
function createEvent() { form.post(route('events.store')); }
</script>

<template>
    <Head :title="t('onboarding.title')" />
    <AppLayout>
        <div class="flex min-h-[60vh] items-center justify-center p-6">
            <div class="w-full max-w-md space-y-4">
                <div class="text-center pb-2">
                    <h1 class="text-2xl font-bold">{{ t('onboarding.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('onboarding.descriptionAdmin') }}</p>
                </div>
                <Card>
                    <CardHeader><CardTitle class="text-sm">{{ t('onboarding.create') }}</CardTitle></CardHeader>
                    <CardContent>
                        <form @submit.prevent="createEvent" class="space-y-3">
                            <div class="grid gap-1.5">
                                <Input v-model="form.name" :placeholder="t('onboarding.placeholder')" required />
                                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                            </div>
                            <Input v-model="form.date" type="date" />
                            <Button type="submit" :disabled="form.processing" class="w-full">
                                {{ form.processing ? '…' : t('common.create') }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
