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
    | The content management system, which is a separate application.
    |
    | This engine holds no storefront copy of its own: headings, banners,
    | testimonials and static page text all arrive from here over HTTP. If the
    | CMS is unreachable the storefront still renders, on the defaults compiled
    | into each controller.
    */
    'cms' => [
        'url' => env('CMS_URL', 'http://127.0.0.1:8001'),
        'token' => env('CMS_TOKEN'),
    ],

    /*
    | The token this engine expects from the CMS when it asks us to approve a
    | shop or release a payout. Deliberately different from the content token:
    | the CMS may read our content without being able to move our money.
    */
    'admin_api' => [
        'token' => env('ENGINE_API_TOKEN'),
    ],

];
