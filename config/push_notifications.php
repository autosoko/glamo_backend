<?php

return [
    'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', true),

    // Supported: fcm_legacy, fcm_v1
    'provider' => env('PUSH_NOTIFICATIONS_PROVIDER', 'fcm_legacy'),

    'fcm_legacy' => [
        'endpoint' => env('FCM_LEGACY_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
        'server_key' => env('FCM_SERVER_KEY', ''),
        'timeout_seconds' => (int) env('FCM_TIMEOUT_SECONDS', 10),
        'chunk_size' => (int) env('FCM_CHUNK_SIZE', 500),
    ],

    'fcm_v1' => [
        'endpoint_template' => env('FCM_V1_ENDPOINT_TEMPLATE', 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send'),
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS', ''),
        // Optional: use JSON content directly instead of file path.
        'credentials_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON', ''),
        'scope' => env('FCM_V1_SCOPE', 'https://www.googleapis.com/auth/firebase.messaging'),
        'timeout_seconds' => (int) env('FCM_TIMEOUT_SECONDS', 10),
        'chunk_size' => (int) env('FCM_V1_CHUNK_SIZE', 100),
    ],
];
