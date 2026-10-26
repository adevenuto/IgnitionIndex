<?php

/*
 * The story figure is framed by the homepage. The hosting edge adds
 * X-Frame-Options: DENY to everything, so the figure must carry a
 * frame-ancestors policy — which browsers obey over that header — or the
 * frame stays blank in production.
 */
test('the story figure is served with a policy that lets our pages frame it', function () {
    $this->get(route('figures.log-loop'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertSee('id="hero"', false);
});

test('the homepage frames the figure from that route', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));

    expect(file_get_contents(resource_path('js/components/marketing/TheLoopFigure.vue')))
        ->toContain('logLoop.url()');
});
