<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit, not '*': Phase 6 (Sanctum SPA auth) needs cookies sent
    // cross-origin, and browsers refuse credentialed requests against a
    // wildcard origin. FRONTEND_URL is set in .env, defaulting to Vite's
    // dev-server port. See Roadmap.md, "Architecture: fully decoupled SPA".
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Sanctum's SPA cookie-based auth (Phase 6). Harmless for
    // Phase 1's plain unauthenticated fetch.
    'supports_credentials' => true,

];
