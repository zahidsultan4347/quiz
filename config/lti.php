<?php

return [
    'default' => [
        'private_key' => env('LTI_PRIVATE_KEY', storage_path('keys/private.key')),
        'public_key' => env('LTI_PUBLIC_KEY', storage_path('keys/public.key')),
        'kid' => env('LTI_KID', 'moodle-quiz-lti-key'),
        'issuer' => env('APP_URL'),
        'client_id' => env('LTI_CLIENT_ID', 'moodle-quiz-lti-client'),
        'deployment_id' => env('LTI_DEPLOYMENT_ID', '1'),
        'auth_login_url' => env('APP_URL') . '/lti/login',
        'auth_token_url' => env('APP_URL') . '/lti/token',
        'tool_keyset_url' => env('APP_URL') . '/lti/.well-known/jwks.json',
        'redirect_url' => env('APP_URL') . '/lti/launch',
    ],
    
    'moodle' => [
        'url' => env('MOODLE_URL'),
        'webservice_token' => env('MOODLE_WS_TOKEN'),
        'rest_format' => 'json',
    ],
    
    'quiz' => [
        'default_time_limit' => 45,
        'auto_save_interval' => 30, // seconds
        'disconnection_timeout' => 300, // seconds
        'max_resume_attempts' => 3,
    ],
];