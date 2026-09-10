/** Schiarisce (percentuale positiva) o scurisce un colore esadecimale. */
function shade(hex: string, percent: number): string {
    const num = parseInt(hex.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const r = Math.min(255, Math.max(0, (num >> 16) + amt));
    const g = Math.min(255, Math.max(0, ((num >> 8) & 0x00ff) + amt));
    const b = Math.min(255, Math.max(0, (num & 0x0000ff) + amt));

    return `#${(0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1)}`;
}

/**
 * La plastica di una carta: il colore scelto in alto a sinistra che sfuma
 * verso il proprio scuro in basso a destra, come la luce su una carta vera.
 */
export function cardGradient(color: string): string {
    return `linear-gradient(135deg, ${color} 0%, ${shade(color, -35)} 100%)`;
}
