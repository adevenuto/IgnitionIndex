<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * Fig. 01 as a story: logging a reading, the data flowing into the index,
 * the gauge reacting, and the service resolving it — a 13-second loop.
 *
 * The figure itself is one self-contained file, public/figures/log-loop.html
 * (vanilla JS, inline SVG, one config object), written so it can move into a
 * Blade partial unchanged. Here it is framed in an iframe so its styles and
 * script stay its own; it borrows this page's type face and follows its
 * colour theme (<html data-theme>).
 *
 * Below sm the frame turns portrait, and the figure — which picks its layout
 * from its own proportions — stacks its cards above the disc to stay legible.
 */
const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
    }>(),
    { class: undefined },
);
</script>

<template>
    <iframe
        src="/figures/log-loop.html"
        title="Logging a reading: the odometer updates, the data flows into the index, and the oil-change gauge goes overdue until the service is logged."
        loading="lazy"
        :class="
            cn(
                'block aspect-[460/640] w-full border-0 sm:aspect-[800/560]',
                props.class,
            )
        "
    />
</template>
