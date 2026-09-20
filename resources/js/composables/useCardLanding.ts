import { nextTick } from 'vue';

/**
 * Makes two swapped cards travel to their new places instead of teleporting.
 *
 * FLIP, with one wrinkle: the wall's two regions are separate `v-for` lists, so
 * a card crossing the boundary is a DIFFERENT element afterwards — destroyed
 * from one list and created in the other. Keying off the DOM node would lose it,
 * so this measures by id before the change and finds the id again after, then
 * animates whatever element now carries it from where its partner used to be.
 *
 * Position only, per §4: the card that arrives is already the right size for the
 * region it landed in: it travels, it does not grow.
 */
export function useCardLanding(attribute = 'data-gauge-id') {
    function elementFor(id: number): HTMLElement | null {
        return document.querySelector<HTMLElement>(`[${attribute}="${id}"]`);
    }

    /**
     * Measure the given cards, run `change`, then play them into place.
     */
    async function land(ids: number[], change: () => void): Promise<void> {
        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (reduced) {
            change();

            return;
        }

        const before = new Map<number, DOMRect>();

        for (const id of ids) {
            const el = elementFor(id);

            if (el !== null) {
                before.set(id, el.getBoundingClientRect());
            }
        }

        change();
        await nextTick();

        for (const id of ids) {
            const el = elementFor(id);
            const was = before.get(id);

            if (el === null || was === undefined) {
                continue;
            }

            const now = el.getBoundingClientRect();
            const dx = was.left - now.left;
            const dy = was.top - now.top;

            // A swap inside one region can leave a card exactly where it was.
            if (Math.abs(dx) < 1 && Math.abs(dy) < 1) {
                continue;
            }

            el.style.transition = 'none';
            el.style.transform = `translate(${dx}px, ${dy}px)`;

            // Read something layout-dependent so the browser commits the start
            // position; without this the transform and its removal coalesce into
            // one frame and nothing animates.
            void el.offsetHeight;

            el.classList.add('ii-card-landing');
            el.style.transition = '';
            el.style.transform = '';

            const clean = (): void => {
                el.classList.remove('ii-card-landing');
                el.removeEventListener('transitionend', clean);
            };

            el.addEventListener('transitionend', clean);
        }
    }

    return { land };
}
