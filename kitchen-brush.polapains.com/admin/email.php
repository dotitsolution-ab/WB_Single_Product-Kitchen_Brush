<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();
ensure_email_schema();

$plainSettings = [
    'mail_from_email' => 'From Email',
    'mail_from_name' => 'From Name',
    'admin_notification_email' => 'Admin Notification Email',
    'zeptomail_api_url' => 'ZeptoMail API URL',
];

$templateSettings = [
    'admin_order_email_subject' => 'Admin Email Subject',
    'admin_order_email_html' => 'Admin HTML Template',
    'admin_order_email_text' => 'Admin Text Template',
    'customer_order_email_subject' => 'Customer Email Subject',
    'customer_order_email_html' => 'Customer HTML Template',
    'customer_order_email_text' => 'Customer Text Template',
];
$smsSettings = [
    'sms_provider_name' => 'SMS Provider Name',
    'sms_api_url' => 'SMS API URL',
    'sms_api_key' => 'SMS API Key',
    'sms_sender_id' => 'SMS Sender ID',
    'sms_success_keyword' => 'Success Keyword (Optional)',
];
$emailLogs = list_email_logs(20);
$smsLogs = list_sms_logs(20);

if (is_post()) {
    verify_csrf();
    try {
        save_setting('email_enabled', isset($_POST['email_enabled']) ? '1' : '0');
        save_setting('sms_enabled', isset($_POST['sms_enabled']) ? '1' : '0');
        save_setting('admin_order_email_enabled', isset($_POST['admin_order_email_enabled']) ? '1' : '0');
        save_setting('customer_order_email_enabled', isset($_POST['customer_order_email_enabled']) ? '1' : '0');
        save_setting('customer_order_sms_enabled', isset($_POST['customer_order_sms_enabled']) ? '1' : '0');

        foreach ($plainSettings as $key => $label) {
            save_setting($key, trim((string)($_POST[$key] ?? '')));
        }
        foreach ($templateSettings as $key => $label) {
            save_setting($key, trim((string)($_POST[$key] ?? '')));
        }
        foreach ($smsSettings as $key => $label) {
            save_setting($key, trim((string)($_POST[$key] ?? '')));
        }
        save_setting('sms_api_method', in_array(($_POST['sms_api_method'] ?? 'POST'), ['GET', 'POST'], true) ? (string)$_POST['sms_api_method'] : 'POST');
        save_setting('sms_request_body', trim((string)($_POST['sms_request_body'] ?? '')));
        save_setting('customer_order_sms_message', trim((string)($_POST['customer_order_sms_message'] ?? '')));

        $sendMailToken = trim((string)($_POST['zeptomail_send_token'] ?? ''));
        if ($sendMailToken !== '') {
            save_setting('zeptomail_send_token', $sendMailToken);
        }

        flash('success', 'Notification settings updated.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('admin/email.php');
}

$pageTitle = 'Notifications';
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <div class="panel-head">
        <div>
            <h1>Notifications</h1>
            <p class="muted">ZeptoMail email, SMS API, and editable order notification templates.</p>
        </div>
    </div>

    <form class="content-panel admin-form email-settings-form" method="post">
        <?= csrf_field() ?>

        <div class="settings-card">
            <h2>ZeptoMail API</h2>
            <label class="toggle-line">
                <input type="checkbox" name="email_enabled" value="1" <?= setting('email_enabled', '0') === '1' ? 'checked' : '' ?>>
                Enable email sending
            </label>
            <div class="form-grid">
                <?php foreach ($plainSettings as $key => $label): ?>
                    <?php
                    $fallback = match ($key) {
                        'mail_from_email', 'admin_notification_email' => setting('support_email'),
                        'mail_from_name' => setting('site_name'),
                        'zeptomail_api_url' => 'https://api.zeptomail.com/v1.1/email',
                        default => '',
                    };
                    ?>
                    <label>
                        <?= e($label) ?>
                        <input type="<?= str_contains($key, 'email') ? 'email' : 'text' ?>" name="<?= e($key) ?>" value="<?= e(setting($key, $fallback)) ?>">
                    </label>
                <?php endforeach; ?>
                <label>
                    ZeptoMail Send Mail Token
                    <input type="password" name="zeptomail_send_token" placeholder="Leave blank to keep current token">
                    <span class="field-help">ZeptoMail Agent → SMTP/API → API tab থেকে Send Mail Token নিন।</span>
                </label>
            </div>
        </div>

        <div class="settings-card">
            <h2>SMS API</h2>
            <label class="toggle-line">
                <input type="checkbox" name="sms_enabled" value="1" <?= setting('sms_enabled', '0') === '1' ? 'checked' : '' ?>>
                Enable SMS sending
            </label>
            <div class="form-grid">
                <?php foreach ($smsSettings as $key => $label): ?>
                    <label>
                        <?= e($label) ?>
                        <input type="text" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
                    </label>
                <?php endforeach; ?>
                <label>
                    SMS Method
                    <select name="sms_api_method">
                        <?php foreach (['POST', 'GET'] as $method): ?>
                            <option value="<?= e($method) ?>" <?= setting('sms_api_method', 'POST') === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>
                Request Body / Query Template
                <textarea name="sms_request_body" rows="4"><?= e(sms_template_value('sms_request_body')) ?></textarea>
                <span class="field-help">Use {{sms_api_key}}, {{sms_sender_id}}, {{phone}}, {{phone_880}}, {{message}}, {{message_url}}. For GET this is appended as query string.</span>
            </label>
        </div>

        <div class="settings-card">
            <h2>Notification Rules</h2>
            <label class="toggle-line">
                <input type="checkbox" name="admin_order_email_enabled" value="1" <?= setting('admin_order_email_enabled', '1') === '1' ? 'checked' : '' ?>>
                Send admin email when a new order arrives
            </label>
            <label class="toggle-line">
                <input type="checkbox" name="customer_order_email_enabled" value="1" <?= setting('customer_order_email_enabled', '1') === '1' ? 'checked' : '' ?>>
                Send customer order email once after order placement
            </label>
            <label class="toggle-line">
                <input type="checkbox" name="customer_order_sms_enabled" value="1" <?= setting('customer_order_sms_enabled', '0') === '1' ? 'checked' : '' ?>>
                Send customer order SMS once after order placement
            </label>
        </div>

        <div class="settings-card">
            <h2>Template Variables</h2>
            <p class="template-vars">
                {{order_number}} {{customer_name}} {{customer_phone}} {{customer_email}} {{customer_address}}
                {{district_area}} {{payment_method}} {{status}} {{subtotal}} {{delivery_charge}} {{total}}
                {{items_table}} {{track_url}} {{admin_order_url}} {{site_name}} {{support_email}} {{contact_phone}}
            </p>
        </div>

        <div class="email-template-grid">
            <div class="settings-card">
                <h2>Admin Template</h2>
                <label>
                    Subject
                    <input type="text" name="admin_order_email_subject" value="<?= e(email_template_value('admin_order_email_subject')) ?>">
                </label>
                <label>
                    HTML Template
                    <textarea name="admin_order_email_html" rows="10"><?= e(email_template_value('admin_order_email_html')) ?></textarea>
                </label>
                <label>
                    Text Template
                    <textarea name="admin_order_email_text" rows="7"><?= e(email_template_value('admin_order_email_text')) ?></textarea>
                </label>
            </div>

            <div class="settings-card">
                <h2>Customer Template</h2>
                <label>
                    Subject
                    <input type="text" name="customer_order_email_subject" value="<?= e(email_template_value('customer_order_email_subject')) ?>">
                </label>
                <label>
                    HTML Template
                    <textarea name="customer_order_email_html" rows="10"><?= e(email_template_value('customer_order_email_html')) ?></textarea>
                </label>
                <label>
                    Text Template
                    <textarea name="customer_order_email_text" rows="7"><?= e(email_template_value('customer_order_email_text')) ?></textarea>
                </label>
            </div>
        </div>

        <div class="settings-card">
            <h2>Customer SMS Template</h2>
            <label>
                SMS Message
                <textarea name="customer_order_sms_message" rows="5" maxlength="480"><?= e(sms_template_value('customer_order_sms_message')) ?></textarea>
                <span class="field-help">Keep it short. Unicode Bangla SMS may use more SMS parts.</span>
            </label>
        </div>

        <button class="button button-primary" type="submit">Save Notification Settings</button>
    </form>

    <div class="content-panel">
        <div class="panel-head">
            <h2>Recent Email Logs</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Time</th><th>Order</th><th>Type</th><th>Recipient</th><th>Status</th><th>Message</th></tr>
                </thead>
                <tbody>
                <?php foreach ($emailLogs as $log): ?>
                    <tr>
                        <td><?= e(date('d M Y H:i', strtotime((string)$log['created_at']))) ?></td>
                        <td><?= e((string)($log['order_number'] ?? '-')) ?></td>
                        <td><?= e($log['email_type']) ?></td>
                        <td><?= e($log['recipient_email']) ?></td>
                        <td><span class="<?= e($log['status'] === 'sent' ? 'badge badge-green' : 'badge badge-red') ?>"><?= e($log['status']) ?></span></td>
                        <td><?= e((string)($log['provider_message_id'] ?: $log['error_message'] ?: '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$emailLogs): ?>
                    <tr><td colspan="6">No email logs yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-head">
            <h2>Recent SMS Logs</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Time</th><th>Order</th><th>Type</th><th>Phone</th><th>Status</th><th>Message</th></tr>
                </thead>
                <tbody>
                <?php foreach ($smsLogs as $log): ?>
                    <tr>
                        <td><?= e(date('d M Y H:i', strtotime((string)$log['created_at']))) ?></td>
                        <td><?= e((string)($log['order_number'] ?? '-')) ?></td>
                        <td><?= e($log['sms_type']) ?></td>
                        <td><?= e(display_phone((string)$log['recipient_phone'])) ?></td>
                        <td><span class="<?= e($log['status'] === 'sent' ? 'badge badge-green' : 'badge badge-red') ?>"><?= e($log['status']) ?></span></td>
                        <td><?= e((string)($log['provider_response'] ?: $log['error_message'] ?: $log['message_text'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$smsLogs): ?>
                    <tr><td colspan="6">No SMS logs yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
