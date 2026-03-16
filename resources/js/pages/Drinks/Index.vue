<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Getränke', href: '/drinks' }];
const page = usePage();
const drinks = computed(() => page.props.drinks as { id: number; name: string }[]);

const form = useForm({ name: '' });
function submit() { form.post(route('drinks.store'), { onSuccess: () => { form.reset(); toast.success(t('toast.drinkAdded')); } }); }

const confirmOpen = ref(false);
const pendingId   = ref<number | null>(null);
function askDelete(id: number) { pendingId.value = id; confirmOpen.value = true; }
function doDelete() { if (pendingId.value) router.delete(route('drinks.destroy', pendingId.value), { onSuccess: () => toast.success(t('toast.drinkDeleted')) }); }
</script>

<template>
    <Head :title="t('drink.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.add') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="flex gap-2">
                        <Input v-model="form.name" :placeholder="t('drink.placeholder')" required />
                        <Button type="submit" :disabled="form.processing">{{ t('common.add') }}</Button>
                    </form>
                    <p v-if="form.errors.name" class="mt-1.5 text-xs text-destructive">{{ form.errors.name }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('drink.forEvent') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="drinks.length === 0" class="text-sm text-muted-foreground">{{ t('drink.none') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="drink in drinks" :key="drink.id" class="flex items-center justify-between py-2.5">
                            <span class="text-sm">{{ drink.name }}</span>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askDelete(drink.id)">{{ t('common.delete') }}</Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('drink.deleteTitle')"
            :description="t('drink.deleteDescription')"
            :confirm-label="t('common.delete')"
            destructive
            @confirm="doDelete"
        />
    </AppLayout>
</template>
