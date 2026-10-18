<?php

return [
    'email' => [
        'mailer' => env('MAIL_MAILER', 'log'), 'scheme' => env('MAIL_SCHEME', 'smtp'),
        'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'),
        'from_address' => env('MAIL_FROM_ADDRESS'), 'from_name' => env('MAIL_FROM_NAME', 'Badminton Hub'),
    ],
    'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect_uri' => env('GOOGLE_REDIRECT_URI')],
    'sms' => ['enabled' => env('SMS_ENABLED', false), 'sid' => env('TWILIO_ACCOUNT_SID'), 'token' => env('TWILIO_AUTH_TOKEN'), 'service' => env('TWILIO_VERIFY_SERVICE_SID')],
    'bank' => ['enabled' => env('SEPAY_ENABLED', false), 'key' => env('SEPAY_WEBHOOK_KEY'), 'account' => env('BANK_ACCOUNT_NUMBER'), 'bank_bin' => env('BANK_BIN'), 'name' => env('BANK_ACCOUNT_NAME')],
];
