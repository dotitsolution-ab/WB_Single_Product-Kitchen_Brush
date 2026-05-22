<?php

declare(strict_types=1);

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        Security::assertLoginAllowed($email);

        $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            Security::recordLoginAttempt($email, false);
            Security::recordEvent('admin_login_failed', null, ['email' => $email]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_user_name'] = (string)$user['name'];
        $_SESSION['admin_login_at'] = time();
        $_SESSION['admin_last_seen'] = time();
        $_SESSION['admin_user_agent_hash'] = Security::userAgentHash();

        Security::recordLoginAttempt($email, true);
        Security::recordEvent('admin_login_success', (int)$user['id']);
        return true;
    }

    public static function check(): bool
    {
        if (empty($_SESSION['admin_user_id'])) {
            return false;
        }

        $idleTimeout = max(10, (int)app_config('security.admin_idle_timeout_minutes', 60)) * 60;
        $lastSeen = (int)($_SESSION['admin_last_seen'] ?? 0);
        if ($lastSeen > 0 && time() - $lastSeen > $idleTimeout) {
            self::logout('admin_session_timeout');
            return false;
        }

        if (!empty($_SESSION['admin_user_agent_hash']) && $_SESSION['admin_user_agent_hash'] !== Security::userAgentHash()) {
            self::logout('admin_session_user_agent_changed');
            return false;
        }

        $_SESSION['admin_last_seen'] = time();
        return true;
    }

    public static function userName(): string
    {
        return (string)($_SESSION['admin_user_name'] ?? 'Admin');
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            redirect('admin/login.php');
        }
    }

    public static function logout(string $eventType = 'admin_logout'): void
    {
        if (!empty($_SESSION['admin_user_id'])) {
            try {
                Security::recordEvent($eventType, (int)$_SESSION['admin_user_id']);
            } catch (Throwable) {
                // Logging must never block logout.
            }
        }

        unset($_SESSION['admin_user_id'], $_SESSION['admin_user_name']);
        unset($_SESSION['admin_login_at'], $_SESSION['admin_last_seen'], $_SESSION['admin_user_agent_hash']);
        session_regenerate_id(true);
    }
}
