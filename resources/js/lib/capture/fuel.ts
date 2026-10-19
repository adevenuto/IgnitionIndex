import type { CaptureLane } from '@/lib/capture/lanes';

/*
 * A fill-up in two steps. The pump step holds gallons and total together
 * because one photo of a pump shows both — splitting them would ask for the
 * same picture twice. The date, the full-tank box and notes are not steps: they
 * have good defaults, so they wait on the review card.
 *
 * Mirrors app/Support/Capture/FuelLane.php, which reads the same keys.
 */
export const fuelLane: CaptureLane = {
    id: 'fuel',
    fields: [
        {
            key: 'odometer',
            name: 'odometer',
            label: 'Odometer',
            unit: 'mi',
            precision: 0,
            placeholder: 'Reading',
        },
        {
            key: 'gallons',
            name: 'gallons',
            label: 'Gallons',
            unit: 'gal',
            precision: 3,
            placeholder: '0.000',
        },
        {
            key: 'total_cost',
            name: 'cost',
            label: 'Total cost',
            unit: 'usd',
            precision: 2,
            placeholder: '0.00',
        },
    ],
    steps: [
        {
            id: 'odometer',
            title: 'Odometer',
            prompt: 'Snap the dash, or say the reading.',
            fields: ['odometer'],
        },
        {
            id: 'pump',
            title: 'At the pump',
            prompt: 'Snap the pump display, or say the gallons and the total.',
            fields: ['gallons', 'total_cost'],
        },
    ],
};
