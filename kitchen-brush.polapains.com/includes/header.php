<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? setting('site_name', app_config('app.name', 'Single Product Store'));
$bodyClass = $bodyClass ?? '';
$hideHeader = $hideHeader ?? false;
$currentPage = basename((string)parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
$publicNav = [
    ['label' => 'শপ', 'href' => '/', 'active' => ['index.php', '']],
    ['label' => 'আমার অ্যাকাউন্ট', 'href' => 'account.php', 'active' => ['account.php']],
    ['label' => 'অর্ডার ট্র্যাক', 'href' => 'track.php', 'active' => ['track.php']],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d9488">
    <title><?= e($pageTitle) ?></title>
    <?php render_tracking_head(); ?>
    <link rel="preload" href="<?= e(asset_url('assets/css/styles.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php render_gtm_noscript(); ?>
<?php if (!$hideHeader): ?>
<header class="site-header">
    <div class="container header-grid">
        <a class="brand" href="<?= e(base_url('/')) ?>"><?= e(setting('site_name', app_config('app.name', 'Store'))) ?></a>
        <nav class="site-nav" aria-label="Primary navigation">
            <?php foreach ($publicNav as $item): ?>
                <?php $isActive = in_array($currentPage, $item['active'], true); ?>
                <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(base_url($item['href'])) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<?php endif; ?>
<main>
