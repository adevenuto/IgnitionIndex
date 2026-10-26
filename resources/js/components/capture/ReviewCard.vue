<script setup lang="ts">
import { BlueprintFrame } from '@/components/ui/blueprint';
import { laneTone } from '@/lib/lanes';

/*
 * The last stop before saving: every value on one card, ruled like the gauge
 * list (gap-px over a divider-coloured ground, per §6) so the last row does
 * not double up against the card's own border. Rows arrive through the slot.
 * Its top edge takes the colour of the lane being logged (§2 lane hues).
 */
defineProps<{ lane: string }>();
</script>

<template>
    <BlueprintFrame class="border-t-[3px] p-0" :class="laneTone(lane).edge">
        <div
            class="grid gap-px overflow-hidden rounded-[inherit] bg-(--hairline-soft) [&>*]:bg-(--surface-card)"
        >
            <slot />
        </div>
    </BlueprintFrame>
</template>
