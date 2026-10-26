/*
 * The type pairings on trial since the 2026-10 softening, and the one in use.
 *
 * Each choice is a heading face and a body face, paired so the two sit
 * together. Anthony liked Outfit best of the first round, so most are
 * geometric like it, a few are rounded, and the display faces are paired
 * with Outfit for body text. Every face is built by
 * vite.config.ts under the variable named here, and product.css points
 * --font-ui-display and --font-ui at the pair when <html data-font> names its
 * id. Only the default is preloaded; the others load on first use.
 *
 * TEMPORARY. Once a pairing is settled it becomes the default and the rest,
 * with the picker, go.
 *
 * The choice is per browser (localStorage), read by an inline script in
 * app.blade.php before first paint so a chosen pairing never flashes the
 * default.
 */
export type FontChoice = {
    id: string;
    label: string;
    /** What kind of type it is, said in the picker. */
    kind: string;
    display: string;
    body: string;
};

export const FONT_CHOICES: FontChoice[] = [
    {
        id: 'inter',
        label: 'Inter',
        kind: 'Neo-grotesque · current',
        display: '--font-inter',
        body: '--font-inter',
    },
    {
        id: 'outfit',
        label: 'Outfit',
        kind: 'Geometric',
        display: '--font-outfit',
        body: '--font-outfit',
    },
    {
        id: 'sora',
        label: 'Sora',
        kind: 'Geometric · technical',
        display: '--font-sora',
        body: '--font-sora',
    },
    {
        id: 'urbanist',
        label: 'Urbanist',
        kind: 'Geometric · refined',
        display: '--font-urbanist',
        body: '--font-urbanist',
    },
    {
        id: 'lexend',
        label: 'Lexend',
        kind: 'Geometric · made for legibility',
        display: '--font-lexend',
        body: '--font-lexend',
    },
    {
        id: 'poppins',
        label: 'Poppins',
        kind: 'Geometric · classic',
        display: '--font-poppins',
        body: '--font-poppins',
    },
    {
        id: 'jost',
        label: 'Jost',
        kind: 'Geometric · Futura-like',
        display: '--font-jost',
        body: '--font-jost',
    },
    {
        id: 'league-spartan',
        label: 'League Spartan',
        kind: 'Geometric · bold headings · with Outfit',
        display: '--font-league-spartan',
        body: '--font-outfit',
    },
    {
        id: 'montserrat',
        label: 'Montserrat',
        kind: 'Geometric · wide, urban',
        display: '--font-montserrat',
        body: '--font-montserrat',
    },
    {
        id: 'red-hat',
        label: 'Red Hat Display',
        kind: 'Geometric · with Red Hat Text',
        display: '--font-red-hat-display',
        body: '--font-red-hat-text',
    },
    {
        id: 'albert-sans',
        label: 'Albert Sans',
        kind: 'Geometric grotesque',
        display: '--font-albert-sans',
        body: '--font-albert-sans',
    },
    {
        id: 'kumbh-sans',
        label: 'Kumbh Sans',
        kind: 'Geometric · compact',
        display: '--font-kumbh-sans',
        body: '--font-kumbh-sans',
    },
    {
        id: 'onest',
        label: 'Onest',
        kind: 'Modern grotesque',
        display: '--font-onest',
        body: '--font-onest',
    },
    {
        id: 'rubik',
        label: 'Rubik',
        kind: 'Softened corners',
        display: '--font-rubik',
        body: '--font-rubik',
    },
    {
        id: 'nunito',
        label: 'Nunito',
        kind: 'Rounded',
        display: '--font-nunito',
        body: '--font-nunito',
    },
    {
        id: 'bricolage',
        label: 'Bricolage Grotesque',
        kind: 'Characterful grotesque · with Outfit',
        display: '--font-bricolage-grotesque',
        body: '--font-outfit',
    },
    {
        id: 'unbounded',
        label: 'Unbounded',
        kind: 'Wide display · with Outfit',
        display: '--font-unbounded',
        body: '--font-outfit',
    },
    {
        id: 'syne',
        label: 'Syne',
        kind: 'Quirky display · with Outfit',
        display: '--font-syne',
        body: '--font-outfit',
    },
];

export const DEFAULT_FONT = 'inter';

/** Keep in step with the inline script in resources/views/app.blade.php. */
export const FONT_STORAGE_KEY = 'ii-font';
export const FONT_PICKER_KEY = 'ii-font-picker';

export function currentFont(): string {
    const id = document.documentElement.dataset.font;

    return FONT_CHOICES.some((choice) => choice.id === id)
        ? (id as string)
        : DEFAULT_FONT;
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
 * this browser and `?fonts=off` turns it off again. Nobody else ever sees it,
 * and it only appears on the homepage.
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

/*
 * The homepage figure on trial, alongside the type: the isometric stack
 * (TheIndexFigure), the orbit (TheOrbitFigure) or the logging story
 * (TheLoopFigure). Chosen in the same
 * homepage-only panel and kept per browser. TEMPORARY, like the rest of it.
 */
export type FigureChoice = 'index' | 'orbit' | 'loop';

export const FIGURE_STORAGE_KEY = 'ii-figure';

export function currentFigure(): FigureChoice {
    try {
        const stored = localStorage.getItem(FIGURE_STORAGE_KEY);

        return stored === 'orbit' || stored === 'loop' ? stored : 'index';
    } catch {
        return 'index';
    }
}

export function applyFigure(figure: FigureChoice): void {
    try {
        localStorage.setItem(FIGURE_STORAGE_KEY, figure);
    } catch {
        // Private browsing: the choice lasts for this page only.
    }
}

/*
 * The colour theme on trial (resources/css/ignition/themes.css): Daylight,
 * the light look, or one of the two dark themes drawn from Anthony's
 * references. Applied through <html data-theme>, kept per browser, and
 * restored before first paint by the inline script in app.blade.php — which
 * also holds each dark theme's ground and status-bar colour. TEMPORARY.
 */
export type ThemeChoice = 'daylight' | 'volt' | 'indigo-night';

export const THEME_CHOICES: { id: ThemeChoice; label: string }[] = [
    { id: 'daylight', label: 'Daylight' },
    { id: 'volt', label: 'Volt' },
    { id: 'indigo-night', label: 'Indigo' },
];

export const THEME_STORAGE_KEY = 'ii-theme';

/** Ground and status-bar colour per theme; keep in step with app.blade.php. */
const THEME_GROUNDS: Record<ThemeChoice, [string, string]> = {
    daylight: ['#f3f5f9', '#fdfdfe'],
    volt: ['#0f1110', '#181b19'],
    'indigo-night': ['#0a0f1e', '#111a2e'],
};

export function currentTheme(): ThemeChoice {
    const theme = document.documentElement.dataset.theme;

    return theme === 'volt' || theme === 'indigo-night' ? theme : 'daylight';
}

export function applyTheme(theme: ThemeChoice): void {
    const root = document.documentElement;

    if (theme === 'daylight') {
        delete root.dataset.theme;
    } else {
        root.dataset.theme = theme;
    }

    root.style.backgroundColor = THEME_GROUNDS[theme][0];
    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', THEME_GROUNDS[theme][1]);

    try {
        if (theme === 'daylight') {
            localStorage.removeItem(THEME_STORAGE_KEY);
        } else {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        }
    } catch {
        // Private browsing: the choice lasts for this page only.
    }
}
