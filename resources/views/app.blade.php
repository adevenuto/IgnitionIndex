<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover is required or env(safe-area-inset-*) resolves to
             0px on iOS, and the navy chrome stops short of the safe areas. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Inline style to set the HTML background color based on our theme in app.css.
             This runs before the CSS bundle loads, so it cannot use var(); the value
             MUST track --color-bg in resources/css/ignition/product.css, or cold
             loads flash the wrong colour. --}}
        <style>
            html {
                background-color: #f3f5f9;
            }
        </style>

        {{-- Matches the near-white top bar (--surface-card), so a phone's status bar
             reads as part of the chrome. --}}
        <meta name="theme-color" content="#fdfdfe">

        {{-- The type face and theme chosen in the design trial (resources/js/lib/fonts.ts),
             applied before first paint so neither flashes the default. A dark theme
             also repaints the ground and the phone's status bar here, since the
             inline values above are the light theme's. Keep the colours in step
             with --color-bg and --surface-card in resources/css/ignition/themes.css. --}}
        <script>
            try {
                var root = document.documentElement;
                var face = localStorage.getItem('ii-font');
                if (face) root.dataset.font = face;
                var theme = localStorage.getItem('ii-theme');
                var grounds = { volt: ['#0f1110', '#181b19'], 'indigo-night': ['#0a0f1e', '#111a2e'] };
                if (grounds[theme]) {
                    root.dataset.theme = theme;
                    root.style.backgroundColor = grounds[theme][0];
                    document.querySelector('meta[name="theme-color"]').setAttribute('content', grounds[theme][1]);
                }
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
