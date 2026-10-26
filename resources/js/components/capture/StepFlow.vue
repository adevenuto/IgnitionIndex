<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { injectSheetFooter } from '@/composables/useSheetFooter';

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
 * all. The way back and forward goes into the sheet's pinned footer
 * (useSheetFooter), beside Save on review, so the whole sheet has one bar at
 * the bottom. Outside such a sheet it pins itself instead.
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

const footer = injectSheetFooter();
const inSheet = footer !== null;
/** The footer's mount point, once the sheet has rendered it. */
const footerEl = computed(() => footer?.value ?? null);

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

        <!-- Into the sheet's footer once it exists; pinned here without one. -->
        <Teleport v-if="footerEl" :to="footerEl">
            <template v-if="index > 0 || !atReview">
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
            </template>
        </Teleport>

        <div
            v-if="!inSheet && (index > 0 || !atReview)"
            class="bg-background sticky bottom-0 z-10 -mx-4 flex items-center gap-3 border-t border-(--color-divider) px-4 py-(--space-4)"
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
