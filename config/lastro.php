<?php

return [
    'demo_enabled' => (bool) env('LASTRO_DEMO_ENABLED', false),
    'demo_examples' => (bool) env('LASTRO_DEMO_EXAMPLES', true),
    'vercel_ingress' => (bool) env('LASTRO_VERCEL_INGRESS', false),
    'vercel_runtime' => (string) env('VERCEL', '') === '1',
    'staff_email' => env('LASTRO_STAFF_EMAIL'),
    'staff_password' => env('LASTRO_STAFF_PASSWORD'),
    'staff_name' => env('LASTRO_STAFF_NAME', 'Equipe'),
];
