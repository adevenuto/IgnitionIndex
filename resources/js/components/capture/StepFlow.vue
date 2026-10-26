<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/*
 * A stepper and nothing else: where you are, how far there is to go, and the
 * way back and forward. It knows no lane and no fields — the steps arrive as
 * titles, the content arrives through the slot, and the final position, one
 * past the last step, is review.
 *
 * Forward is offered on every step, filled or not. Every field can be left
 * for review, so a step never traps anyone behind a capture that failed. It
 * says where it goes ("Next: At the pump"), and the flow decides how far —
 * past any step a capture has already filled.
 *
 * The bar has one segment per step, matching "Step 1 of 2"; review fills them
 * all. The way back and forward sticks to the bottom of the sheet, so it stays
 * in reach however tall the step grows — except at review, where Save is the
 * way forward and a lone Back pinned over it would only cover the card.
 */
const props = defineProps<{
    titles: string[];
    /** 0-based; titles.length means review. */
    index: number;
}>();

const emit = defineEmits<{
    back: [];
    next: [];
}>();

const atReview = computed(() => props.index >= props.titles.length);
const label = computed(() =>
    atReview.value
        ? 'Review'
        : `Step ${props.index + 1} of ${props.titles.length} · ${props.titles[props.index]}`,
);
const nextLabel = computed(() => {
    const following = props.titles[props.index + 1];

    return following ? `Next: ${following}` : 'Review';
});
</script>

<template>
    <div class="flex flex-col gap-(--space-6)">
        <div class="grid gap-(--space-2)">
            <p class="ii-eyebrow" aria-live="polite">{{ label }}</p>
            <!-- One segment per step; filled up to here, and all of them at review. -->
            <div class="flex gap-1" aria-hidden="true">
                <span
                    v-for="segment in titles.length"
                    :key="segment"
                    class="h-1 flex-1 rounded-full transition-colors duration-[var(--dur-fast)]"
                    :class="
                        segment - 1 <= index
                            ? 'bg-accent'
                            : 'bg-(--color-divider)'
                    "
                />
            </div>
        </div>

        <slot />

        <div
            v-if="index > 0 || !atReview"
            class="flex items-center gap-3"
            :class="
                !atReview &&
                'bg-background sticky bottom-0 z-10 -mx-4 border-t border-(--color-divider) px-4 py-(--space-4)'
            "
        >
            <Button
                v-if="index > 0"
                type="button"
                variant="outline"
                size="lg"
                class="text-[14px]"
                @click="emit('back')"
            >
                <ArrowLeft :stroke-width="1.5" />
                Back
            </Button>
            <Button
                v-if="!atReview"
                type="button"
                size="lg"
                class="group flex-1 text-[14px]"
                @click="emit('next')"
            >
                {{ nextLabel }}
                <ArrowRight class="ii-arrow-nudge" :stroke-width="1.5" />
            </Button>
        </div>
    </div>
</template>
