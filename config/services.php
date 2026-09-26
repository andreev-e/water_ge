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

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_API_TOKEN')
    ],

    'facebook' => [
        // One shared Page for all service centers; posts are told apart by hashtags.
        // Groups can't be posted to: Meta removed the Groups API in April 2024.
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'page_token' => env('FACEBOOK_PAGE_TOKEN'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v26.0'),
    ],

    'gwp' => [
        // gwp.ge drops connections from outside Georgia, so prod needs a Georgian egress,
        // e.g. http://user:pass@host:port or socks5h://host:port
        'proxy' => env('GWP_PROXY'),
    ],
];
