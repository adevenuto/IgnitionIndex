<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3';
import { DollarSign, Fuel, Gauge, Plus, Trash2, Wrench } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import FuelFlow from '@/components/capture/FuelFlow.vue';
import InputError from '@/components/InputError.vue';
import { provideSheetFooter } from '@/composables/useSheetFooter';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    destroy as destroyEvent,
    store as storeEvent,
    update as updateEvent,
} from '@/actions/App/Http/Controllers/EventController';
import { formatDate, formatMiles, todayIso } from '@/lib/format';
import { boundsFor, rangeHint } from '@/lib/readings';
import type {
    HistoryEvent,
    QuickAddVehicle,
    Reading,
    ServiceTypeOption,
} from '@/types/garage';

/*
 * The body of the quick-add flow: the four lanes and their fields.
 *
 * Split out from QuickAddSheet so the bottom sheet (mobile) and the centred
 * dialog (desktop) can share one implementation rather than duplicating a form
 * this long. It is only ever mounted while the wrapper is open, so its state
 * starts fresh on every open with no watcher needed.
 *
 * The quick-add flow. Per the brief this is the make-or-break screen and the
 * common case must be two or three taps, so:
 *   - the odometer is pre-filled and the date defaults to today
 *   - cost, shop and notes stay collapsed until asked for
 *   - fuel needs only odometer + gallons + total cost
 *
 * The pre-filled odometer is the vehicle's LAST RECORDED reading. The brief
 * wants the running projected estimate ("≈47,320 — tap to adjust"), but that
 * needs the mileage estimator, which is Phase 2. The interaction is identical;
 * only the number improves.
 *
 * Given an `event`, the same form edits it instead. Its type and vehicle are
 * fixed — a different type is a different entry, and entries never move
 * between cars — so the lane toggle and vehicle picker give way to a label,
 * and every field starts from what was logged.
 *
 * With assisted capture on, a new fuel entry is the stepped flow in
 * FuelFlow instead — photo or voice per step, then a review card — and the
 * spine, the extras and the Save button give way to it until it reaches
 * review. It posts the same fields through this same <Form>, so nothing about
 * saving changes. Editing an entry always uses the plain fields.
 *
 * Every control here is a shadcn primitive. That matters for more than
 * consistency: reka-ui's Select and Checkbox render hidden native inputs bound
 * to their `name`, so Inertia's <Form> — which serialises the DOM — picks them
 * up exactly like a plain field would.
 */
type Lane = 'fuel' | 'visit' | 'expense' | 'odometer';

const props = withDefaults(
    defineProps<{
        vehicles: QuickAddVehicle[];
        serviceTypes: ServiceTypeOption[];
        expenseCategories: string[];
        /** Which lane to open on — used by reminder deep links. */
        initialLane?: Lane;
        /** An entry to edit rather than a new one to log. */
        event?: HistoryEvent | null;
        /** The entry's vehicle's readings, oldest first; editing only. */
        readings?: Reading[];
    }>(),
    {
        initialLane: 'fuel',
        event: null,
        readings: () => [],
    },
);

const emit = defineEmits<{
    saved: [];
}>();

const editing = props.event;

const lane = ref<Lane>(editing?.type ?? props.initialLane);
const showDetails = ref(
    editing !== null &&
        (editing.notes !== null ||
            (editing.type === 'odometer' && editing.cost_cents !== null)),
);
const selectedId = ref<string>(String(props.vehicles[0]?.id ?? ''));
const lineItems = ref<{ service_type_id: string; cost: string }[]>(
    editing?.line_items.length
        ? editing.line_items.map((item) => ({
              service_type_id: String(item.service_type_id),
              cost: dollars(item.cost_cents),
          }))
        : [{ service_type_id: '', cost: '' }],
);

/** Stored cents back into the dollars the cost fields take. */
function dollars(cents: number | null | undefined): string {
    return cents == null ? '' : (cents / 100).toFixed(2);
}

const lanes = [
    { value: 'fuel', label: 'Fuel', icon: Fuel },
    { value: 'visit', label: 'Service', icon: Wrench },
    { value: 'expense', label: 'Expense', icon: DollarSign },
    { value: 'odometer', label: 'Miles', icon: Gauge },
] as const;

const vehicle = computed(
    () =>
        props.vehicles.find(
            (candidate) => String(candidate.id) === selectedId.value,
        ) ?? props.vehicles[0],
);

const odometerPlaceholder = computed(() =>
    vehicle.value?.last_odometer == null
        ? 'Current reading'
        : `${vehicle.value.last_odometer.toLocaleString()}`,
);

/**
 * A single-select ToggleGroup emits an empty value when the active item is
 * pressed again. There is no "no lane" state here, so that is ignored.
 */
function onLaneChange(value: AcceptableValue | AcceptableValue[]) {
    if (typeof value === 'string' && value !== '') {
        lane.value = value as Lane;
    }
}

function addLineItem() {
    lineItems.value.push({ service_type_id: '', cost: '' });
}

function removeLineItem(index: number) {
    lineItems.value.splice(index, 1);
}

/*
 * What the edit sheet can say before saving: the range this reading must sit
 * in, given the date as it stands in the field, and whether it is the car's
 * first or only reading. Bound to the date so moving it moves the range.
 */
const occurredOn = ref(editing?.occurred_on ?? todayIso());

const range = computed(() =>
    editing
        ? rangeHint(boundsFor(props.readings, editing.id, occurredOn.value))
        : null,
);

const isFirst = computed(
    () => editing !== null && props.readings[0]?.id === editing.id,
);

const isOnly = computed(() => editing !== null && props.readings.length <= 1);

const form = computed(() =>
    editing
        ? updateEvent.form(editing.id)
        : storeEvent.form(vehicle.value?.id ?? 0),
);

const page = usePage();

const assisted = computed(
    () =>
        editing === null &&
        lane.value === 'fuel' &&
        page.props.can.assistedCapture,
);

/** The assisted flow's Save lives on its review card, and only there. */
const atReview = ref(false);

const laneItem = computed(
    () => lanes.find((item) => item.value === lane.value) ?? lanes[0],
);

/*
 * Delete is confirmed in place: the footer turns into the question rather
 * than a second modal opening over this one. The confirmation says what else
 * changes, because the entry is rarely the only thing a delete touches.
 */
const confirmingDelete = ref(false);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

const deleteConsequence = computed(() => {
    if (!editing) {
        return '';
    }

    const said: string[] = [];
    const next = props.readings[1];

    if (isFirst.value && next) {
        said.push(
            `Your earliest reading becomes ${formatMiles(next.odometer)} on ${formatDate(next.occurred_on)}.`,
        );
    }

    const names = [...new Set(editing.line_items.map((item) => item.name))];

    if (editing.type === 'visit' && names.length) {
        const one = names.length === 1;

        said.push(
            `${names.join(', ')} ${one ? 'goes' : 'go'} back to ${one ? 'its' : 'their'} previous service, or to Not set if there is none.`,
        );
    } else if (editing.type === 'fuel') {
        said.push('MPG for the next fill-up is worked out again.');
    } else if (!said.length) {
        said.push("The car's mileage estimate is worked out again.");
    }

    return said.join(' ');
});

function deleteEntry(): void {
    if (!editing) {
        return;
    }

    router.delete(destroyEvent.url(editing.id), {
        preserveScroll: true,
        onStart: () => {
            deleting.value = true;
            deleteError.value = null;
        },
        onSuccess: () => emit('saved'),
        onError: (errors) => {
            deleteError.value =
                errors.event ?? Object.values(errors)[0] ?? null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

/** The sheet's pinned footer: Save, plus whatever a stepped flow adds to it. */
const footer = provideSheetFooter();
</script>

<template>
    <Form
        v-if="vehicle"
        v-bind="form"
        :options="{ preserveScroll: true }"
        :reset-on-success="!editing"
        class="flex flex-col gap-5 px-4"
        v-slot="{ errors, processing }"
        @success="emit('saved')"
    >
        <input type="hidden" name="type" :value="lane" />

        <!-- An entry's type is fixed once logged, so editing shows it, not a choice. -->
        <p
            v-if="editing"
            class="text-muted-foreground flex items-center gap-2 text-sm font-bold"
        >
            <component :is="laneItem.icon" class="size-4" :stroke-width="1.5" />
            {{ laneItem.label }} entry
        </p>

        <ToggleGroup
            v-else
            type="single"
            :model-value="lane"
            :spacing="1"
            class="bg-muted w-full rounded-lg p-1"
            aria-label="Entry type"
            @update:model-value="onLaneChange"
        >
            <ToggleGroupItem
                v-for="item in lanes"
                :key="item.value"
                :value="item.value"
                :aria-label="item.label"
                class="ease-standard text-muted-foreground hover:bg-card hover:text-foreground data-[state=on]:bg-card data-[state=on]:text-foreground h-11 flex-1 rounded-md border border-transparent text-sm font-semibold transition-colors duration-[var(--dur-fast)] data-[state=on]:border-(--color-divider) data-[state=on]:shadow-sm"
            >
                <!--
                    One quiet segmented control: the chosen type is lifted onto
                    the card surface, never colour-coded per type — four hues in
                    one row read as noise rather than as a choice.
                -->
                <component :is="item.icon" />
                {{ item.label }}
            </ToggleGroupItem>
        </ToggleGroup>

        <div v-if="!editing && vehicles.length > 1" class="grid gap-1.5">
            <Label for="quick-add-vehicle">Vehicle</Label>
            <Select v-model="selectedId">
                <SelectTrigger id="quick-add-vehicle" class="w-full">
                    <SelectValue placeholder="Choose a vehicle" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in vehicles"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ option.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- The spine: every lane carries these two. -->
        <template v-if="!assisted">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="odometer">Odometer</Label>
                    <Input
                        id="odometer"
                        name="odometer"
                        type="number"
                        inputmode="numeric"
                        required
                        :default-value="
                            editing?.odometer ??
                            vehicle?.last_odometer ??
                            undefined
                        "
                        :placeholder="odometerPlaceholder"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="occurred_on">Date</Label>
                    <Input
                        id="occurred_on"
                        name="occurred_on"
                        v-model="occurredOn"
                        type="date"
                        required
                    />
                </div>
            </div>
            <div
                v-if="isFirst || range"
                class="text-muted-foreground -mt-3 grid gap-0.5 text-xs"
            >
                <p v-if="isFirst">
                    Your earliest reading. Mileage is measured from here.
                </p>
                <p v-if="range">{{ range }}</p>
            </div>
            <InputError :message="errors.odometer" />
            <InputError :message="errors.occurred_on" />
        </template>

        <!-- Fuel, assisted: steps with photo and voice, then review. -->
        <FuelFlow
            v-if="assisted"
            :key="vehicle.id"
            v-model:at-review="atReview"
            :vehicle="vehicle"
            :errors="errors"
        />

        <!-- Fuel: odometer + gallons + total cost is a complete entry. -->
        <template v-else-if="lane === 'fuel'">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="gallons">Gallons</Label>
                    <Input
                        id="gallons"
                        name="gallons"
                        type="number"
                        step="0.001"
                        inputmode="decimal"
                        placeholder="12.4"
                        :default-value="editing?.gallons ?? undefined"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="fuel-cost">Total cost</Label>
                    <Input
                        id="fuel-cost"
                        name="cost"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="44.99"
                        :default-value="dollars(editing?.cost_cents)"
                    />
                </div>
            </div>
            <InputError :message="errors.gallons" />

            <div class="flex items-center gap-2.5">
                <Checkbox
                    id="full_tank"
                    name="full_tank"
                    value="1"
                    :default-value="editing ? editing.full_tank === true : true"
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
        </template>

        <!-- Service: one shop visit, one or more jobs. -->
        <template v-else-if="lane === 'visit'">
            <div class="grid gap-3">
                <div
                    v-for="(item, index) in lineItems"
                    :key="index"
                    class="flex items-end gap-2"
                >
                    <div class="grid min-w-0 flex-1 gap-1.5">
                        <Label :for="`line-${index}`">
                            {{
                                index === 0 ? 'Service' : `Service ${index + 1}`
                            }}
                        </Label>
                        <Select
                            v-model="item.service_type_id"
                            :name="`line_items[${index}][service_type_id]`"
                        >
                            <SelectTrigger :id="`line-${index}`" class="w-full">
                                <SelectValue placeholder="Choose…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in serviceTypes"
                                    :key="type.id"
                                    :value="String(type.id)"
                                >
                                    {{ type.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid w-28 gap-1.5">
                        <Label :for="`line-cost-${index}`">Cost</Label>
                        <Input
                            :id="`line-cost-${index}`"
                            v-model="item.cost"
                            :name="`line_items[${index}][cost]`"
                            type="number"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                    </div>

                    <Button
                        v-if="lineItems.length > 1"
                        type="button"
                        variant="ghost"
                        size="icon-tap"
                        aria-label="Remove this service"
                        @click="removeLineItem(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="self-start"
                @click="addLineItem"
            >
                <Plus />
                Add another service
            </Button>
            <InputError :message="errors.line_items" />

            <div class="grid gap-1.5">
                <Label for="location">Shop</Label>
                <Input
                    id="location"
                    name="location"
                    placeholder="Corner Auto"
                    :default-value="editing?.location ?? undefined"
                />
            </div>
        </template>

        <!-- Expense: purely financial, still carries a reading. -->
        <template v-else-if="lane === 'expense'">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="category">Category</Label>
                    <Select
                        name="category"
                        :default-value="editing?.category ?? 'Insurance'"
                    >
                        <SelectTrigger id="category" class="w-full">
                            <SelectValue placeholder="Choose…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in expenseCategories"
                                :key="category"
                                :value="category"
                            >
                                {{ category }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-cost">Cost</Label>
                    <Input
                        id="expense-cost"
                        name="cost"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="120.00"
                        :default-value="dollars(editing?.cost_cents)"
                    />
                </div>
            </div>
            <InputError :message="errors.category" />
        </template>

        <!-- Optional extras stay collapsed: the common case is 2-3 taps. -->
        <Button
            v-if="!showDetails && !assisted"
            type="button"
            variant="ghost"
            size="sm"
            class="self-start"
            @click="showDetails = true"
        >
            Add notes{{ lane === 'fuel' ? '' : ' or cost' }}
        </Button>

        <template v-if="showDetails && !assisted">
            <div v-if="lane === 'odometer'" class="grid gap-1.5">
                <Label for="misc-cost">Cost</Label>
                <Input
                    id="misc-cost"
                    name="cost"
                    type="number"
                    step="0.01"
                    inputmode="decimal"
                    :default-value="dollars(editing?.cost_cents)"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    name="notes"
                    rows="3"
                    placeholder="Optional"
                    :default-value="editing?.notes ?? undefined"
                />
            </div>
        </template>

        <!--
            Set when the user re-submits after an implausible-jump
            warning, so the guardrail asks once rather than blocking.
        -->
        <input
            v-if="errors.odometer"
            type="hidden"
            name="confirm_odometer"
            value="1"
        />

        <InputError :message="errors.type" />

        <template v-if="!confirmingDelete">
            <div v-if="editing" class="grid gap-1 self-start">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:bg-destructive/10 self-start"
                    :disabled="isOnly"
                    :aria-describedby="isOnly ? 'only-reading' : undefined"
                    @click="confirmingDelete = true"
                >
                    <Trash2 :stroke-width="1.5" />
                    Delete entry
                </Button>
                <!-- Said up front, not after the click: there is nothing to try. -->
                <p
                    v-if="isOnly"
                    id="only-reading"
                    class="text-muted-foreground text-xs"
                >
                    This is the car's only reading. Correct it rather than
                    deleting it.
                </p>
            </div>
        </template>

        <div
            v-else
            class="flex flex-col gap-3 border-t border-(--color-divider) pt-4"
        >
            <div>
                <p class="text-body font-bold">Delete this entry?</p>
                <p class="text-muted-foreground text-sm">
                    {{ deleteConsequence }}
                </p>
            </div>
            <InputError :message="deleteError ?? undefined" />
            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="deleting"
                    @click="
                        confirmingDelete = false;
                        deleteError = null;
                    "
                >
                    Keep
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="deleting"
                    @click="deleteEntry"
                >
                    Delete
                </Button>
            </div>
        </div>

        <!--
          The sheet's one footer, pinned to the bottom of its scroll so the way
          on is always in reach. A stepped flow teleports Back and Next into the
          mount point; Save joins them on review, or stands alone on a plain
          lane. While a delete is being confirmed, that card's own buttons are
          the only actions, so the footer steps aside.
        -->
        <div
            v-show="!confirmingDelete"
            class="bg-background sticky bottom-0 z-10 -mx-4 mt-auto flex items-center gap-3 border-t border-(--color-divider) px-4 pt-(--space-4) pb-[max(var(--space-6),env(safe-area-inset-bottom))]"
        >
            <div ref="footer" class="contents" />
            <Button
                v-if="!assisted || atReview"
                type="submit"
                size="lg"
                class="flex-1"
                :disabled="processing"
            >
                {{
                    errors.odometer
                        ? 'Save anyway'
                        : editing
                          ? 'Save changes'
                          : 'Save'
                }}
            </Button>
        </div>
    </Form>
</template>
