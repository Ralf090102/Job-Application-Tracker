<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Phase 5's job-posting extraction (App\Services\OllamaJobPostingExtractor).
    // Local, no API key — unlike everything else in this file.
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'mistral:latest'),
    ],

    // v2 Phase 2 — JSearch (RapidAPI) job search (App\Services\JobSearchClient).
    // The first real API key this project stores.
    'jsearch' => [
        'key' => env('JSEARCH_API_KEY'),
        'host' => env('JSEARCH_API_HOST', 'jsearch.p.rapidapi.com'),
    ],

    // v2 Phase 2 — shared-secret header guarding the machine-to-machine
    // POST /api/auto-apply/ingest endpoint (n8n calls this, not the SPA, so
    // Sanctum's cookie auth doesn't apply — see
    // App\Http\Middleware\VerifyAutoApplyIngestToken).
    'auto_apply' => [
        'ingest_token' => env('AUTO_APPLY_INGEST_TOKEN'),
    ],

];
