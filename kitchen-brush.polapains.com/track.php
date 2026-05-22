<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$order = null;
$lookupError = null;
$orderNumberValue = (string)($_POST['order_number'] ?? $_GET['order'] ?? '');

if (is_post()) {
    verify_csrf();
    $order = order_by_number($orderNumberValue, (string)($_POST['phone'] ?? ''));
    if (!$order) {
        $lookupError = 'এই অর্ডারের তথ্য পাওয়া যায়নি। Order ID ও মোবাইল নম্বর মিলিয়ে আবার চেষ্টা করুন।';
    }
}

$pageTitle = 'অর্ডার ট্র্যাক করুন';
$bodyClass = 'tracking-body';
require BASE_PATH . '/includes/header.php';
?>

<section class="track-page">
    <div class="container narrow">
    <form class="track-card" method="post">
        <span class="track-eyebrow">অর্ডার স্ট্যাটাস</span>
        <h1>অর্ডার ট্র্যাক করুন</h1>
        <p>আপনার Order ID এবং অর্ডার করার সময় দেওয়া মোবাইল নম্বর লিখুন।</p>
        <?php if ($lookupError): ?>
            <div class="alert alert-error"><?= e($lookupError) ?></div>
        <?php endif; ?>
        <?= csrf_field() ?>
        <label>
            Order ID
            <input type="text" name="order_number" value="<?= e($orderNumberValue) ?>" required placeholder="SP260522ABC123">
        </label>
        <label>
            মোবাইল নম্বর
            <span class="phone-field">
                <span class="phone-prefix">+88</span>
                <input type="tel" name="phone" value="<?= e(normalize_phone((string)($_POST['phone'] ?? ''))) ?>" inputmode="numeric" pattern="01[3-9][0-9]{8}" maxlength="11" data-phone-input required placeholder="01XXXXXXXXX">
            </span>
        </label>
        <button class="button track-submit button-full" type="submit">ট্র্যাক করুন</button>
    </form>

    <?php if ($order): ?>
        <div class="tracking-timeline">
            <?php foreach (status_options() as $status): ?>
                <?php
                $isActive = array_search($status, status_options(), true) <= array_search((string)$order['status'], status_options(), true)
                    && $order['status'] !== 'Cancelled';
                ?>
                <div class="timeline-step <?= $isActive ? 'is-active' : '' ?>">
                    <span></span>
                    <strong><?= e($status) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="track-result-card">
            <h2>বর্তমান স্ট্যাটাস</h2>
            <p><span class="<?= e(status_class($order['status'])) ?>"><?= e($order['status']) ?></span></p>
            <?php if ($order['shipment']): ?>
                <div class="summary-table">
                    <div><span>Courier</span><strong><?= e($order['shipment']['courier_name']) ?></strong></div>
                    <div><span>Tracking Code</span><strong><?= e($order['shipment']['tracking_code'] ?: 'Pending') ?></strong></div>
                    <div><span>Courier Status</span><strong><?= e($order['shipment']['shipment_status'] ?: 'Created') ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
</section>

<?php require BASE_PATH . '/includes/footer.php'; ?>
