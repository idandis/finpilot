/**
 * A deterministic "hash the name into a hue" so a person keeps the same
 * avatar color everywhere, without storing one.
 */
export function avatarStyle(name: string) {
    let hash = 0;

    for (const char of name) {
        hash = (hash << 5) - hash + char.charCodeAt(0);
        hash |= 0;
    }

    return { backgroundColor: `hsl(${Math.abs(hash) % 360}, 45%, 35%)` };
}
