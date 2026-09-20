import { nextTick, ref } from 'vue';

/**
 * A polite live region's message.
 *
 * Pair with `<p class="sr-only" role="status" aria-live="polite">{{ message }}</p>`.
 *
 * Exists because a drag is invisible to a screen reader: the wall rearranges and
 * nothing says so. VehiclePhotoPicker reorders with arrow keys and announces
 * nothing at all — a known gap, and the reason this is a standalone composable
 * rather than something private to the gauge wall.
 */
export function useAnnouncer() {
    const message = ref('');

    /**
     * Clearing first is load-bearing: assigning a string identical to the one
     * already in the node is not a DOM change, so assistive tech has nothing to
     * notice and stays silent. Moving one gauge repeatedly hits that case.
     */
    async function announce(text: string): Promise<void> {
        message.value = '';
        await nextTick();
        message.value = text;
    }

    return { message, announce };
}
