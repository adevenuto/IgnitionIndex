<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { gaugeColor } from '@/lib/gauge';
import { cn } from '@/lib/utils';
import type { GaugeDisplayStatus } from '@/types/gauges';

/*
 * A service gauge: a 240° analogue dial that counts UP toward due.
 *
 * Geometry is transcribed from the design drop's artboard, not invented. The
 * arc length is 44 × 240° expressed in radians — 44 × 4.18879 = 184.31 — so it
 * is a derived constant rather than a magic number.
 *
 * IMPORTANT: `percent` is deliberately UNCAPPED. 112% is a real, meaningful
 * reading and the number must print as 112. Only the arc and the needle clamp,
 * because a dial cannot sweep past its own end stop.
 *
 * ALSO IMPORTANT: the percentage is rendered by the CALLER as a sibling
 * element, never as an SVG <text> child. Two reasons: the mouth between the hub
 * and the arc is too shallow to hold type at readout size, and a Vue `{{ }}`
 * interpolation inside <text> compiles to an SVG-namespaced <span>, which
 * paints nothing at all.
 *
 * `large` and `mini` are different objects rather than one scaled drawing: the
 * mini uses a taller viewBox, a much heavier stroke and NO tick marks, because
 * ticks at 74px wide would read as mud.
 */
const props = withDefaults(
    defineProps<{
        percent: number;
        status: GaugeDisplayStatus;
        variant?: 'large' | 'mini';
        class?: HTMLAttributes['class'];
        /**
         * Sweep the needle on a loop to draw the eye. Opt-in, and honoured only
         * on an uncalibrated dial: a needle that wanders on a gauge showing a
         * real reading would be lying about the reading.
         */
        sweep?: boolean;
    }>(),
    { variant: 'large', class: undefined, sweep: false },
);

/** 44 × (240° in radians) = 44 × 4.18879. */
const ARC_LENGTH = 184.3;
const SWEEP = 240;
const START = -120;

const uncalibrated = computed(() => props.status === 'unknown');

const fraction = computed(
    () => Math.min(Math.max(props.percent, 0), 100) / 100,
);

const dash = computed(() => `${(fraction.value * ARC_LENGTH).toFixed(1)} 400`);

// An uncalibrated dial rests at the stop: there is no reading to point at.
const needle = computed(() => {
    const angle = uncalibrated.value ? START : START + fraction.value * SWEEP;

    return `rotate(${angle.toFixed(1)} 60 62)`;
});

// Colour follows the backend's status rather than re-deriving the threshold
// here; see lib/gauge.ts.
const color = computed(() => gaugeColor(props.status));

const ticks = computed(() =>
    Array.from({ length: 11 }, (_, index) => START + index * 24),
);

const isMini = computed(() => props.variant === 'mini');

const sweeping = computed(() => props.sweep && uncalibrated.value);
</script>

<template>
    <svg
        :viewBox="isMini ? '0 0 120 96' : '0 0 120 92'"
        :class="cn('block h-full w-full', props.class)"
        role="img"
        :aria-label="
            uncalibrated
                ? 'Interval not set'
                : `${Math.round(percent)} percent of interval used`
        "
    >
        <path
            d="M21.9 84 A44 44 0 1 1 98.1 84"
            fill="none"
            stroke="var(--gauge-track)"
            :stroke-width="isMini ? 9 : 6"
        />
        <path
            v-if="!uncalibrated"
            d="M21.9 84 A44 44 0 1 1 98.1 84"
            fill="none"
            :stroke="color"
            :stroke-width="isMini ? 9 : 6"
            :stroke-dasharray="dash"
        />

        <g v-if="!isMini" stroke="var(--gauge-tick)" stroke-width="0.8">
            <line
                v-for="tick in ticks"
                :key="tick"
                x1="60"
                y1="9"
                x2="60"
                y2="15"
                :transform="`rotate(${tick} 60 62)`"
            />
        </g>

        <!--
            The animation sets `transform` in CSS, which replaces this attribute
            rather than composing with it — hence the keyframes carrying the
            resting angle themselves.
        -->
        <g
            :transform="needle"
            :class="
                sweeping && 'gauge-needle-pivot motion-safe:animate-gauge-sweep'
            "
        >
            <path
                :d="
                    isMini
                        ? 'M60 62 L56.5 36 L60 30 L63.5 36 Z'
                        : 'M60 62 L57.2 34 L60 27 L62.8 34 Z'
                "
                fill="var(--color-text)"
            />
        </g>

        <circle
            cx="60"
            cy="62"
            :r="isMini ? 5 : 4"
            fill="var(--color-neutral-100)"
            stroke="var(--color-text)"
            :stroke-width="isMini ? 1.6 : 1.2"
        />
    </svg>
</template>
