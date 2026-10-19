<script setup lang="ts">
import CaptureButtons from '@/components/capture/CaptureButtons.vue';
import type { CaptureStep } from '@/lib/capture/lanes';

/*
 * One step's layout: what to capture, the two ways to capture it, anything
 * worth knowing about the last read, and the fields themselves — which come
 * through the slot, since a step may own one field or several.
 */
defineProps<{
    step: CaptureStep;
    busy: boolean;
    /** A failed read, or the reader's note about what it could not see. */
    message: string | null;
}>();

const emit = defineEmits<{
    photo: [file: File];
    speech: [transcript: string];
}>();
</script>

<template>
    <section class="flex flex-col gap-(--space-6)">
        <div class="grid gap-1">
            <h3 class="font-display text-h3">{{ step.title }}</h3>
            <p class="text-body text-(--color-neutral-700)">
                {{ step.prompt }}
            </p>
        </div>

        <CaptureButtons
            :busy="busy"
            @photo="emit('photo', $event)"
            @speech="emit('speech', $event)"
        />

        <p v-if="message" class="text-foreground text-sm" role="status">
            {{ message }}
        </p>

        <div class="grid gap-(--space-6)">
            <slot />
        </div>

        <slot name="after" />
    </section>
</template>
