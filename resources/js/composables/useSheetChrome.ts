import type { InjectionKey, Ref } from 'vue';
import { inject, provide, ref } from 'vue';

/*
 * The quick-add sheet's fixed chrome, shared with whatever is inside it.
 *
 * The sheet is three bands: a header that stays put (the entry types, and a
 * stepped flow's progress), a body that scrolls, and a footer pinned to the
 * bottom. The form owns all three — Save must sit inside the <form> to submit
 * it — but a stepped flow deeper down needs its progress in the header and its
 * Back and Next in the footer. The form provides the mount points; the flow
 * teleports into them. So what the bars hold changes with the screen: Back
 * and Next on a step, Back and Save on review, Save alone on a plain lane.
 *
 * The body is shared too, so a flow can return it to the top when it moves to
 * another step.
 */
export type SheetChrome = {
    header: Ref<HTMLElement | null>;
    body: Ref<HTMLElement | null>;
    footer: Ref<HTMLElement | null>;
};

const sheetChromeKey: InjectionKey<SheetChrome> = Symbol('sheetChrome');

/** For the owner of the sheet: bind each returned ref to its element. */
export function provideSheetChrome(): SheetChrome {
    const chrome: SheetChrome = {
        header: ref(null),
        body: ref(null),
        footer: ref(null),
    };

    provide(sheetChromeKey, chrome);

    return chrome;
}

/** Null outside such a sheet; the caller then renders its own bars inline. */
export function injectSheetChrome(): SheetChrome | null {
    return inject(sheetChromeKey, null);
}
