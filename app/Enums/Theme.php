<?php

namespace App\Enums;

/**
 * The colour themes a person can choose in Settings → Appearance. Each case is
 * a block of token overrides in resources/css/ignition/themes.css, selected by
 * <html data-theme>; Daylight is the tokens as written, with no overrides.
 *
 * The swatches here are only for the picker's previews and the pre-paint
 * ground in app.blade.php — keep them in step with themes.css.
 */
enum Theme: string
{
    case Volt = 'volt';
    case IndigoNight = 'indigo-night';
    case Lagoon = 'lagoon';
    case Neon = 'neon';
    case Daylight = 'daylight';
    case Sandstone = 'sandstone';

    /** What everyone sees until they choose, guests included. */
    public static function default(): self
    {
        return self::Volt;
    }

    public function label(): string
    {
        return match ($this) {
            self::Volt => 'Volt',
            self::IndigoNight => 'Indigo Night',
            self::Lagoon => 'Lagoon',
            self::Neon => 'Neon',
            self::Daylight => 'Daylight',
            self::Sandstone => 'Sandstone',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Volt => 'Charcoal with an electric lime.',
            self::IndigoNight => 'Deep navy with indigo.',
            self::Lagoon => 'Dark teal-slate with aqua.',
            self::Neon => 'Plum-black with hot pink.',
            self::Daylight => 'Light and cool, with a vivid blue.',
            self::Sandstone => 'Light and warm, with deep teal.',
        };
    }

    public function isDark(): bool
    {
        return ! in_array($this, [self::Daylight, self::Sandstone], true);
    }

    /**
     * The colours a preview is drawn from, and the page ground and status-bar
     * colour painted before the stylesheet loads.
     *
     * @return array{ground: string, card: string, surface: string, text: string, muted: string, accent: string, onAccent: string}
     */
    public function swatches(): array
    {
        return match ($this) {
            self::Volt => ['ground' => '#0f1110', 'card' => '#181b19', 'surface' => '#212522', 'text' => '#eef2ea', 'muted' => '#a9b0aa', 'accent' => '#c6f135', 'onAccent' => '#0f1110'],
            self::IndigoNight => ['ground' => '#0a0f1e', 'card' => '#111a2e', 'surface' => '#18223a', 'text' => '#e6e9f4', 'muted' => '#a3adc8', 'accent' => '#585bef', 'onAccent' => '#ffffff'],
            self::Lagoon => ['ground' => '#091416', 'card' => '#0f1f21', 'surface' => '#16292b', 'text' => '#e4f2f0', 'muted' => '#9fb9b6', 'accent' => '#2dd4bf', 'onAccent' => '#062420'],
            self::Neon => ['ground' => '#110c15', 'card' => '#1a1320', 'surface' => '#241a2b', 'text' => '#f4eaf6', 'muted' => '#b9a8bf', 'accent' => '#f472b6', 'onAccent' => '#1a0b14'],
            self::Daylight => ['ground' => '#f3f5f9', 'card' => '#fdfdfe', 'surface' => '#e9e9ea', 'text' => '#1d1f20', 'muted' => '#5d5d60', 'accent' => '#2f6bed', 'onAccent' => '#ffffff'],
            self::Sandstone => ['ground' => '#f4f1ec', 'card' => '#fcfaf7', 'surface' => '#ebe6de', 'text' => '#24211d', 'muted' => '#625b52', 'accent' => '#0f766e', 'onAccent' => '#ffffff'],
        };
    }

    /**
     * Every theme, shaped for the Appearance page.
     *
     * @return list<array{value: string, label: string, description: string, dark: bool, swatches: array<string, string>}>
     */
    public static function options(): array
    {
        return array_map(fn (self $theme) => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'description' => $theme->description(),
            'dark' => $theme->isDark(),
            'swatches' => $theme->swatches(),
        ], self::cases());
    }
}
