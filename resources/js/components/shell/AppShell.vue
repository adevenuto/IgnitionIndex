<script setup lang="ts">
import { ref } from 'vue';
import ShellDrawer from '@/components/shell/ShellDrawer.vue';
import ShellRail from '@/components/shell/ShellRail.vue';
import ShellTabBar from '@/components/shell/ShellTabBar.vue';
import ShellTopBar from '@/components/shell/ShellTopBar.vue';
import { Toaster } from '@/components/ui/sonner';
import { useLogEntry } from '@/composables/useLogEntry';

/*
 * The Ignition Index shell: a light top bar and rail around the stage, each
 * edged with a hairline (design doc §5, light since 2026-10).
 *
 * The breakpoint switch is CSS-only (Tailwind md:), deliberately — a JS
 * useMediaQuery returns false on the first tick, so a phone would flash the
 * desktop rail before correcting itself.
 */
const navOpen = ref(false);

// The Log button, wherever it is pressed: in place on a vehicle, or off to
// get a vehicle first (see useLogEntry).
const { startLogEntry: onAdd } = useLogEntry();
</script>

<template>
    <!--
      h-dvh, not h-screen: on mobile Safari 100vh is the tallest the viewport
      ever gets, so a fixed bar would sit under the collapsing toolbar.
    -->
    <div class="bg-background flex h-dvh flex-col overflow-hidden">
        <ShellTopBar @add="onAdd" />

        <!--
          min-h-0 is load-bearing: a flex child defaults to min-height:auto, so
          without it the row grows to fit its content and the page scrolls as a
          whole instead of the panes scrolling independently.
        -->
        <div class="flex min-h-0 flex-1 items-stretch">
            <aside
                class="hidden w-[216px] flex-none overflow-y-auto border-r border-(--chrome-hairline) bg-(--chrome-rail) md:block"
            >
                <ShellRail />
            </aside>

            <!--
                This is the scroll container, so its padding-bottom is also the
                floor a `position: sticky; bottom: 0` child can reach — the
                sticky constraint is the scrollport inset by this padding, and no
                negative offset on the child gets past it. A page that ends in a
                full-bleed sticky action bar marks that bar [data-flush-bottom]
                and gets the padding dropped, so the bar sits on the bottom edge
                of the window rather than floating above a band of dead space.

                Desktop only: below md those bars are not sticky, and the padding
                is what keeps the last field clear of the bottom nav and FAB.
            -->
            <main
                class="flex min-w-0 flex-1 flex-col gap-[26px] overflow-y-auto px-[30px] pt-[26px] pb-10 max-md:px-5 max-md:pb-[calc(6rem+env(safe-area-inset-bottom))] md:has-[[data-flush-bottom]]:pb-0"
            >
                <slot />
            </main>
        </div>

        <ShellDrawer v-model:open="navOpen" />
        <ShellTabBar @add="onAdd" @more="navOpen = true" />
        <Toaster />
    </div>
</template>
