<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$orderNumber = (string)($_GET['order'] ?? '');
$order = $orderNumber !== '' ? order_by_number($orderNumber) : null;
if ($order) {
    track_page_visit('thank_you');
}

$pageTitle = 'Thank You';
$bodyClass = 'thank-you-body';
$hideHeader = true;
$hideFooter = true;
require BASE_PATH . '/includes/header.php';
?>

<section class="thank-you-page">
    <?php if (!$order): ?>
        <div class="thank-you-card">
            <span class="thank-you-pill">Order not found</span>
            <h1>অর্ডার খুঁজে পাওয়া যায়নি</h1>
            <p>Order ID ঠিক আছে কিনা দেখে আবার চেষ্টা করুন।</p>
            <div class="thank-you-actions">
                <a class="button thank-you-primary" href="<?= e(base_url('/')) ?>">হোমে যান</a>
                <a class="button thank-you-secondary" href="<?= e(base_url('track.php')) ?>">অর্ডার ট্র্যাক করুন</a>
            </div>
        </div>
    <?php else: ?>
        <div class="thank-you-card">
            <span class="thank-you-pill">অর্ডার গ্রহণ করা হয়েছে</span>
            <div class="thank-you-icon">✓</div>
            <h1>ধন্যবাদ! আপনার অর্ডারটি সফল হয়েছে</h1>
            <p>আমাদের টিম দ্রুত ফোন করে অর্ডার কনফার্ম করবে। Order ID টি সংরক্ষণ করুন।</p>

            <div class="thank-you-order-id">
                <span>Order ID</span>
                <strong><?= e($order['order_number']) ?></strong>
            </div>

            <div class="thank-you-summary">
                <div><span>নাম</span><strong><?= e($order['customer_name']) ?></strong></div>
                <div><span>মোবাইল</span><strong><?= e(display_phone((string)$order['customer_phone'])) ?></strong></div>
                <div><span>মোট</span><strong><?= e(taka($order['total'])) ?></strong></div>
                <div><span>পেমেন্ট</span><strong><?= e($order['payment_method']) ?></strong></div>
            </div>

            <div class="thank-you-rating">
                <strong>কাস্টমার রেটিং</strong>
                <span>★★★★★</span>
                <small>৪.৯/৫ - দ্রুত কনফার্মেশন ও সহজ ডেলিভারি সাপোর্ট</small>
            </div>

            <div class="thank-you-actions">
                <a class="button thank-you-primary" href="<?= e(base_url('/')) ?>">হোমে যান</a>
                <a class="button thank-you-secondary" href="<?= e(base_url('track.php')) ?>">অর্ডার ট্র্যাক করুন</a>
            </div>

            <?php render_order_success_tracking($order); ?>
        </div>
    <?php endif; ?>
</section>

<?php require BASE_PATH . '/includes/footer.php'; ?>
