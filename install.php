<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$error = null;
$success = null;
$hasEnvConfig = getenv('DB_HOST') !== false && getenv('DB_DATABASE') !== false;
$configExists = file_exists(BASE_PATH . '/config.php') || $hasEnvConfig;
$alreadyInstalled = false;

if ($configExists) {
    try {
        $table = db()->query("SHOW TABLES LIKE 'admin_users'")->fetchColumn();
        if ($table) {
            $alreadyInstalled = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
        }
    } catch (Throwable) {
        $alreadyInstalled = false;
    }
}

if (is_post()) {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    try {
        if ($alreadyInstalled) {
            throw new RuntimeException('This store is already installed. Delete or rename install.php.');
        }
        if (!$configExists) {
            throw new RuntimeException('Create config.php from config.sample.php or set Dokploy database environment variables before installation.');
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new InvalidArgumentException('Enter a valid name, email, and at least 8 character password.');
        }

        $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('database/schema.sql could not be read.');
        }

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            db()->exec($statement);
        }

        $adminCount = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($adminCount > 0) {
            throw new RuntimeException('This store already has an admin user. Delete or rename install.php.');
        }

        $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('An admin user with this email already exists.');
        }

        $insert = db()->prepare(
            'INSERT INTO admin_users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
        );
        $insert->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $success = 'Installation complete. Delete or rename install.php, then log in.';
        $alreadyInstalled = true;
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
    <title>Install Store</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/styles.css')) ?>">
</head>
<body>
<main class="installer">
    <form class="content-panel installer-panel" method="post">
        <h1>Install Store</h1>
        <?php if ($alreadyInstalled): ?>
            <div class="alert alert-error">This store is already installed. Delete or rename install.php for security.</div>
        <?php endif; ?>
        <?php if (!$configExists): ?>
            <div class="alert alert-error">Copy config.sample.php to config.php or set Dokploy database environment variables first.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?> <a href="<?= e(base_url('admin/login.php')) ?>">Admin login</a></div>
        <?php endif; ?>
        <?php if (!$alreadyInstalled): ?>
            <?= csrf_field() ?>
            <label>
                Admin Name
                <input type="text" name="name" required>
            </label>
            <label>
                Admin Email
                <input type="email" name="email" required>
            </label>
            <label>
                Admin Password
                <input type="password" name="password" required minlength="8">
            </label>
            <button class="button button-primary button-full" type="submit">Run Install</button>
        <?php endif; ?>
    </form>
</main>
</body>
</html>
