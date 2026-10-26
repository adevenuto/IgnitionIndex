<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import type { NavItem } from '@/types';

/*
 * One rail destination.
 *
 * Active is a solid steel block with near-black ink — square, full width, no
 * indicator bar (design doc §5). Inactive sits at 72% paper and lifts to full
 * paper on a steel wash.
 */
defineProps<{ item: NavItem; active: boolean }>();
</script>

<template>
    <Link
        :href="item.href"
        class="ease-standard flex items-center gap-[11px] rounded-md p-(--space-3) text-[14px] transition-colors duration-[var(--dur-fast)]"
        :class="
            active
                ? 'bg-accent font-semibold text-[var(--shell-active-ink)]'
                : 'text-[var(--shell-ink-secondary)] hover:bg-[var(--shell-hover)] hover:text-[var(--shell-ink)]'
        "
        :aria-current="active ? 'page' : undefined"
    >
        <component
            :is="item.icon as Component"
            v-if="item.icon"
            class="size-[17px] flex-none"
            :stroke-width="1.5"
        />
        {{ item.title }}
        <span
            v-if="item.badge"
            class="ml-auto text-[12px] font-bold"
            :aria-label="`${item.badge} vehicles`"
        >
            {{ item.badge }}
        </span>
    </Link>
</template>
