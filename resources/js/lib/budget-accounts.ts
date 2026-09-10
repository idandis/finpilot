import type { Component } from 'vue';
import { CARD_ICONS } from '@/lib/card-icons';
import type { CardIconName } from '@/lib/card-icons';

/**
 * Conti e carte del budget: sono le stesse righe della sezione Finanza, ma
 * qui contano solo per dire da dove è passato un movimento.
 */
export interface BudgetAccount {
    id: number;
    name: string;
    type: string;
    color: string | null;
    icon: string | null;
    /** Saldo di partenza più le entrate, meno le uscite e i giri in uscita. */
    balance: number;
    /** Archiviato: fuori dai conteggi e fuori dalle scelte sui movimenti. */
    hidden_from_stats: boolean;
    /** Escluso: fuori dalle statistiche del budget, movimenti compresi. */
    excluded_from_stats: boolean;
}

/**
 * I conti da mostrare quando si scrive un movimento: quelli archiviati restano
 * fuori. L'eccezione è il conto già scelto sul movimento che si sta
 * modificando - resta in elenco, altrimenti aprire la modifica basterebbe a
 * fargli perdere il conto senza dire niente.
 */
export const selectableAccounts = (
    accounts: BudgetAccount[],
    selectedId?: number | null,
): BudgetAccount[] =>
    accounts.filter(
        (account) => !account.hidden_from_stats || account.id === selectedId,
    );

export const accountTypeLabels: Record<string, string> = {
    checking: 'Conto corrente',
    credit_card: 'Carta di credito',
    debit_card: 'Carta di debito',
    prepaid_card: 'Carta prepagata',
    cash: 'Contanti',
};

/** La carta di credito è l'icona di riserva: è il conto più comune. */
export const accountIcon = (icon: string | null | undefined): Component =>
    CARD_ICONS[icon as CardIconName] ?? CARD_ICONS['credit-card'];
