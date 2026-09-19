<script setup lang="ts">
import AppShell from '@/components/shell/AppShell.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';

/*
 * Kept at this path and name so app.ts's layout switch is untouched.
 *
 * It no longer takes a `breadcrumbs` prop: Breadcrumbs derives the trail from
 * the current page itself, so pages have nothing to pass and nothing to keep in
 * sync. Inertia ignores layout props a page does not supply, so a stray
 * declaration left behind anywhere is inert rather than broken.
 */
</script>

<template>
    <AppShell>
        <Breadcrumbs />
        <slot />
    </AppShell>
</template>
