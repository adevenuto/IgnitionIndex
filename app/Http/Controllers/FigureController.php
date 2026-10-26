<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the homepage's story figure (resources/figures/log-loop.html), which
 * the page frames in an iframe so its script and styles stay its own.
 *
 * Not a static file in public/ on purpose: the hosting edge adds
 * `X-Frame-Options: DENY` to every response, which stopped the browser from
 * showing the frame at all. A `frame-ancestors 'self'` policy takes
 * precedence over that header in every current browser, so sending it from
 * here lets our own pages frame the figure while nobody else's can.
 *
 * Read as a plain file rather than a Blade view: it is vanilla HTML and JS,
 * and Blade would try to compile the `@json` in its usage notes.
 */
class FigureController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            (string) file_get_contents(resource_path('figures/log-loop.html')),
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Security-Policy' => "frame-ancestors 'self'",
                'Cache-Control' => 'public, max-age=3600',
            ],
        );
    }
}
