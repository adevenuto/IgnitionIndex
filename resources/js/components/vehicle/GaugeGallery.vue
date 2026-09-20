<script setup lang="ts">
import { computed } from 'vue';
import GaugeDial from '@/components/GaugeDial.vue';
import GaugeLegend from '@/components/GaugeLegend.vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import {
    gaugeColor,
    gaugeInk,
    gaugeReadout,
    gaugeStatusLabel,
} from '@/lib/gauge';
import type { Gauge } from '@/types/gauges';

/*
 * The gauge wall: three large dials, then everything else as a two-column list.
 *
 * §6 forbids reordering overdue items to the front — the order is fixed so a
 * gauge does not move under the pointer when its status changes. The first
 * three are the pinned set.
 */
const props = defineProps<{ gauges: Gauge[] }>();

defineEmits<{ select: [gauge: Gauge] }>();

const pinned = computed(() => props.gauges.filter((gauge) => gauge.is_pinned));
const rest = computed(() => props.gauges.filter((gauge) => !gauge.is_pinned));

/*
 * The 1px grid gaps ARE the rules, which is why each cell paints an opaque
 * background over a divider-coloured grid. It only works on an even count: an
 * odd one leaves the last row half-ruled, so a filler cell squares it off. The
 * alternative — dropping a gauge to make the count even — would hide data.
 */
const needsFiller = computed(() => rest.value.length % 2 === 1);
</script>

<template>
    <section class="flex flex-col gap-(--space-4)">
        <div
            class="flex flex-col gap-(--space-3) sm:flex-row sm:items-end sm:justify-between sm:gap-(--space-6)"
        >
            <div>
                <h2 class="font-display text-h3">Gauges</h2>
                <p class="text-[13px] text-(--color-neutral-700)">
                    Percent of interval elapsed. Intervals are set per service
                    by mileage or period.
                </p>
            </div>

            <GaugeLegend />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <BlueprintFrame
                v-for="gauge in pinned"
                :key="gauge.id"
                as="button"
                interactive
                class="flex flex-col items-center px-[18px] pt-[18px] pb-4 text-center"
                :aria-label="`${gauge.name}, ${gauge.label}. Adjust.`"
                @click="$emit('select', gauge)"
            >
                <span
                    class="ii-eyebrow flex w-full items-center justify-between gap-2 tracking-[0.12em]"
                >
                    <span class="truncate">
                        Pinned<template v-if="gauge.basis">
                            · {{ gauge.basis }}</template
                        >
                    </span>
                    <span
                        class="ii-tag ii-tag-outline shrink-0 text-[10px] font-bold tracking-[0.12em] uppercase"
                        :style="{
                            '--ii-tag-border': gaugeColor(gauge.display_status),
                            '--ii-tag-ink': gaugeInk(gauge.display_status),
                        }"
                    >
                        {{ gaugeStatusLabel(gauge.display_status) }}
                    </span>
                </span>

                <GaugeDial
                    class="mt-1 h-[230px] w-full max-w-[300px]"
                    :percent="gauge.percent"
                    :status="gauge.display_status"
                />

                <!-- Sibling, never an SVG <text>; see GaugeDial. -->
                <p class="font-display text-readout">
                    {{ gaugeReadout(gauge.display_status, gauge.percent) }}
                </p>
                <p class="font-display text-[24px]/[1.1] font-semibold">
                    {{ gauge.name }}
                </p>
                <p class="text-[13px] text-(--color-neutral-700)">
                    {{ gauge.label }}
                </p>
            </BlueprintFrame>
        </div>

        <BlueprintFrame v-if="rest.length" class="gap-0 p-0">
            <div class="grid gap-px bg-(--color-divider) sm:grid-cols-2">
                <button
                    v-for="gauge in rest"
                    :key="gauge.id"
                    type="button"
                    class="ease-standard hover:bg-accent-100 flex items-center gap-4 bg-(--color-neutral-100) px-[18px] py-3.5 text-left transition-colors duration-[var(--dur-fast)]"
                    :aria-label="`${gauge.name}, ${gauge.label}. Adjust.`"
                    @click="$emit('select', gauge)"
                >
                    <GaugeDial
                        class="h-[59px] w-[74px] flex-none"
                        variant="mini"
                        :percent="gauge.percent"
                        :status="gauge.display_status"
                    />

                    <span class="min-w-0 flex-1">
                        <span
                            class="font-display block truncate text-[20px]/[1.15] font-semibold"
                        >
                            {{ gauge.name }}
                        </span>
                        <span
                            class="block truncate text-[12px] text-(--color-neutral-700)"
                        >
                            {{ gauge.label
                            }}<template v-if="gauge.basis">
                                · {{ gauge.basis }}</template
                            >
                        </span>
                    </span>

                    <span class="flex-none text-right">
                        <span
                            class="font-display block text-[26px]/none font-semibold"
                            :style="{ color: gaugeColor(gauge.display_status) }"
                        >
                            {{
                                gaugeReadout(
                                    gauge.display_status,
                                    gauge.percent,
                                )
                            }}
                        </span>
                        <span
                            class="block text-[10px] font-bold tracking-[0.12em] uppercase"
                            :style="{ color: gaugeInk(gauge.display_status) }"
                        >
                            {{ gaugeStatusLabel(gauge.display_status) }}
                        </span>
                    </span>
                </button>

                <!-- Squares off an odd count so the last row stays ruled. -->
                <div
                    v-if="needsFiller"
                    class="bg-(--color-neutral-100)"
                    aria-hidden="true"
                />
            </div>
        </BlueprintFrame>
    </section>
</template>
