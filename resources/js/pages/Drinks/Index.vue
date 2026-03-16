<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Getränke', href: '/drinks' }];
const page = usePage();
const drinks = computed(() => page.props.drinks as { id: number; name: string }[]);

const form = useForm({ name: '' });
function submit() { form.post(route('drinks.store'), { onSuccess: () => form.reset() }); }
function deleteDrink(id: number) { router.delete(route('drinks.destroy', id)); }
</script>

<template>
    <Head title="Getränke" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <Card>
                <CardHeader>
                    <CardTitle>Getränk hinzufügen</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="flex gap-2">
                        <Input v-model="form.name" placeholder="z.B. Bier, Wein, Limo, Wasser…" required />
                        <Button type="submit" :disabled="form.processing">Hinzufügen</Button>
                    </form>
                    <p v-if="form.errors.name" class="mt-1.5 text-xs text-destructive">{{ form.errors.name }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Getränke für dieses Event</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="drinks.length === 0" class="text-sm text-muted-foreground">Noch keine Getränke angelegt.</p>
                    <ul v-else class="divide-y">
                        <li v-for="drink in drinks" :key="drink.id" class="flex items-center justify-between py-2.5">
                            <span class="text-sm">{{ drink.name }}</span>
                            <Button variant="destructive" size="sm" @click="deleteDrink(drink.id)">Löschen</Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
