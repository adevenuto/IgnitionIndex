<script setup lang="ts">
/*
 * The paint-colour chip shown beside a vehicle's name.
 *
 * Round since the 2026-10 colour pass (§2, vehicle colour): the owner's paint
 * colour is the car's identity and now shows as a dot here, a band along the
 * top of its cards and a tint on its tiles. Squares of colour still mean
 * status elsewhere, which is why this one is not a square.
 *
 * Colour is never the only identifier — it always sits next to the name — so
 * this is decorative to assistive tech unless a name is known, in which case it
 * gets a label rather than being announced as a bare graphic.
 *
 * The hairline matters: a white or silver car on a paper card would otherwise
 * have no edge at all.
 */
withDefaults(
    defineProps<{
        color?: string | null;
        label?: string | null;
        size?: 'sm' | 'md';
    }>(),
    {
        color: null,
        label: null,
        size: 'md',
    },
);
</script>

<template>
    <span
        v-if="color"
        class="inline-block shrink-0 rounded-full ring-1 ring-(--color-divider)"
        :class="size === 'sm' ? 'size-2' : 'size-3'"
        :style="{ backgroundColor: color }"
        :title="label ?? undefined"
        :aria-hidden="label ? undefined : true"
        :aria-label="label ?? undefined"
        :role="label ? 'img' : undefined"
    />
</template>
