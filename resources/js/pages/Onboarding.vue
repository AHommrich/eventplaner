<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ email: string; isAdmin: boolean; hasPendingRequest: boolean }>();

const { t } = useI18n();

// Admin: direktes Erstellen
const createForm = useForm({ name: '', date: '' });
function createEvent() { createForm.post(route('events.store')); }

// Non-Admin: Anfrage senden
const requestForm = useForm({ event_name: '' });
function submitRequest() { requestForm.post(route('events.request')); }
</script>

<template>
    <Head :title="t('onboarding.title')" />

    <div class="min-h-screen bg-background flex items-center justify-center p-6">
        <div class="w-full max-w-md space-y-4">

            <div class="text-center pb-2">
                <h1 class="text-2xl font-bold">{{ t('onboarding.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ isAdmin ? t('onboarding.descriptionAdmin') : t('onboarding.descriptionUser') }}
                </p>
            </div>

            <!-- Admin: Event direkt erstellen -->
            <Card v-if="isAdmin">
                <CardHeader><CardTitle class="text-sm">{{ t('onboarding.create') }}</CardTitle></CardHeader>
                <CardContent>
                    <form @submit.prevent="createEvent" class="space-y-3">
                        <div class="grid gap-1.5">
                            <Input v-model="createForm.name" :placeholder="t('onboarding.placeholder')" required />
                            <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Input v-model="createForm.date" type="date" />
                        </div>
                        <Button type="submit" :disabled="createForm.processing" class="w-full">
                            {{ createForm.processing ? '…' : t('common.create') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Non-Admin, Anfrage ausstehend -->
            <Card v-else-if="hasPendingRequest">
                <CardHeader><CardTitle class="text-sm">{{ t('onboarding.pendingTitle') }}</CardTitle></CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ t('onboarding.pendingDesc') }}</p>
                </CardContent>
            </Card>

            <!-- Non-Admin, noch keine Anfrage -->
            <Card v-else>
                <CardHeader><CardTitle class="text-sm">{{ t('onboarding.requestTitle') }}</CardTitle></CardHeader>
                <CardContent>
                    <p class="mb-3 text-sm text-muted-foreground">{{ t('onboarding.requestDesc') }}</p>
                    <form @submit.prevent="submitRequest" class="space-y-3">
                        <div class="grid gap-1.5">
                            <Input v-model="requestForm.event_name" :placeholder="t('onboarding.placeholder')" required />
                            <p v-if="requestForm.errors.event_name" class="text-xs text-destructive">{{ requestForm.errors.event_name }}</p>
                        </div>
                        <Button type="submit" :disabled="requestForm.processing" class="w-full">
                            {{ requestForm.processing ? '…' : t('onboarding.request') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <p class="text-center text-xs text-muted-foreground">{{ email }}</p>

            <div class="text-center">
                <Link :href="route('logout')" method="post" as="button" class="text-xs text-muted-foreground underline-offset-4 hover:underline">
                    Ausloggen
                </Link>
            </div>

        </div>
    </div>
</template>
