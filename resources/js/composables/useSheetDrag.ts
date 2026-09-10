import { computed, ref } from 'vue';

/** Oltre questa distanza il foglio si chiude invece di tornare al suo posto. */
const CLOSE_AFTER_PX = 120;

const isCompactScreen = () => window.matchMedia('(max-width: 639px)').matches;

/**
 * Il foglio che si butta giù col dito.
 *
 * Su telefono un pannello appoggiato in basso si chiude trascinandolo, come
 * ci si aspetta da un'app; su schermo largo resta una finestra al centro e il
 * gesto non serve, quindi non parte nemmeno.
 */
export function useSheetDrag(close: () => void) {
    const isDragging = ref(false);
    const offset = ref(0);
    let startY = 0;

    const start = (event: PointerEvent) => {
        if (!isCompactScreen()) {
            return;
        }

        isDragging.value = true;
        startY = event.clientY;
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    };

    const move = (event: PointerEvent) => {
        if (!isDragging.value) {
            return;
        }

        // Solo verso il basso: tirare in su non allunga il pannello.
        offset.value = Math.max(0, event.clientY - startY);
    };

    const end = () => {
        if (!isDragging.value) {
            return;
        }

        const closing = offset.value > CLOSE_AFTER_PX;

        isDragging.value = false;
        offset.value = 0;

        if (closing) {
            close();
        }
    };

    // Mentre il dito trascina il foglio segue senza inerzia; lasciato andare,
    // torna al suo posto con la transizione del tema.
    const dragStyle = computed(() =>
        isDragging.value
            ? { transform: `translateY(${offset.value}px)`, transition: 'none' }
            : undefined,
    );

    return { dragStyle, start, move, end };
}
