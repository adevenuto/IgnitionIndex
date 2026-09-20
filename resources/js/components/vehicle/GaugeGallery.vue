<script setup lang="ts">
import { Pin } from '@lucide/vue';
import { computed, nextTick, toRef, watch } from 'vue';
import { toast } from 'vue-sonner';
import GaugeDial from '@/components/GaugeDial.vue';
import GaugeLegend from '@/components/GaugeLegend.vue';
import GaugeDragHandle from '@/components/vehicle/GaugeDragHandle.vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { useAnnouncer } from '@/composables/useAnnouncer';
import { useCardLanding } from '@/composables/useCardLanding';
import { useCardSwap } from '@/composables/useCardSwap';
import { useGaugeOrder } from '@/composables/useGaugeOrder';
import {
    gaugeColor,
    gaugeInk,
    gaugeReadout,
    gaugeStatusLabel,
} from '@/lib/gauge';
import type { Gauge } from '@/types/gauges';

/*
 * The gauge wall: three large dials, then everything else as a two-column list.
 *
 * §6 forbids reordering overdue items to the front — the order is fixed so a
 * gauge does not move under the pointer when its status changes. What §6 does
 * allow, and what this now exposes, is the user choosing that order and which
 * three are pinned, by dragging one gauge onto another to trade their places.
 *
 * Swapping, never inserting: the pinned row is exactly three by construction
 * and nothing the user did not touch ever moves.
 */
const props = defineProps<{ gauges: Gauge[]; vehicleId: number }>();

defineEmits<{ select: [gauge: Gauge] }>();

const { gauges, order, swap } = useGaugeOrder(
    toRef(props, 'gauges'),
    props.vehicleId,
);
const { message, announce } = useAnnouncer();
const { land } = useCardLanding('data-gauge-id');

const pinned = computed(() => gauges.value.filter((gauge) => gauge.is_pinned));
const rest = computed(() => gauges.value.filter((gauge) => !gauge.is_pinned));

/*
 * The 1px grid gaps ARE the rules, which is why each cell paints an opaque
 * background over a divider-coloured grid. It only works on an even count: an
 * odd one leaves the last row half-ruled, so a filler cell squares it off.
 *
 * Swapping cannot change this either way — the two regions keep their counts —
 * but the filler carries no data-gauge-id, so it can never be a drop target.
 */
const needsFiller = computed(() => rest.value.length % 2 === 1);

const byId = computed(
    () => new Map(gauges.value.map((gauge) => [gauge.id, gauge])),
);

function nameOf(id: number | null): string {
    return id === null ? '' : (byId.value.get(id)?.name ?? '');
}

/**
 * Put focus back on a gauge's handle after the wall re-renders.
 *
 * The two regions are separate v-for lists, so a gauge crossing the boundary is
 * unmounted and remounted and the focused handle goes with it — landing focus on
 * <body> and stranding a keyboard user at the top of the page.
 *
 * Only when focus was actually dropped: the server response re-renders a second
 * time, by which point the user may have tabbed elsewhere, and yanking them back
 * would be worse than the problem.
 */
function restoreFocus(id: number): void {
    const active = document.activeElement;

    if (active !== null && active !== document.body) {
        return;
    }

    document.querySelector<HTMLElement>(`[data-gauge-handle="${id}"]`)?.focus();
}

/** Where a gauge reads on the wall — never the sparse database position. */
function placeOf(id: number | null): string {
    const at = id === null ? -1 : order.value.indexOf(id);

    return at === -1 ? '' : `${at + 1} of ${order.value.length}`;
}

const {
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
} = useCardSwap({
    order: () => order.value,
    attribute: 'data-gauge-id',
    onSwap: async (sourceId, targetId) => {
        const moved = nameOf(sourceId);
        const partner = nameOf(targetId);
        const wasPinned = byId.value.get(sourceId)?.is_pinned ?? false;

        let saved: Promise<void> = Promise.resolve();

        // Measure, swap, then play both cards to their new places.
        await land([sourceId, targetId], () => {
            saved = swap(sourceId, targetId);
        });

        restoreFocus(sourceId);

        /*
         * Only when the pinned set changed. A move inside one region already
         * announced itself by happening under the user's finger; promoting or
         * demoting a gauge is the consequential half, and it is the one thing
         * about a swap you might not notice you did.
         */
        if (wasPinned !== (byId.value.get(sourceId)?.is_pinned ?? false)) {
            const nowPinned = byId.value.get(sourceId)?.is_pinned ?? false;

            toast.success(
                nowPinned
                    ? `${moved} pinned. ${partner} moved down.`
                    : `${moved} unpinned. ${partner} is now pinned.`,
            );
        }

        // Again once the server has answered: that response re-renders the wall
        // a second time and drops focus all over again.
        void saved.then(async () => {
            await nextTick();
            restoreFocus(sourceId);
        });

        void announce(
            `Swapped. ${moved} is now ${placeOf(sourceId)}. ${partner} is now ${placeOf(targetId)}.`,
        );
    },
});

watch(liftedId, (id, was) => {
    if (id !== null) {
        void announce(
            `${nameOf(id)} lifted, ${placeOf(id)}. Arrow keys choose a gauge to swap with, enter to swap, escape to cancel.`,
        );
    } else if (was !== null && !isDragging.value) {
        // Only a real cancel — a commit clears this too, and says its own piece.
        void announce(`Cancelled. ${nameOf(was)} stays at ${placeOf(was)}.`);
    }
});

// Short on purpose: repeated at every keypress, a long string is exhausting.
watch(candidateId, (id) => {
    if (id !== null && isActive.value) {
        void announce(`${nameOf(id)}, ${placeOf(id)}.`);
    }
});

/** Clicking a card edits it — unless something is lifted, when it is the target. */
function activate(gauge: Gauge): void {
    if (isActive.value) {
        chooseTarget(gauge.id);
    }
}

function cardLabel(gauge: Gauge): string {
    if (isActive.value) {
        return liftedId.value === gauge.id
            ? `${gauge.name}, lifted. Activate to cancel.`
            : `Swap ${nameOf(liftedId.value)} with ${gauge.name}`;
    }

    return `${gauge.name}, ${gauge.label}.${gauge.is_pinned ? ' Pinned.' : ''} Adjust.`;
}

/**
 * Picked up, or about to be traded with.
 *
 * Both treatments live in app.css as ii-card-* — they are design-system states
 * built from the hairline, the fill and the corner marks, since §4 leaves no
 * shadow, scale or tilt to reach for.
 */
function cardState(gauge: Gauge): string {
    if (liftedId.value === gauge.id) {
        return 'ii-card-lifted';
    }

    return candidateId.value === gauge.id ? 'ii-card-target' : '';
}
</script>

<template>
    <section
        class="flex flex-col gap-(--space-4)"
        :class="isDragging && 'cursor-grabbing select-none'"
    >
        <div
            class="flex flex-col gap-(--space-3) sm:flex-row sm:items-end sm:justify-between sm:gap-(--space-6)"
        >
            <div>
                <h2 class="font-display text-h3">Gauges</h2>
                <p
                    v-if="isActive"
                    class="text-[13px] text-(--color-accent-700)"
                    data-test="swap-hint"
                >
                    Choose a gauge to swap
                    <span class="font-semibold">{{ nameOf(liftedId) }}</span>
                    with, or press escape.
                </p>
                <p v-else class="text-[13px] text-(--color-neutral-700)">
                    Percent of interval elapsed. Drag a gauge onto another to
                    trade their places.
                </p>
            </div>

            <GaugeLegend />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!--
                A div, not a button. The card needs a drag handle inside it and
                a button within a button is invalid HTML, so the whole-card click
                moves to a stretched overlay below and the handle sits beside it
                rather than within it.
            -->
            <BlueprintFrame
                v-for="gauge in pinned"
                :key="gauge.id"
                :data-gauge-id="gauge.id"
                interactive
                class="ease-standard relative flex flex-col items-center px-[18px] pt-[18px] pb-4 text-center transition-opacity duration-[var(--dur-fast)] focus-within:border-(--color-accent)"
                :class="cardState(gauge)"
            >
                <span
                    class="ii-eyebrow flex w-full items-center justify-between gap-2 tracking-[0.12em]"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <GaugeDragHandle
                            :gauge="gauge"
                            :lifted="liftedId === gauge.id"
                            @pointerdown="onHandlePointerdown($event, gauge.id)"
                            @pointermove="onHandlePointermove"
                            @pointerup="onHandlePointerup"
                            @keydown="onHandleKeydown($event, gauge.id)"
                        />
                        <!--
                            The pin replaces the word "Pinned". It inherits
                            ii-eyebrow's colour through currentColor; the meaning
                            it used to carry moves into the overlay's aria-label,
                            not onto a decorative icon.
                        -->
                        <Pin
                            class="size-3 shrink-0"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        <span v-if="gauge.basis" class="truncate">
                            {{ gauge.basis }}
                        </span>
                    </span>
                    <span
                        class="ii-tag ii-tag-outline shrink-0 text-[10px] font-bold tracking-[0.12em] uppercase"
                        :style="{
                            '--ii-tag-border': gaugeColor(gauge.display_status),
                            '--ii-tag-ink': gaugeInk(gauge.display_status),
                        }"
                    >
                        {{ gaugeStatusLabel(gauge.display_status) }}
                    </span>
                </span>

                <GaugeDial
                    class="mt-1 h-[230px] w-full max-w-[300px]"
                    :percent="gauge.percent"
                    :status="gauge.display_status"
                />

                <!-- Sibling, never an SVG <text>; see GaugeDial. -->
                <p class="font-display text-readout">
                    {{ gaugeReadout(gauge.display_status, gauge.percent) }}
                </p>
                <p class="font-display text-[24px]/[1.1] font-semibold">
                    {{ gauge.name }}
                </p>
                <p class="text-[13px] text-(--color-neutral-700)">
                    {{ gauge.label }}
                </p>

                <button
                    type="button"
                    class="absolute inset-0 z-10 focus-visible:outline-none"
                    :aria-label="cardLabel(gauge)"
                    @click="isActive ? activate(gauge) : $emit('select', gauge)"
                />
            </BlueprintFrame>
        </div>

        <BlueprintFrame v-if="rest.length" class="gap-0 p-0">
            <div class="grid gap-px bg-(--color-divider) sm:grid-cols-2">
                <div
                    v-for="gauge in rest"
                    :key="gauge.id"
                    :data-gauge-id="gauge.id"
                    class="ease-standard hover:bg-accent-100 relative flex items-center gap-3 border border-transparent bg-(--color-neutral-100) px-[18px] py-3.5 text-left transition-colors duration-[var(--dur-fast)] focus-within:border-(--color-accent)"
                    :class="cardState(gauge)"
                >
                    <GaugeDragHandle
                        :gauge="gauge"
                        :lifted="liftedId === gauge.id"
                        @pointerdown="onHandlePointerdown($event, gauge.id)"
                        @pointermove="onHandlePointermove"
                        @pointerup="onHandlePointerup"
                        @keydown="onHandleKeydown($event, gauge.id)"
                    />

                    <GaugeDial
                        class="h-[59px] w-[74px] flex-none"
                        variant="mini"
                        :percent="gauge.percent"
                        :status="gauge.display_status"
                    />

                    <span class="min-w-0 flex-1">
                        <span
                            class="font-display block truncate text-[20px]/[1.15] font-semibold"
                        >
                            {{ gauge.name }}
                        </span>
                        <span
                            class="block truncate text-[12px] text-(--color-neutral-700)"
                        >
                            {{ gauge.label
                            }}<template v-if="gauge.basis">
                                · {{ gauge.basis }}</template
                            >
                        </span>
                    </span>

                    <span class="flex-none text-right">
                        <span
                            class="font-display block text-[26px]/none font-semibold"
                            :style="{ color: gaugeColor(gauge.display_status) }"
                        >
                            {{
                                gaugeReadout(
                                    gauge.display_status,
                                    gauge.percent,
                                )
                            }}
                        </span>
                        <span
                            class="block text-[10px] font-bold tracking-[0.12em] uppercase"
                            :style="{ color: gaugeInk(gauge.display_status) }"
                        >
                            {{ gaugeStatusLabel(gauge.display_status) }}
                        </span>
                    </span>

                    <button
                        type="button"
                        class="absolute inset-0 z-10 focus-visible:outline-none"
                        :aria-label="cardLabel(gauge)"
                        @click="
                            isActive ? activate(gauge) : $emit('select', gauge)
                        "
                    />
                </div>

                <!-- Squares off an odd count so the last row stays ruled. -->
                <div
                    v-if="needsFiller"
                    class="bg-(--color-neutral-100)"
                    aria-hidden="true"
                />
            </div>
        </BlueprintFrame>

        <!-- A drag rearranges the wall silently otherwise. -->
        <p class="sr-only" role="status" aria-live="polite">{{ message }}</p>

        <button v-if="isActive" class="sr-only" type="button" @click="cancel">
            Cancel swapping
        </button>
    </section>
</template>
