<?php

return [

    /*
     * Assisted capture: a photo or a spoken sentence that pre-fills an entry.
     *
     * It is ungated by plan for now and gated by person instead — a comma-
     * separated list of emails — so it can be tried with real accounts before
     * billing exists. When Pro lands, the `use-assisted-capture` gate is the
     * one place that changes.
     */
    'allowed_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CAPTURE_ALLOWED_EMAILS', '')),
    ))),

    // Every read is a paid call, so each person gets a daily allowance.
    'daily_limit' => (int) env('CAPTURE_DAILY_LIMIT', 40),

    'photo' => [
        'max_kilobytes' => 10 * 1024,
        'max_dimension' => 6000,
        // Big enough for a pump display or a dash cluster to stay legible,
        // small enough that the image costs a fraction of what the original
        // would.
        'long_edge' => 1568,
        'quality' => 85,
    ],

];
