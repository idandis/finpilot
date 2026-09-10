<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { accountIcon, accountTypeLabels } from '@/lib/budget-accounts';

/**
 * Un conto in una riga.
 *
 * Il colore scelto non fa da sfondo: resta una barretta sul bordo e la tinta
 * dell'icona. Su una pagina con cinque conti cinque fondi pieni si fanno
 * concorrenza, e la cosa che deve saltare all'occhio è il saldo.
 */
const props = defineProps<{
    account: {
        name: string;
        type: string;
        bank_name: string | null;
        iban: string | null;
        color: string | null;
        icon: string | null;
        balance: number;
        hidden_from_stats?: boolean;
        excluded_from_stats?: boolean;
    };
}>();

const color = computed(() => props.account.color ?? '#3b82f6');

/**
 * Di un conto archiviato si legge solo il nome: saldo, banca e cifre dell'IBAN
 * restano coperti. Un tocco li scopre finché si resta sulla pagina - serve a
 * dare un'occhiata al volo, non a tirarlo fuori dall'archivio: per quello c'è
 * l'interruttore dentro alla modifica del conto.
 */
const revealed = ref(false);

const isMasked = computed(
    () => Boolean(props.account.hidden_from_stats) && !revealed.value,
);

const toggleReveal = () => {
    if (!props.account.hidden_from_stats) {
        return;
    }

    revealed.value = !revealed.value;
};

/** Tipo, banca e ultime cifre: solo quello che c'è davvero, niente numeri finti. */
const details = computed(() =>
    [
        accountTypeLabels[props.account.type] ?? props.account.type,
        props.account.bank_name,
        props.account.iban
            ? `•• ${props.account.iban.replace(/\s/g, '').slice(-4)}`
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <div class="relative flex items-center gap-3 py-4 pr-2 pl-5">
        <span
            class="absolute inset-y-3 left-0 w-1 rounded-r-full"
            :style="{ backgroundColor: color }"
        />

        <!-- La maniglia sta fuori dall'area che scopre i dati: trascinare un
             conto archiviato non deve scoprirlo. -->
        <slot name="handle" />

        <!-- Su un conto archiviato la riga è un pulsante: è lì che si tocca
             per scoprire i dati. Sugli altri non c'è niente da scoprire. -->
        <component
            :is="account.hidden_from_stats ? 'button' : 'div'"
            class="flex min-w-0 flex-1 items-center gap-3 text-left"
            :type="account.hidden_from_stats ? 'button' : undefined"
            :aria-expanded="account.hidden_from_stats ? !isMasked : undefined"
            :title="
                account.hidden_from_stats
                    ? isMasked
                        ? `Mostra i dati di ${account.name}`
                        : `Copri di nuovo i dati di ${account.name}`
                    : undefined
            "
            @click="toggleReveal"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                :style="{ backgroundColor: `${color}26`, color }"
            >
                <component :is="accountIcon(account.icon)" class="size-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-2 text-[15px] font-medium">
                    <span class="truncate">{{ account.name }}</span>
                    <span
                        v-if="account.hidden_from_stats"
                        class="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold tracking-wide text-primary-foreground uppercase"
                        title="Archiviato: fuori dal riepilogo del budget"
                    >
                        Archiviato
                    </span>
                    <span
                        v-if="account.excluded_from_stats"
                        class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                        title="Escluso dalle statistiche, movimenti compresi"
                    >
                        Escluso
                    </span>
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ isMasked ? 'Tocca per mostrare' : details }}
                </p>
            </div>

            <p
                class="shrink-0 text-lg font-semibold tabular-nums"
                :class="
                    isMasked
                        ? 'text-muted-foreground'
                        : account.balance < 0
                          ? 'text-red-500'
                          : ''
                "
            >
                {{ isMasked ? '••••' : formatCurrency(account.balance) }}
            </p>
        </component>

        <slot name="actions" />
    </div>
</template>
