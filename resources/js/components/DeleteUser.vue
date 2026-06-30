<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({ password: '' });

const deleteUser = (e: Event) => {
    e.preventDefault();
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall :title="t('settings.deleteAccount')" :description="t('settings.deleteAccountDesc')" />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">{{ t('settings.deleteWarning') }}</p>
                <p class="text-sm">{{ t('settings.deleteWarningDesc') }}</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">{{ t('settings.deleteAccountBtn') }}</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{ t('settings.deleteConfirmTitle') }}</DialogTitle>
                            <DialogDescription>{{ t('settings.deleteConfirmDesc') }}</DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">{{ t('auth.password') }}</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref="passwordInput"
                                v-model="form.password"
                                :placeholder="t('auth.password')"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" @click="closeModal">{{ t('common.cancel') }}</Button>
                            </DialogClose>
                            <Button type="submit" variant="destructive" :disabled="form.processing">{{ t('settings.deleteAccountBtn') }}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
