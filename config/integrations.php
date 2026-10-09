<?php

return [
    'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect_uri' => env('GOOGLE_REDIRECT_URI')],
    'sms' => ['enabled' => env('SMS_ENABLED', false), 'sid' => env('TWILIO_ACCOUNT_SID'), 'token' => env('TWILIO_AUTH_TOKEN'), 'service' => env('TWILIO_VERIFY_SERVICE_SID')],
    'bank' => ['enabled' => env('SEPAY_ENABLED', false), 'key' => env('SEPAY_WEBHOOK_KEY'), 'account' => env('BANK_ACCOUNT_NUMBER'), 'bank_bin' => env('BANK_BIN'), 'name' => env('BANK_ACCOUNT_NAME')],
];
