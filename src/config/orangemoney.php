<?php

return [
    'api_path'    => env('OM_API_PATH', 'orange-money-webpay/dev/v1'),
    'currency'    => env('OM_CURRENCY', 'OUV'),
    'timeout'         => env('OM_TIMEOUT', 30),
    'connect_timeout' => env('OM_CONNECT_TIMEOUT', 10),
    'token_ttl'       => env('OM_TOKEN_TTL', 3600),
    'auth_header'  => env('OM_AUTH_HEADER', ''),
    'merchant_key' => env('OM_MERCHANT_KEY', ''),
    'return_url'   => env('OM_RETURN_URL', ''),
    'cancel_url'   => env('OM_CANCEL_URL', ''),
    'notif_url'    => env('OM_NOTIF_URL', env('OM_NOTIf_URL', '')),
];
