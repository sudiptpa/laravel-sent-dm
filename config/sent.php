<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The name of the connection to use when none is specified.
    | Must match a key in the "connections" array below.
    |
    */

    'default' => env('SENT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each connection represents a separate Sent.dm API key. Define one entry
    | per tenant / per environment. The "default" connection is used when no
    | connection name is passed to Sent::connection().
    |
    | Multi-tenant example:
    |   Sent::connection('tenant_a')->to('+61...')->send();
    |
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
    |
    | When no channel is specified, Sent.dm auto-routes to the best available
    | channel (WhatsApp preferred, SMS fallback). The SDK value for auto is
    | "sent" — this config key is for documentation; the SDK call omits channel
    | when null is returned from SentMessage::getChannel().
    |
    | Supported: "sms", "whatsapp", "rcs"  (null = auto)
    |
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | The webhook route is opt-in. Set SENT_WEBHOOK_ENABLED=true to register
    | the route. The secret is the "whsec_..." value from the Sent.dm dashboard
    | (shown on webhook create/rotate). Signatures are verified as HMAC-SHA256
    | over "{webhook_id}.{timestamp}.{raw_body}".
    |
    */

    'webhook' => [
        'enabled' => env('SENT_WEBHOOK_ENABLED', false),
        'secret' => env('SENT_WEBHOOK_SECRET'),
        'path' => env('SENT_WEBHOOK_PATH', 'sent/webhook'),
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
    |
    | When enabled, all outbound messages are simulated server-side with no
    | real delivery. Use SENT_SANDBOX=true in local/staging environments.
    | Individual messages can also be sandboxed via ->sandbox() on SentMessage.
    |
    */

    'sandbox' => env('SENT_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Message Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, every outbound message is written to the sent_logs table
    | and delivery status updates arrive automatically via webhook events.
    | Requires the sent_logs migration: php artisan vendor:publish --tag=laravel-sent-migrations
    |
    */

    'logging' => [
        'enabled' => env('SENT_LOGGING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-Out / Consent Management
    |--------------------------------------------------------------------------
    |
    | When enabled, inbound STOP/UNSUBSCRIBE messages are automatically recorded
    | in the sent_opt_outs table. Set guard=true to block outbound messages to
    | opted-out contacts (throws ContactOptedOutException).
    | Requires the sent_opt_outs migration: php artisan vendor:publish --tag=laravel-sent-migrations
    |
    */

    'opt_out' => [
        'enabled' => env('SENT_OPT_OUT_ENABLED', false),
        'guard' => env('SENT_OPT_OUT_GUARD', false),

        // Keywords that trigger an opt-out when received as an inbound message.
        // Add locale-specific keywords (e.g. 'ARRET', 'STOPP') for your market.
        'keywords' => ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'],

        // Keywords that re-enable messaging for a previously opted-out contact.
        'opt_in_keywords' => ['START', 'YES', 'UNSTOP'],
    ],

];
