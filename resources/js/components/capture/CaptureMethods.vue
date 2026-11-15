<script setup lang="ts">
import { Camera, ChevronRight, Mic } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { Button } from '@/components/ui/button';
import { injectVoiceInput } from '@/composables/useVoiceInput';
import type { CaptureMethod, CaptureStep } from '@/lib/capture/lanes';
import { CAPTURE_METHODS } from '@/lib/capture/lanes';

/*
 * The ways to have a step filled in for you, offered after its fields: the
 * voice, then a photo. Typing is not among them — the fields come first and
 * are always there — so these read as extras, which is what they are (and may
 * one day be a paid tier: the list is CAPTURE_METHODS, so gating one is a
 * filter here). They only say which was chosen — the photo file itself, or a
 * request to listen — and the step decides what happens next.
 *
 * Snap is a plain file input with capture="environment", which opens the rear
 * camera on a phone without any permission prompt of our own and falls back to
 * a file picker on a desktop. Say it only exists where the browser can record
 * and voice is turned on.
 *
 * Once a capture has filled the step, the cards fold into one row: the value
 * and what was heard or read are what matter then. Trying again is still one tap.
 */
const props = defineProps<{
    step: CaptureStep;
    /** A read or a recording is in flight; nothing else starts meanwhile. */
    busy: boolean;
    /** The step has just been filled by a capture: show the one-row form. */
    compact?: boolean;
}>();

const emit = defineEmits<{
    photo: [file: File];
    voice: [];
}>();

const voice = injectVoiceInput();
const fileInput = ref<HTMLInputElement | null>(null);

type Method = {
    key: CaptureMethod;
    icon: Component;
    title: string;
    tag: string;
    line: string;
};

const details: Record<CaptureMethod, Omit<Method, 'key' | 'line'>> = {
    voice: { icon: Mic, title: 'Say it', tag: 'Hands-free' },
    photo: { icon: Camera, title: 'Snap it', tag: 'Fastest' },
};

const methods = computed((): Method[] =>
    CAPTURE_METHODS.filter((key) => key !== 'voice' || voice.supported).map(
        (key) => ({ key, ...details[key], line: props.step.cards[key] }),
    ),
);

function choose(method: CaptureMethod): void {
    if (method === 'photo') {
        fileInput.value?.click();
    } else {
        emit('voice');
    }
}

function onFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Cleared so the same photo can be chosen again after a failed read.
    input.value = '';

    if (file) {
        emit('photo', file);
    }
}
</script>

<template>
    <div
        v-if="compact"
        class="grid gap-(--space-3)"
        :class="methods.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
    >
        <Button
            v-for="method in methods"
            :key="method.key"
            type="button"
            variant="outline"
            :disabled="busy"
            @click="choose(method.key)"
        >
            <component :is="method.icon" :stroke-width="1.5" />
            {{ method.title }}
        </Button>
    </div>

    <div v-else class="grid gap-(--space-4) sm:grid-cols-2">
        <BlueprintFrame
            v-for="method in methods"
            :key="method.key"
            as="button"
            type="button"
            interactive
            :disabled="busy"
            class="group focus-visible:border-accent focus-visible:ring-ring/50 flex min-h-16 w-full cursor-pointer items-center gap-(--space-4) p-(--space-4) text-left outline-none hover:bg-[color-mix(in_oklab,var(--color-accent)_8%,var(--surface-card))] focus-visible:ring-[3px] active:bg-[color-mix(in_oklab,var(--color-accent)_14%,var(--surface-card))] disabled:cursor-not-allowed disabled:opacity-45"
            @click="choose(method.key)"
        >
            <span
                class="flex size-12 shrink-0 items-center justify-center rounded-md bg-(--tile-brand-bg) text-(--tile-brand-ink)"
                aria-hidden="true"
            >
                <component
                    :is="method.icon"
                    class="size-5"
                    :stroke-width="1.5"
                />
            </span>

            <span class="grid min-w-0 flex-1 gap-0.5">
                <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-display text-title">{{
                        method.title
                    }}</span>
                    <span
                        class="ii-eyebrow bg-muted rounded-full px-2 py-0.5"
                        >{{ method.tag }}</span
                    >
                </span>
                <span class="text-sm text-(--color-neutral-700)">{{
                    method.line
                }}</span>
            </span>

            <ChevronRight
                class="ii-arrow-nudge group-hover:text-accent size-4 shrink-0 text-(--color-neutral-700)"
                :stroke-width="1.5"
                aria-hidden="true"
            />
        </BlueprintFrame>
    </div>

    <!-- Outside both forms, so the picker is there whichever is showing. -->
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
</template>
