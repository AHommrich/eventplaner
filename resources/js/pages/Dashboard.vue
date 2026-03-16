<script setup lang="ts">
import GuestForm from '@/components/GuestForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Formulare', href: '/dashboard' }];
const page = usePage();
const categories   = computed(() => page.props.categories   as { id: number; title: string }[]);
const guests       = computed(() => page.props.guests       as any[]);
const groups       = computed(() => page.props.groups       as { id: number; name: string }[]);
const foodSpecials = computed(() => page.props.food_specials as { id: number; name: string }[]);

const categoryForm    = useForm({ title: '' });
const groupForm       = useForm({ name: '' });
const foodSpecialForm = useForm({ name: '' });

const submitCategory    = () => categoryForm.post(route('categories.store'),      { onSuccess: () => categoryForm.reset() });
const submitGroup       = () => groupForm.post(route('groups.store'),             { onSuccess: () => groupForm.reset() });
const submitFoodSpecial = () => foodSpecialForm.post(route('foodspecials.store'), { onSuccess: () => foodSpecialForm.reset() });

function handleCreate(form: any) { form.post(route('guests.store'), { onSuccess: () => form.reset() }); }

const confirmOpen = ref(false);
const pendingId   = ref<number | null>(null);
function askDelete(id: number) { pendingId.value = id; confirmOpen.value = true; }
function doDelete() { if (pendingId.value) router.delete(route('guests.destroy', pendingId.value)); }

const likelihoodLabel: Record<string, string> = {
    sure: 'Sicher', likely: 'Wahrscheinlich', maybe: 'Vielleicht', unlikely: 'Unwahrscheinlich', no: 'Nein',
};
const likelihoodClass: Record<string, string> = {
    sure:     'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    likely:   'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
    maybe:    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    unlikely: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    no:       'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const categoryFilter = ref('');
const filteredGuests = computed(() => {
    if (!categoryFilter.value) return guests.value;
    return guests.value.filter((g: any) => g.category?.title === categoryFilter.value);
});
</script>

<template>
    <Head title="Formulare" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 space-y-4">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-4">
                    <Card>
                        <CardHeader><CardTitle>Neue Kategorie</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitCategory" class="flex gap-2">
                                <Input v-model="categoryForm.title" placeholder="Kategoriename" required class="flex-1" />
                                <Button type="submit" :disabled="categoryForm.processing">Erstellen</Button>
                            </form>
                            <p v-if="categoryForm.errors.title" class="mt-1.5 text-xs text-destructive">{{ categoryForm.errors.title }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Neue Gruppe</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitGroup" class="flex gap-2">
                                <Input v-model="groupForm.name" placeholder="Gruppenname" required class="flex-1" />
                                <Button type="submit" :disabled="groupForm.processing">Erstellen</Button>
                            </form>
                            <p v-if="groupForm.errors.name" class="mt-1.5 text-xs text-destructive">{{ groupForm.errors.name }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Neue Essensbesonderheit</CardTitle></CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitFoodSpecial" class="flex gap-2">
                                <Input v-model="foodSpecialForm.name" placeholder="z.B. Vegetarisch" required class="flex-1" />
                                <Button type="submit" :disabled="foodSpecialForm.processing">Erstellen</Button>
                            </form>
                            <p v-if="foodSpecialForm.errors.name" class="mt-1.5 text-xs text-destructive">{{ foodSpecialForm.errors.name }}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader><CardTitle>Neuen Gast erstellen</CardTitle></CardHeader>
                    <CardContent>
                        <GuestForm :categories="categories" :groups="groups" :food-specials="foodSpecials" submit-label="Gast erstellen" @submit="handleCreate" />
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="p-0">
                    <div class="flex items-center gap-3 border-b px-6 py-3">
                        <span class="text-sm text-muted-foreground">Kategorie:</span>
                        <select v-model="categoryFilter" class="flex h-8 rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                            <option value="">Alle</option>
                            <option v-for="cat in [...new Set(guests.map((g) => g.category?.title).filter(Boolean))]" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                        <span class="ml-auto text-xs text-muted-foreground">{{ filteredGuests.length }} Gäste</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b">
                                <tr>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Vorname</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Nachname</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Kategorie</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Gruppe</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Wahrscheinlichkeit</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Einladung</th>
                                    <th class="h-10 px-6 text-left align-middle font-medium text-muted-foreground">Essen</th>
                                    <th class="h-10 px-6"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="guest in filteredGuests" :key="guest.id"
                                    class="border-b transition-colors hover:bg-muted/50 cursor-pointer last:border-0"
                                    @click="router.visit(route('guests.edit', guest.id))">
                                    <td class="px-6 py-3 font-medium">{{ guest.firstname }}</td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ guest.lastname }}</td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ guest.category?.title ?? '–' }}</td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ guest.group?.name ?? '–' }}</td>
                                    <td class="px-6 py-3">
                                        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', likelihoodClass[guest.likelihood] ?? '']">
                                            {{ likelihoodLabel[guest.likelihood] ?? guest.likelihood }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ guest.invite ? 'Ja' : 'Nein' }}</td>
                                    <td class="px-6 py-3 text-muted-foreground text-xs">{{ guest.food_specials?.map((fs: any) => fs.name).join(', ') || '–' }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click.stop="askDelete(guest.id)">Löschen</Button>
                                    </td>
                                </tr>
                                <tr v-if="filteredGuests.length === 0">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-muted-foreground">Noch keine Gäste angelegt.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Gast löschen"
            description="Dieser Gast wird unwiderruflich gelöscht."
            confirm-label="Löschen"
            destructive
            @confirm="doDelete"
        />
    </AppLayout>
</template>
