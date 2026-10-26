<script setup lang="ts">
import { Square } from '@lucide/vue';
import { computed } from 'vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { injectVoiceInput } from '@/composables/useVoiceInput';

/*
 * What the microphone is hearing, while it hears it.
 *
 * The bars are the real input level, not a loop: silence keeps them flat, so
 * a muted or wrong microphone shows itself at once instead of after a wasted
 * recording. Each bar carries a fixed weight and a slow phase of its own so
 * one level reads as a waveform rather than a single block rising and falling.
 *
 * The level is data, so it still moves for those who ask for less motion; only
 * the easing between readings goes.
 */
const voice = injectVoiceInput();

const WEIGHTS = [0.35, 0.55, 0.75, 0.9, 1, 0.9, 0.75, 0.55, 0.35];
const FLOOR = 0.06;

const bars = computed(() => {
    const level = voice.level.value;
    const now = performance.now() / 180;

    return WEIGHTS.map((weight, index) => {
        const wobble = 0.75 + 0.25 * Math.sin(now + index * 1.7);

        return Math.max(FLOOR, Math.min(1, level * weight * wobble));
    });
});
</script>

<template>
    <BlueprintFrame
        class="grid gap-(--space-4) p-(--space-6)"
        role="status"
        aria-live="polite"
    >
        <p class="ii-eyebrow flex items-center gap-2">
            <template v-if="voice.transcribing.value">
                <Spinner class="size-3.5" />
                Hearing it
            </template>
            <template v-else>
                <span class="bg-accent size-2" aria-hidden="true" />
                Listening · {{ voice.remaining.value }} s
            </template>
        </p>

        <div
            class="flex h-14 items-center justify-center gap-1.5"
            aria-hidden="true"
        >
            <span
                v-for="(height, index) in bars"
                :key="index"
                class="bg-accent w-1.5 motion-safe:transition-[height] motion-safe:duration-[var(--dur-fast)]"
                :class="voice.transcribing.value && 'opacity-45'"
                :style="{
                    height: `${(voice.transcribing.value ? FLOOR : height) * 100}%`,
                }"
            />
        </div>

        <Button
            type="button"
            class="w-full"
            :disabled="voice.transcribing.value"
            @click="voice.stop()"
        >
            <Square :stroke-width="1.5" />
            Done
        </Button>
    </BlueprintFrame>
</template>
