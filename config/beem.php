<?php

return [
    'api_key' => env('BEEM_API_KEY'),
    'secret_key' => env('BEEM_SECRET_KEY'),

    // Sender ID registered on Beem (e.g. "Glamo")
    'sender_id' => env('BEEM_SENDER_ID', 'Glamo'),

    // Used when numbers are entered in local format (e.g. 07XXXXXXXX)
    'default_country_code' => env('BEEM_DEFAULT_COUNTRY_CODE', '255'),

    // Beem SMS endpoint (default from Beem docs)
    'sms_url' => env('BEEM_SMS_URL', 'https://apisms.beem.africa/v1/send'),

    // Beem OTP API (PIN request/verification)
    'otp' => [
        'app_id' => env('BEEM_OTP_APP_ID', 1),
        'request_url' => env('BEEM_OTP_REQUEST_URL', 'https://apiotp.beem.africa/v1/request'),
        'verify_url' => env('BEEM_OTP_VERIFY_URL', 'https://apiotp.beem.africa/v1/verify'),
        'ttl_minutes' => env('BEEM_OTP_TTL_MINUTES', 5),
    ],
];
