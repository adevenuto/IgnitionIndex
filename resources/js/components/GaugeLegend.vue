<script setup lang="ts">
/*
 * The three states a gauge is ever drawn in, and their colours.
 *
 * Shared because it is now read in two places that must agree: above the gauge
 * wall, and under Fig. 01 on the marketing page. A second hand-written copy is
 * how the homepage ends up promising a palette the product no longer uses.
 *
 * These are the same three GaugeStatus::display() collapses to — see
 * lib/gauge.ts, which maps status to colour for the dials themselves. Due and
 * Overdue stay separate in the backend because they drive different reminder
 * cadences; they merge at the presentation boundary, which is here.
 */
const legend = [
    { label: 'On interval', color: 'var(--color-accent)' },
    { label: 'Due soon', color: 'var(--status-due)' },
    { label: 'Overdue', color: 'var(--status-overdue)' },
];
</script>

<template>
    <ul class="flex gap-[18px] text-[12px] text-(--color-neutral-700)">
        <li
            v-for="entry in legend"
            :key="entry.label"
            class="flex items-center gap-(--space-2)"
        >
            <!--
                Inline style rather than a class: the colour is data here, and
                these squares carry no hover or state that an inline style
                could quietly override.
            -->
            <span
                class="size-2 flex-none"
                :style="{ background: entry.color }"
                aria-hidden="true"
            />
            {{ entry.label }}
        </li>
    </ul>
</template>
