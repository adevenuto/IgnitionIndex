<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';
import ShellRail from '@/components/shell/ShellRail.vue';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';

/*
 * The rail below md.
 *
 * A Sheet rather than the shadcn Sidebar's own mobile branch: Sheet is reka's
 * Dialog, so focus trapping, scroll locking and Escape come for free, and it
 * renders the SAME ShellRail body as the desktop column.
 *
 * Any tap on something actionable inside it closes it — a link, a button,
 * anything acting as one — including a link to the page already showing,
 * which navigates nowhere and so would otherwise leave the drawer open. Every
 * Inertia visit closes it too, as a backstop for actions started elsewhere.
 */
const open = defineModel<boolean>('open', { required: true });

function onClick(event: MouseEvent): void {
    const target = event.target as Element | null;

    if (target?.closest('a, button, [role="button"], [role="menuitem"]')) {
        open.value = false;
    }
}

const stopListening = router.on('start', () => {
    open.value = false;
});

onBeforeUnmount(stopListening);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="left"
            class="w-[264px] border-0 p-0"
            :style="{ background: 'var(--shell-navy-deep)' }"
            @click="onClick"
        >
            <SheetTitle class="sr-only">Navigation</SheetTitle>
            <ShellRail />
        </SheetContent>
    </Sheet>
</template>
