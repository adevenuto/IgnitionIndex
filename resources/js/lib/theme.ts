/*
 * Applying a colour theme in place (Settings → Appearance).
 *
 * The server renders <html data-theme data-scheme> and the page ground from
 * the saved choice, so a fresh load is already right; this is for switching
 * without a reload — the attributes the stylesheet keys on, the ground behind
 * the app, and the phone's status-bar colour. The homepage figures watch
 * data-theme and recolour themselves.
 */
export type ThemeSwatches = {
    ground: string;
    card: string;
    surface: string;
    text: string;
    muted: string;
    accent: string;
    onAccent: string;
};

export type ThemeOption = {
    value: string;
    label: string;
    description: string;
    dark: boolean;
    swatches: ThemeSwatches;
};

export function applyTheme(theme: ThemeOption): void {
    const root = document.documentElement;

    root.dataset.theme = theme.value;
    root.dataset.scheme = theme.dark ? 'dark' : 'light';
    root.style.backgroundColor = theme.swatches.ground;
    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', theme.swatches.card);
}
