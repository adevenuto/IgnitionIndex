<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useShellNav } from '@/lib/shell-nav';

/*
 * The phone's navigation: a bar along the bottom, where a thumb already is.
 *
 * Logging is the app's one core action, so it is the bar's centrepiece — a
 * large raised button in the theme's accent, between the destinations, not
 * a small icon tucked into the top corner. The rail's three destinations sit
 * either side of it, and More opens the drawer for everything else (Due next,
 * and the rest of the rail).
 *
 * Below md only; the desktop keeps its rail and the top bar's Log entry.
 */
defineEmits<{ add: []; more: [] }>();

const nav = useShellNav();
const { isCurrentOrParentUrl } = useCurrentUrl();

/** Garage and History to the left of Log, Insights and More to the right. */
const left = computed(() => nav.value.slice(0, 2));
const right = computed(() => nav.value.slice(2));
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-(--chrome-hairline) bg-(--chrome-bar) pb-[env(safe-area-inset-bottom)] md:hidden"
        aria-label="Main"
    >
        <div class="grid h-16 grid-cols-5 items-stretch">
            <Link
                v-for="item in left"
                :key="item.title"
                :href="item.href"
                class="flex flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors duration-[var(--dur-fast)]"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'text-accent-700'
                        : 'text-(--chrome-ink-secondary) active:text-(--chrome-ink)'
                "
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
            >
                <component :is="item.icon" class="size-5" :stroke-width="1.6" />
                {{ item.title }}
            </Link>

            <!-- The core action: raised above the bar, in the accent. -->
            <div class="flex justify-center">
                <button
                    type="button"
                    class="bg-primary text-primary-foreground focus-visible:ring-ring/50 -mt-5 flex size-16 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-full shadow-lg ring-4 ring-(--chrome-bar) transition-colors duration-[var(--dur-fast)] outline-none hover:bg-(--primary-hover) focus-visible:ring-[3px] active:bg-(--primary-active)"
                    aria-label="Log an entry"
                    @click="$emit('add')"
                >
                    <Plus class="size-6" :stroke-width="2.25" />
                    <span class="text-[10px] font-bold">Log</span>
                </button>
            </div>

            <Link
                v-for="item in right"
                :key="item.title"
                :href="item.href"
                class="flex flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors duration-[var(--dur-fast)]"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'text-accent-700'
                        : 'text-(--chrome-ink-secondary) active:text-(--chrome-ink)'
                "
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
            >
                <component :is="item.icon" class="size-5" :stroke-width="1.6" />
                {{ item.title }}
            </Link>

            <button
                type="button"
                class="flex cursor-pointer flex-col items-center justify-center gap-1 text-[11px] font-semibold text-(--chrome-ink-secondary) transition-colors duration-[var(--dur-fast)] active:text-(--chrome-ink)"
                @click="$emit('more')"
            >
                <Menu class="size-5" :stroke-width="1.6" />
                More
            </button>
        </div>
    </nav>
</template>
