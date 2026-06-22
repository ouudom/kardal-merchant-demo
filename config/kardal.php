<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kardal Gateway connection
    |--------------------------------------------------------------------------
    | Points at the Kardal/WebPay DEV server. All values come from the
    | credential package the merchant downloads from KUP after approval.
    */

    'base_url' => rtrim((string) env('KARDAL_BASE_URL', ''), '/'),

    // OAuth password-grant credentials (oauth/token)
    'oauth' => [
        'grant_type'    => env('KARDAL_GRANT_TYPE', 'password'),
        'client_id'     => env('KARDAL_CLIENT_ID'),
        'client_secret' => env('KARDAL_CLIENT_SECRET'),
        'username'      => env('KARDAL_USERNAME'),
        'password'      => env('KARDAL_PASSWORD'),
    ],

    // Merchant identity + request signing
    'seller_code' => env('KARDAL_SELLER_CODE'),
    'api_key'     => env('KARDAL_API_KEY'),          // api_secret_key from credential.txt
    'sign_type'   => env('KARDAL_SIGN_TYPE', 'MD5'), // MD5 or HMAC-SHA256

    // KHQR BIC enabled for this merchant (from list-payment-method). e.g. KHQR or KESSKHQR.
    'khqr_service_code' => env('KARDAL_KHQR_SERVICE_CODE', 'DYNAMICBANK'),

    // Card service_code for directPay. Leave empty to omit (gateway defaults to VISA_MASTER).
    // Set to GOOGLEPAY or UNIONPAY only to override.
    'card_service_code' => env('KARDAL_CARD_SERVICE_CODE'),

    // RSA public key (PEM) used to encrypt card + customer data for directPay.
    // Either an absolute path to the *-public.key file or the inline PEM string.
    'public_key_path' => env('KARDAL_PUBLIC_KEY_PATH') ?: storage_path('app/kardal/merchant-public.key'),
    'public_key'      => env('KARDAL_PUBLIC_KEY'),

    // Where Kardal sends server-to-server status callbacks + browser return.
    'notify_url'   => env('KARDAL_NOTIFY_URL'),
    'redirect_url' => env('KARDAL_REDIRECT_URL'),

    // Token cache (seconds). Access token default lifetime is 1800s; refresh early.
    'token_ttl' => (int) env('KARDAL_TOKEN_TTL', 1500),

    /*
    |--------------------------------------------------------------------------
    | Gateway Ecommerce KHQR
    |--------------------------------------------------------------------------
    | New merchant-facing ecommerce endpoint. Current scope: KHQR_KESS/GENERATE
    | only. Payment Link, queryOrder, and card still use the legacy WebPay path.
    */
    'ecommerce' => [
        'base_url'         => rtrim((string) env('KARDAL_GATEWAY_BASE_URL', 'http://localhost:8080'), '/'),
        'client_id'        => env('KARDAL_OAUTH_CLIENT_ID'),
        'client_secret'    => env('KARDAL_OAUTH_CLIENT_SECRET'),
        'scope'            => env('KARDAL_OAUTH_SCOPE', 'merchant.ecommerce.payment:create'),
        'signature_secret' => env('KARDAL_GATEWAY_SIGNATURE_SECRET'),
        'core_merchant_key' => env('CORE_JAVA_MERCHANT_KEY'),
        'token_ttl'        => (int) env('KARDAL_GATEWAY_TOKEN_TTL', 3000),
    ],
];
