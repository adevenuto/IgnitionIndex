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
 * for review, so a step never traps anyone behind a capture that failed.
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

const total = computed(() => props.titles.length + 1);
const atReview = computed(() => props.index >= props.titles.length);
const label = computed(() =>
    atReview.value
        ? 'Review'
        : `Step ${props.index + 1} of ${props.titles.length} · ${props.titles[props.index]}`,
);
</script>

<template>
    <div class="flex flex-col gap-(--space-6)">
        <div class="grid gap-(--space-2)">
            <p class="ii-eyebrow" aria-live="polite">{{ label }}</p>
            <!-- One segment per step plus review; filled up to here. -->
            <div class="flex gap-1" aria-hidden="true">
                <span
                    v-for="segment in total"
                    :key="segment"
                    class="h-1 flex-1 transition-colors duration-[var(--dur-fast)]"
                    :class="
                        segment - 1 <= index
                            ? 'bg-accent'
                            : 'bg-(--color-divider)'
                    "
                />
            </div>
        </div>

        <slot />

        <div v-if="index > 0 || !atReview" class="flex items-center gap-3">
            <Button
                v-if="index > 0"
                type="button"
                variant="ghost"
                @click="emit('back')"
            >
                <ArrowLeft :stroke-width="1.5" />
                Back
            </Button>
            <Button
                v-if="!atReview"
                type="button"
                class="ml-auto"
                @click="emit('next')"
            >
                Next
                <ArrowRight :stroke-width="1.5" />
            </Button>
        </div>
    </div>
</template>
