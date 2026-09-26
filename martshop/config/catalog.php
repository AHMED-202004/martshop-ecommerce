<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy demo catalogue fallback
    |--------------------------------------------------------------------------
    |
    | This exists only to support the gradual local migration from the old
    | in-controller catalogue. It must stay disabled in production.
    |
    */
    'legacy_fallback_enabled' => (bool) env('LEGACY_CATALOG_FALLBACK_ENABLED', false),
];
