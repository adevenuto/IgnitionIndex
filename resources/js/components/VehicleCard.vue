<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Car, ChevronRight, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import GaugeDial from '@/components/GaugeDial.vue';
import VehicleColorDot from '@/components/VehicleColorDot.vue';
import { formatMiles } from '@/lib/format';
import { show } from '@/routes/vehicles';
import type { GarageVehicle } from '@/types/garage';

/*
 * A vehicle in the garage grid.
 *
 * The whole card is the tap target, so nothing inside competes for the click.
 * The spec row carries the projected odometer, which is the number this product
 * is actually about, and the worst gauge — nine healthy items must never dilute
 * one overdue brake job.
 */
const props = defineProps<{
    vehicle: GarageVehicle;
}>();

// A deleted file, an expired session, or being offline all land here — fall
// back to the glyph rather than showing broken-image chrome.
const failed = ref(false);

const specs = computed(() =>
    [
        props.vehicle.year,
        props.vehicle.make,
        props.vehicle.model,
        props.vehicle.trim,
    ]
        .filter(Boolean)
        .join(' '),
);
</script>

<template>
    <!-- `group` so the chevron can answer a hover anywhere on the card. -->
    <Link
        :href="show(vehicle.id)"
        class="ease-standard group block transition-colors duration-[var(--dur-fast)]"
        :data-test="`vehicle-card-${vehicle.id}`"
    >
        <!--
            The same surface and the same sweeping needle as the vehicle page's
            setup prompt, off the same server-computed flag, so a car waiting on
            its gauges reads identically in the grid and on its own page.
        -->
        <Card :class="vehicle.awaiting_setup && 'ii-awaiting-setup'">
            <CardContent>
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h3
                            class="font-display text-title flex items-center gap-2"
                        >
                            <ShieldAlert
                                v-if="vehicle.open_recall_count"
                                class="text-accent-700 size-4 shrink-0"
                                :aria-label="`${vehicle.open_recall_count} open safety recall`"
                            />
                            <VehicleColorDot
                                :color="vehicle.color"
                                :label="`Paint colour ${vehicle.color}`"
                            />
                            <span class="truncate">{{ vehicle.name }}</span>
                        </h3>
                        <p
                            v-if="specs"
                            class="mt-0.5 truncate text-[13px] text-(--color-neutral-700)"
                        >
                            {{ specs }}
                        </p>
                    </div>

                    <span
                        class="flex size-12 shrink-0 items-center justify-center overflow-hidden border border-(--color-divider) bg-(--color-surface) text-(--color-neutral-700)"
                        :style="
                            vehicle.photo_color
                                ? { backgroundColor: vehicle.photo_color }
                                : undefined
                        "
                    >
                        <img
                            v-if="vehicle.photo_thumb_url && !failed"
                            :src="vehicle.photo_thumb_url"
                            :alt="`Photo of ${vehicle.name}`"
                            width="48"
                            height="48"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover"
                            @error="failed = true"
                        />

                        <Car v-else class="size-6" />
                    </span>
                </div>

                <div
                    class="mt-(--space-4) flex items-center gap-3 border-t border-(--color-divider) pt-(--space-3)"
                >
                    <GaugeDial
                        class="h-[46px] w-[58px] flex-none"
                        variant="mini"
                        :percent="(vehicle.worst?.raw_progress ?? 0) * 100"
                        :status="vehicle.worst?.display_status ?? 'unknown'"
                        :sweep="vehicle.awaiting_setup"
                    />

                    <div class="min-w-0 flex-1">
                        <p
                            v-if="vehicle.worst"
                            class="font-display truncate text-[17px] font-semibold"
                        >
                            {{ vehicle.worst.name }}
                        </p>
                        <p
                            v-else
                            class="font-display truncate text-[17px] font-semibold"
                        >
                            Not set up yet
                        </p>

                        <p
                            class="truncate text-[13px] text-(--color-neutral-700)"
                        >
                            <template v-if="vehicle.worst">
                                {{ vehicle.worst.label }}
                                <span v-if="vehicle.due_count > 1">
                                    &middot; +{{ vehicle.due_count - 1 }} more
                                    due
                                </span>
                            </template>
                            <template v-else>
                                Tell us when things were last done
                            </template>
                        </p>

                        <p class="ii-metadata truncate">
                            {{
                                formatMiles(vehicle.mileage.projected_odometer)
                            }}
                            <span v-if="vehicle.mileage.is_projected"
                                >approx.</span
                            >
                        </p>
                    </div>

                    <ChevronRight
                        class="ii-arrow-nudge size-5 shrink-0 text-(--color-neutral-500)"
                    />
                </div>
            </CardContent>
        </Card>
    </Link>
</template>
