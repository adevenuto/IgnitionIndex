<script setup lang="ts">
import { Grip } from '@lucide/vue';
import type { Gauge } from '@/types/gauges';

/*
 * The grip that moves a gauge.
 *
 * One component for both regions so the icon, the hit area, `touch-none` and the
 * toggle semantics are stated once rather than twice and drifting.
 *
 * Always visible, never hover-only: touch has no hover, and a control you cannot
 * discover on the device where you most need it may as well not exist. Muted
 * instead — nineteen grips is real weight on a wall this size.
 */
defineProps<{
    gauge: Gauge;
    lifted: boolean;
}>();

defineEmits<{
    pointerdown: [event: PointerEvent];
    pointermove: [event: PointerEvent];
    pointerup: [event: PointerEvent];
    keydown: [event: KeyboardEvent];
}>();
</script>

<template>
    <!--
        aria-pressed, because this really is a toggle: pressed means "lifted,
        now choose what to trade it with". A plain button would announce
        nothing about the mode it just entered.

        touch-none is on the grip ALONE. On the card it would kill page
        scrolling for the whole wall; here it costs nothing and is what stops a
        touch-drag scrolling the page instead.

        The ::after pad grows the touch target well past the 24px the eyebrow
        row can afford to show, without the button's box pushing that row taller.
    -->
    <button
        type="button"
        :data-gauge-handle="gauge.id"
        :aria-pressed="lifted"
        :aria-label="
            lifted
                ? `${gauge.name} lifted. Choose a gauge to swap it with, or press escape.`
                : `Move ${gauge.name}. Press space to lift it.`
        "
        class="ease-standard relative z-20 flex size-6 shrink-0 cursor-grab touch-none items-center justify-center text-(--color-neutral-500) transition-colors duration-[var(--dur-fast)] after:absolute after:-inset-2 after:content-[''] hover:text-(--color-text) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) active:cursor-grabbing"
        :class="lifted && 'cursor-grabbing text-(--color-accent)'"
        @pointerdown="$emit('pointerdown', $event)"
        @pointermove="$emit('pointermove', $event)"
        @pointerup="$emit('pointerup', $event)"
        @pointercancel="$emit('pointerup', $event)"
        @keydown="$emit('keydown', $event)"
    >
        <Grip class="size-4" :stroke-width="1.5" aria-hidden="true" />
    </button>
</template>
