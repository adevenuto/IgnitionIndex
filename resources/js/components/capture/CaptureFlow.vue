<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import CaptureConfirmation from '@/components/capture/CaptureConfirmation.vue';
import CaptureRejectedDialog from '@/components/capture/CaptureRejectedDialog.vue';
import CaptureFieldInput from '@/components/capture/CaptureFieldInput.vue';
import CaptureStep from '@/components/capture/CaptureStep.vue';
import ReviewCard from '@/components/capture/ReviewCard.vue';
import ReviewRow from '@/components/capture/ReviewRow.vue';
import StepFlow from '@/components/capture/StepFlow.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useCaptureFlow } from '@/composables/useCaptureFlow';
import { useCaptureReader } from '@/composables/useCaptureReader';
import { provideVoiceInput } from '@/composables/useVoiceInput';
import type {
    CaptureContext,
    CaptureField,
    CaptureLane,
    CaptureReading,
} from '@/lib/capture/lanes';
import { describeFieldValue } from '@/lib/capture/lanes';
import { resolveSpeechLocally } from '@/lib/capture/resolveSpeech';
import { formatNumber } from '@/lib/format';

/*
 * Any lane's assisted entry: its steps, capture on each, then review.
 *
 * Everything lane-specific arrives as configuration (the lane) or through the
 * slots (whatever the review card needs beyond the lane's own fields). This
 * component sits inside the quick-add <Form> and posts its values as hidden
 * inputs, so a captured entry is submitted exactly like a typed one and hits
 * the same validation, guardrails and save path.
 *
 * Speech is heard by the server (useVoiceInput), then settled on the device
 * when it can be — one number for a one-field step — and read by the server
 * only when it cannot.
 */
const props = withDefaults(
    defineProps<{
        lane: CaptureLane;
        vehicleId: number;
        /** What is known about the vehicle: the lane's checks run against it. */
        context: CaptureContext;
        /** A one-tap value per field, e.g. the odometer estimate. */
        suggestions?: Record<string, { label: string; value: number }>;
        /** The quick-add form's server errors, by posted field name. */
        errors?: Record<string, string | undefined>;
    }>(),
    { suggestions: () => ({}), errors: () => ({}) },
);

const emit = defineEmits<{
    /** Everything a read returned, including fields outside the lane's steps. */
    reading: [reading: CaptureReading];
}>();

/** Lets the sheet show its Save button only once the flow reaches review. */
const atReview = defineModel<boolean>('atReview', { default: false });

const flow = useCaptureFlow(props.lane, () => props.context);
const reader = useCaptureReader(props.lane.id, () => props.vehicleId);
// Held here rather than per step, so the steps share one microphone.
provideVoiceInput(() => props.vehicleId);

const fieldsByKey = Object.fromEntries(
    props.lane.fields.map((field) => [field.key, field]),
) as Record<string, CaptureField>;

const stepFields = computed(() =>
    (flow.step.value?.fields ?? []).map((key) => fieldsByKey[key]),
);

const stepSuggestions = computed(() =>
    stepFields.value
        .filter((field) => props.suggestions[field.key] !== undefined)
        .map((field) => ({ field, ...props.suggestions[field.key] })),
);

const message = computed(() => reader.error.value ?? flow.note.value);

/** The photo being read, shown back under the scan line until the read ends. */
const scanning = ref<File | null>(null);

const reference = computed(() =>
    flow.step.value?.reference === 'lastOdometer' &&
    props.context.lastOdometer !== null
        ? `${formatNumber(props.context.lastOdometer)} mi`
        : null,
);

/** The last capture filled something on the step now showing. */
const capturedHere = computed(
    () =>
        flow.outcome.value?.applied.some((key) =>
            flow.step.value?.fields.includes(key),
        ) ?? false,
);

function filledAt(key: string): number | null {
    const outcome = flow.outcome.value;

    return outcome?.applied.includes(key) ? outcome.at : null;
}

watch(
    flow.atReview,
    (value) => {
        atReview.value = value;
    },
    { immediate: true },
);

async function onPhoto(file: File): Promise<void> {
    scanning.value = file;

    const reading = await reader.read(file).finally(() => {
        scanning.value = null;
    });

    if (reading) {
        flow.apply(reading, 'photo');
        emit('reading', reading);
    }
}

async function onSpeech(transcript: string): Promise<void> {
    const local = resolveSpeechLocally(stepFields.value, transcript);

    if (local) {
        reader.error.value = null;
        flow.apply({ fields: local, flags: [], note: null }, 'voice');

        return;
    }

    const reading = await reader.read(transcript);

    if (reading) {
        flow.apply(reading, 'voice');
        emit('reading', reading);
    }
}

function display(field: CaptureField): string | null {
    const raw = flow.values[field.key];

    return raw === '' ? null : describeFieldValue(field, Number(raw));
}
</script>

<template>
    <!-- What actually posts. Always present, whichever step is showing. -->
    <input
        v-for="field in lane.fields"
        :key="field.key"
        type="hidden"
        :name="field.name"
        :value="flow.values[field.key]"
    />

    <StepFlow
        :titles="lane.steps.map((step) => step.title)"
        :index="flow.stepIndex.value"
        @back="flow.back()"
        @next="flow.next()"
    >
        <CaptureStep
            v-if="flow.step.value"
            :key="flow.step.value.id"
            :step="flow.step.value"
            :busy="reader.reading.value"
            :scanning="scanning"
            :reference="reference"
            :message="message"
            :captured="capturedHere"
            @photo="onPhoto"
            @speech="onSpeech"
        >
            <CaptureFieldInput
                v-for="field in stepFields"
                :key="field.key"
                :field="field"
                :model-value="flow.values[field.key]"
                :provenance="flow.provenance[field.key]"
                :flag="flow.flags[field.key]"
                :filled-at="filledAt(field.key)"
                @update:model-value="
                    flow.set(field.key, String($event), 'typed')
                "
                @enter="flow.next()"
            />

            <template #status>
                <!-- Every field, not just this step's: one sentence can fill the next step too. -->
                <CaptureConfirmation
                    v-if="flow.outcome.value"
                    :outcome="flow.outcome.value"
                    :fields="lane.fields"
                    :values="flow.values"
                />
            </template>

            <template #after>
                <div
                    v-for="suggestion in stepSuggestions"
                    :key="suggestion.field.key"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="
                            flow.set(
                                suggestion.field.key,
                                String(suggestion.value),
                                'estimate',
                            )
                        "
                    >
                        {{ suggestion.label }}
                    </Button>
                </div>
            </template>
        </CaptureStep>

        <template v-else>
            <ReviewCard :lane="lane.id">
                <ReviewRow
                    v-for="field in lane.fields"
                    :key="field.key"
                    :label="field.label"
                    :value="display(field)"
                    :provenance="flow.provenance[field.key]"
                    :flag="flow.flags[field.key]"
                    @edit="flow.edit(field.key)"
                />
                <slot name="review-rows" :values="flow.values" />
            </ReviewCard>

            <div class="grid gap-1">
                <InputError
                    v-for="field in lane.fields"
                    :key="field.key"
                    :message="errors[field.name]"
                />
            </div>

            <slot name="review" />
        </template>
    </StepFlow>

    <!-- A refused capture is said over the sheet, where it cannot be missed. -->
    <CaptureRejectedDialog
        :outcome="flow.outcome.value"
        :fields="lane.fields"
    />
</template>
