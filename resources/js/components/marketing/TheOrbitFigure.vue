<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import GaugeDial from '@/components/GaugeDial.vue';
import { gaugeColor, gaugeStatusLabel } from '@/lib/gauge';
import { cn } from '@/lib/utils';
import type { GaugeDisplayStatus } from '@/types/gauges';

/*
 * Fig. 01, reimagined — the index as an orbit rather than a stack.
 *
 * The same data model as TheIndexFigure, drawn in three dimensions: a tilted
 * disc of concentric rings (vehicles outermost, then readings, then
 * intervals) turns slowly around a gauge that stands up out of its centre.
 * Readings travel inward along the rings, the two input plates ride the disc
 * as upright cards, and the gauge itself steps through the states the
 * product reports — on interval, due soon, overdue — so the drawing shows
 * the loop the headline describes rather than its parts.
 *
 * How the 3D works: the disc is a CSS plane under perspective, tilted with
 * rotateX and spun with rotateZ driven by --orbit, a registered <angle>
 * custom property. Everything standing on the disc is a billboard: placed on
 * the plane, then counter-rotated by the same --orbit and stood up with the
 * opposite tilt, so it travels round with the disc while always facing the
 * reader. One animated property moves the whole scene, so nothing drifts out
 * of step.
 *
 * Motion is decoration here, so with reduced motion the disc rests at a
 * three-quarter angle, nothing floats, and the gauge holds one reading.
 *
 * Literal hexes appear only through the status helpers; everything else is
 * tokens, so this figure follows the chrome rather than fixing its colours.
 */
const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
    }>(),
    { class: undefined },
);

/* ── Geometry (in the disc's 400-unit square) ────────────────────────────── */

const C = 200;
const RINGS = [
    { r: 184, label: 'Vehicles', dash: undefined },
    { r: 136, label: 'Readings', dash: undefined },
    { r: 88, label: 'Intervals', dash: '6 6' },
];

/** A closed circle as a path, starting at its left, for text to run along. */
function circlePath(r: number): string {
    return `M ${C - r},${C} a ${r},${r} 0 1,1 ${r * 2},0 a ${r},${r} 0 1,1 ${-r * 2},0`;
}

/** Tick marks round the outer ring, one per 6°, heavier every 30°. */
const ticks = Array.from({ length: 60 }, (_, i) => {
    const angle = (i * 6 * Math.PI) / 180;
    const major = i % 5 === 0;
    const r1 = 196;
    const r2 = major ? 206 : 201;

    return {
        key: i,
        major,
        x1: C + r1 * Math.cos(angle),
        y1: C + r1 * Math.sin(angle),
        x2: C + r2 * Math.cos(angle),
        y2: C + r2 * Math.sin(angle),
    };
});

/** A point on a ring, as a percentage of the disc, for placing billboards. */
function onRing(r: number, degrees: number): { left: string; top: string } {
    const angle = (degrees * Math.PI) / 180;

    return {
        left: `${((C + r * Math.cos(angle)) / 400) * 100}%`,
        top: `${((C + r * Math.sin(angle)) / 400) * 100}%`,
    };
}

/* ── What stands on the disc ─────────────────────────────────────────────── */

const plates = [
    {
        key: 'odometer',
        label: 'Odometer',
        value: '15,100 mi · Sep 8',
        at: onRing(172, 200),
        accent: true,
    },
    {
        key: 'interval',
        label: 'Interval',
        value: 'every 5,000 mi',
        at: onRing(172, 20),
        accent: false,
    },
];

/** Three cars on the outer ring, one in each state the gauges report. */
const cars = (
    [
        ['ok', 120],
        ['due', 300],
        ['overdue', 345],
    ] as [GaugeDisplayStatus, number][]
).map(([status, degrees]) => ({
    key: status,
    color: gaugeColor(status),
    at: onRing(184, degrees),
}));

/** Readings travelling round each ring toward the centre, at their own pace. */
const pulses = [
    { r: 184, seconds: 14, delay: 0 },
    { r: 136, seconds: 10, delay: -3 },
    { r: 88, seconds: 7, delay: -1 },
    { r: 184, seconds: 14, delay: -7 },
];

/* ── The gauge steps through the states ──────────────────────────────────── */

const STOPS: { percent: number; status: GaugeDisplayStatus }[] = [
    { percent: 36, status: 'ok' },
    { percent: 71, status: 'ok' },
    { percent: 88, status: 'due' },
    { percent: 112, status: 'overdue' },
];

const percent = ref(71);
const stop = ref(1);
const status = computed(() => STOPS[stop.value].status);
const label = computed(() => gaugeStatusLabel(status.value));

let frame = 0;
let timer = 0;

/*
 * The cards and gauge are drawn at a fixed size for a ~540px figure; on a
 * narrower one they scale down with it, or they would overrun a phone.
 */
const stage = ref<HTMLElement | null>(null);
const fit = ref(1);
let resize: ResizeObserver | null = null;

function easeTo(target: number): void {
    const from = percent.value;
    const start = performance.now();
    const duration = 900;

    cancelAnimationFrame(frame);

    const tick = (now: number) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - t) ** 3;

        percent.value = Math.round(from + (target - from) * eased);

        if (t < 1) {
            frame = requestAnimationFrame(tick);
        }
    };

    frame = requestAnimationFrame(tick);
}

onMounted(() => {
    resize = new ResizeObserver(([entry]) => {
        fit.value = Math.min(1, Math.max(0.55, entry.contentRect.width / 540));
    });

    if (stage.value) {
        resize.observe(stage.value);
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    timer = window.setInterval(() => {
        stop.value = (stop.value + 1) % STOPS.length;
        easeTo(STOPS[stop.value].percent);
    }, 3200);
});

onBeforeUnmount(() => {
    resize?.disconnect();
    window.clearInterval(timer);
    cancelAnimationFrame(frame);
});
</script>

<template>
    <div
        ref="stage"
        :class="cn('orbit-stage relative w-full overflow-hidden', props.class)"
        :style="{ '--fit': fit }"
        role="img"
        aria-label="A tilted disc of rings — vehicles, readings and intervals — turning slowly around a gauge at its centre. Readings travel inward along the rings, an odometer reading and a service interval ride the disc as cards, and the gauge steps through on interval, due soon and overdue."
    >
        <div class="orbit-plane" aria-hidden="true">
            <!-- The disc: rings, ticks and their names, drawn flat on the plane. -->
            <svg viewBox="0 0 400 400" class="absolute inset-0 size-full">
                <defs>
                    <path
                        v-for="ring in RINGS"
                        :id="`orbit-ring-${ring.r}`"
                        :key="ring.r"
                        :d="circlePath(ring.r - 12)"
                    />
                    <radialGradient id="orbit-floor">
                        <stop
                            offset="0%"
                            stop-color="var(--color-accent)"
                            stop-opacity="0.16"
                        />
                        <stop
                            offset="70%"
                            stop-color="var(--color-accent)"
                            stop-opacity="0.05"
                        />
                        <stop
                            offset="100%"
                            stop-color="var(--color-accent)"
                            stop-opacity="0"
                        />
                    </radialGradient>
                </defs>

                <circle :cx="C" :cy="C" r="206" fill="url(#orbit-floor)" />

                <line
                    v-for="tick in ticks"
                    :key="tick.key"
                    :x1="tick.x1"
                    :y1="tick.y1"
                    :x2="tick.x2"
                    :y2="tick.y2"
                    stroke="var(--color-text)"
                    :stroke-opacity="tick.major ? 0.45 : 0.2"
                    :stroke-width="tick.major ? 1.6 : 1"
                />

                <g v-for="ring in RINGS" :key="ring.r">
                    <circle
                        :cx="C"
                        :cy="C"
                        :r="ring.r"
                        fill="none"
                        stroke="var(--color-text)"
                        stroke-opacity="0.32"
                        stroke-width="1.4"
                        :stroke-dasharray="ring.dash"
                    />
                    <text
                        font-size="14"
                        font-weight="700"
                        letter-spacing="4"
                        fill="var(--color-text)"
                        fill-opacity="0.55"
                        style="font-family: var(--font-ui-display)"
                    >
                        <textPath
                            :href="`#orbit-ring-${ring.r}`"
                            startOffset="2%"
                        >
                            {{ ring.label.toUpperCase() }}
                        </textPath>
                    </text>
                </g>

                <!-- The core the readings feed: where the product happens. -->
                <circle
                    :cx="C"
                    :cy="C"
                    r="40"
                    fill="var(--color-accent)"
                    fill-opacity="0.9"
                />
                <circle
                    :cx="C"
                    :cy="C"
                    r="40"
                    fill="none"
                    stroke="var(--surface-card)"
                    stroke-opacity="0.5"
                    stroke-width="1.5"
                />
            </svg>

            <!-- Readings in transit: each a dot riding its ring. -->
            <span
                v-for="(pulse, index) in pulses"
                :key="index"
                class="orbit-pulse"
                :style="{
                    width: `${(pulse.r / 200) * 100}%`,
                    height: `${(pulse.r / 200) * 100}%`,
                    animationDuration: `${pulse.seconds}s`,
                    animationDelay: `${pulse.delay}s`,
                }"
            />

            <!-- Cars on the outer ring, flat on the disc, in their status colours. -->
            <span
                v-for="car in cars"
                :key="car.key"
                class="orbit-car"
                :style="{
                    left: car.at.left,
                    top: car.at.top,
                    background: car.color,
                }"
            />

            <!-- Billboards: they ride the disc but always face the reader. -->
            <div
                v-for="plate in plates"
                :key="plate.key"
                class="orbit-billboard"
                :style="{ left: plate.at.left, top: plate.at.top }"
            >
                <div
                    class="orbit-float bg-card grid gap-0.5 rounded-md border px-3 py-2 whitespace-nowrap shadow-sm"
                    :class="
                        plate.accent
                            ? 'border-accent'
                            : 'border-(--hairline-soft)'
                    "
                >
                    <span class="ii-eyebrow">{{ plate.label }}</span>
                    <span
                        class="font-display text-[15px]/tight font-semibold tabular-nums"
                    >
                        {{ plate.value }}
                    </span>
                </div>
            </div>

            <div
                class="orbit-billboard orbit-gauge grid justify-items-center"
                style="left: 50%; top: 50%"
            >
                <div
                    class="orbit-float orbit-float-slow bg-card grid w-[150px] justify-items-center gap-0 rounded-lg border border-(--hairline-soft) px-3 pt-2 pb-2.5 shadow-md"
                >
                    <GaugeDial
                        class="h-[96px] w-full"
                        :percent="percent"
                        :status="status"
                    />
                    <span
                        class="font-display text-[26px]/none font-bold tabular-nums"
                    >
                        {{ percent }}%
                    </span>
                    <span
                        class="mt-1 text-[11px] font-semibold"
                        :style="{ color: gaugeColor(status) }"
                    >
                        {{ label }}
                    </span>
                </div>
                <!-- The stem: the gauge is fed from the core below it. -->
                <span
                    class="block h-14 w-0.5 bg-linear-to-b from-(--color-accent) to-(--color-accent)/0"
                    aria-hidden="true"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * The one animated value: how far round the disc has turned. Registered so
 * it interpolates as an angle, and inherited so every billboard can cancel
 * it out and keep facing the reader.
 */
@property --orbit {
    syntax: '<angle>';
    inherits: true;
    initial-value: -28deg;
}

.orbit-stage {
    aspect-ratio: 760 / 646;
    perspective: 1100px;
    perspective-origin: 50% 10%;
}

.orbit-plane {
    --tilt: 58deg;
    position: absolute;
    left: 9%;
    top: 19%;
    width: 82%;
    aspect-ratio: 1;
    transform-style: preserve-3d;
    transform: rotateX(var(--tilt)) rotateZ(var(--orbit));
}

.orbit-pulse {
    position: absolute;
    left: 50%;
    top: 50%;
    translate: -50% -50%;
    border-radius: 9999px;
    animation: orbit-pulse linear infinite;
    pointer-events: none;
}

.orbit-pulse::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    width: 9px;
    height: 9px;
    translate: -50% -50%;
    border-radius: 9999px;
    background: var(--color-accent);
    box-shadow: 0 0 0 4px
        color-mix(in oklab, var(--color-accent) 22%, transparent);
}

.orbit-car {
    position: absolute;
    width: 3.4%;
    aspect-ratio: 1;
    translate: -50% -50%;
    rotate: 45deg;
    border-radius: 3px;
    outline: 3px solid var(--surface-card);
}

/* Placed on the plane, then turned back and stood up to face the reader. */
.orbit-billboard {
    position: absolute;
    /* zoom, not scale: it resizes the box itself, so the billboard's own
       transform still centres it on its spot. scale would apply first and
       shift it sideways. */
    zoom: var(--fit, 1);
    transform-style: preserve-3d;
    transform: translate(-50%, -100%) rotateZ(calc(var(--orbit) * -1))
        rotateX(calc(var(--tilt) * -1));
    transform-origin: 50% 100%;
}

.orbit-gauge {
    /* Raised on a stem above the core, so the cards pass beneath it. */
    transform: translate(-50%, -100%) rotateZ(calc(var(--orbit) * -1))
        rotateX(calc(var(--tilt) * -1));
}

@media (prefers-reduced-motion: no-preference) {
    .orbit-plane {
        animation: orbit-turn 48s linear infinite;
    }

    .orbit-float {
        animation: orbit-float 4.8s ease-in-out infinite alternate;
    }

    .orbit-float-slow {
        animation-duration: 6.2s;
    }
}

@media (prefers-reduced-motion: reduce) {
    .orbit-pulse {
        animation: none;
        rotate: 30deg;
    }
}

@keyframes orbit-turn {
    from {
        --orbit: -28deg;
    }
    to {
        --orbit: 332deg;
    }
}

/* Inward: each reading rides its ring the opposite way to the disc's turn. */
@keyframes orbit-pulse {
    from {
        rotate: 360deg;
    }
    to {
        rotate: 0deg;
    }
}

@keyframes orbit-float {
    from {
        translate: 0 0;
    }
    to {
        translate: 0 -8px;
    }
}
</style>
