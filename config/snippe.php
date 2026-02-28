<?php

return [
    // Docs base: https://api.snippe.sh
    'base_url' => rtrim((string) env('SNIPPE_BASE_URL', 'https://api.snippe.sh'), '/'),
    'api_key' => (string) env('SNIPPE_API_KEY', ''),
    // Public HTTPS URL used by Snippe to send webhook callbacks.
    'webhook_url' => (string) env('SNIPPE_WEBHOOK_URL', ''),

    // Optional: webhook verification secret (if Snippe sends signatures)
    'webhook_secret' => (string) env('SNIPPE_WEBHOOK_SECRET', ''),

    // Maximum allowed webhook age (seconds) to prevent replay attacks
    'webhook_tolerance' => (int) env('SNIPPE_WEBHOOK_TOLERANCE', 300),

    // Timeout seconds for outgoing requests
    'timeout' => (int) env('SNIPPE_TIMEOUT', 30),
];
