<?php

return [
    'demo_enabled' => (bool) env('LASTRO_DEMO_ENABLED', false),
    'staff_email' => env('LASTRO_STAFF_EMAIL'),
    'staff_password' => env('LASTRO_STAFF_PASSWORD'),
    'staff_name' => env('LASTRO_STAFF_NAME', 'Equipe'),
];
