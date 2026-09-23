<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GaugeDial from '@/components/GaugeDial.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateInterval } from '@/actions/App/Http/Controllers/VehicleIntervalController';
import { todayIso } from '@/lib/format';
import type { Gauge } from '@/types/gauges';

/*
 * Adjust one gauge in place.
 *
 * A modal rather than a route: setting a last-done date is a few seconds of
 * work, and navigating away from the cluster to do it loses the context of the
 * other gauges you were comparing it against.
 *
 * The rough buckets fill the date field rather than replacing it. Nobody
 * remembers the date of their last oil change, but the field stays there for
 * anyone who does — or who wants to correct a bucket afterwards.
 */
const props = defineProps<{
    gauge: Gauge | null;
    projectedOdometer: number;
}>();

const open = defineModel<boolean>('open', { required: true });

const lastDoneAt = ref('');
const lastDoneOdometer = ref('');

/*
 * The time interval is stored as a single `interval_months`, but nobody thinks
 * in "60 months" — they think in years, with months for the remainder. These
 * two fields are a presentation of that one number; the hidden input below puts
 * them back together, so the server contract is unchanged.
 */
const intervalYears = ref('');
const intervalMonths = ref('');
const intervalMiles = ref('');

/*
 * "I have never had this done."
 *
 * Distinct from uncalibrated, which means we do not know. Never is knowledge:
 * the interval has been running since the car was new, so the mileage axis
 * counts from odometer 0 and the gauge can finally say something.
 *
 * Stored as last_done_odometer = 0 with no date — no new column, because that
 * pair already means exactly this to IntervalProgress.
 */
const neverDone = ref(false);

/**
 * Only where it can produce a reading.
 *
 * The time axis is deliberately left out of "never": we know the odometer, but
 * nothing tells us when the car entered service, and IntervalProgress does not
 * guess dates — that is the rule that stops it announcing an oil change is
 * overdue when it was done last week. So on a gauge with no mileage interval,
 * "Never" could only leave it exactly as uncalibrated as it already was, and a
 * control that cannot do anything should not be offered.
 */
const canBeNever = computed(() => Number(intervalMiles.value) > 0);

function splitInterval(months: number | null): void {
    if (months === null) {
        intervalYears.value = '';
        intervalMonths.value = '';

        return;
    }

    const years = Math.floor(months / 12);
    const rest = months % 12;

    // Blank rather than "0" for an empty part: a zero reads as a value someone
    // chose, and two of them look like a interval of nothing.
    intervalYears.value = years === 0 ? '' : String(years);
    intervalMonths.value = rest === 0 ? '' : String(rest);
}

/**
 * The two fields as the one number the server stores.
 *
 * Empty when they add up to nothing, so clearing both means "no time interval"
 * rather than an interval of zero — which `min:1` would reject and which the
 * user would read as an error for having emptied a field on purpose.
 */
const intervalMonthsTotal = computed(() => {
    const total =
        (Number(intervalYears.value) || 0) * 12 +
        (Number(intervalMonths.value) || 0);

    return total === 0 ? '' : String(total);
});

const BUCKETS: { label: string; months: number }[] = [
    { label: 'This month', months: 0 },
    { label: '1–3 mo', months: 2 },
    { label: '3–6 mo', months: 4 },
    { label: '6–12 mo', months: 9 },
    { label: 'Over a year', months: 18 },
];

// Reseed from the gauge each time it opens, so reopening never shows the
// previous gauge's answers.
watch(
    () => [open.value, props.gauge?.id],
    () => {
        if (open.value && props.gauge) {
            lastDoneAt.value = props.gauge.last_done_at ?? '';
            lastDoneOdometer.value =
                props.gauge.last_done_odometer === null
                    ? ''
                    : String(props.gauge.last_done_odometer);
            splitInterval(props.gauge.interval_months);
            intervalMiles.value =
                props.gauge.interval_miles === null
                    ? ''
                    : String(props.gauge.interval_miles);
            // The stored signature of "never": counted from new, with no date.
            neverDone.value =
                props.gauge.last_done_at === null &&
                props.gauge.last_done_odometer === 0;
        }
    },
    { immediate: true },
);

function toggleNever(): void {
    neverDone.value = !neverDone.value;

    // Odometer 0 is the claim being made; the date is cleared because "never"
    // has no date, and leaving the old one would contradict it.
    lastDoneAt.value = '';
    lastDoneOdometer.value = neverDone.value ? '0' : '';
}

function chooseBucket(months: number): void {
    neverDone.value = false;

    const date = new Date();
    date.setMonth(date.getMonth() - months);

    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    lastDoneAt.value = `${date.getFullYear()}-${month}-${day}`;
}

const intervalSummary = computed(() => {
    if (!props.gauge) {
        return '';
    }

    const parts: string[] = [];

    if (props.gauge.interval_miles) {
        parts.push(`${props.gauge.interval_miles.toLocaleString()} mi`);
    }

    if (props.gauge.interval_months) {
        parts.push(inYearsAndMonths(props.gauge.interval_months));
    }

    return parts.length ? `Every ${parts.join(' or ')}` : 'No interval set';
});

/** "5 years", "18 months", "1 year 6 months" — never "60 months". */
function inYearsAndMonths(months: number): string {
    const years = Math.floor(months / 12);
    const rest = months % 12;
    const said: string[] = [];

    if (years > 0) {
        said.push(`${years} ${years === 1 ? 'year' : 'years'}`);
    }

    if (rest > 0 || years === 0) {
        said.push(`${rest} ${rest === 1 ? 'month' : 'months'}`);
    }

    return said.join(' ');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="gauge" class="sm:max-w-md">
            <DialogHeader class="text-left">
                <div class="flex items-center gap-3">
                    <GaugeDial
                        class="h-[46px] w-[58px] flex-none"
                        variant="mini"
                        :percent="gauge.raw_progress * 100"
                        :status="gauge.display_status"
                    />
                    <div class="min-w-0">
                        <DialogTitle class="text-h3">
                            {{ gauge.name }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ intervalSummary }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <Form
                v-bind="updateInterval.form(gauge.id)"
                :options="{ preserveScroll: true }"
                class="flex flex-col gap-5"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label>When was this last done?</Label>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="bucket in BUCKETS"
                            :key="bucket.label"
                            type="button"
                            size="sm"
                            variant="surface"
                            @click="chooseBucket(bucket.months)"
                        >
                            {{ bucket.label }}
                        </Button>

                        <Button
                            v-if="canBeNever"
                            type="button"
                            size="sm"
                            :variant="neverDone ? 'default' : 'surface'"
                            :aria-pressed="neverDone"
                            @click="toggleNever()"
                        >
                            Never
                        </Button>
                    </div>

                    <!--
                        Never answers the question, so the date field goes with
                        it rather than sitting there empty inviting a
                        contradiction. The button stays pressed and toggles back.
                    -->
                    <Input
                        v-if="!neverDone"
                        id="last_done_at"
                        v-model="lastDoneAt"
                        name="last_done_at"
                        type="date"
                        :max="todayIso()"
                    />
                    <p v-else class="text-muted-foreground text-xs">
                        Counting from new — the gauge runs on mileage from 0.
                    </p>
                    <InputError :message="errors.last_done_at" />
                </div>

                <div v-if="!neverDone" class="grid gap-1.5">
                    <Label for="last_done_odometer">
                        Odometer then
                        <span class="text-muted-foreground font-normal">
                            — optional
                        </span>
                    </Label>
                    <Input
                        id="last_done_odometer"
                        v-model="lastDoneOdometer"
                        name="last_done_odometer"
                        type="number"
                        inputmode="numeric"
                        :placeholder="String(projectedOdometer)"
                    />
                    <p class="text-muted-foreground text-xs">
                        Without this, only the time side of the gauge can count
                        down.
                    </p>
                    <InputError :message="errors.last_done_odometer" />
                </div>

                <!--
                    Both fields must still reach the server: a name absent from
                    the request is simply not validated, so fill() would leave
                    the old date and odometer in place and "never" would save as
                    nothing at all.
                -->
                <template v-else>
                    <input type="hidden" name="last_done_at" value="" />
                    <input type="hidden" name="last_done_odometer" value="0" />
                </template>

                <!--
                    No longer behind an "Adjust how often" button. How often a
                    service comes round is part of the schedule, not an advanced
                    setting, and hiding it meant the dialog could not answer the
                    question it opens with — "every 5,000 mi" in the header, with
                    no way to see the rest without hunting for a toggle.
                -->
                <div class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label for="interval_miles">Every (miles)</Label>
                        <!--
                            Bound rather than uncontrolled so "Never" can appear
                            the moment a mileage interval exists, instead of
                            only after a save.
                        -->
                        <Input
                            id="interval_miles"
                            v-model="intervalMiles"
                            name="interval_miles"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            placeholder="—"
                        />
                        <InputError :message="errors.interval_miles" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="interval_years">Years</Label>
                            <Input
                                id="interval_years"
                                v-model="intervalYears"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                placeholder="—"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="interval_months">Months</Label>
                            <Input
                                id="interval_months"
                                v-model="intervalMonths"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                placeholder="—"
                            />
                        </div>
                    </div>

                    <!--
                        The pair carries no name of its own; this is what the
                        server sees. Both fields feed one stored number.
                    -->
                    <input
                        type="hidden"
                        name="interval_months"
                        :value="intervalMonthsTotal"
                    />
                    <InputError :message="errors.interval_months" />

                    <p class="text-muted-foreground text-xs">
                        Set both and the gauge is due on whichever comes first.
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">Save</Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
