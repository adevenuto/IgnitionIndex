import { router, usePage } from '@inertiajs/vue3';
import { useQuickAdd } from '@/composables/useQuickAdd';
import { garage } from '@/routes';
import { create, show } from '@/routes/vehicles';

/*
 * What the shell's Log button does, from anywhere.
 *
 * A page that already knows the vehicle (a vehicle's own page) claims the
 * button and opens the log sheet in place. Everywhere else the button needs a
 * vehicle first, so it goes and gets one:
 *
 *   - no vehicles yet  -> add the first one
 *   - exactly one      -> that vehicle, with the sheet already open (?log)
 *   - several          -> the garage in pick mode (?pick), where tapping a car
 *                         opens the sheet for it
 *
 * So it is always one tap from "I want to log something" to the sheet, or as
 * close as the person's garage allows.
 */
export function useLogEntry() {
    const page = usePage();
    const { triggerQuickAdd } = useQuickAdd();

    function startLogEntry(): void {
        if (triggerQuickAdd()) {
            return;
        }

        const summary = page.props.garage;

        if (!summary || summary.vehicle_count === 0) {
            router.visit(create());
        } else if (summary.only_vehicle_id !== null) {
            router.visit(
                show(summary.only_vehicle_id, { query: { log: 'open' } }),
            );
        } else {
            router.visit(garage({ query: { pick: '1' } }));
        }
    }

    return { startLogEntry };
}
