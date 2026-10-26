<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { cn } from '@/lib/utils';

/*
 * Fig. 01 — the index. An exploded isometric of the data model, for the
 * marketing hero: Vehicles + Readings + Intervals feed Gauges.
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
 *    depth. Each theme on trial has its own palette below (the system's own
 *    values plus derived tints), picked from <html data-theme>. Do not "fix"
 *    this to tokens.
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

type Palette = {
    paper: string;
    /** Ink as an "r,g,b" triple, for the translucent hairlines and fills. */
    ink: string;
    inkHex: string;
    accent: string;
    accentRgb: string;
    accentTop: string;
    accentLeft: string;
    /** Ink for the label on the solid (accent) slab. */
    onAccent: string;
    onAccentEdge: string;
    ground: string;
    due: string;
    overdue: string;
};

/** One per theme on trial (resources/css/ignition/themes.css). */
type ThemeId =
    | 'daylight'
    | 'volt'
    | 'indigo-night'
    | 'lagoon'
    | 'neon'
    | 'sandstone';

const PALETTES: Record<ThemeId, Palette> = {
    daylight: {
        paper: '#f2f2f3',
        ink: '29,31,32',
        inkHex: '#1d1f20',
        accent: '#2f6bed',
        accentRgb: '47,107,237',
        accentTop: '#5b8cf2',
        accentLeft: '#2253c4',
        onAccent: '#f2f2f3',
        onAccentEdge: 'rgba(242,242,243,0.34)',
        ground: '#ececed',
        due: '#c08a2e',
        overdue: '#b4442f',
    },
    volt: {
        paper: '#181b19',
        ink: '238,242,234',
        inkHex: '#eef2ea',
        accent: '#c6f135',
        accentRgb: '198,241,53',
        accentTop: '#d9f77a',
        accentLeft: '#8fb823',
        onAccent: '#0f1110',
        onAccentEdge: 'rgba(15,17,16,0.3)',
        ground: '#141715',
        due: '#e8a93f',
        overdue: '#f26b52',
    },
    'indigo-night': {
        paper: '#111a2e',
        ink: '230,233,244',
        inkHex: '#e6e9f4',
        accent: '#6366f1',
        accentRgb: '99,102,241',
        accentTop: '#8b8ef6',
        accentLeft: '#4a4fcf',
        onAccent: '#ffffff',
        onAccentEdge: 'rgba(255,255,255,0.25)',
        ground: '#0e1628',
        due: '#eab04a',
        overdue: '#f2735c',
    },
    lagoon: {
        paper: '#0f1f21',
        ink: '228,242,240',
        inkHex: '#e4f2f0',
        accent: '#2dd4bf',
        accentRgb: '45,212,191',
        accentTop: '#6ee7d6',
        accentLeft: '#1a8a7e',
        onAccent: '#062420',
        onAccentEdge: 'rgba(6,36,32,0.3)',
        ground: '#0c1a1c',
        due: '#e8ad44',
        overdue: '#f2735c',
    },
    neon: {
        paper: '#1a1320',
        ink: '244,234,246',
        inkHex: '#f4eaf6',
        accent: '#f472b6',
        accentRgb: '244,114,182',
        accentTop: '#f9a8d4',
        accentLeft: '#a33478',
        onAccent: '#1a0b14',
        onAccentEdge: 'rgba(26,11,20,0.3)',
        ground: '#150f1a',
        due: '#eab04a',
        overdue: '#f2735c',
    },
    sandstone: {
        paper: '#f4f1ec',
        ink: '36,33,29',
        inkHex: '#24211d',
        accent: '#0f766e',
        accentRgb: '15,118,110',
        accentTop: '#2a9a8d',
        accentLeft: '#0b4c47',
        onAccent: '#fcfaf7',
        onAccentEdge: 'rgba(252,250,247,0.34)',
        ground: '#ece7df',
        due: '#c08a2e',
        overdue: '#b4442f',
    },
};

const themeId = ref<ThemeId>('volt');
const P = computed(() => PALETTES[themeId.value]);
/** The theme's ink at an opacity. */
const inkA = (alpha: number) => `rgba(${P.value.ink},${alpha})`;

function readTheme(): void {
    const theme = document.documentElement.dataset.theme;
    themeId.value = theme && theme in PALETTES ? (theme as ThemeId) : 'volt';
}

type LayerKind = 'wire' | 'solid';

/** Three faces plus edge and ink, per layer treatment. */
const FACES = computed(
    (): Record<
        LayerKind,
        { top: string; right: string; left: string; edge: string; ink: string }
    > => ({
        // A line drawing: near-transparent fills, hairlines in the ink.
        wire: {
            top: `rgba(${P.value.accentRgb},0.07)`,
            right: inkA(0.045),
            left: inkA(0.075),
            edge: inkA(0.42),
            ink: P.value.inkHex,
        },
        // The one solid object on the board — where the product happens.
        solid: {
            top: P.value.accentTop,
            right: P.value.accent,
            left: P.value.accentLeft,
            edge: P.value.onAccentEdge,
            ink: P.value.onAccent,
        },
    }),
);

const LAYERS: { name: string; kind: LayerKind }[] = [
    { name: 'Vehicles', kind: 'wire' },
    { name: 'Readings', kind: 'wire' },
    { name: 'Intervals', kind: 'wire' },
    { name: 'Gauges', kind: 'solid' },
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
            [P.value.accent, 1.58, 1.02],
            [P.value.due, 1.98, 1.36],
            [P.value.overdue, 1.46, 1.58],
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
        const face = FACES.value[layer.kind];

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

/* ── Inputs ──────────────────────────────────────────────────────────────── */

/*
 * The two readings a gauge is computed from, drawn as plates at the lower left
 * with routed connectors into the slabs that consume them. Authored in flat
 * viewBox space rather than projected — they are annotations on the drawing,
 * not objects in it.
 */
/** Inner padding of a plate, the same on both sides of its text. */
const PILL_PAD = 16;
const PILL = { x: 16, height: 70 };

/*
 * Both plates take the width of the longest line on either, plus the same
 * padding on each side — so the text sits as far from the right edge as from
 * the left, and the two plates still line up. Measured, not guessed: the face
 * can change under the drawing (the type-face picker), and each face sets
 * "15,100 mi · Sep 8" to a different length. Until the first measurement,
 * a width that fits the widest face on offer.
 */
const textEls = ref<SVGTextElement[]>([]);
const contentWidth = ref(218);
const plateWidth = computed(() => contentWidth.value + PILL_PAD * 2);

async function measure(): Promise<void> {
    await document.fonts.ready;

    const widths = textEls.value.map((el) => el.getComputedTextLength());

    if (widths.length > 0) {
        contentWidth.value = Math.ceil(Math.max(...widths));
    }
}

let observer: MutationObserver | null = null;

onMounted(() => {
    readTheme();
    void measure();

    // A new face (data-font) or theme (data-theme) is chosen on <html>:
    // recolour, and measure once a new face has loaded.
    observer = new MutationObserver(() => {
        readTheme();
        requestAnimationFrame(() => void measure());
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-font', 'data-theme'],
    });
});

onBeforeUnmount(() => observer?.disconnect());

const inputs = computed(() => [
    {
        label: 'ODOMETER',
        value: '15,100 mi · Sep 8',
        y: 560,
        border: P.value.accent,
        stroke: P.value.accent,
        dash: 'none',
        // Readings — the layer an odometer entry lands in.
        target: 1,
        leg: 268,
    },
    {
        label: 'INTERVAL',
        value: 'every 5,000 mi',
        y: 630,
        border: inkA(0.4),
        stroke: inkA(0.45),
        dash: '7 6',
        // Intervals.
        target: 2,
        leg: 292,
    },
]);

const connectors = computed(() =>
    inputs.value.map((input) => {
        const [endX, endY] = project(
            0,
            0.6,
            BASE + input.target * PITCH - THICKNESS / 2,
        );
        const startY = input.y + PILL.height / 2;
        const right = PILL.x + plateWidth.value;
        // The vertical leg stays clear of a plate that has grown.
        const leg = Math.max(input.leg, right + 28);

        return {
            key: input.label,
            stroke: input.stroke,
            dash: input.dash,
            endX: endX.toFixed(1),
            endY: endY.toFixed(1),
            // Out of the plate, one rounded turn down, one turn into the slab.
            path:
                `M${right},${startY} H${leg - 16} q16,0 16,-16` +
                ` V${(endY + 16).toFixed(1)} q0,-16 16,-16 H${endX.toFixed(1)}`,
        };
    }),
);
</script>

<template>
    <svg
        viewBox="0 90 760 646"
        :class="cn('block h-auto w-full', props.class)"
        role="img"
        aria-label="Isometric diagram of the Ignition Index data model: vehicles, readings and intervals feed the gauges layer. Three vehicles sit on the ground plane, marked on interval, due soon and overdue."
    >
        <!-- Ground plane -->
        <polygon :points="ground" :fill="P.ground" />
        <path
            :d="groundGrid"
            fill="none"
            :stroke="inkA(0.13)"
            stroke-width="1.6"
        />
        <polygon :points="footprint" :fill="inkA(0.1)" />
        <path
            :d="axis"
            :stroke="inkA(0.3)"
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
            />
            <polygon
                :points="slab.right"
                :fill="slab.face.right"
                :stroke="slab.face.edge"
                stroke-width="1.6"
            />
            <polygon
                :points="slab.top"
                :fill="slab.face.top"
                :stroke="slab.face.edge"
                stroke-width="1.6"
            />

            <g :transform="slab.labelTransform">
                <text
                    x="0"
                    y="9"
                    text-anchor="middle"
                    style="font-family: var(--font-ui-display)"
                    font-size="28"
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
                :stroke="inkA(0.35)"
                stroke-width="1.6"
            />
        </g>

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
                :width="plateWidth"
                :height="PILL.height"
                :fill="P.paper"
                :stroke="input.border"
                stroke-width="1.6"
            />
            <text
                ref="textEls"
                :x="PILL.x + PILL_PAD"
                :y="input.y + 25"
                style="font-family: var(--font-ui)"
                font-size="21"
                font-weight="700"
                letter-spacing="2"
                :fill="inkA(0.6)"
            >
                {{ input.label }}
            </text>
            <text
                ref="textEls"
                :x="PILL.x + PILL_PAD"
                :y="input.y + 57"
                style="font-family: var(--font-ui)"
                font-size="25"
                font-weight="600"
                :fill="P.inkHex"
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
                :fill="P.paper"
                :stroke="inkA(0.35)"
                stroke-width="1.6"
            />
            <polygon :points="vehicle.chip" :fill="vehicle.color" />
        </g>
    </svg>
</template>
