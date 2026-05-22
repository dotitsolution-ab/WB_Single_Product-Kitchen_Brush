<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$setupError = null;
$product = null;

try {
    $product = active_product();
} catch (Throwable $exception) {
    $setupError = $exception->getMessage();
}

if ($setupError === null) {
    track_page_visit('home');
}

$pageTitle = setting('site_name', app_config('app.name', 'Single Product Store'));
$bodyClass = 'landing-body';
$hideHeader = true;
$hideFooter = true;
require BASE_PATH . '/includes/header.php';
?>

<?php if ($setupError): ?>
    <section class="container section">
        <div class="alert alert-error">
            <?= e($setupError) ?> Copy <strong>config.sample.php</strong> to <strong>config.php</strong>, update database credentials, then run <strong>/install.php</strong>.
        </div>
    </section>
<?php elseif (!$product): ?>
    <section class="container section">
        <div class="alert alert-error">No active product found. Add a product from the admin panel.</div>
    </section>
<?php else: ?>
    <?php
    $deliveryOptions = delivery_options($product);
    $selectedDelivery = old('delivery_area', 'inside_dhaka');
    if (!array_key_exists($selectedDelivery, $deliveryOptions)) {
        $selectedDelivery = 'inside_dhaka';
    }
    $quantity = max(1, (int)old('quantity', '1'));
    $insideCharge = (float)$deliveryOptions['inside_dhaka']['charge'];
    $outsideCharge = (float)$deliveryOptions['outside_dhaka']['charge'];
    $features = landing_rows('feature_rows', ['title', 'text', 'image']);
    $usages = landing_rows('usage_rows', ['title', 'image']);
    $reasons = landing_rows('reason_rows', ['title']);
    $heroTitle = landing_value('hero_title');
    $heroImage = image_src(landing_image_value('hero_image_url'), (string)$product['image_url']);
    ?>
    <section class="funnel">
        <div class="funnel-hero">
            <div class="funnel-copy">
                <span class="funnel-badge"><?= e(landing_value('badge')) ?></span>
                <h1><?= e($heroTitle) ?></h1>
                <p><?= e(landing_value('hero_subtitle')) ?></p>

                <div class="offer-card">
                    <div class="offer-meta">
                        <div>
                            <?php if (!empty($product['compare_price'])): ?>
                                <del><?= e(taka($product['compare_price'])) ?></del>
                            <?php endif; ?>
                            <span class="offer-note">এখন মাত্র</span>
                        </div>
                        <em><?= e(landing_value('discount_label')) ?></em>
                    </div>
                    <strong class="offer-price">
                        <span class="offer-price-number"><?= e(number_format((float)$product['price'], 0)) ?></span>
                        <span class="offer-price-currency">টাকা</span>
                    </strong>
                </div>

                <a class="funnel-cta" href="#checkout"><?= e(landing_value('cta_text')) ?></a>

                <div class="delivery-strip">
                    <div>
                        <strong>ঢাকার ভিতরে</strong>
                        <span>ডেলিভারি <?= e(taka($insideCharge)) ?></span>
                    </div>
                    <div>
                        <strong>ঢাকার বাইরে</strong>
                        <span>ডেলিভারি <?= e(taka($outsideCharge)) ?></span>
                    </div>
                </div>
            </div>

            <div class="hero-product">
                <img class="hero-main-img" src="<?= e($heroImage) ?>" alt="<?= e($product['name']) ?>" loading="eager" width="760" height="760">
            </div>
        </div>

        <?php if ($features): ?>
            <section class="funnel-section">
                <h2>কেন এই রোটেটিং ক্লিনিং ব্রাশ?</h2>
                <div class="feature-grid">
                    <?php foreach ($features as $feature): ?>
                        <article class="feature-tile">
                            <?php $featureImage = image_src($feature['image']); ?>
                            <?php if ($featureImage !== ''): ?>
                                <img src="<?= e($featureImage) ?>" alt="<?= e($feature['title']) ?>" loading="lazy">
                            <?php endif; ?>
                            <strong><?= e($feature['title']) ?></strong>
                            <?php if ($feature['text'] !== ''): ?>
                                <span><?= e($feature['text']) ?></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($usages): ?>
            <section class="funnel-section">
                <h2>ব্যবহারের উপায়?</h2>
                <div class="usage-grid">
                    <?php foreach ($usages as $usage): ?>
                        <article class="usage-card">
                            <?php $usageImage = image_src($usage['image']); ?>
                            <?php if ($usageImage !== ''): ?>
                                <img src="<?= e($usageImage) ?>" alt="<?= e($usage['title']) ?>" loading="lazy">
                            <?php endif; ?>
                            <strong><?= e($usage['title']) ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($reasons): ?>
            <section class="funnel-section">
                <h2>কেন এটি সবার পছন্দ?</h2>
                <div class="reason-strip">
                    <?php foreach ($reasons as $reason): ?>
                        <div><span>✓</span><?= e($reason['title']) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="checkout" class="funnel-order">
            <form class="order-card" method="post" action="<?= e(base_url('checkout.php')) ?>" data-order-form data-unit-price="<?= e((string)(float)$product['price']) ?>" data-inside-charge="<?= e((string)$insideCharge) ?>" data-outside-charge="<?= e((string)$outsideCharge) ?>">
                <h2>আপনার অর্ডার</h2>
                <?php if ($message = flash('error')): ?>
                    <div class="alert alert-error"><?= e($message) ?></div>
                <?php endif; ?>
                <?= csrf_field() ?>

                <div class="order-form-section">
                    <p class="form-label">কয় পিস অর্ডার করবেন?</p>
                    <div class="quantity-stepper" data-quantity-stepper>
                        <button type="button" data-qty-decrease aria-label="Decrease quantity">-</button>
                        <input type="number" name="quantity" value="<?= e((string)$quantity) ?>" min="1" max="<?= e((string)max(1, (int)$product['stock'])) ?>" inputmode="numeric" data-qty-input aria-label="Quantity">
                        <button type="button" data-qty-increase aria-label="Increase quantity">+</button>
                    </div>
                </div>

                <div class="order-form-section">
                    <p class="form-label">ডেলিভারি এরিয়া নির্বাচন করুন</p>
                    <div class="pill-options delivery-options">
                        <?php foreach ($deliveryOptions as $key => $option): ?>
                        <label>
                            <input type="radio" name="delivery_area" value="<?= e($key) ?>" <?= $selectedDelivery === $key ? 'checked' : '' ?>>
                            <span><?= e($option['label']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="order-form-section customer-fields">
                    <p class="form-label">আপনার তথ্য</p>
                    <label>
                        <input type="text" name="name" value="<?= e(old('name')) ?>" autocomplete="name" required maxlength="120" placeholder="আপনার নাম">
                    </label>
                    <label>
                        <span class="phone-field">
                            <span class="phone-prefix">+88</span>
                            <input type="tel" name="phone" value="<?= e(normalize_phone(old('phone'))) ?>" autocomplete="tel" inputmode="numeric" pattern="01[3-9][0-9]{8}" maxlength="11" data-phone-input required placeholder="মোবাইল নম্বর">
                        </span>
                    </label>
                    <label>
                        <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" maxlength="190" placeholder="ইমেইল (অপশনাল)">
                    </label>
                    <label>
                        <textarea name="address" rows="3" required maxlength="500" placeholder="সম্পূর্ণ ঠিকানা"><?= e(old('address')) ?></textarea>
                    </label>
                </div>
                <input type="hidden" name="delivery_note" value="">
                <button class="funnel-submit" type="submit">অর্ডার কনফার্ম করুন</button>
                <p class="secure-note">আপনার তথ্য ১০০% নিরাপদ এবং গোপন রাখা হবে</p>
            </form>

            <aside class="order-card summary-card">
                <h2>অর্ডার সারসংক্ষেপ</h2>
                <div class="summary-row"><span>পণ্যের নাম:</span><strong><?= e($product['name']) ?></strong></div>
                <div class="summary-row"><span>পরিমাণ:</span><strong><span data-summary-qty><?= e($quantity) ?></span> পিস</strong></div>
                <div class="summary-row"><span>সাবটোটাল:</span><strong data-summary-subtotal><?= e(taka((float)$product['price'] * $quantity)) ?></strong></div>
                <div class="summary-row"><span>ডেলিভারি:</span><strong data-summary-delivery><?= e(taka((float)$deliveryOptions[$selectedDelivery]['charge'])) ?></strong></div>
                <div class="summary-total"><span>মোট:</span><strong data-summary-total><?= e(taka(((float)$product['price'] * $quantity) + (float)$deliveryOptions[$selectedDelivery]['charge'])) ?></strong></div>
            </aside>
        </section>
        <a class="sticky-order-cta" href="#checkout">
            <span><?= e(landing_value('cta_text')) ?></span>
            <strong><?= e(taka($product['price'])) ?></strong>
        </a>
    </section>
<?php endif; ?>

<?php
clear_old();
require BASE_PATH . '/includes/footer.php';
