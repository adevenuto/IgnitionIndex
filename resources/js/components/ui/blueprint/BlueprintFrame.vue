<script setup lang="ts">
import type { Component, HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * A frame: every card, panel and framed region in the app.
 *
 * It began as a blueprint object — square, hairline, four `+` registration
 * marks — and was softened in 2026-10 (design doc §4): rounded, near-white,
 * one soft elevation, no marks. One component for every frame is what let
 * that change land everywhere at once, which is why it keeps its name.
 */
const props = withDefaults(
  defineProps<{
    as?: string | Component
    class?: HTMLAttributes["class"]
    /** Interactive frames take the steel border on hover, as gauge cards do. */
    interactive?: boolean
  }>(),
  { as: "div", interactive: false },
)
</script>

<template>
  <component
    :is="props.as"
    data-slot="blueprint"
    :class="
      cn(
        'ii-blueprint',
        interactive &&
          'hover:border-accent transition-colors duration-[var(--dur-fast)] ease-standard',
        props.class,
      )
    "
  >
    <slot />
  </component>
</template>
