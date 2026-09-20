import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * Picking up one card and trading it with another — by pointer, by tap, or by
 * keyboard.
 *
 * Deliberately knows nothing about gauges, Inertia or the DOM beyond a
 * `data-*` attribute: it answers "which card am I over?" and "which card have I
 * chosen?", then calls back with two ids. That is the whole of swap semantics.
 * Insert-and-shift would need drop gaps and index arithmetic; trading a pair
 * needs neither.
 *
 * ── Three input paths, one state machine ──
 *
 *   DRAG    pointerdown on a handle, move past a threshold, release over a card.
 *   LIFT    tap or Space/Enter a handle, then choose a partner. This is not a
 *           nicety: the wall is taller than a phone, a hand-rolled drag has no
 *           edge auto-scroll, and the whole point of the feature is moving a
 *           gauge from the bottom list up into the pinned row. Without lift,
 *           touch users cannot reach it.
 *   KEYS    the same lift, with arrows walking a candidate instead of a finger.
 *
 * Arrows move a CANDIDATE, never the card. Moving the card one slot per press
 * would compose a run of adjacent swaps into an insert-and-shift — exactly the
 * semantics this wall does not have — and would fire a request per keystroke.
 */

type Options = {
    /** Card ids in the order they are drawn, across every region. */
    order: () => number[];
    /** Commit. Called once per gesture. */
    onSwap: (sourceId: number, targetId: number) => void;
    /** DOM attribute carrying a card's id. */
    attribute?: string;
};

/** Enough travel to mean "drag" rather than a shaky tap. */
const DRAG_THRESHOLD = 6;

export function useCardSwap({
    order,
    onSwap,
    attribute = 'data-card-id',
}: Options) {
    /** The card picked up, by either path. */
    const liftedId = ref<number | null>(null);
    /** The card it would trade with right now. */
    const candidateId = ref<number | null>(null);
    /** True only while a pointer is actually dragging, not merely lifted. */
    const isDragging = ref(false);

    const isActive = computed(() => liftedId.value !== null);

    let pointerId: number | null = null;
    let origin: { x: number; y: number } | null = null;
    let handle: Element | null = null;
    let frame = 0;

    function cardIdAt(x: number, y: number): number | null {
        // elementFromPoint rather than cached rects: the wall scrolls, and stale
        // rects would need scroll and resize listeners to stay honest. Returns
        // null outside the viewport, which reads correctly as "no target".
        const found = document
            .elementFromPoint(x, y)
            ?.closest(`[${attribute}]`);
        const raw = found?.getAttribute(attribute);

        return raw === null || raw === undefined ? null : Number(raw);
    }

    function reset(): void {
        if (frame !== 0) {
            cancelAnimationFrame(frame);
            frame = 0;
        }

        if (handle !== null && pointerId !== null) {
            // Releasing a capture we no longer hold throws in some browsers.
            try {
                (handle as HTMLElement).releasePointerCapture(pointerId);
            } catch {
                // Already released — nothing to undo.
            }
        }

        liftedId.value = null;
        candidateId.value = null;
        isDragging.value = false;
        pointerId = null;
        origin = null;
        handle = null;
    }

    function commit(): void {
        const source = liftedId.value;
        const target = candidateId.value;

        reset();

        if (source !== null && target !== null && source !== target) {
            onSwap(source, target);
        }
    }

    /* ── Pointer ─────────────────────────────────────────────────────────── */

    function onHandlePointerdown(event: PointerEvent, id: number): void {
        if (
            !event.isPrimary ||
            (event.pointerType === 'mouse' && event.button !== 0)
        ) {
            return;
        }

        // Stops text selection and the browser's own image drag. The handle is a
        // sibling of the card's action button, not a child, so nothing here can
        // reach the card's click.
        event.preventDefault();

        handle = event.currentTarget as Element;
        pointerId = event.pointerId;
        origin = { x: event.clientX, y: event.clientY };
        liftedId.value = id;
        candidateId.value = null;
        isDragging.value = false;

        // Capture keeps events coming even once the finger leaves the handle,
        // which it does immediately.
        handle.setPointerCapture(event.pointerId);
    }

    function onHandlePointermove(event: PointerEvent): void {
        if (origin === null || liftedId.value === null) {
            return;
        }

        if (!isDragging.value) {
            const travelled =
                Math.abs(event.clientX - origin.x) +
                Math.abs(event.clientY - origin.y);

            if (travelled < DRAG_THRESHOLD) {
                return;
            }

            isDragging.value = true;
        }

        // Hit-test once per frame, not once per move event.
        const { clientX, clientY } = event;

        if (frame !== 0) {
            return;
        }

        frame = requestAnimationFrame(() => {
            frame = 0;
            const over = cardIdAt(clientX, clientY);
            candidateId.value = over === liftedId.value ? null : over;
        });
    }

    function onHandlePointerup(): void {
        // Released without travelling: treat the press as a tap, and stay lifted
        // so the next tap anywhere on the wall chooses a partner.
        if (!isDragging.value) {
            const stillLifted = liftedId.value;
            reset();
            liftedId.value = stillLifted;

            return;
        }

        commit();
    }

    /* ── Keyboard ────────────────────────────────────────────────────────── */

    function step(delta: number): void {
        const all = order();
        const ids = all.filter((id) => id !== liftedId.value);

        if (ids.length === 0) {
            return;
        }

        let next: number;

        if (candidateId.value === null) {
            // The first press starts from where the lifted card sits, so Left
            // offers the gauge just before it rather than wrapping to the end of
            // the wall. Removing the lifted id already shifts everything after
            // it down one, which is why this needs no +1 for a forward step.
            const from = all.indexOf(liftedId.value ?? -1);
            next = delta > 0 ? from : from - 1;
        } else {
            next = ids.indexOf(candidateId.value) + delta;
        }

        candidateId.value =
            ids[Math.max(0, Math.min(ids.length - 1, next))] ?? null;
    }

    function onHandleKeydown(event: KeyboardEvent, id: number): void {
        const keys = [
            ' ',
            'Enter',
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
            'Escape',
        ];

        if (!keys.includes(event.key)) {
            return;
        }

        // A <button> fires click on Space and Enter; handling keydown and
        // preventing default is what stops the gesture running twice.
        event.preventDefault();

        if (event.key === 'Escape') {
            reset();

            return;
        }

        if (liftedId.value === null) {
            if (event.key === ' ' || event.key === 'Enter') {
                liftedId.value = id;
                candidateId.value = null;
            }

            return;
        }

        switch (event.key) {
            case ' ':
            case 'Enter':
                // Nothing chosen yet, so this second press means "put it back".
                if (candidateId.value === null) {
                    reset();
                } else {
                    commit();
                }
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                step(-1);
                break;
            case 'ArrowRight':
            case 'ArrowDown':
                step(1);
                break;
            case 'Home':
                candidateId.value =
                    order().filter((x) => x !== liftedId.value)[0] ?? null;
                break;
            case 'End': {
                const ids = order().filter((x) => x !== liftedId.value);
                candidateId.value = ids[ids.length - 1] ?? null;
                break;
            }
        }
    }

    /* ── Lift mode: choosing a partner by tap or click ───────────────────── */

    /** A card was activated while something is lifted. */
    function chooseTarget(id: number): void {
        if (liftedId.value === null || liftedId.value === id) {
            reset();

            return;
        }

        candidateId.value = id;
        commit();
    }

    function cancel(): void {
        reset();
    }

    function onWindowKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape' && liftedId.value !== null) {
            reset();
        }
    }

    window.addEventListener('keydown', onWindowKeydown);
    onBeforeUnmount(() =>
        window.removeEventListener('keydown', onWindowKeydown),
    );

    return {
        liftedId,
        candidateId,
        isDragging,
        isActive,
        onHandlePointerdown,
        onHandlePointermove,
        onHandlePointerup,
        onHandleKeydown,
        chooseTarget,
        cancel,
    };
}
