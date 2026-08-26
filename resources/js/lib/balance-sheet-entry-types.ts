import type { BalanceSheetGenericEntryType } from '@/types';

export type BalanceSheetEntryTypeConfig = {
    title: string;
    singular: string;
    addLabel: string;
    amountLabel: string;
    showAmount: boolean;
    showFrequency: boolean;
    showHours: boolean;
    showProgress: boolean;
    showLinkedAsset: boolean;
    showLinkedLiability: boolean;
};

export const balanceSheetEntryTypeConfig: Record<
    BalanceSheetGenericEntryType,
    BalanceSheetEntryTypeConfig
> = {
    income: {
        title: 'Entrate',
        singular: 'entrata',
        addLabel: 'Aggiungi entrata',
        amountLabel: 'Importo mensile',
        showAmount: true,
        showFrequency: true,
        showHours: false,
        showProgress: false,
        showLinkedAsset: true,
        showLinkedLiability: false,
    },
    expense: {
        title: 'Uscite',
        singular: 'uscita',
        addLabel: 'Aggiungi uscita',
        amountLabel: 'Importo mensile',
        showAmount: true,
        showFrequency: true,
        showHours: false,
        showProgress: false,
        showLinkedAsset: true,
        showLinkedLiability: true,
    },
    liability: {
        title: 'Passività',
        singular: 'passività',
        addLabel: 'Aggiungi passività',
        amountLabel: 'Debito residuo',
        showAmount: true,
        showFrequency: false,
        showHours: false,
        showProgress: false,
        showLinkedAsset: false,
        showLinkedLiability: false,
    },
    time: {
        title: 'Tempo',
        singular: 'voce di tempo',
        addLabel: 'Aggiungi voce di tempo',
        amountLabel: 'Ore/mese',
        showAmount: false,
        showFrequency: false,
        showHours: true,
        showProgress: false,
        showLinkedAsset: true,
        showLinkedLiability: false,
    },
    goal: {
        title: 'Obiettivi',
        singular: 'obiettivo',
        addLabel: 'Aggiungi obiettivo',
        amountLabel: 'Progresso',
        showAmount: false,
        showFrequency: false,
        showHours: false,
        showProgress: true,
        showLinkedAsset: false,
        showLinkedLiability: false,
    },
};
