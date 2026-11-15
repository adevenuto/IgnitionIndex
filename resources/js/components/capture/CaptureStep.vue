<script setup lang="ts">
import CaptureMethods from '@/components/capture/CaptureMethods.vue';
import ScanOverlay from '@/components/capture/ScanOverlay.vue';
import VoiceMeter from '@/components/capture/VoiceMeter.vue';
import { injectVoiceInput } from '@/composables/useVoiceInput';
import type { CaptureStep } from '@/lib/capture/lanes';

/*
 * One step's layout, in the order it is read: what the step asks for and why,
 * the fields themselves — which come through the slot, since a step may own
 * one field or several — and then, set apart, the ways to have them filled in
 * for you. Typing is the one way that always works, so it comes first; voice
 * and a photo are extras after it.
 *
 * What the last capture did sits between the two, directly under the fields
 * it filled, so a value and where it came from are read together.
 *
 * While a photo is being read or the microphone is open, that takes the
 * methods' place: the person is mid-capture and the other way in would only be
 * in the way. The voice round trip is run from here rather than from the
 * cards, because the cards are gone while it runs.
 */
defineProps<{
    step: CaptureStep;
    /** A read is in flight. */
    busy: boolean;
    /** The photo being read, while it is. */
    scanning: File | null;
    /** The step's reference value, ready to show, e.g. "62,000 mi". */
    reference: string | null;
    /** A failed read, or the reader's note about what it could not see. */
    message: string | null;
    /** A capture has just filled this step, so the methods step back. */
    captured: boolean;
}>();

const emit = defineEmits<{
    photo: [file: File];
    speech: [transcript: string];
}>();

const voice = injectVoiceInput();

async function onVoice(): Promise<void> {
    const heard = await voice.listen();

    if (heard) {
        emit('speech', heard);
    }
}
</script>

<template>
    <section class="flex flex-col gap-(--space-6)">
        <div class="grid gap-(--space-2)">
            <h3 class="font-display text-h3">{{ step.heading }}</h3>
            <p class="text-body text-(--color-neutral-700)">
                {{ step.description }}
            </p>
            <p
                v-if="reference"
                class="justify-self-start rounded-full border border-(--color-divider) px-2.5 py-0.5 text-sm text-(--color-neutral-700) tabular-nums"
            >
                Last reading: {{ reference }}
            </p>
        </div>

        <div class="grid gap-(--space-6)">
            <slot />
        </div>

        <slot name="after" />

        <!-- What the last capture did: under the fields it filled. -->
        <slot name="status" />

        <div class="grid gap-(--space-4)">
            <div class="flex items-center gap-(--space-3)" aria-hidden="true">
                <span class="h-px flex-1 bg-(--color-divider)" />
                <span class="ii-eyebrow">Or skip the typing</span>
                <span class="h-px flex-1 bg-(--color-divider)" />
            </div>

            <ScanOverlay v-if="scanning && busy" :file="scanning" />
            <VoiceMeter
                v-else-if="voice.listening.value || voice.transcribing.value"
            />
            <CaptureMethods
                v-else
                :step="step"
                :busy="busy"
                :compact="captured"
                @photo="emit('photo', $event)"
                @voice="onVoice"
            />

            <p
                v-if="voice.error.value || message"
                class="text-foreground text-sm"
                role="status"
            >
                {{ voice.error.value ?? message }}
            </p>
        </div>
    </section>
</template>
