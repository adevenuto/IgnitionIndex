<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/*
 * Fig. 01 — the index. An exploded isometric of the data model, for the
 * marketing hero: Vehicles + Readings + Intervals feed Gauges, with Fleet
 * drawn as an addition on top rather than a step in the stack.
 *
 * Geometry is derived, not transcribed. Every point goes through project(),
 * a 2:1 isometric projection, so the slab pitch and plate size are two
 * constants rather than ~70 hand-tuned polygon points. Design doc §11 has the
 * drawing's rules.
 *
 * ── Two deliberate departures from AGENTS.md, both load-bearing ──
 *
 * 1. THE COLOURS ARE LITERAL HEXES, NOT TOKENS. This is one self-contained
 *    illustration whose fills are compositional (five tonal steps that read as
 *    stacked planes under one light), not semantic. Wiring them to
 *    var(--color-*) would mean a token tweak silently flattens the drawing's
 *    depth. The four values used are the system's own — #5980a6 accent,
 *    #16233a shell navy, #1d1f20 ink, #f2f2f3 paper — plus derived tints of
 *    them. Do not "fix" this to tokens.
 *
 * 2. FONT SIZES ARE AUTHORED IN VIEWBOX UNITS, ~2× their rendered size. The
 *    760-unit viewBox renders into a ~378px hero column, so an authored 21
 *    lands at ~10px on screen — the system's eyebrow floor. Nothing here may
 *    go below 21 authored units. If the drawing gets crowded, cut annotations;
 *    do not shrink them.
 */

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
    }>(),
    { class: undefined },
);

/* ── Projection ──────────────────────────────────────────────────────────── */

/** Plate edge in viewBox units. */
const SCALE = 158;
/** Slab thickness. */
const THICKNESS = 36;
/** Vertical gap between slab tops — what makes it an exploded view. */
const PITCH = 62;
/** Height of the lowest slab above the ground plane. */
const BASE = 78;
/** Ground plane half-extent, in plate units. */
const GROUND = 2.15;

const ORIGIN = { x: 392, y: 392 };
const COS_30 = 0.866;

/**
 * 2:1 isometric. x and y run along the plate; z is height in viewBox units
 * (already scaled, so slab thicknesses read the same at any plate size).
 */
function project(x: number, y: number, z = 0): [number, number] {
    return [
        ORIGIN.x + COS_30 * SCALE * (x - y),
        ORIGIN.y + 0.5 * SCALE * (x + y) - z,
    ];
}

const fmt = ([x, y]: [number, number]) => `${x.toFixed(1)},${y.toFixed(1)}`;
const polygon = (points: [number, number][]) => points.map(fmt).join(' ');

/* ── Palette ─────────────────────────────────────────────────────────────── */

const PAPER = '#f2f2f3';
const INK = '#1d1f20';
const ACCENT = '#5980a6';
const NAVY = '#16233a';

type LayerKind = 'wire' | 'solid' | 'outline';

/** Three faces plus edge and ink, per layer treatment. */
const FACES: Record<
    LayerKind,
    { top: string; right: string; left: string; edge: string; ink: string }
> = {
    // A line drawing: near-transparent fills, dark hairlines.
    wire: {
        top: 'rgba(89,128,166,0.07)',
        right: 'rgba(29,31,32,0.045)',
        left: 'rgba(29,31,32,0.075)',
        edge: 'rgba(29,31,32,0.42)',
        ink: INK,
    },
    // The one solid object on the board — where the product happens.
    solid: {
        top: '#6d92b6',
        right: ACCENT,
        left: '#496c8e',
        edge: 'rgba(242,242,243,0.34)',
        ink: PAPER,
    },
    // Dashed navy: present but not included.
    outline: {
        top: 'rgba(22,35,58,0.08)',
        right: 'rgba(22,35,58,0.13)',
        left: 'rgba(22,35,58,0.2)',
        edge: NAVY,
        ink: NAVY,
    },
};

const LAYERS: { name: string; kind: LayerKind }[] = [
    { name: 'Vehicles', kind: 'wire' },
    { name: 'Readings', kind: 'wire' },
    { name: 'Intervals', kind: 'wire' },
    { name: 'Gauges', kind: 'solid' },
    { name: 'Fleet', kind: 'outline' },
];

/* ── Vehicles on the plane ───────────────────────────────────────────────── */

/*
 * Three vehicles on the ground, one in each state the gauges report.
 *
 * 407abf6 took the earlier markers off, and was right to: their chips were
 * IDENTITY colours — the one place §7 allows a non-system colour — which read
 * as noise on an otherwise monochrome drawing, and removing the colour left
 * four unexplained blank diamonds behind.
 *
 * These are the §2 status colours instead, and the legend under the figure
 * names them. That is the difference: the plane now says something a reader can
 * decode, and it is the same three states the product actually draws, so the
 * drawing and the gauge wall cannot disagree.
 *
 * Literal hexes, per the note at the top of this file — they are the system's
 * own values (#c08a2e due, #b4442f overdue) and must not be swapped for
 * var(--status-*), which would flatten the drawing's depth if a token moved.
 */
const TILE = 0.17;
const CHIP = 0.095;

const vehicles = computed(() =>
    (
        [
            // Ordered back to front, so severity increases toward the reader.
            [ACCENT, 1.58, 1.02],
            ['#c08a2e', 1.98, 1.36],
            ['#b4442f', 1.46, 1.58],
        ] as [string, number, number][]
    ).map(([color, x, y]) => ({
        key: `${x}-${y}`,
        color,
        plate: polygon([
            project(x - TILE, y - TILE),
            project(x + TILE, y - TILE),
            project(x + TILE, y + TILE),
            project(x - TILE, y + TILE),
        ]),
        // Lifted one unit so the chip cannot z-fight the plate it sits on.
        chip: polygon([
            project(x - CHIP, y - CHIP, 1),
            project(x + CHIP, y - CHIP, 1),
            project(x + CHIP, y + CHIP, 1),
            project(x - CHIP, y + CHIP, 1),
        ]),
    })),
);

/* ── Ground plane ────────────────────────────────────────────────────────── */

const ground = computed(() =>
    polygon([
        project(-GROUND, -GROUND),
        project(GROUND, -GROUND),
        project(GROUND, GROUND),
        project(-GROUND, GROUND),
    ]),
);

const groundGrid = computed(() => {
    const lines: string[] = [];

    for (let i = -6; i <= 6; i++) {
        const t = i / 2.8;
        lines.push(
            `M${fmt(project(t, -GROUND))} L${fmt(project(t, GROUND))}`,
            `M${fmt(project(-GROUND, t))} L${fmt(project(GROUND, t))}`,
        );
    }

    return lines.join(' ');
});

/** The stack's shadow on the plane — slightly proud of the plate, as a drawing. */
const footprint = computed(() =>
    polygon([
        project(-0.1, -0.1),
        project(1.1, -0.1),
        project(1.1, 1.1),
        project(-0.1, 1.1),
    ]),
);

/** Dashed riser marking the stack's vertical axis. */
const axis = computed(
    () => `M${fmt(project(1, 1))} L${fmt(project(1, 1, 44))}`,
);

/* ── Slabs ───────────────────────────────────────────────────────────────── */

const slabs = computed(() =>
    LAYERS.map((layer, index) => {
        const top = BASE + index * PITCH;
        const bottom = top - THICKNESS;
        const face = FACES[layer.kind];

        const a = project(0, 0, top);
        const b = project(1, 0, top);
        const c = project(1, 1, top);
        const d = project(0, 1, top);

        // Label lies in the right face's plane, reading up to the right.
        const anchor = project(1, 0.5, top - THICKNESS / 2);

        return {
            key: layer.name,
            label: layer.name.toUpperCase(),
            face,
            dash: layer.kind === 'outline' ? '8 5' : undefined,
            top: polygon([a, b, c, d]),
            right: polygon([
                c,
                b,
                project(1, 0, bottom),
                project(1, 1, bottom),
            ]),
            left: polygon([d, c, project(1, 1, bottom), project(0, 1, bottom)]),
            labelTransform: `matrix(${COS_30},-0.5,0,1,${anchor[0].toFixed(1)},${anchor[1].toFixed(1)})`,
            // Up-arrow at the near corner: this slab lifted off the one below.
            riser: `M${fmt(a)} l0,-12 M${fmt(a)} l-9,-5 M${fmt(a)} l9,-5`,
        };
    }),
);

/** Leader off the top slab, naming who Fleet is for. */
const fleetLeader = computed(() => {
    const [x, y] = project(0, 0, BASE + (LAYERS.length - 1) * PITCH);

    return {
        path: `M${x.toFixed(1)},${y.toFixed(1)} L${(x - 66).toFixed(1)},${(y - 38).toFixed(1)} H${(x - 172).toFixed(1)}`,
        textX: (x - 168).toFixed(1),
        textY: (y - 46).toFixed(1),
    };
});

/* ── Inputs ──────────────────────────────────────────────────────────────── */

/*
 * The two readings a gauge is computed from, drawn as plates at the lower left
 * with routed connectors into the slabs that consume them. Authored in flat
 * viewBox space rather than projected — they are annotations on the drawing,
 * not objects in it.
 */
const PILL = { x: 16, width: 230, height: 70 };

const inputs = [
    {
        label: 'ODOMETER',
        value: '15,100 mi · Sep 8',
        y: 560,
        border: ACCENT,
        stroke: ACCENT,
        dash: 'none',
        // Readings — the layer an odometer entry lands in.
        target: 1,
        leg: 268,
    },
    {
        label: 'INTERVAL',
        value: 'every 5,000 mi',
        y: 630,
        border: 'rgba(29,31,32,0.4)',
        stroke: 'rgba(29,31,32,0.45)',
        dash: '7 6',
        // Intervals.
        target: 2,
        leg: 292,
    },
];

const connectors = computed(() =>
    inputs.map((input) => {
        const [endX, endY] = project(
            0,
            0.6,
            BASE + input.target * PITCH - THICKNESS / 2,
        );
        const startY = input.y + PILL.height / 2;

        return {
            key: input.label,
            stroke: input.stroke,
            dash: input.dash,
            endX: endX.toFixed(1),
            endY: endY.toFixed(1),
            // Out of the plate, one rounded turn down, one turn into the slab.
            path:
                `M${PILL.x + PILL.width},${startY} H${input.leg - 16} q16,0 16,-16` +
                ` V${(endY + 16).toFixed(1)} q0,-16 16,-16 H${endX.toFixed(1)}`,
        };
    }),
);
</script>

<template>
    <svg
        viewBox="0 0 760 736"
        :class="cn('block h-auto w-full', props.class)"
        role="img"
        aria-label="Isometric diagram of the Ignition Index data model: vehicles, readings and intervals feed the gauges layer, with fleet roll-up drawn as an additional layer for teams. Three vehicles sit on the ground plane, marked on interval, due soon and overdue."
    >
        <!-- Ground plane -->
        <polygon :points="ground" fill="#ececed" />
        <path
            :d="groundGrid"
            fill="none"
            stroke="rgba(29,31,32,0.13)"
            stroke-width="1.6"
        />
        <polygon :points="footprint" fill="rgba(29,31,32,0.1)" />
        <path
            :d="axis"
            stroke="rgba(29,31,32,0.3)"
            stroke-width="1.6"
            stroke-dasharray="4 5"
        />

        <!-- The stack, drawn bottom to top so each slab overlaps the one below -->
        <g v-for="slab in slabs" :key="slab.key">
            <polygon
                :points="slab.left"
                :fill="slab.face.left"
                :stroke="slab.face.edge"
                stroke-width="1.6"
                :stroke-dasharray="slab.dash"
            />
            <polygon
                :points="slab.right"
                :fill="slab.face.right"
                :stroke="slab.face.edge"
                stroke-width="1.6"
                :stroke-dasharray="slab.dash"
            />
            <polygon
                :points="slab.top"
                :fill="slab.face.top"
                :stroke="slab.face.edge"
                stroke-width="1.6"
                :stroke-dasharray="slab.dash"
            />

            <g :transform="slab.labelTransform">
                <text
                    x="0"
                    y="9"
                    text-anchor="middle"
                    font-family="Barlow Condensed, Barlow, sans-serif"
                    font-size="34"
                    font-weight="600"
                    letter-spacing="1"
                    :fill="slab.face.ink"
                >
                    {{ slab.label }}
                </text>
            </g>

            <path
                :d="slab.riser"
                fill="none"
                stroke="rgba(29,31,32,0.35)"
                stroke-width="1.6"
            />
        </g>

        <!-- Fleet leader -->
        <path
            :d="fleetLeader.path"
            fill="none"
            :stroke="NAVY"
            stroke-width="1.6"
        />
        <text
            :x="fleetLeader.textX"
            :y="fleetLeader.textY"
            font-family="Barlow, sans-serif"
            font-size="21"
            font-weight="700"
            letter-spacing="2.4"
            :fill="NAVY"
        >
            FOR TEAMS
        </text>

        <!-- Connectors first, so the plates sit on top of their own leaders -->
        <g v-for="connector in connectors" :key="connector.key">
            <path
                :d="connector.path"
                fill="none"
                :stroke="connector.stroke"
                stroke-width="2"
                :stroke-dasharray="connector.dash"
            />
            <circle
                :cx="connector.endX"
                :cy="connector.endY"
                r="5"
                :fill="connector.stroke"
            />
        </g>

        <!-- Input plates -->
        <g v-for="input in inputs" :key="input.label">
            <rect
                :x="PILL.x"
                :y="input.y"
                :width="PILL.width"
                :height="PILL.height"
                :fill="PAPER"
                :stroke="input.border"
                stroke-width="1.6"
            />
            <text
                :x="PILL.x + 16"
                :y="input.y + 24"
                font-family="Barlow, sans-serif"
                font-size="21"
                font-weight="700"
                letter-spacing="2.4"
                fill="rgba(29,31,32,0.6)"
            >
                {{ input.label }}
            </text>
            <text
                :x="PILL.x + 16"
                :y="input.y + 56"
                font-family="Barlow Condensed, Barlow, sans-serif"
                font-size="30"
                font-weight="600"
                :fill="INK"
            >
                {{ input.value }}
            </text>
        </g>

        <!--
            Drawn last, so the markers sit on top of the plane and its grid.
            They occupy the front-right of the ground, clear of the stack's
            footprint and of the odometer callout on the left.
        -->
        <g v-for="vehicle in vehicles" :key="vehicle.key">
            <polygon
                :points="vehicle.plate"
                :fill="PAPER"
                stroke="rgba(29,31,32,0.35)"
                stroke-width="1.6"
            />
            <polygon :points="vehicle.chip" :fill="vehicle.color" />
        </g>
    </svg>
</template>
