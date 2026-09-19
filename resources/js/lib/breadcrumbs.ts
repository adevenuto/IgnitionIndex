import { garage, history, insights, notifications } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import { create, edit as vehicleEdit, show } from '@/routes/vehicles';
import type { BreadcrumbItem } from '@/types';

/*
 * The breadcrumb trail, derived from whichever page Inertia is showing.
 *
 * Every page used to declare its own trail, which meant "Garage" was written out
 * in four places and a page that forgot to declare one inherited whatever the
 * last page had set — the bug behind stale crumbs after a vehicle was deleted
 * and the garage came back still claiming to be inside a car that no longer
 * existed. A trail computed from the current component cannot go stale, because
 * there is nothing to go stale: it is recomputed from scratch on every visit.
 *
 * A page names ITS OWN crumb and its parent. The chain is walked from here, so
 * no ancestor is ever spelled out twice and moving a page re-parents its whole
 * trail in one line.
 */

/** The props a page carries that a crumb might need to name itself. */
export type CrumbProps = {
    vehicle?: { id: number; name?: string | null };
};

type Page = {
    /** A string, or a function when the title comes from the page's data. */
    title: string | ((props: CrumbProps) => string);
    href: (props: CrumbProps) => BreadcrumbItem['href'];
    /** The component name this page sits under, if any. */
    parent?: string;
};

/*
 * Keyed by Inertia component name, exactly as `page.component` reports it.
 *
 * A page absent from here shows no breadcrumbs, which is the right default for
 * Welcome and the auth screens — they do not even render the app shell.
 */
const PAGES: Record<string, Page> = {
    Garage: {
        title: 'Garage',
        href: () => garage(),
    },
    History: {
        title: 'History',
        href: () => history(),
    },
    Insights: {
        title: 'Insights',
        href: () => insights(),
    },
    Notifications: {
        title: 'Notifications',
        href: () => notifications(),
    },
    'vehicles/Create': {
        title: 'Add Vehicle',
        href: () => create(),
        parent: 'Garage',
    },
    'vehicles/Show': {
        // The car's own name, which the server sends on both vehicle screens so
        // the two cannot disagree about what to call it.
        title: (props) => props.vehicle?.name ?? 'Vehicle',
        href: (props) => show(props.vehicle?.id ?? 0),
        parent: 'Garage',
    },
    'vehicles/Edit': {
        title: 'Edit',
        href: (props) => vehicleEdit(props.vehicle?.id ?? 0),
        parent: 'vehicles/Show',
    },
    'settings/Profile': {
        title: 'Profile settings',
        href: () => profileEdit(),
    },
    'settings/Security': {
        title: 'Security settings',
        href: () => securityEdit(),
    },
};

/**
 * The trail for a component, ancestors first.
 *
 * Walks up `parent` with a seen-set, so a cycle introduced by a bad edit drops
 * the trail rather than hanging the page.
 */
export function breadcrumbsFor(
    component: string,
    props: CrumbProps,
): BreadcrumbItem[] {
    const trail: BreadcrumbItem[] = [];
    const seen = new Set<string>();

    let key: string | undefined = component;

    while (key !== undefined && !seen.has(key)) {
        seen.add(key);

        const page: Page | undefined = PAGES[key];

        if (page === undefined) {
            break;
        }

        trail.unshift({
            title:
                typeof page.title === 'function'
                    ? page.title(props)
                    : page.title,
            href: page.href(props),
        });

        key = page.parent;
    }

    return trail;
}
