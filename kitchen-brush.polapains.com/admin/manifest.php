<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
}

$appName = (string)app_config('app.name', 'Kitchen Brush');
$manifest = [
    'name' => $appName . ' Admin',
    'short_name' => 'Admin',
    'description' => 'Mobile order inbox for the store admin.',
    'start_url' => base_url('admin/mobile.php'),
    'scope' => base_url('admin/'),
    'display' => 'standalone',
    'background_color' => '#f6f8f6',
    'theme_color' => '#0f766e',
    'orientation' => 'portrait',
    'icons' => [
        [
            'src' => base_url('admin/app-icon.svg'),
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any maskable',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
