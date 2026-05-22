<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAdmin();

$migrator = new Migrator(db(), BASE_PATH);
$error = null;

if (is_post()) {
    verify_csrf();
    try {
        $pending = $migrator->pendingMigrations();
        $hasDangerous = array_filter($pending, static fn (array $migration): bool => (bool)$migration['dangerous']) !== [];
        $allowDangerous = trim((string)($_POST['confirm_dangerous'] ?? '')) === 'RUN';

        if ($hasDangerous && !$allowDangerous) {
            throw new RuntimeException('Dangerous SQL found. Type RUN in the confirmation box before running updates.');
        }

        $ran = $migrator->runPending($allowDangerous);

        if ($ran) {
            Security::recordEvent('admin_migrations_run', (int)($_SESSION['admin_user_id'] ?? 0), [
                'migrations' => $ran,
            ]);
            flash('success', 'Updates completed: ' . implode(', ', $ran));
        } else {
            flash('success', 'No pending updates.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('admin/update.php');
}

try {
    $pending = $migrator->pendingMigrations();
    $recent = $migrator->recentMigrations(20);
} catch (Throwable $exception) {
    $pending = [];
    $recent = [];
    $error = $exception->getMessage();
}

$hasDangerous = array_filter($pending, static fn (array $migration): bool => (bool)$migration['dangerous']) !== [];

$pageTitle = 'Updates';
require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-section">
    <h1>Updates</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="content-panel">
        <div class="panel-head">
            <div>
                <h2>Pending Database Updates</h2>
                <p class="muted">Upload SQL files to <code>database/migrations</code>, then run them here after admin login.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Migration</th>
                    <th>Checksum</th>
                    <th>Risk</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $migration): ?>
                    <tr>
                        <td><code><?= e($migration['name']) ?></code></td>
                        <td><code><?= e(substr((string)$migration['checksum'], 0, 16)) ?></code></td>
                        <td>
                            <?php if ($migration['dangerous']): ?>
                                <span class="badge badge-red">Needs RUN confirmation</span>
                            <?php else: ?>
                                <span class="badge badge-green">Safe</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pending): ?>
                    <tr><td colspan="3">No pending updates.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form class="admin-form update-form" method="post">
            <?= csrf_field() ?>
            <?php if ($hasDangerous): ?>
                <div class="alert alert-error">
                    Dangerous SQL found. Review the SQL first. To continue, type <strong>RUN</strong> below.
                </div>
                <label>
                    Dangerous SQL confirmation
                    <input type="text" name="confirm_dangerous" value="" placeholder="Type RUN" autocomplete="off">
                </label>
            <?php endif; ?>
            <button class="button button-primary" type="submit" <?= $pending ? '' : 'disabled' ?>>Run Pending Updates</button>
        </form>
    </div>

    <div class="content-panel">
        <div class="panel-head">
            <h2>Recently Run Updates</h2>
            <span class="muted">Last 20</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Migration</th>
                    <th>Batch</th>
                    <th>Ran At</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $migration): ?>
                    <tr>
                        <td><code><?= e($migration['migration']) ?></code></td>
                        <td><?= e($migration['batch']) ?></td>
                        <td><?= e(date('d M Y H:i', strtotime((string)$migration['ran_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                    <tr><td colspan="3">No updates have run yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
