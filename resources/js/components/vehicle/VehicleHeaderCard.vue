<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImagePlus, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { Button } from '@/components/ui/button';
import FuelBenchmarkLine from '@/components/FuelBenchmark.vue';
import { formatNumber } from '@/lib/format';
import { edit } from '@/routes/vehicles';
import type { FuelBenchmark, MileageSummary } from '@/types/gauges';
import type { VehicleDetail } from '@/types/garage';

const props = defineProps<{
    vehicle: VehicleDetail;
    mileage: MileageSummary;
    fuel: FuelBenchmark | null;
}>();

const spec = computed(() =>
    [
        props.vehicle.year,
        props.vehicle.make,
        props.vehicle.model,
        props.vehicle.trim,
    ]
        .filter(Boolean)
        .join(' '),
);

/*
 * "Sep 8" plus "2026 · 7 days ago", derived client-side.
 *
 * days_since_reading is already on the payload, so the relative phrase needs no
 * controller change and there is only one source for how stale a reading is.
 */
const lastReading = computed(() => {
    const iso = props.mileage.last_odometer_at;

    if (!iso) {
        return null;
    }

    const date = new Date(`${iso}T00:00:00`);
    const days = props.mileage.days_since_reading;

    const relative =
        days === null
            ? null
            : days === 0
              ? 'today'
              : days === 1
                ? 'yesterday'
                : `${days} days ago`;

    return {
        day: date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        }),
        sub: [date.getFullYear(), relative].filter(Boolean).join(' · '),
    };
});
</script>

<template>
    <BlueprintFrame class="grid grid-cols-1 gap-0 p-0 xl:grid-cols-[420px_1fr]">
        <!--
          The photo is washed into the steel accent (§4). The clipping lives on
          an INNER wrapper so the frame's corner marks, which sit outside the
          box, are not cut off by it.
        -->
        <div
            class="relative aspect-[16/9] md:aspect-auto md:h-[290px] md:border-r md:border-(--color-divider)"
        >
            <!--
                Deliberately NOT .ii-duotone. Section 4 washes photography into
                the steel accent, but a vehicle photo is the one image on the
                page whose real colour is information — it is how you recognise
                your own car, and the identity square beside the name already
                carries the paint colour.
            -->
            <div
                v-if="vehicle.photo_url"
                class="absolute inset-0 overflow-hidden"
                :style="{
                    background: vehicle.photo_color ?? 'var(--color-surface)',
                }"
            >
                <img
                    :src="vehicle.photo_url"
                    alt=""
                    class="size-full object-cover"
                />
            </div>

            <!-- Not specified by the doc; a dashed well keeps the grid stable. -->
            <Link
                v-else
                :href="edit(vehicle.id)"
                class="ease-standard hover:border-accent hover:text-accent absolute inset-3 flex flex-col items-center justify-center gap-2 border border-dashed border-(--color-neutral-400) text-(--color-neutral-700) transition-colors duration-[var(--dur-fast)]"
            >
                <ImagePlus class="size-6" :stroke-width="1.5" />
                <span class="text-[13px]">Add a photo</span>
            </Link>
        </div>

        <div class="flex flex-col p-(--space-6) md:px-7 md:py-6">
            <div
                class="flex flex-col gap-(--space-4) sm:flex-row sm:items-start sm:justify-between sm:gap-(--space-6)"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-(--space-3)">
                        <span
                            class="size-[10px] flex-none"
                            :style="{
                                background:
                                    vehicle.color ?? 'var(--color-neutral-500)',
                            }"
                            aria-hidden="true"
                        />
                        <h1 class="font-display text-h1 truncate">
                            {{ vehicle.name }}
                        </h1>
                    </div>
                    <p class="mt-0.5 text-[15px] text-(--color-neutral-700)">
                        {{ spec }}
                    </p>
                </div>

                <div class="flex flex-none gap-2">
                    <Button
                        as-child
                        variant="outline"
                        class="h-[34px] px-3.5 text-[13px]"
                    >
                        <Link :href="edit(vehicle.id)">
                            <Pencil :stroke-width="1.5" />
                            Edit
                        </Link>
                    </Button>
                </div>
            </div>

            <div
                class="my-(--space-6) h-px bg-(--color-divider)"
                aria-hidden="true"
            />

            <dl class="grid grid-cols-2 gap-(--space-6) md:grid-cols-4">
                <div>
                    <dt class="ii-eyebrow">Odometer</dt>
                    <dd class="font-display text-metric">
                        {{
                            mileage.projected_odometer === null
                                ? '—'
                                : formatNumber(mileage.projected_odometer)
                        }}<span
                            class="font-sans text-[17px] font-normal text-(--color-neutral-700)"
                        >
                            mi</span
                        >
                    </dd>
                </div>

                <div>
                    <dt class="ii-eyebrow">Last reading</dt>
                    <dd class="font-display text-metric">
                        {{ lastReading?.day ?? '—' }}
                    </dd>
                    <p
                        v-if="lastReading"
                        class="text-[12px] text-(--color-neutral-700)"
                    >
                        {{ lastReading.sub }}
                    </p>
                </div>

                <div>
                    <dt class="ii-eyebrow">Avg / month</dt>
                    <dd class="font-display text-metric">
                        {{
                            vehicle.avg_miles_per_month === null
                                ? '—'
                                : formatNumber(vehicle.avg_miles_per_month)
                        }}<span
                            v-if="vehicle.avg_miles_per_month !== null"
                            class="font-sans text-[17px] font-normal text-(--color-neutral-700)"
                        >
                            mi</span
                        >
                    </dd>
                </div>

                <div>
                    <dt class="ii-eyebrow">Services due</dt>
                    <dd
                        class="font-display text-metric"
                        :class="
                            vehicle.services_due_count > 0
                                ? 'text-(--status-overdue)'
                                : ''
                        "
                    >
                        {{ vehicle.services_due_count }}
                    </dd>
                    <p
                        v-if="vehicle.services_overdue_count > 0"
                        class="text-[12px] text-(--color-neutral-700)"
                    >
                        {{ vehicle.services_overdue_count }} overdue
                    </p>
                </div>
            </dl>

            <div class="min-h-3.5 flex-1" aria-hidden="true" />

            <!--
                The card's quiet footer: fuel economy on the left as the lower
                priority reading, the VIN opposite it.
            -->
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-(--space-6)"
            >
                <FuelBenchmarkLine v-if="fuel" :fuel="fuel" />

                <p
                    v-if="vehicle.vin"
                    class="text-[11px] tracking-[0.06em] text-(--color-neutral-700) sm:ml-auto"
                >
                    VIN {{ vehicle.vin }}
                </p>
            </div>
        </div>
    </BlueprintFrame>
</template>
