/*
 * Numbers out of what someone said, without a network call.
 *
 * Most voice entries are one number for one field ("twelve point five", "62,410
 * miles"), and those are cheap and instant to read here. Anything richer — two
 * quantities, run-together digits like "sixty-two four ten", money said as
 * "forty-eight twelve" — goes to the server instead. This only has to be
 * right about the easy cases, and say so when a case is not easy.
 *
 * The transcriber mostly hands back digits already ("12.5 gallons", "$48.12."),
 * so the word parser is the fallback rather than the main path.
 */

export type SpokenUnit = 'mi' | 'gal' | 'usd';

export type SpokenQuantity = {
    value: number;
    unit: SpokenUnit | null;
};

const SMALL: Record<string, number> = {
    zero: 0,
    oh: 0,
    one: 1,
    two: 2,
    three: 3,
    four: 4,
    five: 5,
    six: 6,
    seven: 7,
    eight: 8,
    nine: 9,
    ten: 10,
    eleven: 11,
    twelve: 12,
    thirteen: 13,
    fourteen: 14,
    fifteen: 15,
    sixteen: 16,
    seventeen: 17,
    eighteen: 18,
    nineteen: 19,
    twenty: 20,
    thirty: 30,
    forty: 40,
    fifty: 50,
    sixty: 60,
    seventy: 70,
    eighty: 80,
    ninety: 90,
};

const SCALES: Record<string, number> = { thousand: 1_000, million: 1_000_000 };

const UNITS: Record<string, SpokenUnit> = {
    mi: 'mi',
    mile: 'mi',
    miles: 'mi',
    gal: 'gal',
    gallon: 'gal',
    gallons: 'gal',
    dollar: 'usd',
    dollars: 'usd',
    buck: 'usd',
    bucks: 'usd',
    usd: 'usd',
};

/** Every quantity in the sentence, in order, with its unit when one was said. */
export function spokenQuantities(text: string): SpokenQuantity[] {
    const tokens = text
        .toLowerCase()
        // A full stop ends a sentence; only a point between digits is decimal.
        .replace(/\.(?!\d)/g, ' ')
        // "$48.12" → "48.12 dollars", so the unit is found the same way as a spoken one.
        .replace(/\$\s*([\d.,]+)/g, '$1 dollars')
        .replace(/(\d),(?=\d{3}\b)/g, '$1')
        .replace(/-/g, ' ')
        .split(/[^a-z0-9.]+/)
        .filter(Boolean);

    const found: SpokenQuantity[] = [];
    let index = 0;

    while (index < tokens.length) {
        const parsed = readNumber(tokens, index);

        if (parsed === null) {
            index++;
            continue;
        }

        index = parsed.next;
        const unit = UNITS[tokens[index] ?? ''] ?? null;

        if (unit !== null) {
            index++;
        }

        found.push({ value: parsed.value, unit });
    }

    return found;
}

/**
 * One number at tokens[start], written as digits or as words, with an optional
 * spoken decimal ("point five") or "and a half".
 */
function readNumber(
    tokens: string[],
    start: number,
): { value: number; next: number } | null {
    const first = tokens[start];

    if (/^\d+(\.\d+)?$/.test(first)) {
        // Transcribers write "12 and a half" with digits, so the half applies here too.
        return withHalf(tokens, Number(first), start + 1);
    }

    let total = 0;
    let current = 0;
    let index = start;
    let any = false;

    while (index < tokens.length) {
        const token = tokens[index];

        if (token in SMALL) {
            // A word only extends the number if it fits a slot still open:
            // "sixty two" is 62, but "sixty two four ten" is three numbers,
            // not 76 — and that one belongs to the server.
            const value = SMALL[token];
            const tail = current % 100;
            const fits =
                tail === 0 || (tail % 10 === 0 && tail >= 20 && value < 10);

            if (any && !fits) {
                break;
            }

            current += value;
        } else if (token === 'hundred' && any) {
            current *= 100;
        } else if (token in SCALES && any) {
            total += current * SCALES[token];
            current = 0;
        } else if (
            token === 'and' &&
            any &&
            (tokens[index + 1] ?? '') in SMALL
        ) {
            // "one hundred and five" — keep going.
        } else {
            break;
        }

        any = true;
        index++;
    }

    if (!any) {
        return null;
    }

    let value = total + current;

    if (tokens[index] === 'point') {
        let digits = '';
        index++;

        while (
            index < tokens.length &&
            tokens[index] in SMALL &&
            SMALL[tokens[index]] < 10
        ) {
            digits += String(SMALL[tokens[index]]);
            index++;
        }

        value = Number(`${value}.${digits || '0'}`);

        return { value, next: index };
    }

    return withHalf(tokens, value, index);
}

/** A trailing "and a half", when there is one. */
function withHalf(
    tokens: string[],
    value: number,
    index: number,
): { value: number; next: number } {
    return tokens[index] === 'and' &&
        tokens[index + 1] === 'a' &&
        tokens[index + 2] === 'half'
        ? { value: value + 0.5, next: index + 3 }
        : { value, next: index };
}

/**
 * The one number a single-field step is asking for, or null when the sentence
 * is not that simple and should be read by the server instead.
 */
export function singleSpokenNumber(
    text: string,
    unit: SpokenUnit,
): number | null {
    const quantities = spokenQuantities(text);

    if (quantities.length !== 1) {
        return null;
    }

    const [only] = quantities;

    return only.unit === null || only.unit === unit ? only.value : null;
}
