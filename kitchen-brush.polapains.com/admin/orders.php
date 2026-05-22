<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Orders';
$filters = [
    'status' => (string)($_GET['status'] ?? ''),
    'q' => (string)($_GET['q'] ?? ''),
];
$orders = list_orders($filters);
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Orders</h1>
    <form class="filter-bar" method="get">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search order, name, phone">
        <select name="status">
            <option value="">All Status</option>
            <?php foreach (status_options() as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-secondary" type="submit">Filter</button>
    </form>

    <div class="content-panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['order_number']) ?></td>
                        <td><?= e($order['customer_name']) ?><br><span class="muted"><?= e(display_phone((string)$order['customer_phone'])) ?></span></td>
                        <td><?= e(money($order['total'])) ?></td>
                        <td><span class="<?= e(status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td>
                        <td><?= e(date('d M Y', strtotime((string)$order['created_at']))) ?></td>
                        <td><a href="<?= e(base_url('admin/order.php?id=' . $order['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="6">No matching orders.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
