<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    event:   { id: number; name: string };
    owner:   { id: number; name: string; email: string };
    members: { id: number; name: string; email: string }[];
}>();

const { t } = useI18n();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Zugang verwalten', href: '/event/access' }];
const form = useForm({ email: '' });

function invite() { form.post(route('event.access.invite'), { onSuccess: () => { form.reset(); toast.success(t('toast.accessAdded')); } }); }

const confirmOpen  = ref(false);
const pendingUserId = ref<number | null>(null);
const pendingName   = ref('');
function askRemove(member: { id: number; name: string }) { pendingUserId.value = member.id; pendingName.value = member.name; confirmOpen.value = true; }
function doRemove() { if (pendingUserId.value) router.delete(route('event.access.remove', pendingUserId.value), { onSuccess: () => toast.success(t('toast.accessRemoved')) }); }
</script>

<template>
    <Head :title="t('access.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <Card>
                <CardHeader>
                    <CardTitle>{{ event.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ t('access.description') }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('access.addUser') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="invite" class="flex gap-2">
                        <Input v-model="form.email" type="email" :placeholder="t('access.emailPlaceholder')" required />
                        <Button type="submit" :disabled="form.processing">{{ t('common.add') }}</Button>
                    </form>
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-destructive">{{ form.errors.email }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('access.currentAccess') }}
                        <InfoTooltip :text="t('access.coOrganizerInfo')" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium">{{ owner.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ owner.email }}</p>
                            </div>
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">{{ t('access.owner') }}</span>
                        </li>
                        <li v-for="member in members" :key="member.id" class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium">{{ member.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                            </div>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="askRemove(member)"><Trash2 class="h-4 w-4" /></Button>
                        </li>
                        <li v-if="members.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                            {{ t('access.none') }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('access.removeTitle', { name: pendingName })"
            :description="t('access.removeDescription')"
            :confirm-label="t('common.remove')"
            destructive
            @confirm="doRemove"
        />
    </AppLayout>
</template>
