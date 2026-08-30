<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Copy,
    Eye,
    EyeOff,
    ListFilter,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordEntryController from '@/actions/App/Http/Controllers/PasswordEntryController';
import PasswordGroupController from '@/actions/App/Http/Controllers/PasswordGroupController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PASSWORD_GROUP_ICON_NAMES,
    PASSWORD_GROUP_ICONS,
} from '@/lib/password-group-icons';
import type { PasswordGroupIconName } from '@/lib/password-group-icons';
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
    () =>
        props.groups.find((group) => group.id === selectedGroupId.value) ??
        null,
);

function selectGroup(group: PasswordGroup) {
    selectedGroupId.value = group.id;
}

function groupIcon(group: PasswordGroup) {
    const name = (group.icon ?? 'other') as PasswordGroupIconName;

    return PASSWORD_GROUP_ICONS[name] ?? PASSWORD_GROUP_ICONS.other;
}

const isAddCategoryOpen = ref(false);
const newGroupIcon = ref<PasswordGroupIconName>('other');

const isAddEntryOpen = ref(false);

watch(selectedGroupId, () => {
    isAddEntryOpen.value = false;
});

function destroyGroup(group: PasswordGroup) {
    if (
        confirm(
            `Eliminare il gruppo "${group.name}"? Verranno eliminati anche tutti gli account al suo interno.`,
        )
    ) {
        if (selectedGroupId.value === group.id) {
            selectedGroupId.value = null;
        }

        router.delete(PasswordGroupController.destroy(group.id).url, {
            preserveScroll: true,
        });
    }
}

function destroyEntry(entry: PasswordEntry) {
    if (confirm(`Eliminare l'account "${entry.platform_name}"?`)) {
        router.delete(PasswordEntryController.destroy(entry.id).url, {
            preserveScroll: true,
        });
    }
}

// --- Search across every group's accounts ---
const search = ref('');

const isSearching = computed(() => search.value.trim().length > 0);

function resetFilters() {
    search.value = '';
    selectedGroupId.value = null;
}

type VisibleEntry = { entry: PasswordEntry; group: PasswordGroup };

const searchResults = computed<VisibleEntry[]>(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return [];
    }

    return props.groups.flatMap((group) =>
        group.entries
            .filter(
                (entry) =>
                    entry.platform_name.toLowerCase().includes(query) ||
                    entry.username?.toLowerCase().includes(query),
            )
            .map((entry) => ({ entry, group })),
    );
});

const visibleEntries = computed<VisibleEntry[]>(() => {
    if (isSearching.value) {
        return searchResults.value;
    }

    if (!selectedGroup.value) {
        return [];
    }

    const group = selectedGroup.value;

    return group.entries.map((entry) => ({ entry, group }));
});

const emptyMessage = computed(() => {
    if (isSearching.value) {
        return 'Nessun account trovato.';
    }

    if (!selectedGroup.value) {
        return 'Seleziona una categoria qui sopra per vedere i suoi account.';
    }

    return 'Nessun account in questo gruppo ancora.';
});

// A small deterministic "hash the platform name into a hue" so every
// account gets a consistent, distinct avatar color without storing one.
function avatarStyle(platformName: string) {
    let hash = 0;

    for (const char of platformName) {
        hash = (hash << 5) - hash + char.charCodeAt(0);
        hash |= 0;
    }

    const hue = Math.abs(hash) % 360;

    return { backgroundColor: `hsl(${hue}, 45%, 28%)` };
}

function avatarInitial(platformName: string): string {
    return Array.from(platformName)[0]?.toUpperCase() ?? '?';
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
        const response = await fetch(
            PasswordEntryController.reveal.url(entry.id),
            {
                headers: { Accept: 'application/json' },
            },
        );

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
        <Heading
            title="Password"
            description="Le password sono cifrate nel database e mostrate solo quando le richiedi esplicitamente."
        />

        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Cerca un account o uno username..."
                    class="h-11 pl-9"
                />
            </div>
            <Button
                variant="outline"
                size="icon"
                class="h-11 w-11 shrink-0"
                title="Reimposta ricerca e categoria selezionata"
                @click="resetFilters"
            >
                <ListFilter class="size-4" />
            </Button>
        </div>

        <div class="space-y-6">
            <div>
                <h3 class="text-sm font-medium">Categorie</h3>

                <div class="mt-3 flex gap-4 overflow-x-auto pb-2">
                    <button
                        type="button"
                        class="flex shrink-0 flex-col items-center gap-1.5"
                        @click="isAddCategoryOpen = true"
                    >
                        <span
                            class="flex size-14 items-center justify-center rounded-full border-2 border-dashed border-muted-foreground/40 text-muted-foreground transition hover:border-primary hover:text-primary"
                        >
                            <Plus class="size-5" />
                        </span>
                        <span class="text-xs text-muted-foreground">Nuova</span>
                    </button>

                    <div
                        v-for="group in groups"
                        :key="group.id"
                        class="group relative flex shrink-0 flex-col items-center gap-1.5"
                    >
                        <button
                            type="button"
                            class="flex size-14 items-center justify-center rounded-full transition"
                            :class="
                                group.id === selectedGroupId
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-foreground hover:bg-muted/70'
                            "
                            @click="selectGroup(group)"
                        >
                            <component :is="groupIcon(group)" class="size-5" />
                        </button>
                        <span
                            class="max-w-[4.5rem] truncate text-xs text-muted-foreground"
                            >{{ group.name }}</span
                        >
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="absolute -top-1 -right-1 size-5 rounded-full bg-background text-muted-foreground opacity-0 shadow-sm transition group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina categoria"
                            @click.stop="destroyGroup(group)"
                        >
                            <Trash2 class="size-3" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="groups.length === 0"
                    class="mt-3 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Nessuna categoria ancora: creane una col cerchio "+" qui
                    sopra.
                </div>
            </div>

            <Dialog v-model:open="isAddCategoryOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nuova categoria</DialogTitle>
                    </DialogHeader>
                    <Form
                        v-bind="PasswordGroupController.store.form()"
                        reset-on-success
                        class="grid grid-cols-1 gap-4"
                        v-slot="{ errors, processing }"
                        @success="isAddCategoryOpen = false"
                    >
                        <div class="grid gap-2">
                            <Label for="group-name">Nome</Label>
                            <Input
                                id="group-name"
                                name="name"
                                placeholder="Es. Lavoro"
                                required
                                autofocus
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Icona</Label>
                            <input
                                type="hidden"
                                name="icon"
                                :value="newGroupIcon"
                            />
                            <div class="grid grid-cols-5 gap-2">
                                <button
                                    v-for="iconName in PASSWORD_GROUP_ICON_NAMES"
                                    :key="iconName"
                                    type="button"
                                    class="flex items-center justify-center rounded-lg p-2.5 transition"
                                    :class="
                                        newGroupIcon === iconName
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-foreground hover:bg-muted/70'
                                    "
                                    @click="newGroupIcon = iconName"
                                >
                                    <component
                                        :is="PASSWORD_GROUP_ICONS[iconName]"
                                        class="size-5"
                                    />
                                </button>
                            </div>
                            <InputError :message="errors.icon" />
                        </div>
                        <Button type="submit" :disabled="processing"
                            >Crea categoria</Button
                        >
                    </Form>
                </DialogContent>
            </Dialog>

            <div v-if="groups.length > 0" class="min-w-0 space-y-4">
                <h3 v-if="isSearching" class="text-sm font-medium">
                    Risultati per "{{ search }}"
                </h3>
                <div
                    v-else-if="selectedGroup"
                    class="flex items-center justify-between gap-4"
                >
                    <h3 class="text-sm font-medium">
                        {{ selectedGroup.name }}
                    </h3>
                    <Button size="sm" @click="isAddEntryOpen = true">
                        <Plus />
                        Aggiungi account
                    </Button>
                </div>

                <Dialog v-if="selectedGroup" v-model:open="isAddEntryOpen">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                >Nuovo account in "{{
                                    selectedGroup.name
                                }}"</DialogTitle
                            >
                        </DialogHeader>
                        <Form
                            :key="selectedGroup.id"
                            v-bind="
                                PasswordEntryController.store.form(
                                    selectedGroup.id,
                                )
                            "
                            reset-on-success
                            class="grid grid-cols-1 gap-4"
                            v-slot="{ errors, processing }"
                            @success="isAddEntryOpen = false"
                        >
                            <div class="grid gap-2">
                                <Label for="platform_name">Piattaforma</Label>
                                <Input
                                    id="platform_name"
                                    name="platform_name"
                                    placeholder="Es. GitHub"
                                    required
                                    autofocus
                                />
                                <InputError :message="errors.platform_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="username">Username</Label>
                                <Input
                                    id="username"
                                    name="username"
                                    placeholder="Es. iana"
                                />
                                <InputError :message="errors.username" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                />
                                <InputError :message="errors.password" />
                            </div>
                            <Button type="submit" :disabled="processing"
                                >Aggiungi account</Button
                            >
                        </Form>
                    </DialogContent>
                </Dialog>

                <div
                    v-if="visibleEntries.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    {{ emptyMessage }}
                </div>

                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div
                        v-for="{ entry, group } in visibleEntries"
                        :key="entry.id"
                        class="space-y-1.5 rounded-xl bg-muted dark:bg-muted/40 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                                :style="avatarStyle(entry.platform_name)"
                            >
                                {{ avatarInitial(entry.platform_name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">
                                    {{ entry.platform_name }}
                                </p>
                                <p
                                    v-if="isSearching"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ group.name }}
                                </p>
                            </div>
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
                            <p
                                class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                            >
                                {{ entry.username ?? '—' }}
                            </p>
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
                            <p
                                class="min-w-0 flex-1 truncate font-mono text-sm"
                            >
                                {{
                                    visibleEntryIds.has(entry.id)
                                        ? revealedPasswords[entry.id]
                                        : '••••••••'
                                }}
                            </p>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0 text-muted-foreground"
                                :disabled="loadingEntryIds.has(entry.id)"
                                :title="
                                    visibleEntryIds.has(entry.id)
                                        ? 'Nascondi password'
                                        : 'Mostra password'
                                "
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
            </div>
        </div>
    </div>
</template>
