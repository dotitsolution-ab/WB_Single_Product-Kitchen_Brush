<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Single Product Store',
        'base_url' => '',
        'timezone' => 'Asia/Dhaka',
        'debug' => false,
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'pass' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    'steadfast' => [
        'base_url' => 'https://portal.steadfast.com.bd/api/v1',
        'api_key' => '',
        'secret_key' => '',
    ],
    'security' => [
        'session_name' => 'sp_store_session',
        'admin_idle_timeout_minutes' => 60,
        'login_max_attempts' => 5,
        'login_decay_minutes' => 15,
    ],
];
