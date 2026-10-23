<script setup lang="ts">
import { Camera, Mic, Square } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { injectVoiceInput } from '@/composables/useVoiceInput';

/*
 * Snap and Say it. These only gather input — a photo file, or the words heard
 * — and hand it up; deciding what it means belongs to the flow.
 *
 * Snap is a plain file input with capture="environment", which opens the rear
 * camera on a phone without any permission prompt of our own and falls back to
 * a file picker on a desktop. Say it records a clip and has the server hear it
 * (useVoiceInput); it only exists where the browser can record and voice is on.
 */
defineProps<{
    /** A read is in flight; both buttons wait for it. */
    busy: boolean;
}>();

const emit = defineEmits<{
    photo: [file: File];
    speech: [transcript: string];
}>();

const speech = injectVoiceInput();
const fileInput = ref<HTMLInputElement | null>(null);

function onFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Cleared so the same photo can be chosen again after a failed read.
    input.value = '';

    if (file) {
        emit('photo', file);
    }
}

async function onSpeak(): Promise<void> {
    if (speech.listening.value) {
        speech.stop();

        return;
    }

    const heard = await speech.listen();

    if (heard) {
        emit('speech', heard);
    }
}
</script>

<template>
    <div class="grid gap-(--space-2)">
        <div class="flex flex-wrap gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="
                    busy || speech.listening.value || speech.transcribing.value
                "
                @click="fileInput?.click()"
            >
                <Spinner v-if="busy" />
                <Camera v-else :stroke-width="1.5" />
                {{ busy ? 'Reading…' : 'Snap' }}
            </Button>

            <Button
                v-if="speech.supported"
                type="button"
                variant="outline"
                :disabled="busy || speech.transcribing.value"
                :aria-pressed="speech.listening.value"
                :class="speech.listening.value && 'border-accent text-accent'"
                @click="onSpeak"
            >
                <Spinner v-if="speech.transcribing.value" />
                <Square
                    v-else-if="speech.listening.value"
                    :stroke-width="1.5"
                />
                <Mic v-else :stroke-width="1.5" />
                {{
                    speech.transcribing.value
                        ? 'Hearing…'
                        : speech.listening.value
                          ? 'Listening… tap to stop'
                          : 'Say it'
                }}
            </Button>

            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png"
                capture="environment"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                @change="onFile"
            />
        </div>

        <p
            v-if="speech.error.value"
            class="text-meta text-(--color-neutral-700)"
        >
            {{ speech.error.value }}
        </p>
    </div>
</template>
