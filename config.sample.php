<?php

declare(strict_types=1);

$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string)$value;
};

$envInt = static function (string $key, int $default) use ($env): int {
    $value = $env($key);
    if ($value === '' || !is_numeric($value)) {
        return $default;
    }

    return (int)$value;
};

$envBool = static function (string $key, bool $default = false) use ($env): bool {
    $value = $env($key);
    if ($value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

return [
    'app' => [
        'name' => $env('APP_NAME', 'Single Product Store'),
        'base_url' => $env('APP_BASE_URL'),
        'timezone' => $env('APP_TIMEZONE', 'Asia/Dhaka'),
        'debug' => $envBool('APP_DEBUG', false),
    ],
    'database' => [
        'url' => $env('DATABASE_URL', $env('DB_URL')),
        'driver' => $env('DB_DRIVER', 'pgsql'),
        'host' => $env('DB_HOST', 'localhost'),
        'port' => $envInt('DB_PORT', 5432),
        'name' => $env('DB_DATABASE', 'your_database_name'),
        'user' => $env('DB_USERNAME', 'your_database_user'),
        'pass' => $env('DB_PASSWORD', 'your_database_password'),
        'charset' => $env('DB_CHARSET', 'utf8'),
        'sslmode' => $env('DB_SSLMODE', 'prefer'),
        'connect_timeout' => $envInt('DB_CONNECT_TIMEOUT', 10),
    ],
    'steadfast' => [
        'base_url' => $env('STEADFAST_BASE_URL', 'https://portal.steadfast.com.bd/api/v1'),
        'api_key' => $env('STEADFAST_API_KEY'),
        'secret_key' => $env('STEADFAST_SECRET_KEY'),
    ],
    'security' => [
        'session_name' => $env('SESSION_NAME', 'sp_store_session'),
        'admin_idle_timeout_minutes' => $envInt('ADMIN_IDLE_TIMEOUT_MINUTES', 60),
        'login_max_attempts' => $envInt('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => $envInt('LOGIN_DECAY_MINUTES', 15),
    ],
];
