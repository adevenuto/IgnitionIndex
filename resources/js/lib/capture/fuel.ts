import {
    nearEstimate,
    notBelowLast,
    ratioWithin,
    within,
} from '@/lib/capture/checks';
import type { CaptureLane } from '@/lib/capture/lanes';

/*
 * A fill-up in two steps. The pump step holds gallons and total together
 * because one photo of a pump shows both — splitting them would ask for the
 * same picture twice. The date, the full-tank box and notes are not steps: they
 * have good defaults, so they wait on the review card.
 *
 * Mirrors app/Support/Capture/FuelLane.php, which reads the same keys and
 * raises the same flags, so a value reads the same whichever side noticed it.
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
            cards: {
                photo: 'Read the total miles off the dash',
                voice: 'Say the reading, like "62,410"',
                type: 'Key in the digits',
            },
            reference: 'lastOdometer',
        },
        {
            id: 'pump',
            title: 'At the pump',
            prompt: 'Snap the pump display, or say the gallons and the total.',
            fields: ['gallons', 'total_cost'],
            cards: {
                photo: 'Read gallons and total off the pump',
                voice: 'Say both, like "12.5 gallons, $48.12"',
                type: 'Key in gallons and total',
            },
        },
    ],
    checks: [
        notBelowLast('odometer'),
        nearEstimate('odometer'),
        within('gallons', 0.5, 50, (gallons) =>
            gallons < 0.5
                ? `${gallons} gallons is too little for a fill-up.`
                : `${gallons} gallons is more than a tank holds.`,
        ),
        ratioWithin(
            'total_cost',
            'gallons',
            1.5,
            10,
            (price) => `That works out to $${price.toFixed(2)} a gallon.`,
        ),
    ],
};
