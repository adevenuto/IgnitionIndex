import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';
import { toast } from 'vue-sonner';
import { update as saveGaugeOrder } from '@/actions/App/Http/Controllers/VehicleGaugeOrderController';
import type { Gauge } from '@/types/gauges';

/**
 * The gauge wall's order, shown before the server has agreed to it.
 *
 * ── Why swaps are held as a list rather than a snapshot ──
 *
 * The obvious optimistic pattern — copy the array, mutate, restore the copy on
 * failure — breaks here. The wall is not the only thing writing to `gauges`:
 * saving in the edit dialog redirects `back()`, which re-pushes the prop with
 * fresh percentages, and that arrives mid-flight and wipes the pending move.
 *
 * So local order is DERIVED: server truth, with the not-yet-confirmed swaps
 * replayed over it. Any prop push recomputes from the new truth and the swaps
 * still outstanding, which makes unrelated updates, out-of-order responses and
 * rollback all the same code path instead of three special cases.
 */
export function useGaugeOrder(source: Ref<Gauge[]>, vehicleId: number) {
    /**
     * Swaps sent but not yet confirmed.
     *
     * Deliberately NOT a ref. `ref([])` deep-wraps its contents, so reading an
     * entry back hands you a reactive proxy of it, and removing one by identity
     * silently never matches — the entry stays pending forever and every later
     * prop push replays it over server data that already includes it, undoing
     * the very move it was meant to hold. Nothing here needs to be reactive:
     * recompute() is always called explicitly.
     */
    let pending: [number, number][] = [];

    /**
     * Replay swaps over a list.
     *
     * Exchanges the array slot AND `is_pinned`/`position`. The flag matters: the
     * two regions are `filter(is_pinned)` and `filter(!is_pinned)`, so without
     * trading it a cross-region drag would appear to move the card only within
     * the region it started in. The server does exactly this exchange.
     */
    function applySwaps(list: Gauge[], swaps: [number, number][]): Gauge[] {
        const next = [...list];

        for (const [a, b] of swaps) {
            const i = next.findIndex((gauge) => gauge.id === a);
            const j = next.findIndex((gauge) => gauge.id === b);

            if (i === -1 || j === -1) {
                continue;
            }

            const [left, right] = [next[i], next[j]];

            if (left === undefined || right === undefined) {
                continue;
            }

            // New objects: the props are not ours to mutate.
            next[i] = {
                ...left,
                is_pinned: right.is_pinned,
                position: right.position,
            };
            next[j] = {
                ...right,
                is_pinned: left.is_pinned,
                position: left.position,
            };
            [next[i], next[j]] = [next[j], next[i]];
        }

        return next;
    }

    const gauges = ref<Gauge[]>(applySwaps(source.value, pending));

    function recompute(): void {
        gauges.value = applySwaps(source.value, pending);
    }

    watch(source, recompute, { deep: false });

    /** Display order across both regions — pinned first, then the rest. */
    const order = computed(() => [
        ...gauges.value
            .filter((gauge) => gauge.is_pinned)
            .map((gauge) => gauge.id),
        ...gauges.value
            .filter((gauge) => !gauge.is_pinned)
            .map((gauge) => gauge.id),
    ]);

    /*
     * One gesture at a time. Inertia cancels an in-flight visit when the next
     * one starts, so two quick drags could leave the first swap painted on
     * screen and never saved.
     */
    let chain: Promise<void> = Promise.resolve();

    function swap(sourceId: number, targetId: number): Promise<void> {
        const entry: [number, number] = [sourceId, targetId];

        pending = [...pending, entry];
        recompute();

        chain = chain.then(
            () =>
                new Promise<void>((resolve) => {
                    router.patch(
                        saveGaugeOrder.url(vehicleId),
                        { source_id: sourceId, target_id: targetId },
                        {
                            // A wall that jumps to the top after every move is
                            // unusable; the state we keep here must survive too.
                            preserveScroll: true,
                            preserveState: true,
                            onFinish: () => {
                                pending = pending.filter((s) => s !== entry);
                                recompute();
                                resolve();
                            },
                            onError: () => {
                                // No fresh prop is coming, so dropping the entry
                                // above is the rollback.
                                toast.error('Could not move that gauge.');
                            },
                        },
                    );
                }),
        );

        return chain;
    }

    return { gauges, order, swap };
}
