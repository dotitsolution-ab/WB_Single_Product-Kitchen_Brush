<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once BASE_PATH . '/includes/admin_mobile.php';

Auth::requireAdmin();

$selectedOrderId = max(0, (int)($_GET['order'] ?? 0));
$initialState = admin_mobile_state_payload([], 100, 0, $selectedOrderId);
$bootPayload = [
    'apiUrl' => base_url('admin/mobile-api.php'),
    'csrf' => csrf_token(),
    'loginUrl' => base_url('admin/login.php'),
    'ordersUrl' => base_url('admin/orders.php'),
    'desktopDashboardUrl' => base_url('admin/index.php'),
    'serviceWorkerUrl' => base_url('admin/service-worker.js'),
    'notificationIcon' => base_url('admin/app-icon.svg'),
    'statusOptions' => status_options(),
    'selectedOrderId' => $selectedOrderId,
    'state' => $initialState,
];
$bootJson = json_encode(
    $bootPayload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <title>Admin Mobile App</title>
    <link rel="manifest" href="<?= e(base_url('admin/manifest.php')) ?>">
    <link rel="icon" href="<?= e(base_url('admin/app-icon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(base_url('admin/app-icon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body class="admin-mobile-body">
<div class="mobile-app-shell" data-admin-mobile-app>
    <header class="mobile-app-header">
        <div>
            <span class="mobile-kicker">Admin App</span>
            <h1><?= e(setting('site_name', (string)app_config('app.name', 'Store'))) ?></h1>
        </div>
        <a class="mobile-link-button" href="<?= e(base_url('admin/index.php')) ?>">Panel</a>
    </header>

    <section class="mobile-hero">
        <div class="mobile-hero-main">
            <span class="mobile-live-dot" data-connection-state>Live</span>
            <strong data-pending-total><?= e((string)$initialState['stats']['pending']) ?></strong>
            <span>Pending orders</span>
        </div>
        <div class="mobile-hero-side">
            <div>
                <span>Today</span>
                <strong data-orders-today><?= e((string)$initialState['stats']['orders_today']) ?></strong>
            </div>
            <div>
                <span>Sales</span>
                <strong data-sales-total><?= e($initialState['stats']['sales_total_formatted']) ?></strong>
            </div>
        </div>
    </section>

    <section class="mobile-control-row" aria-label="Admin app controls">
        <button class="mobile-control" type="button" data-notification-toggle>Notify</button>
        <button class="mobile-control" type="button" data-install-app hidden>Install</button>
        <button class="mobile-control" type="button" data-refresh-orders>Refresh</button>
        <span class="mobile-sync-label" data-last-sync>Syncing</span>
    </section>

    <section class="mobile-search-panel">
        <label>
            <span>Search</span>
            <input type="search" data-order-search placeholder="Order, name, phone">
        </label>
    </section>

    <nav class="mobile-status-tabs" aria-label="Order status filters" data-status-tabs>
        <button class="is-active" type="button" data-filter-status="">All <span data-status-count="All">0</span></button>
        <?php foreach (status_options() as $status): ?>
            <button type="button" data-filter-status="<?= e($status) ?>"><?= e($status) ?> <span data-status-count="<?= e($status) ?>">0</span></button>
        <?php endforeach; ?>
    </nav>

    <main class="mobile-order-list" data-order-list aria-live="polite"></main>

    <div class="mobile-toast" data-mobile-toast hidden></div>

    <div class="mobile-detail-backdrop" data-detail-backdrop hidden></div>
    <aside class="mobile-detail-panel" data-detail-panel hidden aria-live="polite">
        <div class="mobile-detail-handle"></div>
        <div class="mobile-detail-content" data-detail-content></div>
    </aside>
</div>

<script id="admin-mobile-data" type="application/json"><?= $bootJson ?></script>
<script src="<?= e(asset_url('assets/js/admin-mobile.js')) ?>" defer></script>
</body>
</html>
