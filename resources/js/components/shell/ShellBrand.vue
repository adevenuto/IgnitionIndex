<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { garage } from '@/routes';

/*
 * The lockup: logo chip plus the wordmark.
 *
 * Two arrangements. `inline` is the shell's — mark on the left, wordmark on two
 * condensed lines beside it. `stacked` centres a larger mark with the name on
 * one line beneath, which is what the auth screens want: there is no chrome
 * around them, so the lockup is the only thing identifying the product.
 *
 * Every ground the lockup sits on is light since the 2026-10 chrome change, so
 * it is the navy mark with ink text — except under a dark trial theme, where
 * the paper outline (ignition-index-logo-light.png) shows instead. Both are
 * rendered; themes.css shows one (.ii-logo-light / .ii-logo-dark).
 * Design doc §1 is explicit that the mark never sits on a filled chip or box.
 *
 * `href` exists because this renders an anchor. The auth layout used to wrap it
 * in a second <Link>, which nests one anchor inside another — invalid HTML, and
 * browsers recover from it unpredictably.
 */
const props = withDefaults(
    defineProps<{
        compact?: boolean;
        orientation?: 'inline' | 'stacked';
        href?: NonNullable<InertiaLinkProps['href']>;
    }>(),
    {
        compact: false,
        orientation: 'inline',
        href: undefined,
    },
);

const stacked = computed(() => props.orientation === 'stacked');

const target = computed(() => props.href ?? garage());
</script>

<template>
    <Link
        :href="target"
        :class="
            stacked
                ? 'flex flex-col items-center gap-(--space-3)'
                : compact
                  ? 'flex flex-none items-center gap-[11px]'
                  : 'flex w-[194px] flex-none items-center gap-[11px]'
        "
    >
        <img
            src="/img/ignition-index-logo.png"
            alt=""
            class="ii-logo-light block flex-none"
            :class="stacked ? 'w-20' : 'w-15'"
        />
        <img
            src="/img/ignition-index-logo-light.png"
            alt=""
            class="ii-logo-dark flex-none"
            :class="stacked ? 'w-20' : 'w-15'"
        />

        <span
            v-if="stacked"
            class="font-display text-foreground text-[22px]/none font-bold tracking-[0.02em] uppercase"
        >
            Ignition Index
        </span>

        <span
            v-else-if="!compact"
            class="font-display text-foreground text-[18px]/[1.05] font-bold tracking-[0.01em] uppercase"
        >
            Ignition<br />Index
        </span>

        <span class="sr-only">Ignition Index — home</span>
    </Link>
</template>
