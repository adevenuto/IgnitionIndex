<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed, nextTick, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { injectSheetChrome } from '@/composables/useSheetChrome';

/*
 * A stepper and nothing else: where you are, how far there is to go, and the
 * way back and forward. It knows no lane and no fields — the steps arrive as
 * titles, the content arrives through the slot, and the final position, one
 * past the last step, is review.
 *
 * Forward waits on the step: until `canAdvance`, Next is dimmed, so no one
 * reaches the pump with no odometer behind them. It is dimmed rather than
 * disabled — a disabled button swallows the tap and says nothing — so a tap
 * still reaches the flow, which takes the person to what is missing. It says
 * where it goes ("Next: At the pump"), and the flow decides how far — past any
 * step a capture has already filled.
 *
 * The bar has one segment per step, matching "Step 1 of 2"; review fills them
 * all. Inside the quick-add sheet (useSheetChrome) the progress goes into the
 * sheet's fixed header and Back and Next into its pinned footer, so only the
 * step itself scrolls. Outside such a sheet both render here instead.
 */
const props = withDefaults(
    defineProps<{
        titles: string[];
        /** 0-based; titles.length means review. */
        index: number;
        /** The step showing is complete enough to leave forwards. */
        canAdvance?: boolean;
    }>(),
    { canAdvance: true },
);

const emit = defineEmits<{
    back: [];
    next: [];
}>();

const chrome = injectSheetChrome();
const inSheet = chrome !== null;
/** The mount points, once the sheet has rendered them. */
const headerEl = computed(() => chrome?.header.value ?? null);
const footerEl = computed(() => chrome?.footer.value ?? null);

const atReview = computed(() => props.index >= props.titles.length);
/** The step's name, large, with where it sits in the flow beside it. */
const current = computed(() =>
    atReview.value ? 'Review' : props.titles[props.index],
);
const counter = computed(() =>
    atReview.value
        ? 'Ready to save'
        : `Step ${props.index + 1} of ${props.titles.length}`,
);
const nextLabel = computed(() => {
    const following = props.titles[props.index + 1];

    return following ? `Next: ${following}` : 'Review';
});

// A new step starts at its heading, not wherever the last one was scrolled to.
watch(
    () => props.index,
    async () => {
        await nextTick();
        chrome?.body.value?.scrollTo({ top: 0 });
    },
);
</script>

<template>
    <div class="flex flex-col gap-(--space-6)">
        <!-- Into the sheet's fixed header once it exists; here without one. -->
        <Teleport
            v-if="headerEl || !inSheet"
            :to="headerEl ?? 'body'"
            :disabled="!inSheet"
        >
            <div class="grid gap-(--space-3)">
                <div
                    class="flex items-baseline justify-between gap-(--space-3)"
                    aria-live="polite"
                >
                    <p
                        class="font-display text-foreground text-[17px] font-semibold"
                    >
                        {{ current }}
                    </p>
                    <p
                        class="shrink-0 text-sm text-(--color-neutral-700) tabular-nums"
                    >
                        {{ counter }}
                    </p>
                </div>
                <!-- One segment per step; filled up to here, and all of them at review. -->
                <div class="flex gap-1.5" aria-hidden="true">
                    <span
                        v-for="segment in titles.length"
                        :key="segment"
                        class="h-1.5 flex-1 rounded-full transition-colors duration-[var(--dur-fast)]"
                        :class="
                            segment - 1 <= index
                                ? 'bg-accent'
                                : 'bg-(--color-divider)'
                        "
                    />
                </div>
            </div>
        </Teleport>

        <slot />

        <!-- Into the sheet's footer once it exists; pinned here without one. -->
        <Teleport v-if="footerEl" :to="footerEl">
            <template v-if="index > 0 || !atReview">
                <Button
                    v-if="index > 0"
                    type="button"
                    variant="outline"
                    size="lg"
                    @click="emit('back')"
                >
                    <ArrowLeft :stroke-width="1.5" />
                    Back
                </Button>
                <Button
                    v-if="!atReview"
                    type="button"
                    size="lg"
                    class="group flex-1 aria-disabled:opacity-60"
                    :aria-disabled="!canAdvance"
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
                @click="emit('back')"
            >
                <ArrowLeft :stroke-width="1.5" />
                Back
            </Button>
            <Button
                v-if="!atReview"
                type="button"
                size="lg"
                class="group flex-1 aria-disabled:opacity-60"
                :aria-disabled="!canAdvance"
                @click="emit('next')"
            >
                {{ nextLabel }}
                <ArrowRight class="ii-arrow-nudge" :stroke-width="1.5" />
            </Button>
        </div>
    </div>
</template>
