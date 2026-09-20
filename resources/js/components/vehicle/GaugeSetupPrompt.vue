<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import GaugeDial from '@/components/GaugeDial.vue';
import { BlueprintFrame } from '@/components/ui/blueprint';

/*
 * Shown on a car whose gauges are all still unset.
 *
 * A seeded schedule opens as a wall of grey rings reading "—", which is honest
 * — nothing can come due until we know when it was last done — but on its own
 * it looks like the product failed to load rather than like it is waiting for
 * something. This says which, and what to do about it.
 *
 * Carries the amber of §2's "due soon" rather than a colour invented for it.
 * That is the right register: no gauge here is overdue, because none of them is
 * running — this is the state where the product cannot tell you anything yet,
 * which is a warning about the setup rather than about the car. Rust is
 * reserved for a real overdue service.
 *
 * It disappears the moment any single gauge is set: the message is "make a
 * start", not "finish", and a banner that lingers after the first one would
 * read as nagging.
 */
defineProps<{ count: number }>();
</script>

<template>
    <!--
        ii-awaiting-setup overrides ii-blueprint's divider border and paper
        fill, and is shared with the garage card. The corner marks stay — §4
        says a framed element never loses them.
    -->
    <BlueprintFrame
        class="ii-awaiting-setup flex flex-col items-start gap-(--space-6) p-(--space-8) sm:flex-row sm:items-center"
    >
        <!--
            The dial rather than a generic icon: it is the exact thing the
            reader is looking at further down the page, in the exact state it is
            in, which lands quicker than a sentence explaining what grey means.
            It sweeps to catch the eye, and rests at the stop between sweeps.
        -->
        <GaugeDial
            class="h-[72px] w-24 flex-none"
            variant="mini"
            :percent="0"
            status="unknown"
            sweep
            aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
            <h2
                class="font-display text-h3 flex items-center gap-(--space-2) text-(--status-due-ink)"
            >
                <TriangleAlert class="size-5 shrink-0" :stroke-width="1.75" />
                Your gauges aren't set yet
            </h2>
            <p class="text-body mt-(--space-2) text-(--color-neutral-700)">
                All {{ count }} are waiting on a starting point, so nothing here
                can tell you what's due. Tap any gauge and say roughly when it
                was last done — it starts counting down from there, by mileage
                and time, whichever comes first.
            </p>
        </div>
    </BlueprintFrame>
</template>
