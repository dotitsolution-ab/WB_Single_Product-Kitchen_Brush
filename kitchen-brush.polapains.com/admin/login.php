<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (Auth::check()) {
    redirect('admin/index.php');
}

$error = null;
if (is_post()) {
    verify_csrf();
    try {
        if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            redirect('admin/index.php');
        }
        $error = 'Invalid admin email or password.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body>
<main class="installer">
    <form class="content-panel installer-panel" method="post">
        <h1>Admin Login</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" autocomplete="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button button-primary button-full" type="submit">Login</button>
    </form>
</main>
</body>
</html>
