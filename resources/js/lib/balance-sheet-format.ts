export function formatCurrency(value: number | string): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

export function formatHours(value: number | string): string {
    return `${new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 }).format(Number(value))} h`;
}
