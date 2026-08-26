/**
 * Testo leggibile sopra un colore scelto dall'utente: le tinte chiare della
 * tavolozza (giallo, ambra) hanno bisogno di testo scuro, le altre di bianco.
 */
export function readableTextOn(backgroundHex: string): string {
    const hex = backgroundHex.replace('#', '');

    if (hex.length !== 6) {
        return '#ffffff';
    }

    const channels = [0, 2, 4].map((index) => parseInt(hex.slice(index, index + 2), 16) / 255);
    const linear = (channel: number) =>
        channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

    const luminance =
        0.2126 * linear(channels[0]) + 0.7152 * linear(channels[1]) + 0.0722 * linear(channels[2]);

    return luminance > 0.45 ? '#0b0b0b' : '#ffffff';
}
