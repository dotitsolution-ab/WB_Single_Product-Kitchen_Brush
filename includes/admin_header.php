<?php

declare(strict_types=1);

Auth::requireAdmin();
$pageTitle = $pageTitle ?? 'Admin';
$currentAdminPage = basename((string)parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
$adminNavItems = [
    ['label' => 'Dashboard', 'href' => 'admin/index.php', 'active' => ['index.php']],
    ['label' => 'Orders', 'href' => 'admin/orders.php', 'active' => ['orders.php', 'order.php', 'invoice.php']],
    ['label' => 'Product', 'href' => 'admin/product.php', 'active' => ['product.php']],
    ['label' => 'Media', 'href' => 'admin/media.php', 'active' => ['media.php']],
    ['label' => 'Notifications', 'href' => 'admin/email.php', 'active' => ['email.php']],
    ['label' => 'Settings', 'href' => 'admin/settings.php', 'active' => ['settings.php']],
    ['label' => 'Security', 'href' => 'admin/security.php', 'active' => ['security.php']],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Admin</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body class="admin-body">
<aside class="admin-sidebar">
    <a class="admin-brand" href="<?= e(base_url('admin/index.php')) ?>">
        <span><?= e(setting('site_name', 'Store')) ?></span>
        <small>Admin Panel</small>
    </a>
    <nav class="admin-nav" aria-label="Admin navigation">
        <?php foreach ($adminNavItems as $item): ?>
            <?php $isActive = in_array($currentAdminPage, $item['active'], true); ?>
            <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(base_url($item['href'])) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-actions">
        <a class="button button-secondary button-full" href="<?= e(base_url('/')) ?>" target="_blank" rel="noopener">View Store</a>
        <a class="admin-logout" href="<?= e(base_url('admin/logout.php')) ?>">Logout</a>
    </div>
</aside>
<main class="admin-main">
    <header class="admin-topbar">
        <div class="admin-user-pill">
            <p class="muted">Logged in as</p>
            <strong><?= e(Auth::userName()) ?></strong>
        </div>
    </header>
    <?php if ($message = flash('success')): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="alert alert-error"><?= e($message) ?></div>
    <?php endif; ?>
