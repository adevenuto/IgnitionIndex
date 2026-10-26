/*
 * The type faces on trial since the 2026-10 softening, and the one in use.
 *
 * Every face here is built by vite.config.ts under the variable named here,
 * and product.css points --font-ui at it when <html data-font> names its id.
 * Only the default is preloaded; the others load on first use, so offering
 * them costs nothing until someone picks one.
 *
 * The choice is per browser (localStorage), read by an inline script in
 * app.blade.php before first paint so a chosen face never flashes the
 * default. Once a face is settled, it becomes the default and the rest go.
 */
export type FontChoice = { id: string; label: string };

export const FONT_CHOICES: FontChoice[] = [
    { id: 'inter', label: 'Inter' },
    { id: 'plus-jakarta-sans', label: 'Plus Jakarta Sans' },
    { id: 'manrope', label: 'Manrope' },
    { id: 'dm-sans', label: 'DM Sans' },
    { id: 'figtree', label: 'Figtree' },
    { id: 'outfit', label: 'Outfit' },
];

export const DEFAULT_FONT = 'inter';

/** Keep in step with the inline script in resources/views/app.blade.php. */
export const FONT_STORAGE_KEY = 'ii-font';
export const FONT_PICKER_KEY = 'ii-font-picker';

export function currentFont(): string {
    return document.documentElement.dataset.font ?? DEFAULT_FONT;
}

export function applyFont(id: string): void {
    if (id === DEFAULT_FONT) {
        delete document.documentElement.dataset.font;
    } else {
        document.documentElement.dataset.font = id;
    }

    try {
        if (id === DEFAULT_FONT) {
            localStorage.removeItem(FONT_STORAGE_KEY);
        } else {
            localStorage.setItem(FONT_STORAGE_KEY, id);
        }
    } catch {
        // Private browsing: the choice lasts for this page only.
    }
}

/**
 * The picker shows only for those who asked for it: `?fonts` turns it on for
 * this browser and `?fonts=off` turns it off again. Nobody else ever sees it.
 */
export function fontPickerEnabled(): boolean {
    const param = new URLSearchParams(window.location.search).get('fonts');

    try {
        if (param === 'off') {
            localStorage.removeItem(FONT_PICKER_KEY);
        } else if (param !== null) {
            localStorage.setItem(FONT_PICKER_KEY, '1');
        }

        return localStorage.getItem(FONT_PICKER_KEY) === '1';
    } catch {
        return param !== null && param !== 'off';
    }
}
