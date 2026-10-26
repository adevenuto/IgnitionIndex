<script setup lang="ts">
import { Camera, ChevronRight, Keyboard, Mic } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { Button } from '@/components/ui/button';
import { injectVoiceInput } from '@/composables/useVoiceInput';
import type { CaptureStep } from '@/lib/capture/lanes';

/*
 * The three ways to fill a step, as cards large enough to hit one-handed at a
 * pump: a photo, the voice, or the keyboard. These only say which was chosen —
 * the photo file itself, or a request to listen or to type — and the step
 * decides what happens next.
 *
 * Snap is a plain file input with capture="environment", which opens the rear
 * camera on a phone without any permission prompt of our own and falls back to
 * a file picker on a desktop. Say it only exists where the browser can record
 * and voice is turned on.
 *
 * Once a capture has filled the step, the cards fold into one row: the value
 * and what was heard or read are what matter then, and three tall cards would
 * push both below the fold. Trying again is still one tap.
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
    type: [];
}>();

const voice = injectVoiceInput();
const fileInput = ref<HTMLInputElement | null>(null);

type Method = {
    key: 'photo' | 'voice' | 'type';
    icon: Component;
    title: string;
    tag?: string;
    line: string;
};

const methods = computed((): Method[] =>
    [
        {
            key: 'photo' as const,
            icon: Camera,
            title: 'Snap',
            tag: 'Fastest',
            line: props.step.cards.photo,
        },
        {
            key: 'voice' as const,
            icon: Mic,
            title: 'Say it',
            tag: 'Hands-free',
            line: props.step.cards.voice,
        },
        {
            key: 'type' as const,
            icon: Keyboard,
            title: 'Type it',
            line: props.step.cards.type,
        },
    ].filter((method) => method.key !== 'voice' || voice.supported),
);

function choose(method: Method['key']): void {
    if (method === 'photo') {
        fileInput.value?.click();
    } else if (method === 'voice') {
        emit('voice');
    } else {
        emit('type');
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
        :class="methods.length === 3 ? 'grid-cols-3' : 'grid-cols-2'"
    >
        <Button
            v-for="method in methods"
            :key="method.key"
            type="button"
            variant="outline"
            :disabled="busy && method.key !== 'type'"
            @click="choose(method.key)"
        >
            <component :is="method.icon" :stroke-width="1.5" />
            {{ method.title }}
        </Button>
    </div>

    <div v-else class="grid gap-(--space-4)">
        <BlueprintFrame
            v-for="method in methods"
            :key="method.key"
            as="button"
            type="button"
            interactive
            :disabled="busy && method.key !== 'type'"
            class="group focus-visible:border-accent focus-visible:ring-ring/50 flex min-h-16 w-full cursor-pointer items-center gap-(--space-4) p-(--space-4) text-left outline-none hover:bg-[color-mix(in_oklab,var(--color-accent)_8%,var(--color-neutral-100))] focus-visible:ring-[3px] active:bg-[color-mix(in_oklab,var(--color-accent)_14%,var(--color-neutral-100))] disabled:cursor-not-allowed disabled:opacity-45"
            @click="choose(method.key)"
        >
            <span
                class="text-accent group-hover:border-accent flex size-12 shrink-0 items-center justify-center border border-(--color-divider) bg-(--color-bg) transition-colors duration-[var(--dur-fast)]"
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
                        v-if="method.tag"
                        class="ii-eyebrow border border-(--color-divider) px-1.5 py-px"
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
