<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$orderId = (int)($_GET['id'] ?? 0);

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'status') {
            update_order_status($orderId, (string)($_POST['status'] ?? 'Pending'));
            flash('success', 'Order status updated.');
        } elseif ($action === 'customer_email') {
            send_order_customer_email_once($orderId);
            flash('success', 'Customer email sent.');
        } elseif ($action === 'customer_sms') {
            send_order_customer_sms_once($orderId);
            flash('success', 'Customer SMS sent.');
        } elseif ($action === 'shipment') {
            save_manual_shipment($orderId, $_POST);
            flash('success', 'Courier details saved.');
        } elseif ($action === 'steadfast') {
            create_steadfast_shipment($orderId);
            flash('success', 'Steadfast shipment created.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('admin/order.php?id=' . $orderId);
}

$order = order_with_items_by_id($orderId);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$pageTitle = 'Order ' . $order['order_number'];
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <div class="panel-head">
        <div>
            <h1>Order <?= e($order['order_number']) ?></h1>
            <p><span class="<?= e(status_class($order['status'])) ?>"><?= e($order['status']) ?></span></p>
        </div>
        <a class="button button-secondary" href="<?= e(base_url('admin/invoice.php?id=' . $order['id'])) ?>" target="_blank" rel="noopener">Print Invoice</a>
    </div>

    <div class="order-detail-grid">
        <div class="content-panel">
            <h2>Customer</h2>
            <div class="summary-table">
                <div><span>Name</span><strong><?= e($order['customer_name']) ?></strong></div>
                <div><span>Phone</span><strong><?= e(display_phone((string)$order['customer_phone'])) ?></strong></div>
                <div><span>Email</span><strong><?= e((string)($order['customer_email'] ?? 'Not provided')) ?></strong></div>
                <div><span>Area</span><strong><?= e($order['district_area']) ?></strong></div>
                <div><span>Address</span><strong><?= e($order['customer_address']) ?></strong></div>
            </div>
            <?php if (!empty($order['delivery_note'])): ?>
                <p class="note-box"><?= nl2br(e($order['delivery_note'])) ?></p>
            <?php endif; ?>
        </div>

        <form class="content-panel" method="post">
            <h2>Status</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <label>
                Order Status
                <select name="status">
                    <?php foreach (status_options() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary button-full" type="submit">Update Status</button>
        </form>
    </div>

    <div class="content-panel">
        <h2>Items</h2>
        <div class="table-wrap">
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
        </div>
    </div>

    <div class="order-detail-grid">
        <form class="content-panel" method="post">
            <h2>Courier Details</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="shipment">
            <label>
                Courier
                <input type="text" name="courier_name" value="<?= e($order['shipment']['courier_name'] ?? 'Steadfast') ?>">
            </label>
            <label>
                Consignment ID
                <input type="text" name="consignment_id" value="<?= e($order['shipment']['consignment_id'] ?? '') ?>">
            </label>
            <label>
                Tracking Code
                <input type="text" name="tracking_code" value="<?= e($order['shipment']['tracking_code'] ?? '') ?>">
            </label>
            <label>
                Courier Status
                <input type="text" name="shipment_status" value="<?= e($order['shipment']['shipment_status'] ?? '') ?>">
            </label>
            <button class="button button-secondary button-full" type="submit">Save Courier</button>
        </form>

        <form class="content-panel" method="post" data-confirm="Create shipment in Steadfast now?">
            <h2>Steadfast</h2>
            <p class="muted">Creates a courier order using the saved Steadfast API settings.</p>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="steadfast">
            <button class="button button-primary button-full" type="submit">Create Steadfast Shipment</button>
        </form>

        <form class="content-panel" method="post">
            <h2>Customer Email</h2>
            <p class="muted">Sends the customer order email once using the saved template.</p>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="customer_email">
            <button class="button button-secondary button-full" type="submit">Send Customer Email</button>
        </form>

        <form class="content-panel" method="post">
            <h2>Customer SMS</h2>
            <p class="muted">Sends the customer order SMS once using the saved SMS template.</p>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="customer_sms">
            <button class="button button-secondary button-full" type="submit">Send Customer SMS</button>
        </form>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
