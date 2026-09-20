<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { DollarSign, Fuel, Gauge, Plus, Trash2, Wrench } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { store as storeEvent } from '@/actions/App/Http/Controllers/EventController';
import { todayIso } from '@/lib/format';
import type { QuickAddVehicle, ServiceTypeOption } from '@/types/garage';

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
    }>(),
    {
        initialLane: 'fuel',
    },
);

const emit = defineEmits<{
    saved: [];
}>();

const lane = ref<Lane>(props.initialLane);
const showDetails = ref(false);
const selectedId = ref<string>(String(props.vehicles[0]?.id ?? ''));
const lineItems = ref<{ service_type_id: string; cost: string }[]>([
    { service_type_id: '', cost: '' },
]);

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
</script>

<template>
    <Form
        v-if="vehicle"
        v-bind="storeEvent.form(vehicle.id)"
        :options="{ preserveScroll: true }"
        reset-on-success
        class="flex flex-col gap-5 px-4 pb-6"
        v-slot="{ errors, processing }"
        @success="emit('saved')"
    >
        <input type="hidden" name="type" :value="lane" />

        <ToggleGroup
            type="single"
            :model-value="lane"
            class="bg-muted w-full p-1"
            aria-label="Entry type"
            @update:model-value="onLaneChange"
        >
            <ToggleGroupItem
                v-for="item in lanes"
                :key="item.value"
                :value="item.value"
                :aria-label="item.label"
                class="ease-standard data-[state=on]:bg-primary data-[state=on]:text-primary-foreground text-muted-foreground h-11 flex-1 text-sm font-bold transition-colors duration-[var(--dur-fast)]"
            >
                <component :is="item.icon" />
                {{ item.label }}
            </ToggleGroupItem>
        </ToggleGroup>

        <div v-if="vehicles.length > 1" class="grid gap-1.5">
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
        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
                <Label for="odometer">Odometer</Label>
                <Input
                    id="odometer"
                    name="odometer"
                    type="number"
                    inputmode="numeric"
                    required
                    :default-value="vehicle?.last_odometer ?? undefined"
                    :placeholder="odometerPlaceholder"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="occurred_on">Date</Label>
                <Input
                    id="occurred_on"
                    name="occurred_on"
                    type="date"
                    required
                    :default-value="todayIso()"
                />
            </div>
        </div>
        <InputError :message="errors.odometer" />
        <InputError :message="errors.occurred_on" />

        <!-- Fuel: odometer + gallons + total cost is a complete entry. -->
        <template v-if="lane === 'fuel'">
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
                    />
                </div>
            </div>
            <InputError :message="errors.gallons" />

            <div class="flex items-center gap-2.5">
                <Checkbox
                    id="full_tank"
                    name="full_tank"
                    value="1"
                    default-value
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
                />
            </div>
        </template>

        <!-- Expense: purely financial, still carries a reading. -->
        <template v-else-if="lane === 'expense'">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="category">Category</Label>
                    <Select name="category" default-value="Insurance">
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
                    />
                </div>
            </div>
            <InputError :message="errors.category" />
        </template>

        <!-- Optional extras stay collapsed: the common case is 2-3 taps. -->
        <Button
            v-if="!showDetails"
            type="button"
            variant="ghost"
            size="sm"
            class="self-start"
            @click="showDetails = true"
        >
            Add notes{{ lane === 'fuel' ? '' : ' or cost' }}
        </Button>

        <template v-if="showDetails">
            <div v-if="lane === 'odometer'" class="grid gap-1.5">
                <Label for="misc-cost">Cost</Label>
                <Input
                    id="misc-cost"
                    name="cost"
                    type="number"
                    step="0.01"
                    inputmode="decimal"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    name="notes"
                    rows="3"
                    placeholder="Optional"
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

        <Button type="submit" size="lg" :disabled="processing">
            {{ errors.odometer ? 'Save anyway' : 'Save' }}
        </Button>
    </Form>
</template>
