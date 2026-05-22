<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$metaCapiStatus = [
    'access_token' => setting('facebook_capi_access_token') !== '' ? 'Saved' : 'Missing',
    'last_status' => setting('facebook_capi_last_status', 'No server event sent yet'),
    'last_message' => setting('facebook_capi_last_message'),
    'last_order' => setting('facebook_capi_last_order'),
    'last_event_id' => setting('facebook_capi_last_event_id'),
    'last_checked_at' => setting('facebook_capi_last_checked_at'),
    'last_response' => setting('facebook_capi_last_response'),
];

$settings = [
    'site_name' => 'Site Name',
    'contact_phone' => 'Contact Phone',
    'support_email' => 'Support Email',
    'whatsapp_number' => 'WhatsApp Number',
    'whatsapp_message' => 'Default WhatsApp Message',
    'gtm_id' => 'Google Tag Manager ID',
    'ga4_id' => 'GA4 Measurement ID',
    'facebook_pixel_id' => 'Facebook Pixel ID',
    'facebook_capi_graph_version' => 'Meta Graph API Version',
    'facebook_capi_test_event_code' => 'Meta CAPI Test Event Code',
    'facebook_domain_verification' => 'Facebook Domain Verification',
    'google_site_verification' => 'Google Site Verification',
    'steadfast_base_url' => 'Steadfast Base URL',
    'steadfast_api_key' => 'Steadfast API Key',
];

if (is_post()) {
    verify_csrf();
    try {
        foreach ($settings as $key => $label) {
            save_setting($key, trim((string)($_POST[$key] ?? '')));
        }

        $secret = trim((string)($_POST['steadfast_secret_key'] ?? ''));
        if ($secret !== '') {
            save_setting('steadfast_secret_key', $secret);
        }

        $metaCapiToken = trim((string)($_POST['facebook_capi_access_token'] ?? ''));
        if ($metaCapiToken !== '') {
            save_setting('facebook_capi_access_token', $metaCapiToken);
        }

        flash('success', 'Settings updated.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/settings.php');
}

$pageTitle = 'Settings';
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Settings</h1>
    <form class="content-panel admin-form" method="post">
        <?= csrf_field() ?>
        <?php foreach ($settings as $key => $label): ?>
            <label>
                <?= e($label) ?>
                <input type="text" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
            </label>
        <?php endforeach; ?>
        <label>
            Steadfast Secret Key
            <input type="password" name="steadfast_secret_key" placeholder="Leave blank to keep current key">
        </label>
        <label>
            Meta CAPI Access Token
            <input type="password" name="facebook_capi_access_token" placeholder="Leave blank to keep current token">
        </label>
        <div class="settings-card">
            <h2>Meta CAPI Diagnostics</h2>
            <p class="muted">Access token: <strong><?= e($metaCapiStatus['access_token']) ?></strong></p>
            <p class="muted">Last status: <strong><?= e($metaCapiStatus['last_status']) ?></strong></p>
            <?php if ($metaCapiStatus['last_message'] !== ''): ?>
                <p class="muted">Message: <?= e($metaCapiStatus['last_message']) ?></p>
            <?php endif; ?>
            <?php if ($metaCapiStatus['last_order'] !== ''): ?>
                <p class="muted">Order: <?= e($metaCapiStatus['last_order']) ?></p>
            <?php endif; ?>
            <?php if ($metaCapiStatus['last_event_id'] !== ''): ?>
                <p class="muted">Event ID: <?= e($metaCapiStatus['last_event_id']) ?></p>
            <?php endif; ?>
            <?php if ($metaCapiStatus['last_checked_at'] !== ''): ?>
                <p class="muted">Checked at: <?= e($metaCapiStatus['last_checked_at']) ?></p>
            <?php endif; ?>
            <?php if ($metaCapiStatus['last_response'] !== ''): ?>
                <p class="muted">Response: <?= e($metaCapiStatus['last_response']) ?></p>
            <?php endif; ?>
        </div>
        <button class="button button-primary" type="submit">Save Settings</button>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
