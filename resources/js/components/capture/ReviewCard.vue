<script setup lang="ts">
import { BlueprintFrame } from '@/components/ui/blueprint';

/*
 * The last stop before saving: every value on one card, ruled like the gauge
 * list (gap-px over a divider-coloured ground, per §6) so the last row does
 * not double up against the card's own border. Rows arrive through the slot.
 */
</script>

<template>
    <BlueprintFrame class="p-0">
        <div
            class="grid gap-px bg-(--color-divider) [&>*]:bg-(--color-neutral-100)"
        >
            <slot />
        </div>
    </BlueprintFrame>
</template>
