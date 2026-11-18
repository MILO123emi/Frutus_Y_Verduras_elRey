<?php

// Keep seed-time overrides available when Laravel's configuration is cached.
return [
    'admin_username' => env('ADMIN_USERNAME', 'abuelo'),
    'admin_password' => env('ADMIN_PASSWORD', 'rey2026'),
    'admin_pin' => (string) env('ADMIN_PIN', '1234'),
];
