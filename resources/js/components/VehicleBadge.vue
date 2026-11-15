<script setup lang="ts">
import { Car } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { QuickAddVehicle } from '@/types/garage';

/*
 * Which car an entry is going on, said as the car rather than as a caption:
 * its photo in a small round frame, its paint as a dot on the frame's edge,
 * and its name with the year, make and model under it.
 *
 * Built from whatever the page knows. The garage and vehicle pages pass the
 * photo, colour and spec; History knows only the name, and the badge is then
 * the glyph and the name. A photo that fails to load falls back to the glyph.
 */
const props = defineProps<{
    vehicle: QuickAddVehicle;
}>();

const failed = ref(false);

const spec = computed(() => {
    const line = [props.vehicle.year, props.vehicle.make, props.vehicle.model]
        .filter(Boolean)
        .join(' ');

    // A car named by its spec would say the same thing twice.
    return line && line !== props.vehicle.name ? line : null;
});
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-(--space-3) rounded-full border border-(--color-divider) bg-(--surface-card) py-1 pr-4 pl-1"
    >
        <span class="relative shrink-0">
            <img
                v-if="vehicle.photo_thumb_url && !failed"
                :src="vehicle.photo_thumb_url"
                alt=""
                class="size-10 rounded-full object-cover"
                @error="failed = true"
            />
            <span
                v-else
                class="flex size-10 items-center justify-center rounded-full bg-(--tile-slate-bg) text-(--tile-slate-ink)"
                aria-hidden="true"
            >
                <Car class="size-5" :stroke-width="1.5" />
            </span>

            <!-- The paint, ringed in the badge's own ground so it reads on any photo. -->
            <span
                v-if="vehicle.color"
                class="absolute -right-0.5 -bottom-0.5 size-3.5 rounded-full shadow-[inset_0_0_0_1px_rgb(0_0_0/0.18)] ring-2 ring-(--surface-card)"
                :style="{ backgroundColor: vehicle.color }"
                aria-hidden="true"
            />
        </span>

        <span class="grid min-w-0 text-left">
            <span class="text-foreground truncate text-[15px] font-semibold">
                {{ vehicle.name }}
            </span>
            <span
                v-if="spec"
                class="truncate text-xs text-(--color-neutral-700)"
            >
                {{ spec }}
            </span>
        </span>
    </span>
</template>
