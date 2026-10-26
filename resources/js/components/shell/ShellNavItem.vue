<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import type { NavItem } from '@/types';

/*
 * One rail destination.
 *
 * Active is a light brand tint with brand ink (design doc §5): a clear
 * "you are here" that leaves solid colour to the actions. Inactive is
 * secondary ink that lifts to full ink on a faint ink wash.
 */
defineProps<{ item: NavItem; active: boolean }>();
</script>

<template>
    <Link
        :href="item.href"
        class="ease-standard flex items-center gap-[11px] rounded-md p-(--space-3) text-[14px] transition-colors duration-[var(--dur-fast)]"
        :class="
            active
                ? 'bg-accent-100 text-accent-700 font-semibold'
                : 'text-(--chrome-ink-secondary) hover:bg-(--chrome-hover) hover:text-(--chrome-ink)'
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
