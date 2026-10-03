<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    */

    'default' => env('SENT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [
        'default' => [
            'api_key' => env('SENT_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Channel
    |--------------------------------------------------------------------------
    */

    'default_channel' => env('SENT_DEFAULT_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('SENT_QUEUE_CONNECTION'),
        'name' => env('SENT_QUEUE_NAME', 'default'),
        'tries' => env('SENT_QUEUE_TRIES', 3),
        'backoff' => [1, 5, 10],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'enabled' => env('SENT_WEBHOOK_ENABLED', false),
        'secret' => env('SENT_WEBHOOK_SECRET'),
        'path' => env('SENT_WEBHOOK_PATH', 'sent/webhook'),
        'dedup_ttl' => env('SENT_WEBHOOK_DEDUP_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('SENT_CACHE_ENABLED', true),
        'ttl' => env('SENT_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    */

    'sandbox' => env('SENT_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Message Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('SENT_LOGGING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-Out Management
    |--------------------------------------------------------------------------
    */

    'opt_out' => [
        'enabled' => env('SENT_OPT_OUT_ENABLED', false),
        'guard' => env('SENT_OPT_OUT_GUARD', false),

        'tenant_resolver' => null,

        'keywords' => ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'],

        'opt_in_keywords' => ['START', 'YES', 'UNSTOP'],
    ],

];
