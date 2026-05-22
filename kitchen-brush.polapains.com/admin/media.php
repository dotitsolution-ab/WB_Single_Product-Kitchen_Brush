<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');

    try {
        if ($action === 'delete') {
            Media::delete((string)($_POST['path'] ?? ''));
            flash('success', 'Image deleted.');
        } else {
            $path = Media::upload($_FILES['image'] ?? []);
            flash('success', 'Image uploaded: ' . $path);
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('admin/media.php');
}

$pageTitle = 'Media Library';
$items = Media::items();
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Media Library</h1>

    <form class="content-panel admin-form" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <label>
            Upload Image
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <span class="field-help">Allowed: JPG, PNG, WebP, GIF. Max <?= e(Media::maxUploadMb()) ?> MB.</span>
        </label>
        <button class="button button-primary" type="submit">Upload Image</button>
    </form>

    <div class="content-panel">
        <div class="panel-head">
            <h2>Images</h2>
            <a class="button button-secondary" href="<?= e(base_url('admin/product.php')) ?>">Use In Product</a>
        </div>
        <div class="media-grid">
            <?php foreach ($items as $item): ?>
                <article class="media-card">
                    <img src="<?= e($item['url']) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                    <strong><?= e($item['name']) ?></strong>
                    <input type="text" value="<?= e($item['path']) ?>" readonly data-copy-value>
                    <button class="button button-secondary button-full" type="button" data-copy-button>Copy Path</button>
                    <?php if ($item['deletable']): ?>
                        <form method="post" data-confirm="Delete this image from the server? Make sure it is not selected in the product content.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="path" value="<?= e($item['path']) ?>">
                            <button class="button button-danger button-full" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <p class="muted">No images found yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
