import type { InjectionKey, Ref } from 'vue';
import { inject, provide, ref } from 'vue';

/*
 * The quick-add sheet's one pinned footer, shared with whatever is inside it.
 *
 * The form owns the footer — it is where Save lives, and Save must sit inside
 * the <form> to submit it — but a stepped flow deeper down needs its Back and
 * Next in the same bar. The form provides the footer's mount point; the flow
 * teleports its buttons into it. So there is one bar at the bottom of the
 * sheet, and what it holds changes with the screen: Back and Next on a step,
 * Back and Save on review, Save alone on a plain lane.
 */
const sheetFooterKey: InjectionKey<Ref<HTMLElement | null>> =
    Symbol('sheetFooter');

/** For the owner of the footer: bind the returned ref to the mount point. */
export function provideSheetFooter(): Ref<HTMLElement | null> {
    const target = ref<HTMLElement | null>(null);

    provide(sheetFooterKey, target);

    return target;
}

/** Null outside a sheet that has a footer; the caller then renders inline. */
export function injectSheetFooter(): Ref<HTMLElement | null> | null {
    return inject(sheetFooterKey, null);
}
