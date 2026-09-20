<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import GaugeDial from '@/components/GaugeDial.vue';
import GaugeLegend from '@/components/GaugeLegend.vue';
import TheIndexFigure from '@/components/marketing/TheIndexFigure.vue';
import ShellCheckerStrip from '@/components/shell/ShellCheckerStrip.vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { gaugeColor, gaugeInk, gaugeStatusLabel } from '@/lib/gauge';
import { computed } from 'vue';
import { garage, login, register } from '@/routes';
import type { GaugeDisplayStatus } from '@/types/gauges';

/*
 * The marketing page. It carries its own navy header rather than the app shell,
 * since a visitor has no garage to navigate — app.ts already resolves this page
 * to no layout, so there is nothing to declare here.
 *
 * Content is static and lifted from the design drop, prices included, so
 * nothing here is invented.
 */

type DemoGauge = {
    name: string;
    percent: number;
    basis: string;
    meta: string;
    status: GaugeDisplayStatus;
};

const demo: DemoGauge[] = [
    {
        name: 'Oil & filter',
        percent: 71,
        basis: '5,000 mi',
        meta: '1,450 mi to go',
        status: 'ok',
    },
    {
        name: 'Tire rotation',
        percent: 36,
        basis: '7,500 mi',
        meta: '4,800 mi to go',
        status: 'ok',
    },
    {
        name: 'Cabin air filter',
        percent: 88,
        basis: '15,000 mi',
        meta: '1,800 mi to go',
        status: 'due',
    },
    {
        name: 'Wiper blades',
        percent: 112,
        basis: '12 mo',
        meta: 'Overdue by 1.4 mo',
        status: 'overdue',
    },
];

const steps = [
    {
        n: '01',
        title: 'Add the vehicle',
        body: 'Year, make, model and VIN. Name it whatever you call it in the driveway.',
    },
    {
        n: '02',
        title: 'Set the intervals',
        body: 'Start from the factory schedule or write your own — miles, months, or whichever comes first.',
    },
    {
        n: '03',
        title: 'Log a reading',
        body: 'Snap the odometer when you fill up. Every gauge on the vehicle recalculates from that one number.',
    },
];

const fleetStats = [
    { value: 'Unlimited', label: 'Vehicles per account' },
    { value: 'Groups', label: 'By site, crew or class' },
    { value: 'Roles', label: 'Drivers log, managers configure' },
    { value: 'CSV', label: 'Export for accounting' },
];

const tiers = [
    {
        name: 'Solo',
        badge: 'Free',
        price: '$0',
        per: 'forever',
        line: 'One or two cars and the full gauge set.',
        cta: 'Start free',
        dark: false,
        features: [
            '2 vehicles',
            'Unlimited services and intervals',
            'Odometer log and history',
            'Due-soon email',
        ],
    },
    {
        name: 'Garage',
        badge: 'Popular',
        price: '$6',
        per: '/ month',
        line: 'For the enthusiast with a project and a daily.',
        cta: 'Start 14-day trial',
        dark: false,
        features: [
            'Up to 8 vehicles',
            'Receipt and document storage',
            'Parts and cost tracking',
            'Insights: cost per mile',
            'Shared access for a partner',
        ],
    },
    {
        name: 'Fleet',
        badge: 'Paid tier',
        price: '$4',
        per: '/ vehicle / month',
        line: 'For companies running vehicles as equipment.',
        cta: 'Request access',
        dark: true,
        features: [
            'Unlimited vehicles',
            'Groups by site, crew or class',
            'Driver and manager roles',
            'Fleet-wide overdue roll-up',
            'CSV export and API',
            'Priority support',
        ],
    },
];

// Read at render rather than hard-coded, so the footer does not quietly go stale
// on 1 January.
const year = new Date().getFullYear();

/*
 * A signed-in visitor still lands here from a bookmark or the logo, and the
 * guest nav left them stranded: "Sign in" bounces them straight back out to
 * Fortify's home and there was no way into the garage at all.
 *
 * `auth.user` is typed non-nullable because every other reader sits behind the
 * auth middleware, but this page is public and it really is null for a guest.
 */
const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const navLinks = [
    { label: 'Gauges', href: '#gauges' },
    { label: 'How it works', href: '#how' },
    { label: 'Fleet', href: '#fleet' },
    { label: 'Pricing', href: '#pricing' },
];
</script>

<template>
    <Head title="Know what your car needs next" />

    <div class="bg-background text-foreground flex min-h-dvh flex-col">
        <header :style="{ background: 'var(--shell-navy)' }">
            <div
                class="mx-auto flex w-full max-w-[1200px] flex-wrap items-center gap-(--space-6) px-7 py-3.5"
            >
                <a href="#top" class="mr-auto flex items-center gap-[11px]">
                    <img
                        src="/img/ignition-index-logo-light.png"
                        alt=""
                        class="block w-12 flex-none"
                    />
                    <span
                        class="font-display text-[20px]/[1.05] font-semibold tracking-[0.02em] uppercase"
                        :style="{ color: 'var(--color-bg)' }"
                    >
                        Ignition<br />Index
                    </span>
                    <span class="sr-only">Ignition Index — home</span>
                </a>

                <nav class="hidden items-center gap-[26px] text-[14px] sm:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="ease-standard text-(--shell-ink-secondary) transition-colors duration-[var(--dur-fast)] hover:text-(--color-bg)"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-(--space-3)">
                    <Link
                        v-if="user"
                        :href="garage()"
                        class="font-display bg-accent hover:bg-accent-700 active:bg-accent-800 hover:border-accent-700 active:border-accent-800 ease-standard flex h-[38px] items-center border border-(--color-accent) px-5 text-[17px] font-semibold tracking-[0.04em] uppercase transition-colors duration-[var(--dur-fast)]"
                        :style="{ color: 'var(--color-bg)' }"
                    >
                        Garage
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="ease-standard text-[14px] text-(--shell-ink-secondary) transition-colors duration-[var(--dur-fast)] hover:text-(--color-bg)"
                        >
                            Sign in
                        </Link>
                        <Link
                            :href="register()"
                            class="font-display bg-accent hover:bg-accent-700 active:bg-accent-800 hover:border-accent-700 active:border-accent-800 ease-standard flex h-[38px] items-center border border-(--color-accent) px-5 text-[17px] font-semibold tracking-[0.04em] uppercase transition-colors duration-[var(--dur-fast)]"
                            :style="{ color: 'var(--color-bg)' }"
                        >
                            Start free
                        </Link>
                    </template>
                </div>
            </div>

            <ShellCheckerStrip />
        </header>

        <main class="flex-1">
            <!-- ── Hero ───────────────────────────────────────────────── -->
            <section
                id="top"
                class="mx-auto w-full max-w-[1200px] px-7 pt-[78px]"
            >
                <div class="grid items-center gap-[54px] lg:grid-cols-2">
                    <div>
                        <p
                            class="ii-tag ii-tag-outline text-[11px] font-bold tracking-[0.14em] uppercase"
                            :style="{
                                '--ii-tag-border': 'var(--color-accent-700)',
                                '--ii-tag-ink': 'var(--color-accent-700)',
                                padding: '5px 12px',
                            }"
                        >
                            Maintenance, indexed
                        </p>

                        <h1
                            class="font-display mt-(--space-4) text-[clamp(46px,6vw,76px)]/[0.98] font-semibold tracking-[-0.02em] uppercase"
                        >
                            Know what<br />your car<br />needs next
                        </h1>

                        <p
                            class="mt-(--space-6) max-w-[46ch] text-[18px]/[1.55] text-pretty text-(--color-neutral-800)"
                        >
                            Every service becomes a gauge. Set the interval once
                            — by mileage or by calendar — and Ignition Index
                            reads your odometer against it, so the answer is a
                            glance instead of a shoebox of receipts.
                        </p>

                        <div
                            class="mt-(--space-8) flex flex-wrap gap-(--space-3)"
                        >
                            <Link
                                :href="register()"
                                class="font-display bg-accent hover:bg-accent-700 active:bg-accent-800 hover:border-accent-700 active:border-accent-800 ease-standard flex h-[46px] items-center border border-(--color-accent) px-[26px] text-[19px] font-semibold tracking-[0.04em] uppercase transition-colors duration-[var(--dur-fast)]"
                                :style="{ color: 'var(--color-bg)' }"
                            >
                                Start free
                            </Link>
                            <a
                                href="#how"
                                class="hover:bg-hover-surface ease-standard flex h-[46px] items-center border border-(--color-divider) px-[22px] text-[15px] font-medium transition-colors duration-[var(--dur-fast)]"
                            >
                                See how it works
                            </a>
                        </div>

                        <p
                            class="mt-(--space-8) flex flex-wrap items-center gap-(--space-6) text-[13px] text-(--color-neutral-700)"
                        >
                            <span>Free for up to 2 vehicles</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>No card required</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>Import from a spreadsheet</span>
                        </p>
                    </div>

                    <!--
                        Fig. 01 — the data model as an exploded isometric. It says what the
                        headline claims (readings + intervals become gauges) in one object,
                        and carries the Fleet tier without a second section.
                    -->
                    <figure class="flex flex-col gap-(--space-3)">
                        <figcaption
                            class="ii-eyebrow flex items-baseline gap-2.5 text-(--color-neutral-700)"
                        >
                            <span class="text-(--color-accent-700)"
                                >Fig. 01</span
                            >
                            <span>The index</span>
                        </figcaption>

                        <BlueprintFrame class="gap-0 p-(--space-6)">
                            <TheIndexFigure />
                        </BlueprintFrame>

                        <!--
                            The same three states the gauge wall names, in the
                            same component, so the homepage cannot promise a
                            palette the product has stopped using.

                            Below the frame rather than inside the drawing:
                            407abf6 took colour off the plane deliberately,
                            because identity chips read as noise against an
                            otherwise monochrome blueprint. These are §2 status
                            colours doing a caption's job, which is a different
                            thing from colouring the figure itself.
                        -->
                        <GaugeLegend />
                    </figure>
                </div>
            </section>

            <!-- ── 01 The gauge ───────────────────────────────────────── -->
            <section
                id="gauges"
                class="mx-auto w-full max-w-[1200px] px-7 pt-24"
            >
                <div
                    class="flex flex-wrap items-end justify-between gap-(--space-6)"
                >
                    <div>
                        <h6 class="ii-eyebrow text-(--color-accent-700)">
                            01 — The gauge
                        </h6>
                        <h2
                            class="font-display mt-(--space-2) text-[clamp(32px,3.4vw,46px)] font-semibold tracking-[-0.01em] uppercase"
                        >
                            One dial per service
                        </h2>
                    </div>
                    <p
                        class="max-w-[44ch] text-[16px]/[1.55] text-pretty text-(--color-neutral-800)"
                    >
                        The needle shows how much of the interval you have used.
                        Steel is on interval, amber is due soon, rust is
                        overdue. Nothing else competes for the color.
                    </p>
                </div>

                <div
                    class="mt-(--space-8) grid gap-[18px] sm:grid-cols-2 lg:grid-cols-4"
                >
                    <BlueprintFrame
                        v-for="gauge in demo"
                        :key="gauge.name"
                        class="flex flex-col items-center gap-0 px-4 pt-[18px] pb-4"
                    >
                        <span
                            class="ii-eyebrow flex w-full items-center justify-between gap-2 tracking-[0.12em]"
                        >
                            <span>{{ gauge.basis }}</span>
                            <span
                                class="ii-tag ii-tag-outline text-[10px] font-bold tracking-[0.12em] uppercase"
                                :style="{
                                    '--ii-tag-border': gaugeColor(gauge.status),
                                    '--ii-tag-ink': gaugeInk(gauge.status),
                                }"
                            >
                                {{ gaugeStatusLabel(gauge.status) }}
                            </span>
                        </span>

                        <GaugeDial
                            class="h-[155px] w-full max-w-[200px]"
                            :percent="gauge.percent"
                            :status="gauge.status"
                        />

                        <p class="font-display text-[40px]/none font-semibold">
                            {{ gauge.percent }}%
                        </p>
                        <p
                            class="font-display text-center text-[21px]/[1.1] font-semibold"
                        >
                            {{ gauge.name }}
                        </p>
                        <p
                            class="text-center text-[12px] text-(--color-neutral-700)"
                        >
                            {{ gauge.meta }}
                        </p>
                    </BlueprintFrame>
                </div>
            </section>

            <!-- ── 02 How it works ────────────────────────────────────── -->
            <section id="how" class="mx-auto w-full max-w-[1200px] px-7 pt-24">
                <h6 class="ii-eyebrow text-(--color-accent-700)">
                    02 — How it works
                </h6>
                <h2
                    class="font-display mt-(--space-2) mb-(--space-8) text-[clamp(32px,3.4vw,46px)] font-semibold tracking-[-0.01em] uppercase"
                >
                    Three inputs, then it runs itself
                </h2>

                <BlueprintFrame class="gap-0 p-0">
                    <!-- The 1px gaps are the rules; each cell paints over them. -->
                    <div
                        class="grid gap-px bg-(--color-divider) md:grid-cols-3"
                    >
                        <div
                            v-for="step in steps"
                            :key="step.n"
                            class="flex flex-col gap-(--space-2) bg-(--color-neutral-100) px-7 py-[30px]"
                        >
                            <p
                                class="font-display text-accent text-[58px]/[0.9] font-semibold"
                            >
                                {{ step.n }}
                            </p>
                            <p
                                class="font-display text-[24px]/[1.1] font-semibold uppercase"
                            >
                                {{ step.title }}
                            </p>
                            <p
                                class="text-[15px]/[1.55] text-pretty text-(--color-neutral-800)"
                            >
                                {{ step.body }}
                            </p>
                        </div>
                    </div>
                </BlueprintFrame>
            </section>

            <!-- ── 03 Fleet ───────────────────────────────────────────── -->
            <section
                id="fleet"
                class="mt-24"
                :style="{
                    background: 'var(--color-accent-900)',
                    color: 'var(--color-bg)',
                }"
            >
                <div
                    class="mx-auto grid w-full max-w-[1200px] items-center gap-[54px] px-7 py-[78px] lg:grid-cols-2"
                >
                    <div>
                        <h6
                            class="ii-eyebrow"
                            :style="{ color: 'var(--shell-ink-meta)' }"
                        >
                            03 — Fleet
                        </h6>
                        <h2
                            class="font-display mt-(--space-2) text-[clamp(32px,3.4vw,46px)] font-semibold tracking-[-0.01em] uppercase"
                        >
                            Same gauges,<br />a hundred vehicles
                        </h2>
                        <p
                            class="mt-(--space-6) max-w-[48ch] text-[17px]/[1.55] text-pretty"
                            :style="{ color: 'var(--shell-ink-secondary)' }"
                        >
                            A fleet is a garage that outgrew one person. Group
                            vehicles by site or crew, give drivers permission to
                            log a reading and nothing else, and let the index
                            roll up: what is overdue today, what lands next
                            week, what it will cost.
                        </p>
                        <!--
                            Outlined on the reversed field, so the hover is a
                            paper wash rather than a colour change.
                        -->
                        <Link
                            :href="register()"
                            class="font-display ease-standard mt-(--space-8) inline-flex h-[46px] items-center border border-[rgb(242_242_243/0.4)] px-[26px] text-[19px] font-semibold tracking-[0.04em] text-(--color-bg) uppercase transition-colors duration-[var(--dur-fast)] hover:border-[rgb(242_242_243/0.7)] hover:bg-[rgb(242_242_243/0.12)] active:bg-[rgb(242_242_243/0.2)]"
                        >
                            Talk to us about fleet
                        </Link>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-px"
                        :style="{ background: 'rgb(242 242 243 / 0.22)' }"
                    >
                        <div
                            v-for="stat in fleetStats"
                            :key="stat.value"
                            class="px-5 py-6"
                            :style="{ background: 'var(--color-accent-900)' }"
                        >
                            <p
                                class="font-display text-[44px]/none font-semibold"
                            >
                                {{ stat.value }}
                            </p>
                            <p
                                class="mt-1.5 text-[13px]/[1.4]"
                                :style="{ color: 'var(--shell-ink-secondary)' }"
                            >
                                {{ stat.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── 04 Pricing ─────────────────────────────────────────── -->
            <section
                id="pricing"
                class="mx-auto w-full max-w-[1200px] px-7 pt-24"
            >
                <h6 class="ii-eyebrow text-(--color-accent-700)">
                    04 — Pricing
                </h6>
                <h2
                    class="font-display mt-(--space-2) mb-(--space-8) text-[clamp(32px,3.4vw,46px)] font-semibold tracking-[-0.01em] uppercase"
                >
                    Pick a tier
                </h2>

                <div class="grid gap-5 md:grid-cols-3">
                    <BlueprintFrame
                        v-for="tier in tiers"
                        :key="tier.name"
                        class="flex flex-col gap-0 px-6 pt-[26px] pb-6"
                        :style="
                            tier.dark
                                ? {
                                      background: 'var(--shell-navy)',
                                      color: 'var(--color-bg)',
                                  }
                                : undefined
                        "
                    >
                        <div
                            class="flex items-center justify-between gap-(--space-3)"
                        >
                            <p
                                class="font-display text-[26px] font-semibold tracking-[0.02em] uppercase"
                            >
                                {{ tier.name }}
                            </p>
                            <span
                                class="ii-tag ii-tag-outline text-[10px] font-bold tracking-[0.12em] uppercase"
                                :style="{
                                    '--ii-tag-border': tier.dark
                                        ? 'rgb(242 242 243 / 0.6)'
                                        : 'var(--color-accent-700)',
                                    '--ii-tag-ink': tier.dark
                                        ? 'rgb(242 242 243 / 0.6)'
                                        : 'var(--color-accent-700)',
                                }"
                            >
                                {{ tier.badge }}
                            </span>
                        </div>

                        <p class="mt-(--space-4) flex items-baseline gap-1.5">
                            <span
                                class="font-display text-[52px]/none font-semibold"
                            >
                                {{ tier.price }}
                            </span>
                            <span
                                class="text-[14px]"
                                :style="{
                                    color: tier.dark
                                        ? 'var(--shell-ink-secondary)'
                                        : 'var(--color-neutral-700)',
                                }"
                            >
                                {{ tier.per }}
                            </span>
                        </p>

                        <p
                            class="mt-(--space-3) text-[14px]/[1.5] text-pretty"
                            :style="{
                                color: tier.dark
                                    ? 'var(--shell-ink-secondary)'
                                    : 'var(--color-neutral-700)',
                            }"
                        >
                            {{ tier.line }}
                        </p>

                        <div
                            class="my-(--space-6) h-px"
                            :style="{
                                background: tier.dark
                                    ? 'rgb(242 242 243 / 0.22)'
                                    : 'var(--color-divider)',
                            }"
                            aria-hidden="true"
                        />

                        <ul class="flex flex-1 flex-col gap-[9px]">
                            <li
                                v-for="feature in tier.features"
                                :key="feature"
                                class="flex items-start gap-2.5 text-[14px]/[1.45]"
                            >
                                <Check
                                    class="mt-[3px] size-[15px] flex-none"
                                    :stroke-width="1.5"
                                />
                                {{ feature }}
                            </li>
                        </ul>

                        <!--
                            Classes, not an inline style: an inline style wins
                            over any class selector, so a hover: utility could
                            never override a resting colour set inline.
                        -->
                        <Link
                            :href="register()"
                            class="font-display ease-standard mt-(--space-8) flex h-[42px] items-center justify-center border text-[18px] font-semibold tracking-[0.04em] uppercase transition-colors duration-[var(--dur-fast)]"
                            :class="
                                tier.dark
                                    ? 'border-accent bg-accent hover:border-accent-700 hover:bg-accent-700 active:border-accent-800 active:bg-accent-800 text-(--shell-active-ink)'
                                    : 'hover:bg-hover-surface active:bg-foreground/14 border-(--color-neutral-400) hover:border-(--color-accent)'
                            "
                        >
                            {{ tier.cta }}
                        </Link>
                    </BlueprintFrame>
                </div>
            </section>
        </main>

        <footer
            class="mt-24"
            :style="{
                background: 'var(--shell-navy)',
                color: 'var(--shell-ink-secondary)',
            }"
        >
            <ShellCheckerStrip />
            <div
                class="mx-auto flex w-full max-w-[1200px] flex-wrap items-center gap-(--space-6) px-7 py-10"
            >
                <img
                    src="/img/ignition-index-logo-light.png"
                    alt=""
                    class="block w-10 flex-none"
                />
                <span
                    class="font-display text-[16px] tracking-[0.06em] uppercase"
                    :style="{ color: 'var(--color-bg)' }"
                >
                    Ignition Index
                </span>

                <span class="ml-auto text-[13px]">
                    &copy; {{ year }} Ignition Index
                </span>
            </div>
        </footer>
    </div>
</template>
