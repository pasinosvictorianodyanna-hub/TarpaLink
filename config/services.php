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

    /*
    | PayMongo, sandbox only. With no secret key the app runs in simulated mode:
    | intents are generated locally and the webhook accepts events signed with
    | the local secret, so the full flow can be demonstrated without live keys.
    | Simulated mode is refused outside `local` — see PayMongo::verifySignature().
    */
    'paymongo' => [
        'secret' => env('PAYMONGO_SECRET_KEY'),
        'public' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    ],

    /*
     * Address lookup, through OpenStreetMap's Nominatim.
     *
     * No API key, which is why it is the default: a project that needs a card on
     * file to place a pin is a project that ships with seeded coordinates. The
     * usage policy is strict, though — one request a second and a real
     * User-Agent that identifies the application — so results are cached for a
     * month and the address box is a deliberate submit, not a type-ahead.
     */
    'nominatim' => [
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        // Off by default in tests and for offline demos: an address box that
        // hangs for ten seconds because the sandbox has no route out is worse
        // than one that says it cannot look addresses up.
        'enabled' => (bool) env('GEOCODER_ENABLED', true),
        'country' => env('GEOCODER_COUNTRY', 'ph'),
        'timeout' => (int) env('GEOCODER_TIMEOUT', 6),
    ],

    /*
     * Inbound mail, so a reply reaches the job it is about.
     *
     * `address` is the mailbox the provider forwards to us — every outbound
     * notification carries `reply+<reference>@` on it, so the job survives the
     * customer hitting Reply. Leave it empty and no Reply-To is set at all,
     * which is the honest default: a reply address that nobody reads is worse
     * than none, because the customer believes they were heard.
     *
     * `secret` signs the forwarded body. Empty means the webhook refuses
     * everything rather than accepting unsigned history from anyone who finds
     * the URL.
     */
    'inbound' => [
        'address' => env('INBOUND_ADDRESS'),
        'secret' => env('INBOUND_SECRET'),
        'max_body' => (int) env('INBOUND_MAX_BODY', 20000),
    ],

];
