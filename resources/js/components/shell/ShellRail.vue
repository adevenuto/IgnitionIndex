<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ShellDueNext from '@/components/shell/ShellDueNext.vue';
import ShellNavItem from '@/components/shell/ShellNavItem.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useShellNav } from '@/lib/shell-nav';

/*
 * The rail's contents, without the rail's own chrome — so the desktop column
 * and the mobile drawer render exactly the same thing and cannot drift.
 */
const nav = useShellNav();
const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const hasDueNext = computed(
    () => (page.props.garage?.due_next ?? []).length > 0,
);
</script>

<template>
    <!-- min-h-full, not h-full: the rail can grow past the viewport and let
         the aside scroll rather than clipping its own overflow. -->
    <div class="flex min-h-full flex-col px-[14px] py-[22px]">
        <p
            class="ii-eyebrow px-2 pb-3 tracking-[0.16em] text-[var(--shell-ink-eyebrow)]"
        >
            Workspace
        </p>

        <nav class="flex flex-col gap-[3px]">
            <ShellNavItem
                v-for="item in nav"
                :key="item.title"
                :item="item"
                :active="isCurrentOrParentUrl(item.href)"
            />
        </nav>

        <template v-if="hasDueNext">
            <div class="h-[30px]" aria-hidden="true" />
            <p
                class="ii-eyebrow px-2 pb-3 tracking-[0.16em] text-[var(--shell-ink-eyebrow)]"
            >
                Due next
            </p>
            <ShellDueNext />
        </template>
    </div>
</template>
