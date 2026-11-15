<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import QuickAddForm from '@/components/QuickAddForm.vue';
import VehicleBadge from '@/components/VehicleBadge.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type {
    HistoryEvent,
    QuickAddVehicle,
    Reading,
    ServiceTypeOption,
} from '@/types/garage';

/*
 * Chrome for the quick-add flow: a bottom sheet on mobile, a centred dialog on
 * desktop. QuickAddForm is the shared body.
 *
 * Two wrappers rather than one restyled sheet: SheetContent hardcodes
 * `inset-x-0 bottom-0` plus a slide-from-bottom animation for side="bottom",
 * and centring needs a translate on both axes — the two fight, and the result
 * is a modal that jumps as it opens. DialogContent already centres and zooms.
 *
 * useMediaQuery is safe here in a way it would not be in the app shell: this
 * only renders after a deliberate tap, long past the first tick, so there is no
 * flash of the wrong variant. The breakpoint pairs exactly with Tailwind's `md`
 * (and with the sidebar's 767.98px cutoff) so there is no width where neither
 * matches.
 *
 * Both are three bands — a fixed header, a body that scrolls, a pinned footer
 * (QuickAddForm, useSheetChrome) — so the entry types and a flow's progress
 * never scroll away. On a phone the sheet takes the whole screen: the steps
 * are long enough to need it, and a sheet that grows and shrinks between
 * steps moves its own header out from under the thumb.
 */
const props = defineProps<{
    vehicles: QuickAddVehicle[];
    serviceTypes: ServiceTypeOption[];
    expenseCategories: string[];
    initialLane?: 'fuel' | 'visit' | 'expense' | 'odometer';
    /** Opens the sheet on an existing entry, to edit or delete it. */
    event?: HistoryEvent | null;
    /** The entry's vehicle's readings, so the edit form can show its range. */
    readings?: Reading[];
}>();

const open = defineModel<boolean>('open', { required: true });

const isDesktop = useMediaQuery('(min-width: 768px)');

const title = () => (props.event ? 'Edit entry' : 'Log an entry');

const subtitle = () =>
    props.event
        ? props.event.vehicle_name
        : props.vehicles.length > 1
          ? 'Choose a vehicle below'
          : (props.vehicles[0]?.name ?? '');

/**
 * With one car in play the header shows it as a badge, photo and paint and
 * all; the plain line stays for screen readers. With several, the line says
 * to choose one, and the form's picker does the rest.
 */
const badgeVehicle = () =>
    props.vehicles.length === 1 ? props.vehicles[0] : null;
</script>

<template>
    <Dialog v-if="isDesktop" v-model:open="open">
        <DialogContent
            class="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0 px-4 pt-5 pb-(--space-4) text-left">
                <DialogTitle class="text-h2">{{ title() }}</DialogTitle>
                <DialogDescription
                    :class="badgeVehicle() ? 'sr-only' : 'text-body'"
                >
                    {{ subtitle() }}
                </DialogDescription>
                <VehicleBadge
                    v-if="badgeVehicle()"
                    :vehicle="badgeVehicle()!"
                    class="mt-1 self-start"
                />
            </DialogHeader>

            <QuickAddForm
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :expense-categories="expenseCategories"
                :initial-lane="initialLane"
                :event="event"
                :readings="readings"
                @saved="open = false"
            />
        </DialogContent>
    </Dialog>

    <Sheet v-else v-model:open="open">
        <SheetContent
            side="bottom"
            class="top-0 h-dvh gap-0 overflow-hidden border-t-0 [&_[data-slot=sheet-close]]:top-[calc(env(safe-area-inset-top)+0.75rem)]"
        >
            <!-- pr clears the 44px close button; pt clears the notch. -->
            <SheetHeader
                class="shrink-0 pt-[calc(env(safe-area-inset-top)+1rem)] pr-16"
            >
                <SheetTitle class="text-h2">{{ title() }}</SheetTitle>
                <SheetDescription
                    :class="badgeVehicle() ? 'sr-only' : 'text-body'"
                >
                    {{ subtitle() }}
                </SheetDescription>
                <VehicleBadge
                    v-if="badgeVehicle()"
                    :vehicle="badgeVehicle()!"
                    class="mt-1 self-start"
                />
            </SheetHeader>

            <QuickAddForm
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :expense-categories="expenseCategories"
                :initial-lane="initialLane"
                :event="event"
                :readings="readings"
                @saved="open = false"
            />
        </SheetContent>
    </Sheet>
</template>
