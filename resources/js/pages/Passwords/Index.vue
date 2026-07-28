<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Copy, Eye, EyeOff, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordEntryController from '@/actions/App/Http/Controllers/PasswordEntryController';
import PasswordGroupController from '@/actions/App/Http/Controllers/PasswordGroupController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as passwordRoutes from '@/routes/passwords';
import type { PasswordEntry, PasswordGroup } from '@/types';

const props = defineProps<{
    groups: PasswordGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Password', href: passwordRoutes.index() }],
    },
});

const selectedGroupId = ref<number | null>(props.groups[0]?.id ?? null);

const selectedGroup = computed<PasswordGroup | null>(
    () => props.groups.find((group) => group.id === selectedGroupId.value) ?? null,
);

function selectGroup(group: PasswordGroup) {
    selectedGroupId.value = group.id;
}

const isAddEntryOpen = ref(false);

watch(selectedGroupId, () => {
    isAddEntryOpen.value = false;
});

function destroyGroup(group: PasswordGroup) {
    if (confirm(`Eliminare il gruppo "${group.name}"? Verranno eliminati anche tutti gli account al suo interno.`)) {
        if (selectedGroupId.value === group.id) {
            selectedGroupId.value = null;
        }

        router.delete(PasswordGroupController.destroy(group.id).url, { preserveScroll: true });
    }
}

function destroyEntry(entry: PasswordEntry) {
    if (confirm(`Eliminare l'account "${entry.platform_name}"?`)) {
        router.delete(PasswordEntryController.destroy(entry.id).url, { preserveScroll: true });
    }
}

// Passwords are never included in the page's own props (see
// PasswordGroupController::index()) - each one is fetched only when the
// user actually asks to see or copy it, and cached here for the rest of
// the page's lifetime so toggling visibility twice doesn't refetch.
const revealedPasswords = reactive<Record<number, string>>({});
const visibleEntryIds = reactive<Set<number>>(new Set());
const loadingEntryIds = reactive<Set<number>>(new Set());

async function ensureRevealed(entry: PasswordEntry): Promise<string> {
    if (revealedPasswords[entry.id] !== undefined) {
        return revealedPasswords[entry.id];
    }

    loadingEntryIds.add(entry.id);

    try {
        const response = await fetch(PasswordEntryController.reveal.url(entry.id), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('reveal failed');
        }

        const data = (await response.json()) as { password: string };
        revealedPasswords[entry.id] = data.password;

        return data.password;
    } finally {
        loadingEntryIds.delete(entry.id);
    }
}

async function toggleVisibility(entry: PasswordEntry) {
    if (visibleEntryIds.has(entry.id)) {
        visibleEntryIds.delete(entry.id);

        return;
    }

    try {
        await ensureRevealed(entry);
        visibleEntryIds.add(entry.id);
    } catch {
        toast.error('Impossibile recuperare la password.');
    }
}

async function copyPassword(entry: PasswordEntry) {
    try {
        const password = await ensureRevealed(entry);
        await navigator.clipboard.writeText(password);
        toast.success('Password copiata negli appunti.');
    } catch {
        toast.error('Impossibile copiare la password.');
    }
}

async function copyUsername(entry: PasswordEntry) {
    if (!entry.username) {
        return;
    }

    await navigator.clipboard.writeText(entry.username);
    toast.success('Username copiato negli appunti.');
}
</script>

<template>
    <Head title="Password" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading title="Password" description="Le password sono cifrate nel database e mostrate solo quando le richiedi esplicitamente." />

        <div class="space-y-6">
            <Form
                v-bind="PasswordGroupController.store.form()"
                reset-on-success
                class="flex w-full items-center gap-2"
                v-slot="{ errors, processing }"
            >
                <Input name="name" placeholder="Nuova categoria" aria-label="Nuova categoria" class="h-10 flex-1" required />
                <Button type="submit" :disabled="processing" class="shrink-0">
                    <Plus />
                    Aggiungi categoria
                </Button>
                <InputError :message="errors.name" />
            </Form>

            <div class="flex flex-wrap items-center gap-2 border-b pb-4">
                <div
                    v-for="group in groups"
                    :key="group.id"
                    class="group flex shrink-0 items-center gap-1 rounded-lg py-1.5 pr-1.5 pl-4 text-sm transition"
                    :class="group.id === selectedGroupId ? 'bg-primary text-primary-foreground font-medium' : 'text-muted-foreground hover:bg-muted'"
                >
                    <button type="button" class="flex items-center gap-1.5" @click="selectGroup(group)">
                        {{ group.name }}
                        <span class="text-xs opacity-70">({{ group.entries.length }})</span>
                    </button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="h-6 w-6 shrink-0 opacity-0 group-hover:opacity-100"
                        :class="
                            group.id === selectedGroupId
                                ? 'text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground'
                                : 'hover:bg-destructive/10 hover:text-destructive'
                        "
                        title="Elimina gruppo"
                        @click="destroyGroup(group)"
                    >
                        <Trash2 class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <div v-if="groups.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                Nessun gruppo ancora: creane uno qui sopra per iniziare.
            </div>

            <div v-else class="min-w-0 space-y-4">
                <div v-if="!selectedGroup" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Seleziona una categoria qui sopra per vedere i suoi account.
                </div>

                <template v-else>
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-sm font-medium">{{ selectedGroup.name }}</h3>
                        <Button size="sm" @click="isAddEntryOpen = true">
                            <Plus />
                            Aggiungi account
                        </Button>
                    </div>

                    <Dialog v-model:open="isAddEntryOpen">
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Nuovo account in "{{ selectedGroup.name }}"</DialogTitle>
                            </DialogHeader>
                            <Form
                                :key="selectedGroup.id"
                                v-bind="PasswordEntryController.store.form(selectedGroup.id)"
                                reset-on-success
                                class="grid grid-cols-1 gap-4"
                                v-slot="{ errors, processing }"
                                @success="isAddEntryOpen = false"
                            >
                                <div class="grid gap-2">
                                    <Label for="platform_name">Piattaforma</Label>
                                    <Input id="platform_name" name="platform_name" placeholder="Es. GitHub" required autofocus />
                                    <InputError :message="errors.platform_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="username">Username</Label>
                                    <Input id="username" name="username" placeholder="Es. iana" />
                                    <InputError :message="errors.username" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="password">Password</Label>
                                    <Input id="password" name="password" type="password" required />
                                    <InputError :message="errors.password" />
                                </div>
                                <Button type="submit" :disabled="processing">Aggiungi account</Button>
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <div v-if="selectedGroup.entries.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Nessun account in questo gruppo ancora.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div v-for="entry in selectedGroup.entries" :key="entry.id" class="space-y-1.5 rounded-xl bg-muted p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="min-w-0 truncate font-medium">{{ entry.platform_name }}</p>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    title="Elimina account"
                                    @click="destroyEntry(entry)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>

                            <div class="flex min-w-0 items-center gap-1.5">
                                <p class="min-w-0 flex-1 truncate text-sm text-muted-foreground">{{ entry.username ?? '—' }}</p>
                                <Button
                                    v-if="entry.username"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground"
                                    title="Copia username"
                                    @click="copyUsername(entry)"
                                >
                                    <Copy />
                                </Button>
                            </div>

                            <div class="flex min-w-0 items-center gap-1.5">
                                <p class="min-w-0 flex-1 truncate font-mono text-sm">
                                    {{ visibleEntryIds.has(entry.id) ? revealedPasswords[entry.id] : '••••••••' }}
                                </p>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground"
                                    :disabled="loadingEntryIds.has(entry.id)"
                                    :title="visibleEntryIds.has(entry.id) ? 'Nascondi password' : 'Mostra password'"
                                    @click="toggleVisibility(entry)"
                                >
                                    <EyeOff v-if="visibleEntryIds.has(entry.id)" />
                                    <Eye v-else />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground"
                                    title="Copia password"
                                    @click="copyPassword(entry)"
                                >
                                    <Copy />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
