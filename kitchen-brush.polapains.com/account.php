<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$order = null;
$lookupError = null;

if (is_post()) {
    verify_csrf();
    $orderNumber = (string)($_POST['order_number'] ?? '');
    $phone = (string)($_POST['phone'] ?? '');
    $order = order_by_number($orderNumber, $phone);

    if (!$order) {
        $lookupError = 'No order matched with that order ID and phone number.';
    }
}

$pageTitle = 'My Account';
require BASE_PATH . '/includes/header.php';
?>

<section class="container section account-grid">
    <form class="content-panel" method="post">
        <h1>My Account</h1>
        <?php if ($lookupError): ?>
            <div class="alert alert-error"><?= e($lookupError) ?></div>
        <?php endif; ?>
        <?= csrf_field() ?>
        <label>
            Order ID
            <input type="text" name="order_number" value="<?= e($_POST['order_number'] ?? '') ?>" required>
        </label>
        <label>
            Phone
            <span class="phone-field">
                <span class="phone-prefix">+88</span>
                <input type="tel" name="phone" value="<?= e(normalize_phone((string)($_POST['phone'] ?? ''))) ?>" inputmode="numeric" pattern="01[3-9][0-9]{8}" maxlength="11" data-phone-input required placeholder="01XXXXXXXXX">
            </span>
        </label>
        <button class="button button-primary button-full" type="submit">View Order</button>
    </form>

    <div class="content-panel">
        <?php if (!$order): ?>
            <h2>Order Details</h2>
            <p class="muted">Enter your order ID and phone number to see the latest order details.</p>
        <?php else: ?>
            <h2>Order <?= e($order['order_number']) ?></h2>
            <p><span class="<?= e(status_class($order['status'])) ?>"><?= e($order['status']) ?></span></p>
            <div class="summary-table">
                <div><span>Name</span><strong><?= e($order['customer_name']) ?></strong></div>
                <div><span>Total</span><strong><?= e(money($order['total'])) ?></strong></div>
                <div><span>Payment</span><strong><?= e($order['payment_method']) ?></strong></div>
                <div><span>Date</span><strong><?= e(date('d M Y', strtotime((string)$order['created_at']))) ?></strong></div>
            </div>
            <h3>Items</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Product</th><th>Qty</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td><?= e($item['quantity']) ?></td>
                            <td><?= e(money($item['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($order['shipment']): ?>
                <h3>Courier</h3>
                <div class="summary-table">
                    <div><span>Courier</span><strong><?= e($order['shipment']['courier_name']) ?></strong></div>
                    <div><span>Tracking</span><strong><?= e($order['shipment']['tracking_code'] ?: 'Pending') ?></strong></div>
                    <div><span>Status</span><strong><?= e($order['shipment']['shipment_status'] ?: 'Created') ?></strong></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require BASE_PATH . '/includes/footer.php'; ?>
