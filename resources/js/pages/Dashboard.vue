<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue';
import GuestTable from '@/components/GuestTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.forms'), href: '/dashboard' }];
const page = usePage();
const categories   = computed(() => page.props.categories   as { id: number; title: string }[]);
const guests       = computed(() => page.props.guests       as any[]);
const groups       = computed(() => page.props.groups       as { id: number; name: string }[]);
const foodSpecials = computed(() => page.props.food_specials as { id: number; name: string }[]);

const categoryForm    = useForm({ title: '' });
const groupForm       = useForm({ name: '' });
const foodSpecialForm = useForm({ name: '' });

const submitCategory    = () => categoryForm.post(route('categories.store'),      { onSuccess: () => { categoryForm.reset(); toast.success(t('toast.categoryCreated')); } });
const submitGroup       = () => groupForm.post(route('groups.store'),             { onSuccess: () => { groupForm.reset(); toast.success(t('toast.groupCreated')); } });
const submitFoodSpecial = () => foodSpecialForm.post(route('foodspecials.store'), { onSuccess: () => { foodSpecialForm.reset(); toast.success(t('toast.foodSpecialCreated')); } });

function handleCreate(form: any) { form.post(route('guests.store'), { onSuccess: () => { form.reset(); toast.success(t('toast.guestCreated')); } }); }
</script>

<template>
    <Head :title="t('nav.forms')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-4">
                    <Card>
                        <CardHeader><CardTitle>{{ t('category.new') }}</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitCategory" class="flex gap-2">
                                <Input v-model="categoryForm.title" :placeholder="t('category.namePlaceholder')" required class="flex-1" />
                                <Button type="submit" :disabled="categoryForm.processing">{{ t('common.create') }}</Button>
                            </form>
                            <p v-if="categoryForm.errors.title" class="mt-1.5 text-xs text-destructive">{{ categoryForm.errors.title }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ t('group.new') }}</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitGroup" class="flex gap-2">
                                <Input v-model="groupForm.name" :placeholder="t('group.namePlaceholder')" required class="flex-1" />
                                <Button type="submit" :disabled="groupForm.processing">{{ t('common.create') }}</Button>
                            </form>
                            <p v-if="groupForm.errors.name" class="mt-1.5 text-xs text-destructive">{{ groupForm.errors.name }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ t('foodSpecial.new') }}</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitFoodSpecial" class="flex gap-2">
                                <Input v-model="foodSpecialForm.name" :placeholder="t('foodSpecial.placeholder')" required class="flex-1" />
                                <Button type="submit" :disabled="foodSpecialForm.processing">{{ t('common.create') }}</Button>
                            </form>
                            <p v-if="foodSpecialForm.errors.name" class="mt-1.5 text-xs text-destructive">{{ foodSpecialForm.errors.name }}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader><CardTitle>{{ t('guest.createNew') }}</CardTitle></CardHeader>
                    <CardContent>
                        <GuestForm :categories="categories" :groups="groups" :food-specials="foodSpecials" :submit-label="t('guest.create')" @submit="handleCreate" />
                    </CardContent>
                </Card>
            </div>

            <GuestTable :guests="guests" />

        </div>
    </AppLayout>
</template>
