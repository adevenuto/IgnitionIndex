import type { CaptureField } from '@/lib/capture/lanes';
import { spokenQuantities } from '@/lib/capture/spokenNumbers';

/*
 * Settle a sentence on the device when it is unambiguous for the step being
 * shown, or return null to have the server read it.
 *
 * Unambiguous means every number said lands on exactly one of the step's
 * fields: a lone number on a one-field step, or numbers whose spoken units
 * ("gallons", "dollars") each name a different field. "12.5" on the pump step
 * could be either field, so it goes to the server rather than being guessed.
 */
export function resolveSpeechLocally(
    fields: CaptureField[],
    text: string,
): Record<string, number> | null {
    const quantities = spokenQuantities(text);

    if (quantities.length === 0 || quantities.length > fields.length) {
        return null;
    }

    if (fields.length === 1 && quantities.length === 1) {
        const [quantity] = quantities;

        return quantity.unit === null || quantity.unit === fields[0].unit
            ? { [fields[0].key]: quantity.value }
            : null;
    }

    const resolved: Record<string, number> = {};

    for (const quantity of quantities) {
        const field = fields.find(
            (candidate) => candidate.unit === quantity.unit,
        );

        if (field === undefined || field.key in resolved) {
            return null;
        }

        resolved[field.key] = quantity.value;
    }

    return resolved;
}
