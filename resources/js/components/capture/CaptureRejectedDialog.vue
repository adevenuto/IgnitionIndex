<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CaptureField, CaptureOutcome } from '@/lib/capture/lanes';
import { describeFieldValue } from '@/lib/capture/lanes';

/*
 * When a reading is refused — an odometer below the last one, whether spoken,
 * photographed, or typed and carried to Next — it is said in a pop-up over
 * the sheet, not a line of small print under the fields. The person was looking at the microphone, not the
 * form; a refusal they cannot miss is the point.
 *
 * It opens once per capture (keyed on the outcome's timestamp) and closing it
 * is the "try again": the step and its microphone are right there behind it.
 */
const props = defineProps<{
    outcome: CaptureOutcome | null;
    fields: CaptureField[];
}>();

const open = ref(false);

const item = computed(() => {
    const first = props.outcome?.rejected[0];
    const field = props.fields.find(
        (candidate) => candidate.key === first?.field,
    );

    if (!first || !field) {
        return null;
    }

    const value = describeFieldValue(field, first.value);
    const source = props.outcome?.source;
    const typed = source === 'typed';

    return {
        title: first.headline ?? `${value} doesn't look right`,
        detail: `${first.detail ?? first.message} ${
            typed
                ? 'Check the number and enter it again.'
                : 'Please try again, or type it in.'
        }`,
        said: `${
            typed
                ? 'Entered'
                : source === 'photo'
                  ? 'Read from the photo'
                  : 'Heard'
        }: ${value}`,
        action: typed ? 'Fix it' : 'Try again',
    };
});

/*
 * Closing lands in the field the reading belongs to — selected, so a typed
 * number can simply be retyped — rather than on whatever opened the pop-up.
 */
function returnToField(event: Event): void {
    const input = document.getElementById(
        `capture-${props.outcome?.rejected[0]?.field}`,
    );

    if (input instanceof HTMLInputElement) {
        event.preventDefault();
        input.focus();
        // Selected once the dialog has finished handing focus back, or the
        // selection is lost and a new number is typed onto the old one.
        requestAnimationFrame(() => input.select());
    }
}

watch(
    () => props.outcome?.at,
    () => {
        open.value = item.value !== null;
    },
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            v-if="item"
            class="sm:max-w-md"
            :show-close-button="false"
            @close-auto-focus="returnToField"
        >
            <DialogHeader class="items-center text-center sm:text-center">
                <span
                    class="bg-accent-100 text-accent-700 mb-(--space-2) flex size-12 items-center justify-center rounded-full"
                >
                    <CircleAlert class="size-6" :stroke-width="1.75" />
                </span>
                <DialogTitle class="text-h3">{{ item.title }}</DialogTitle>
                <DialogDescription class="text-body">
                    {{ item.detail }}
                </DialogDescription>
                <p class="ii-metadata mt-(--space-1)">{{ item.said }}</p>
            </DialogHeader>

            <DialogFooter>
                <Button
                    type="button"
                    size="lg"
                    class="w-full"
                    @click="open = false"
                >
                    {{ item.action }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
