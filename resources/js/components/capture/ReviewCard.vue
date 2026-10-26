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
            class="grid gap-px overflow-hidden rounded-[inherit] bg-(--hairline-soft) [&>*]:bg-(--surface-card)"
        >
            <slot />
        </div>
    </BlueprintFrame>
</template>
