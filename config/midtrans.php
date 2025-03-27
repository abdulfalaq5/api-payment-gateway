<?php

return [
    'use' => env('MIDTRANS_USE', false),
    'server_key' => env('MIDTRANS_SERVER_KEY', ''),
    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,
    'url' => env('MIDTRANS_URL', 'https://app.midtrans.com/snap/v2/vtweb/'),
    'sandbox_url' => env('MIDTRANS_SANDBOX_URL', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'),
]; 