<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Dashboard';
$stats = dashboard_stats();
$analytics = analytics_stats();
$latestOrders = list_orders([]);
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Dashboard</h1>
    <div class="stats-grid">
        <div class="stat-card">
            <span>Today</span>
            <strong><?= e($stats['orders_today']) ?></strong>
        </div>
        <div class="stat-card">
            <span>Total Orders</span>
            <strong><?= e($stats['orders_total']) ?></strong>
        </div>
        <div class="stat-card">
            <span>Pending</span>
            <strong><?= e($stats['pending']) ?></strong>
        </div>
        <div class="stat-card">
            <span>Total Sales</span>
            <strong><?= e(money($stats['sales_total'])) ?></strong>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-head">
            <div>
                <h2>Page Funnel</h2>
                <p class="muted">Home page থেকে thank-you page পর্যন্ত visitor count.</p>
            </div>
        </div>
        <div class="stats-grid analytics-grid">
            <div class="stat-card">
                <span>Home Visitors</span>
                <strong><?= e($analytics['home']['unique_visitors']) ?></strong>
                <small>Today: <?= e($analytics['home']['visitors_today']) ?> / Views: <?= e($analytics['home']['total_views']) ?></small>
            </div>
            <div class="stat-card">
                <span>Thank You Visitors</span>
                <strong><?= e($analytics['thank_you']['unique_visitors']) ?></strong>
                <small>Today: <?= e($analytics['thank_you']['visitors_today']) ?> / Views: <?= e($analytics['thank_you']['total_views']) ?></small>
            </div>
            <div class="stat-card">
                <span>Visit To Order Rate</span>
                <strong><?= e((string)$analytics['conversion_rate']) ?>%</strong>
                <small>Unique thank-you visitors / home visitors</small>
            </div>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-head">
            <h2>Latest Orders</h2>
            <a class="button button-secondary" href="<?= e(base_url('admin/orders.php')) ?>">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($latestOrders, 0, 10) as $order): ?>
                    <tr>
                        <td><?= e($order['order_number']) ?></td>
                        <td><?= e($order['customer_name']) ?><br><span class="muted"><?= e(display_phone((string)$order['customer_phone'])) ?></span></td>
                        <td><?= e(money($order['total'])) ?></td>
                        <td><span class="<?= e(status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td>
                        <td><a href="<?= e(base_url('admin/order.php?id=' . $order['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$latestOrders): ?>
                    <tr><td colspan="5">No orders yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
