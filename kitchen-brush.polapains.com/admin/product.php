<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

if (is_post()) {
    verify_csrf();
    try {
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'seed_kitchen_brush') {
            seed_kitchen_brush_content();
            flash('success', 'Kitchen brush demo content applied. Upload the image files in assets/images or replace the URLs below.');
        } else {
            save_product($_POST);
            flash('success', 'Product updated.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/product.php');
}

$product = active_product();
$mediaItems = Media::items();
$pageTitle = 'Product';
require BASE_PATH . '/includes/admin_header.php';

function render_media_picker(string $targetId, array $mediaItems): void
{
    ?>
    <div class="media-picker">
        <select data-media-select="<?= e($targetId) ?>">
            <option value="">Choose from Media Library</option>
            <?php render_media_options($mediaItems); ?>
        </select>
        <a class="button button-secondary" href="<?= e(base_url('admin/media.php')) ?>">Media</a>
    </div>
    <?php
}

function render_inline_media_picker(array $mediaItems): void
{
    ?>
    <div class="media-picker media-picker-inline" data-media-field>
        <select data-media-select-field>
            <option value="">Choose from Media Library</option>
            <?php render_media_options($mediaItems); ?>
        </select>
        <a class="button button-secondary" href="<?= e(base_url('admin/media.php')) ?>">Media</a>
    </div>
    <?php
}

function render_media_options(array $mediaItems): void
{
    foreach ($mediaItems as $item): ?>
        <option value="<?= e($item['path']) ?>"><?= e($item['name']) ?></option>
    <?php endforeach;
}

function landing_admin_rows(string $key, array $columns, int $minimumRows): array
{
    $rows = landing_rows($key, $columns);
    while (count($rows) < $minimumRows) {
        $row = [];
        foreach ($columns as $column) {
            $row[$column] = '';
        }
        $rows[] = $row;
    }

    return $rows;
}

function render_feature_editor_row(array $row, array $mediaItems): void
{
    ?>
    <div class="landing-editor-row feature-editor-row" data-repeatable-row>
        <label>
            Title
            <input type="text" name="feature_title[]" value="<?= e($row['title'] ?? '') ?>" maxlength="120">
        </label>
        <label>
            Small Text
            <input type="text" name="feature_text[]" value="<?= e($row['text'] ?? '') ?>" maxlength="160">
        </label>
        <label class="media-field">
            Image
            <input type="text" name="feature_image[]" value="<?= e($row['image'] ?? '') ?>" data-media-input placeholder="Select image from Media Library">
            <?php render_inline_media_picker($mediaItems); ?>
        </label>
        <button class="button button-secondary" type="button" data-remove-row>Remove</button>
    </div>
    <?php
}

function render_usage_editor_row(array $row, array $mediaItems): void
{
    ?>
    <div class="landing-editor-row usage-editor-row" data-repeatable-row>
        <label>
            Title
            <input type="text" name="usage_title[]" value="<?= e($row['title'] ?? '') ?>" maxlength="120">
        </label>
        <label class="media-field">
            Image
            <input type="text" name="usage_image[]" value="<?= e($row['image'] ?? '') ?>" data-media-input placeholder="Select image from Media Library">
            <?php render_inline_media_picker($mediaItems); ?>
        </label>
        <button class="button button-secondary" type="button" data-remove-row>Remove</button>
    </div>
    <?php
}

function render_reason_editor_row(array $row): void
{
    ?>
    <div class="landing-editor-row reason-editor-row" data-repeatable-row>
        <label>
            Reason
            <input type="text" name="reason_title[]" value="<?= e($row['title'] ?? '') ?>" maxlength="160">
        </label>
        <button class="button button-secondary" type="button" data-remove-row>Remove</button>
    </div>
    <?php
}
?>

<section class="admin-section">
    <h1>Product</h1>
    <?php if (!$product): ?>
        <div class="alert alert-error">No active product found. Run installer or import database/schema.sql.</div>
    <?php else: ?>
        <form class="content-panel compact-admin-form" method="post" data-confirm="Apply kitchen brush demo content? This will replace current product text and landing content.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="seed_kitchen_brush">
            <div>
                <h2>Quick Setup</h2>
                <p class="muted">Use this once if the live page still shows old demo content like Premium Single Product.</p>
            </div>
            <button class="button button-secondary" type="submit">Apply Kitchen Brush Content</button>
        </form>
        <form class="content-panel admin-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <h2>Core Product</h2>
            <label>
                Product Name
                <input type="text" name="name" value="<?= e($product['name']) ?>" required maxlength="190">
            </label>
            <label>
                Tagline
                <input type="text" name="tagline" value="<?= e($product['tagline']) ?>" maxlength="255">
            </label>
            <label>
                Description
                <textarea name="description" rows="5"><?= e($product['description']) ?></textarea>
            </label>
            <label>
                Highlights
                <textarea name="highlights" rows="5"><?= e($product['highlights']) ?></textarea>
            </label>
            <div class="form-grid">
                <label>
                    Price
                    <input type="number" name="price" value="<?= e($product['price']) ?>" min="0" step="1" required>
                </label>
                <label>
                    Compare Price
                    <input type="number" name="compare_price" value="<?= e($product['compare_price']) ?>" min="0" step="1">
                </label>
                <label>
                    Stock
                    <input type="number" name="stock" value="<?= e($product['stock']) ?>" min="0" step="1" required>
                </label>
            </div>
            <label>
                Main Product Image URL
                <input id="image_url" type="text" name="image_url" value="<?= e($product['image_url']) ?>" data-media-input>
                <span class="field-help">Suggested: assets/images/kitchen-brush-pan-cleaning.jpg</span>
            </label>
            <?php render_media_picker('image_url', $mediaItems); ?>

            <h2>Landing Page Content</h2>
            <label>
                Hero Badge
                <input type="text" name="landing_badge" value="<?= e(landing_value('badge')) ?>">
            </label>
            <label>
                Hero Title
                <textarea name="landing_hero_title" rows="2"><?= e(landing_value('hero_title')) ?></textarea>
            </label>
            <label>
                Hero Subtitle
                <textarea name="landing_hero_subtitle" rows="2"><?= e(landing_value('hero_subtitle')) ?></textarea>
            </label>
            <div class="form-grid">
                <label>
                    Discount Label
                    <input type="text" name="landing_discount_label" value="<?= e(landing_value('discount_label')) ?>">
                </label>
                <label>
                    Button Text
                    <input type="text" name="landing_cta_text" value="<?= e(landing_value('cta_text')) ?>">
                </label>
            </div>
            <label>
                Hero Top Image URL
                <input id="landing_hero_image_url" type="text" name="landing_hero_image_url" value="<?= e(landing_value('hero_image_url')) ?>" data-media-input>
                <span class="field-help">Use the last image at the top: assets/images/kitchen-brush-hero-drain.jpg</span>
            </label>
            <?php render_media_picker('landing_hero_image_url', $mediaItems); ?>
            <label>
                Demo / Circle Image URL
                <input id="landing_demo_image_url" type="text" name="landing_demo_image_url" value="<?= e(landing_value('demo_image_url')) ?>" data-media-input>
                <span class="field-help">Suggested: assets/images/kitchen-brush-plate-demo.jpg</span>
            </label>
            <?php render_media_picker('landing_demo_image_url', $mediaItems); ?>
            <div class="form-grid">
                <label>
                    Dhaka Inside Delivery Charge
                    <input type="number" name="landing_delivery_inside_charge" value="<?= e(landing_value('delivery_inside_charge')) ?>" min="0" step="1">
                </label>
                <label>
                    Dhaka Outside Delivery Charge
                    <input type="number" name="landing_delivery_outside_charge" value="<?= e(landing_value('delivery_outside_charge')) ?>" min="0" step="1">
                </label>
            </div>
            <div class="landing-editor">
                <div class="landing-editor-head">
                    <div>
                        <h3>Feature Cards</h3>
                        <p class="field-help">Title/text লিখুন, তারপর image dropdown থেকে select করুন.</p>
                    </div>
                    <button class="button button-secondary" type="button" data-add-row="feature-row-template" data-row-target="#feature-rows">Add Feature</button>
                </div>
                <div id="feature-rows" class="landing-editor-rows">
                    <?php foreach (landing_admin_rows('feature_rows', ['title', 'text', 'image'], 4) as $row): ?>
                        <?php render_feature_editor_row($row, $mediaItems); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="landing-editor">
                <div class="landing-editor-head">
                    <div>
                        <h3>Usage Images</h3>
                        <p class="field-help">Usage card image-ও Media Library থেকে select করা যাবে.</p>
                    </div>
                    <button class="button button-secondary" type="button" data-add-row="usage-row-template" data-row-target="#usage-rows">Add Usage</button>
                </div>
                <div id="usage-rows" class="landing-editor-rows">
                    <?php foreach (landing_admin_rows('usage_rows', ['title', 'image'], 4) as $row): ?>
                        <?php render_usage_editor_row($row, $mediaItems); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="landing-editor">
                <div class="landing-editor-head">
                    <div>
                        <h3>Why People Like It</h3>
                        <p class="field-help">One reason per row.</p>
                    </div>
                    <button class="button button-secondary" type="button" data-add-row="reason-row-template" data-row-target="#reason-rows">Add Reason</button>
                </div>
                <div id="reason-rows" class="landing-editor-rows">
                    <?php foreach (landing_admin_rows('reason_rows', ['title'], 4) as $row): ?>
                        <?php render_reason_editor_row($row); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <template id="feature-row-template">
                <?php render_feature_editor_row(['title' => '', 'text' => '', 'image' => ''], $mediaItems); ?>
            </template>
            <template id="usage-row-template">
                <?php render_usage_editor_row(['title' => '', 'image' => ''], $mediaItems); ?>
            </template>
            <template id="reason-row-template">
                <?php render_reason_editor_row(['title' => '']); ?>
            </template>
            <button class="button button-primary" type="submit">Save Product</button>
        </form>
    <?php endif; ?>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
