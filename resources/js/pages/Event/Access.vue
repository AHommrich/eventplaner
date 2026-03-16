<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    event:   { id: number; name: string };
    owner:   { id: number; name: string; email: string };
    members: { id: number; name: string; email: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Zugang verwalten', href: '/event/access' }];
const form = useForm({ email: '' });

function invite() { form.post(route('event.access.invite'), { onSuccess: () => form.reset() }); }
function remove(userId: number) { router.delete(route('event.access.remove', userId)); }
</script>

<template>
    <Head title="Zugang verwalten" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <Card>
                <CardHeader>
                    <CardTitle>{{ event.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">Verwalte wer Zugang zu diesem Event hat.</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>User hinzufügen</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="invite" class="flex gap-2">
                        <Input v-model="form.email" type="email" placeholder="Email-Adresse des Users" required />
                        <Button type="submit" :disabled="form.processing">Hinzufügen</Button>
                    </form>
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-destructive">{{ form.errors.email }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Aktueller Zugang</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium">{{ owner.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ owner.email }}</p>
                            </div>
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">Owner</span>
                        </li>
                        <li v-for="member in members" :key="member.id" class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium">{{ member.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                            </div>
                            <Button variant="destructive" size="sm" @click="remove(member.id)">Entfernen</Button>
                        </li>
                        <li v-if="members.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                            Noch niemand anderes hat Zugang.
                        </li>
                    </ul>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
