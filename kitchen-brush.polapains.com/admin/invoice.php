<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$order = order_with_items_by_id((int)($_GET['id'] ?? 0));
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= e($order['order_number']) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body class="invoice-body">
<main class="invoice">
    <div class="invoice-actions">
        <button class="button button-primary" type="button" onclick="window.print()">Print</button>
    </div>

    <header class="invoice-head">
        <div>
            <h1><?= e(setting('site_name', 'Store')) ?></h1>
            <p><?= e(setting('contact_phone', '01700000000')) ?></p>
        </div>
        <div>
            <strong>Invoice</strong>
            <p><?= e($order['order_number']) ?></p>
            <p><?= e(date('d M Y', strtotime((string)$order['created_at']))) ?></p>
        </div>
    </header>

    <section class="invoice-grid">
        <div>
            <h2>Bill To</h2>
            <p><strong><?= e($order['customer_name']) ?></strong></p>
            <p><?= e(display_phone((string)$order['customer_phone'])) ?></p>
            <p><?= e($order['customer_address']) ?>, <?= e($order['district_area']) ?></p>
        </div>
        <div>
            <h2>Status</h2>
            <p><?= e($order['status']) ?></p>
            <p><?= e($order['payment_method']) ?></p>
        </div>
    </section>

    <table>
        <thead>
        <tr><th>Product</th><th>Unit</th><th>Qty</th><th>Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e(money($item['unit_price'])) ?></td>
                <td><?= e($item['quantity']) ?></td>
                <td><?= e(money($item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr><th colspan="3">Subtotal</th><td><?= e(money($order['subtotal'])) ?></td></tr>
        <tr><th colspan="3">Delivery</th><td><?= e(money($order['delivery_charge'])) ?></td></tr>
        <tr><th colspan="3">Total</th><td><?= e(money($order['total'])) ?></td></tr>
        </tfoot>
    </table>

    <?php if ($order['shipment']): ?>
        <section class="invoice-grid">
            <div>
                <h2>Courier</h2>
                <p><?= e($order['shipment']['courier_name']) ?></p>
                <p>Tracking: <?= e($order['shipment']['tracking_code']) ?></p>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
