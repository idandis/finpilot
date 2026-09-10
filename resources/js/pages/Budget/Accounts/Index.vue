<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ChartNoAxesColumn,
    ChevronRight,
    GripVertical,
    Coins,
    CreditCard,
    EllipsisVertical,
    Hash,
    Landmark,
    Pencil,
    Plus,
    Trash2,
    User,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BudgetAccountCard from '@/components/budget/BudgetAccountCard.vue';
import BudgetAccountRow from '@/components/budget/BudgetAccountRow.vue';
import BudgetColorPicker from '@/components/budget/BudgetColorPicker.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useSheetDrag } from '@/composables/useSheetDrag';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { accountIcon, accountTypeLabels } from '@/lib/budget-accounts';
import budgetAccounts from '@/routes/budget-accounts';

interface Account {
    id: number;
    name: string;
    type: string;
    bank_name: string | null;
    holder_name: string | null;
    iban: string | null;
    initial_balance: number;
    color: string | null;
    icon: string | null;
    hidden_from_stats: boolean;
    excluded_from_stats: boolean;
    movements_count: number;
    balance: number;
}

const props = defineProps<{
    accounts: Account[];
    accountTypes: string[];
    icons: string[];
}>();

/**
 * Copia locale riordinabile: il trascinamento muove la riga subito, il server
 * conferma dopo. Si riallinea a ogni nuovo giro di props.
 */
const ordered = ref<Account[]>([...props.accounts]);

watch(
    () => props.accounts,
    (accounts) => {
        ordered.value = [...accounts];
    },
);

// Il totale è quello del budget: né gli archiviati né gli esclusi ci entrano.
const countedAccounts = computed(() =>
    props.accounts.filter(
        (account) => !account.hidden_from_stats && !account.excluded_from_stats,
    ),
);

const archivedCount = computed(
    () => props.accounts.filter((account) => account.hidden_from_stats).length,
);

const excludedCount = computed(
    () =>
        props.accounts.filter(
            (account) =>
                account.excluded_from_stats && !account.hidden_from_stats,
        ).length,
);

const totalBalance = computed(() =>
    countedAccounts.value.reduce((sum, account) => sum + account.balance, 0),
);

const isSheetOpen = ref(false);

// Su telefono il pannello si butta giù col dito, come quello dei movimenti.
const {
    dragStyle: sheetDragStyle,
    start: startSheetDrag,
    move: moveSheetDrag,
    end: endSheetDrag,
} = useSheetDrag(() => (isSheetOpen.value = false));
const editingAccount = ref<Account | null>(null);

const form = useForm({
    name: '',
    type: 'checking',
    bank_name: '',
    holder_name: '',
    iban: '',
    initial_balance: '',
    color: '#3b82f6',
    icon: 'credit-card' as string | null,
    hidden_from_stats: false,
    excluded_from_stats: false,
});

// L'anteprima nel pannello: la carta che si sta compilando, così colore e
// icona si scelgono guardando il risultato.
const preview = computed(() => ({
    name: form.name || 'Nome del conto',
    type: form.type,
    bank_name: form.bank_name || null,
    holder_name: form.holder_name || null,
    iban: form.iban || null,
    color: form.color,
    icon: form.icon,
    hidden_from_stats: form.hidden_from_stats,
    balance: Number(String(form.initial_balance).replace(',', '.')) || 0,
}));

/*
 * Trascinamento con i pointer events invece del drag&drop nativo dell'HTML:
 * quello non parte al tocco, e su telefono sarebbe rimasto inutilizzabile.
 * È lo stesso meccanismo dell'elenco delle categorie.
 */
const draggingId = ref<number | null>(null);
const hasMoved = ref(false);

const persistOrder = () => {
    router.post(
        budgetAccounts.reorder.url(),
        { ids: ordered.value.map((account) => account.id) },
        { preserveScroll: true, preserveState: true, only: ['accounts'] },
    );
};

/** Sposta la riga nella copia locale. Vero se qualcosa è cambiato davvero. */
const reorderLocally = (from: number, to: number) => {
    if (from === to || from < 0 || to < 0 || to >= ordered.value.length) {
        return false;
    }

    const rows = [...ordered.value];
    const [moved] = rows.splice(from, 1);
    rows.splice(to, 0, moved);
    ordered.value = rows;

    return true;
};

const startDrag = (account: Account, event: PointerEvent) => {
    draggingId.value = account.id;
    hasMoved.value = false;

    // Con la cattura del pointer i movimenti continuano ad arrivare alla
    // maniglia anche quando il dito è ormai sopra un'altra riga.
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
};

const dragOverRow = (event: PointerEvent) => {
    if (draggingId.value === null) {
        return;
    }

    const row = document
        .elementFromPoint(event.clientX, event.clientY)
        ?.closest('[data-account-id]');
    const targetId = Number(row?.getAttribute('data-account-id') ?? 0);

    if (!targetId || targetId === draggingId.value) {
        return;
    }

    const from = ordered.value.findIndex(
        (account) => account.id === draggingId.value,
    );
    const to = ordered.value.findIndex((account) => account.id === targetId);

    if (reorderLocally(from, to)) {
        hasMoved.value = true;
    }
};

const endDrag = () => {
    if (hasMoved.value) {
        persistOrder();
    }

    draggingId.value = null;
    hasMoved.value = false;
};

const openCreate = () => {
    editingAccount.value = null;
    form.reset();
    form.clearErrors();
    isSheetOpen.value = true;
};

const openEdit = (account: Account) => {
    editingAccount.value = account;
    form.reset();
    form.clearErrors();
    form.name = account.name;
    form.type = account.type;
    form.bank_name = account.bank_name ?? '';
    form.holder_name = account.holder_name ?? '';
    form.iban = account.iban ?? '';
    form.initial_balance = String(account.initial_balance);
    form.color = account.color ?? '#3b82f6';
    form.icon = account.icon ?? 'credit-card';
    form.hidden_from_stats = account.hidden_from_stats;
    form.excluded_from_stats = account.excluded_from_stats;
    isSheetOpen.value = true;
};

const closeSheet = () => {
    isSheetOpen.value = false;
    editingAccount.value = null;
    form.reset();
};

const submit = () => {
    // L'importo si digita con la virgola, il server lo vuole col punto.
    form.transform((data) => ({
        ...data,
        initial_balance: String(data.initial_balance).replace(',', '.') || '0',
        iban: data.iban.replace(/\s/g, '').toUpperCase(),
    }));

    const options = { preserveScroll: true, onSuccess: closeSheet };

    if (editingAccount.value) {
        form.put(budgetAccounts.update.url(editingAccount.value.id), options);

        return;
    }

    form.post(budgetAccounts.store.url(), options);
};

const destroy = (account: Account) => {
    const warning =
        account.movements_count > 0
            ? ` I ${account.movements_count} movimenti collegati restano nel budget, ma senza conto di provenienza.`
            : '';

    if (!confirm(`Eliminare il conto “${account.name}”?${warning}`)) {
        return;
    }

    router.delete(budgetAccounts.destroy.url(account.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Conti e carte" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4 pb-16">
        <div class="flex items-start justify-between gap-4">
            <Heading title="Conti e carte" />
            <Button class="shrink-0" @click="openCreate">
                <Plus class="mr-1 size-4" />
                Nuovo conto
            </Button>
        </div>

        <div
            v-if="accounts.length"
            class="rounded-2xl bg-muted/60 px-5 py-4 dark:bg-muted/40"
        >
            <p class="text-sm text-muted-foreground">
                Totale sui {{ countedAccounts.length }} conti
                <template v-if="archivedCount">
                    · {{ archivedCount }}
                    {{ archivedCount === 1 ? 'archiviato' : 'archiviati' }}
                </template>
                <template v-if="excludedCount">
                    · {{ excludedCount }}
                    {{ excludedCount === 1 ? 'escluso' : 'esclusi' }}
                </template>
            </p>
            <p
                class="mt-0.5 text-3xl font-bold tabular-nums"
                :class="totalBalance < 0 ? 'text-red-500' : ''"
            >
                {{ formatCurrency(totalBalance) }}
            </p>
        </div>

        <div
            v-if="accounts.length"
            class="divide-y divide-border/50 overflow-hidden rounded-2xl bg-muted/60 dark:bg-muted/40"
        >
            <BudgetAccountRow
                v-for="account in ordered"
                :key="account.id"
                :account="account"
                :data-account-id="account.id"
                :class="draggingId === account.id ? 'bg-foreground/5' : ''"
            >
                <template #handle>
                    <span
                        class="-my-4 shrink-0 cursor-grab touch-none py-4 text-muted-foreground active:cursor-grabbing"
                        :title="`Trascina per riordinare ${account.name}`"
                        @pointerdown="startDrag(account, $event)"
                        @pointermove="dragOverRow"
                        @pointerup="endDrag"
                        @pointercancel="endDrag"
                    >
                        <GripVertical class="size-4" />
                    </span>
                </template>

                <template #actions>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0 text-muted-foreground"
                                :title="`Azioni su ${account.name}`"
                                :aria-label="`Azioni su ${account.name}`"
                            >
                                <EllipsisVertical class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="openEdit(account)">
                                <Pencil class="size-4" />
                                Modifica
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                @click="destroy(account)"
                            >
                                <Trash2 class="size-4" />
                                Elimina
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </BudgetAccountRow>
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">
                Nessun conto. Aggiungi la prima carta o il primo conto corrente:
                da quel momento ogni entrata e ogni uscita del budget potrà dire
                da dove è passata.
            </p>
            <Button variant="link" @click="openCreate"
                >Crea il primo conto</Button
            >
        </div>

        <p class="text-xs text-muted-foreground">
            Crea un conto di tipo “Contanti” per seguire anche il portafoglio:
            si scala quando paghi in contanti e cresce quando prelevi da una
            carta con un trasferimento.
        </p>
    </div>

    <!-- Il pannello del conto: su telefono un foglio che si butta giù -->
    <Dialog v-model:open="isSheetOpen">
        <DialogContent
            class="sheet-panel top-auto bottom-0 left-0 flex w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-t-2xl rounded-b-none border-x-0 border-b-0 p-0 data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-w-lg sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-2xl sm:border max-sm:[&>[data-slot=dialog-close]]:hidden"
            :style="sheetDragStyle"
        >
            <div
                class="shrink-0 touch-none select-none sm:cursor-default"
                @pointerdown="startSheetDrag"
                @pointermove="moveSheetDrag"
                @pointerup="endSheetDrag"
                @pointercancel="endSheetDrag"
            >
                <div class="flex justify-center pt-3 pb-2 sm:hidden">
                    <span
                        class="h-1.5 w-10 rounded-full bg-muted-foreground/30"
                    />
                </div>

                <DialogHeader
                    class="p-0 sm:border-b sm:border-border/60 sm:px-12 sm:py-4"
                >
                    <DialogTitle
                        class="sr-only text-base font-semibold sm:not-sr-only sm:text-center"
                    >
                        {{
                            editingAccount
                                ? `Modifica ${editingAccount.name}`
                                : 'Nuovo conto'
                        }}
                    </DialogTitle>
                </DialogHeader>
            </div>

            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-background"
            >
                <!-- La carta che stai disegnando, aggiornata mentre scrivi -->
                <div class="bg-muted/60 px-4 py-4 dark:bg-muted/40">
                    <BudgetAccountCard :account="preview" />
                </div>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Generale
                </p>

                <div class="ios-group bg-muted/60 dark:bg-muted/40">
                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <Wallet class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">Nome</span>
                        <Input
                            v-model="form.name"
                            placeholder="Es. Intesa quotidiano"
                            aria-label="Nome del conto"
                            class="h-9 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>

                    <Select v-model="form.type">
                        <SelectTrigger
                            class="h-auto w-full rounded-none border-0 bg-transparent px-4 py-5 shadow-none focus-visible:ring-0 dark:bg-transparent dark:hover:bg-transparent [&>svg]:hidden"
                        >
                            <span class="flex min-w-0 items-center gap-4">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                                >
                                    <CreditCard
                                        class="size-4 text-muted-foreground"
                                    />
                                </span>
                                <span class="truncate text-[15px]">Tipo</span>
                            </span>
                            <span
                                class="ml-auto flex min-w-0 items-center gap-1.5"
                            >
                                <span
                                    class="truncate text-[15px] text-muted-foreground"
                                >
                                    {{
                                        accountTypeLabels[form.type] ??
                                        form.type
                                    }}
                                </span>
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground/60"
                                />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in accountTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ accountTypeLabels[type] ?? type }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <Coins class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">Saldo attuale</span>
                        <Input
                            v-model="form.initial_balance"
                            inputmode="decimal"
                            placeholder="0,00"
                            aria-label="Saldo attuale"
                            class="h-9 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>

                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <Archive class="size-4 text-muted-foreground" />
                        </span>
                        <Label
                            for="account-archived"
                            class="text-[15px] font-normal"
                        >
                            Archivia il conto
                        </Label>
                        <Switch
                            id="account-archived"
                            :model-value="form.hidden_from_stats"
                            class="ml-auto shrink-0"
                            @update:model-value="
                                (checked) =>
                                    (form.hidden_from_stats = !!checked)
                            "
                        />
                    </div>

                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <ChartNoAxesColumn
                                class="size-4 text-muted-foreground"
                            />
                        </span>
                        <Label
                            for="account-excluded"
                            class="text-[15px] font-normal"
                        >
                            Escludi dalle statistiche
                        </Label>
                        <Switch
                            id="account-excluded"
                            :model-value="form.excluded_from_stats"
                            class="ml-auto shrink-0"
                            @update:model-value="
                                (checked) =>
                                    (form.excluded_from_stats = !!checked)
                            "
                        />
                    </div>
                </div>

                <p class="px-4 pt-2 text-xs text-muted-foreground">
                    Un conto escluso resta usabile e il suo saldo si aggiorna,
                    ma i movimenti che ci passano non compaiono nel mese e non
                    contano nello speso.
                </p>

                <p
                    v-if="form.errors.name"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.name }}
                </p>
                <p
                    v-if="form.errors.type"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.type }}
                </p>
                <p
                    v-if="form.errors.initial_balance"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.initial_balance }}
                </p>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Dettagli
                </p>

                <div class="ios-group bg-muted/60 dark:bg-muted/40">
                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <Landmark class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">Banca</span>
                        <Input
                            v-model="form.bank_name"
                            placeholder="Facoltativa"
                            aria-label="Banca"
                            class="h-9 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>

                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <User class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">Intestatario</span>
                        <Input
                            v-model="form.holder_name"
                            placeholder="Facoltativo"
                            aria-label="Intestatario"
                            class="h-9 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>

                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <Hash class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">IBAN</span>
                        <Input
                            v-model="form.iban"
                            placeholder="Facoltativo"
                            aria-label="IBAN"
                            autocapitalize="characters"
                            spellcheck="false"
                            class="h-9 min-w-0 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>
                </div>

                <p
                    v-if="form.errors.bank_name"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.bank_name }}
                </p>
                <p
                    v-if="form.errors.holder_name"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.holder_name }}
                </p>
                <p
                    v-if="form.errors.iban"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ form.errors.iban }}
                </p>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Aspetto
                </p>

                <!-- Icona e colore sono griglie, non righe: stanno nella
                     stessa fascia ma con il loro spazio. -->
                <div class="space-y-4 bg-muted/60 px-4 py-5 dark:bg-muted/40">
                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground"
                            >Icona</Label
                        >
                        <div class="grid grid-cols-6 gap-2">
                            <button
                                v-for="icon in icons"
                                :key="icon"
                                type="button"
                                class="flex h-10 items-center justify-center rounded-lg border-2 transition-transform"
                                :class="
                                    form.icon === icon
                                        ? 'scale-105 border-foreground'
                                        : 'border-transparent bg-background/60'
                                "
                                :title="icon"
                                @click="form.icon = icon"
                            >
                                <component
                                    :is="accountIcon(icon)"
                                    class="size-5"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground"
                            >Colore</Label
                        >
                        <BudgetColorPicker v-model="form.color" />
                    </div>
                </div>
            </div>

            <div
                class="shrink-0 border-t border-border/60 p-4 pb-[calc(1rem_+_env(safe-area-inset-bottom))] sm:pb-4"
            >
                <Button
                    class="h-12 w-full text-base"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ editingAccount ? 'Salva conto' : 'Crea conto' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/*
 * Divisori come su un elenco di iPhone: partono dopo l'icona invece che dal
 * bordo, così le righe si leggono come un blocco solo.
 */
.ios-group > * + * {
    position: relative;
}

.ios-group > * + *::before {
    content: '';
    position: absolute;
    top: 0;
    left: 4rem;
    right: 0;
    height: 1px;
    background-color: var(--border);
}
</style>
