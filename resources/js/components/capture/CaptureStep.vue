<script setup lang="ts">
import { ref } from 'vue';
import CaptureMethods from '@/components/capture/CaptureMethods.vue';
import ScanOverlay from '@/components/capture/ScanOverlay.vue';
import VoiceMeter from '@/components/capture/VoiceMeter.vue';
import { injectVoiceInput } from '@/composables/useVoiceInput';
import type { CaptureStep } from '@/lib/capture/lanes';

/*
 * One step's layout: what to capture and what to check it against, the ways
 * to capture it, the fields themselves — which come through the slot, since a
 * step may own one field or several — and what the last capture did, which
 * sits directly under the methods so a refusal is seen without scrolling.
 *
 * While a photo is being read or the microphone is open, that takes the
 * methods' place: the person is mid-capture and the other ways in would only
 * be in the way. The voice round trip is run from here rather than from the
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
const fieldsEl = ref<HTMLElement | null>(null);

async function onVoice(): Promise<void> {
    const heard = await voice.listen();

    if (heard) {
        emit('speech', heard);
    }
}

function onType(): void {
    fieldsEl.value?.querySelector('input')?.focus();
}
</script>

<template>
    <section class="flex flex-col gap-(--space-6)">
        <div class="grid gap-1">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-display text-h3">{{ step.title }}</h3>
                <p
                    v-if="reference"
                    class="mt-1 shrink-0 border border-(--color-divider) px-2 py-0.5 text-sm text-(--color-neutral-700) tabular-nums"
                >
                    Last: {{ reference }}
                </p>
            </div>
            <p class="text-body text-(--color-neutral-700)">
                {{ step.prompt }}
            </p>
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
            @type="onType"
        />

        <p
            v-if="voice.error.value || message"
            class="text-foreground text-sm"
            role="status"
        >
            {{ voice.error.value ?? message }}
        </p>

        <!-- What the last capture did: right under the methods, where the eye already is. -->
        <slot name="status" />

        <div ref="fieldsEl" class="grid gap-(--space-6)">
            <slot />
        </div>

        <slot name="after" />
    </section>
</template>
