<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Security';
$events = [];
$error = null;

try {
    $events = Security::events(100);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Security</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <div class="content-panel">
        <div class="panel-head">
            <h2>Recent Security Events</h2>
            <span class="muted">Last 100 events</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Admin</th>
                    <th>IP</th>
                    <th>Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= e(date('d M Y H:i', strtotime((string)$event['created_at']))) ?></td>
                        <td><?= e($event['event_type']) ?></td>
                        <td><?= e($event['admin_user_id'] ?: '-') ?></td>
                        <td><?= e($event['ip_address']) ?></td>
                        <td><code><?= e($event['details'] ?: '-') ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$events): ?>
                    <tr><td colspan="5">No security events yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
