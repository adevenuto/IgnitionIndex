<!DOCTYPE html>
{{-- The colour theme (Settings → Appearance, App\Enums\Theme), rendered here so
     the first paint is already in it. Guests and anyone who has not chosen get
     the default. --}}
@php($theme = auth()->user()?->colourTheme() ?? \App\Enums\Theme::default())
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}" data-scheme="{{ $theme->isDark() ? 'dark' : 'light' }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover is required or env(safe-area-inset-*) resolves to
             0px on iOS, and the navy chrome stops short of the safe areas. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- The theme's ground, before the CSS bundle loads (so it cannot use var()).
             The value comes from Theme::swatches(), which MUST track --color-bg in
             themes.css / product.css, or cold loads flash the wrong colour. --}}
        <style>
            html {
                background-color: {{ $theme->swatches()['ground'] }};
            }
        </style>

        {{-- Matches the top bar (--surface-card), so a phone's status bar reads as
             part of the chrome. --}}
        <meta name="theme-color" content="{{ $theme->swatches()['card'] }}">

        {{-- The type face chosen in the design trial (resources/js/lib/fonts.ts),
             applied before first paint so it never flashes the default. --}}
        <script>
            try {
                var face = localStorage.getItem('ii-font');
                if (face) document.documentElement.dataset.font = face;
            } catch (e) {}
        </script>

        {{-- Pre-launch. robots.txt asks crawlers not to fetch the site; this asks
             them not to index it if they do anyway, which is the half that keeps
             it out of results. Laravel Cloud only sends X-Robots-Tag on its own
             *.laravel.cloud domains, never a custom one. Remove both at launch. --}}
        <meta name="robots" content="noindex, nofollow">

        {{-- favicon.ico carries the 16/32/48 bitmaps for browsers and for the
             address bar; the two PNGs are what modern browsers actually pick. --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
