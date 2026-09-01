/**
 * Le migliaia sono separate da uno spazio stretto invece che dal punto:
 * "2 000,00" si riconosce a colpo d'occhio come duemila, mentre "2.000,00"
 * si confonde con un decimale. È U+202F, uno spazio unicode che non manda
 * il numero a capo e non si allarga con la giustificazione.
 *
 * `useGrouping: 'always'` serve perché l'italiano di default non raggruppa i
 * numeri di quattro cifre: senza, 2000 resterebbe "2000,00" e 10000 diventerebbe
 * "10 000,00", proprio i due casi da distinguere a colpo d'occhio.
 */
const THIN_SPACE = ' ';

function withSpacedThousands(formatted: string): string {
    return formatted.split('.').join(THIN_SPACE);
}

export function formatCurrency(value: number | string): string {
    return withSpacedThousands(new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        useGrouping: 'always',
    }).format(Number(value)));
}

export function formatHours(value: number | string): string {
    const hours = new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 1,
        useGrouping: 'always',
    }).format(Number(value));

    return `${withSpacedThousands(hours)} h`;
}

/**
 * Importo senza simbolo di valuta: usato dove la valuta è già dichiarata
 * una volta sola in testa alla sezione (es. "In EUR").
 */
export function formatAmount(value: number | string): string {
    const amount = Number(value);

    return withSpacedThousands(new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        useGrouping: 'always',
    }).format(amount === 0 ? 0 : amount));
}
