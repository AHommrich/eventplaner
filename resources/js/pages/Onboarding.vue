<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ email: string }>();

const { t } = useI18n();
const copied = ref(false);
function copyEmail() {
    navigator.clipboard.writeText(props.email);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const showCreateForm = ref(false);
const eventForm = useForm({ name: '', date: '' });
function createEvent() { eventForm.post(route('events.store')); }
</script>

<template>
    <Head :title="t('onboarding.title')" />

    <div class="min-h-screen bg-background flex items-center justify-center p-6">
        <div class="w-full max-w-md space-y-4">

            <div class="text-center pb-2">
                <h1 class="text-2xl font-bold">{{ t('onboarding.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('onboarding.description') }}
                </p>
            </div>

            <!-- Email -->
            <Card>
                <CardHeader><CardTitle class="text-sm">Deine Email-Adresse</CardTitle></CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <span class="flex-1 font-mono text-sm truncate text-foreground">{{ email }}</span>
                        <Button variant="outline" size="sm" @click="copyEmail">
                            {{ copied ? 'Kopiert!' : 'Kopieren' }}
                        </Button>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Teile diese Adresse mit dem Admin, damit er dich zu einem Event hinzufügen kann.
                    </p>
                </CardContent>
            </Card>

            <!-- Event erstellen -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm">{{ t('onboarding.create') }}</CardTitle>
                        <Button v-if="!showCreateForm" size="sm" @click="showCreateForm = true">{{ t('onboarding.create') }}</Button>
                    </div>
                </CardHeader>
                <CardContent v-if="showCreateForm">
                    <form @submit.prevent="createEvent" class="space-y-3">
                        <div class="grid gap-1.5">
                            <Input v-model="eventForm.name" :placeholder="t('onboarding.placeholder')" required />
                            <p v-if="eventForm.errors.name" class="text-xs text-destructive">{{ eventForm.errors.name }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Input v-model="eventForm.date" type="date" />
                        </div>
                        <div class="flex gap-2">
                            <Button type="submit" :disabled="eventForm.processing" class="flex-1">
                                {{ eventForm.processing ? '…' : t('common.create') }}
                            </Button>
                            <Button type="button" variant="outline" @click="showCreateForm = false">{{ t('common.cancel') }}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

        </div>
    </div>
</template>
