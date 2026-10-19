<script setup lang="ts">
import { Camera, Gauge, Mic } from '@lucide/vue';
import type { Provenance } from '@/lib/capture/lanes';

/*
 * Where a value came from, said quietly. A typed value says nothing — it is
 * the person's own — but a read or an estimate is labelled everywhere it
 * appears, so nothing reaches the review card unexplained.
 */
defineProps<{ provenance: Provenance | null }>();

const labels = {
    photo: { icon: Camera, text: 'From photo' },
    voice: { icon: Mic, text: 'Heard' },
    estimate: { icon: Gauge, text: 'Estimate' },
} as const;
</script>

<template>
    <span
        v-if="provenance && provenance !== 'typed'"
        class="text-meta inline-flex items-center gap-1 text-(--color-neutral-700)"
    >
        <component
            :is="labels[provenance].icon"
            class="size-3.5"
            :stroke-width="1.5"
        />
        {{ labels[provenance].text }}
    </span>
</template>
