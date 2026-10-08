<?php

return [
    'enabled' => env('MOMO_ENABLED', false),
    'partner_code' => env('MOMO_PARTNER_CODE', ''),
    'access_key' => env('MOMO_ACCESS_KEY', ''),
    'secret_key' => env('MOMO_SECRET_KEY', ''),
    'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    'redirect_url' => env('MOMO_REDIRECT_URL', ''),
    'ipn_url' => env('MOMO_IPN_URL', ''),
];
