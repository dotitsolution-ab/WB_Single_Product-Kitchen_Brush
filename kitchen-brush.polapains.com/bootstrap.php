<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

$configFile = file_exists(BASE_PATH . '/config.php')
    ? BASE_PATH . '/config.php'
    : BASE_PATH . '/config.sample.php';

$GLOBALS['app_config'] = require $configFile;

date_default_timezone_set($GLOBALS['app_config']['app']['timezone'] ?? 'Asia/Dhaka');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://connect.facebook.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://www.googletagmanager.com https://connect.facebook.net https://www.facebook.com; frame-src 'self' https://www.googletagmanager.com https://www.facebook.com;");
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $trackingCookieOptions = [
        'expires' => time() + 7776000,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    $fbclid = trim((string)($_GET['fbclid'] ?? ''));
    if ($fbclid !== '' && strlen($fbclid) <= 500 && preg_match('/^[A-Za-z0-9_.-]+$/', $fbclid) === 1) {
        $fbc = 'fb.1.' . (time() * 1000) . '.' . $fbclid;
        setcookie('sp_fbc', $fbc, $trackingCookieOptions);
        $_COOKIE['sp_fbc'] = $fbc;
    }

    if (empty($_COOKIE['_fbp']) && empty($_COOKIE['sp_fbp'])) {
        $fbp = 'fb.1.' . (time() * 1000) . '.' . random_int(100000000, 2147483647);
        setcookie('sp_fbp', $fbp, $trackingCookieOptions);
        $_COOKIE['sp_fbp'] = $fbp;
    }
}

ini_set('display_errors', !empty($GLOBALS['app_config']['app']['debug']) ? '1' : '0');
ini_set('expose_php', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
error_reporting(E_ALL);

session_name($GLOBALS['app_config']['security']['session_name'] ?? 'sp_store_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $path = BASE_PATH . '/includes/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/store.php';
