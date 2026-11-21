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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'flask' => [
        'url' => env('FLASK_API_URL'),
    ],

    // Balance assistant (Claude Messages API). Off unless AI_ASSISTANT_ENABLED=true; the endpoint 404s while off.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_ASSISTANT_MODEL', 'claude-opus-5-5'),
        'api_version' => env('AI_ASSISTANT_API_VERSION', '2026-10-21'),
        // Comma-separated anthropic-beta values; without a server-side-fallback-* beta no `fallbacks` is sent.
        'betas' => env('AI_ASSISTANT_BETAS', 'server-side-fallback-2026-07-01'),
        'enabled' => (bool) env('AI_ASSISTANT_ENABLED', false),
        'base_url' => rtrim(env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'), '/'),
    ],

    'node' => [
        'path' => env('NODEPATH'),
    ],

];
