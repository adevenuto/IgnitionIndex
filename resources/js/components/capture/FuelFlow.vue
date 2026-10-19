<script setup lang="ts">
import { computed, ref } from 'vue';
import CaptureFlow from '@/components/capture/CaptureFlow.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CaptureReading } from '@/lib/capture/lanes';
import { fuelLane } from '@/lib/capture/fuel';
import { formatNumber, todayIso } from '@/lib/format';
import type { QuickAddVehicle } from '@/types/garage';

/*
 * The fuel lane, assisted: the shared flow with fuel's configuration, plus the
 * three things fuel keeps on its review card rather than in a step — the date,
 * which is almost always today; whether the tank was filled, almost always
 * yes; and notes, almost always none.
 *
 * The odometer estimate is offered as a tap, never pre-filled. An untouched
 * estimate saved as a reading would feed back into the very estimate it came
 * from, and the projection would quietly drift from the car.
 */
const props = defineProps<{
    vehicle: QuickAddVehicle;
    errors: Record<string, string | undefined>;
}>();

const atReview = defineModel<boolean>('atReview', { default: false });

// Held here rather than in the inputs: the review card unmounts whenever a
// row is tapped to go back, and these must survive the round trip.
const occurredOn = ref(todayIso());
const fullTank = ref(true);
const notes = ref('');

const suggestions = computed(
    (): Record<string, { label: string; value: number }> => {
        const projected = props.vehicle.projected_odometer;
        const last = props.vehicle.last_odometer;

        // With no movement since the last reading there is nothing to suggest:
        // the car has surely moved, and offering the old number invites a stale one.
        if (projected == null || last == null || projected <= last) {
            return {};
        }

        return {
            odometer: {
                label: `Use the estimate, ≈${formatNumber(projected)} mi`,
                value: projected,
            },
        };
    },
);

/** A receipt or a sentence can carry the date; a pump photo rarely does. */
function onReading(reading: CaptureReading): void {
    const date = reading.fields.occurred_on;

    if (typeof date === 'string') {
        occurredOn.value = date;
    }
}

function perGallon(values: Record<string, string>): string | null {
    const gallons = Number(values.gallons);
    const total = Number(values.total_cost);

    return gallons > 0 && total > 0
        ? `$${(total / gallons).toFixed(3)} / gal`
        : null;
}
</script>

<template>
    <CaptureFlow
        v-model:at-review="atReview"
        :lane="fuelLane"
        :vehicle-id="vehicle.id"
        :suggestions="suggestions"
        :errors="errors"
        @reading="onReading"
    >
        <template #review-rows="{ values }">
            <div
                v-if="perGallon(values)"
                class="flex items-baseline justify-between gap-3 px-(--space-6) py-(--space-3)"
            >
                <span class="ii-eyebrow">Price per gallon</span>
                <span class="text-body tabular-nums">{{
                    perGallon(values)
                }}</span>
            </div>
        </template>

        <template #review>
            <div class="grid gap-1.5">
                <Label for="occurred_on">Date</Label>
                <Input
                    id="occurred_on"
                    v-model="occurredOn"
                    name="occurred_on"
                    type="date"
                    required
                />
                <InputError :message="errors.occurred_on" />
            </div>

            <div class="flex items-center gap-2.5">
                <Checkbox
                    id="full_tank"
                    v-model="fullTank"
                    name="full_tank"
                    value="1"
                />
                <!-- Clicking the text toggles the box, so it says so. -->
                <Label
                    for="full_tank"
                    class="text-body cursor-pointer font-normal"
                >
                    Filled the tank
                    <span class="text-muted-foreground">
                        — needed for MPG
                    </span>
                </Label>
            </div>

            <div class="grid gap-1.5">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    v-model="notes"
                    name="notes"
                    rows="2"
                    placeholder="Optional"
                />
            </div>
        </template>
    </CaptureFlow>
</template>
