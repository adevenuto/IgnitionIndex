<script setup lang="ts">
import { CircleAlert, CircleHelp } from '@lucide/vue';
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
 * A reading that a check stops is said in a pop-up over the sheet, not a line
 * of small print under the fields — the person was looking at the microphone,
 * the camera or the keypad, not the form.
 *
 * Two kinds:
 *   - refused: an odometer below the last reading. It cannot be right, so
 *     the only way on is to fix it (Try again / Fix it).
 *   - questioned: plausible but unlikely, like 140,000 mi on a car expected
 *     near 15,600. It might be right, so the person decides: "Yes, it's
 *     right" keeps it and is not asked again; the other button fixes it.
 *
 * Whatever was spoken, photographed or typed is named, so it is clear what
 * the pop-up is about. It opens once per outcome, keyed on its timestamp.
 */
const props = defineProps<{
    outcome: CaptureOutcome | null;
    fields: CaptureField[];
}>();

const emit = defineEmits<{
    /** The person says the questioned value is right. */
    keep: [fields: string[]];
    /** The person wants another go at a captured value: clear it. */
    retry: [fields: string[]];
}>();

const open = ref(false);

const item = computed(() => {
    const refused = props.outcome?.rejected[0];
    const asked = refused ? undefined : props.outcome?.questioned[0];
    const first = refused ?? asked;
    const field = props.fields.find(
        (candidate) => candidate.key === first?.field,
    );

    if (!first || !field) {
        return null;
    }

    const value = describeFieldValue(field, first.value);
    const source = props.outcome?.source;
    const typed = source === 'typed';
    const said = typed
        ? 'Entered'
        : source === 'photo'
          ? 'Read from the photo'
          : 'Heard';

    return {
        kind: refused ? ('refused' as const) : ('questioned' as const),
        field: first.field,
        title: first.headline ?? `${value} doesn't look right`,
        detail: refused
            ? `${first.detail ?? first.message} ${
                  typed
                      ? 'Check the number and enter it again.'
                      : 'Please try again, or type it in.'
              }`
            : `${first.detail ?? first.message} Is ${value} right?`,
        said: `${said}: ${value}`,
        fix: typed ? 'Fix it' : 'Try again',
        captured: !typed,
    };
});

/** How the pop-up was closed, so focus can go where the person is headed. */
let closedBy: 'fix' | 'keep' = 'fix';

function fix(): void {
    closedBy = 'fix';

    // A questioned capture was put in the field; another go starts it empty.
    if (item.value?.kind === 'questioned' && item.value.captured) {
        emit('retry', [item.value.field]);
    }

    open.value = false;
}

function keep(): void {
    closedBy = 'keep';

    if (item.value) {
        emit('keep', [item.value.field]);
    }

    open.value = false;
}

/*
 * Fixing lands in the field the reading belongs to — selected, so a typed
 * number can simply be retyped — rather than on whatever opened the pop-up.
 * Keeping it leaves focus to the flow, which has moved on.
 */
function returnToField(event: Event): void {
    if (closedBy === 'keep') {
        event.preventDefault();

        return;
    }

    const input = document.getElementById(`capture-${item.value?.field}`);

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
        closedBy = 'fix';
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
                    <CircleAlert
                        v-if="item.kind === 'refused'"
                        class="size-6"
                        :stroke-width="1.75"
                    />
                    <CircleHelp v-else class="size-6" :stroke-width="1.75" />
                </span>
                <DialogTitle class="text-h3">{{ item.title }}</DialogTitle>
                <DialogDescription class="text-body">
                    {{ item.detail }}
                </DialogDescription>
                <p class="ii-metadata mt-(--space-1)">{{ item.said }}</p>
            </DialogHeader>

            <DialogFooter class="gap-(--space-2)">
                <template v-if="item.kind === 'questioned'">
                    <Button
                        type="button"
                        size="lg"
                        variant="outline"
                        class="w-full sm:flex-1"
                        @click="fix"
                    >
                        {{ item.fix }}
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        class="w-full sm:flex-1"
                        @click="keep"
                    >
                        Yes, it's right
                    </Button>
                </template>

                <Button
                    v-else
                    type="button"
                    size="lg"
                    class="w-full"
                    @click="fix"
                >
                    {{ item.fix }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
