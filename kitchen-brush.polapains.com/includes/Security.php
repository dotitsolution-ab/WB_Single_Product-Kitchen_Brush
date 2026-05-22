<?php

declare(strict_types=1);

final class Security
{
    public static function clientIp(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public static function userAgentHash(): string
    {
        return hash('sha256', self::userAgent());
    }

    public static function ensureTables(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(500) NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_attempts_lookup (email, ip_address, attempted_at),
                INDEX idx_login_attempts_attempted (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS security_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(80) NOT NULL,
                admin_user_id INT UNSIGNED NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(500) NULL,
                details TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_security_events_type (event_type),
                INDEX idx_security_events_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function assertLoginAllowed(string $email): void
    {
        self::ensureTables();
        self::cleanupLoginAttempts();

        $cutoff = date('Y-m-d H:i:s', time() - (self::loginDecayMinutes() * 60));
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email
                AND ip_address = :ip_address
                AND success = 0
                AND attempted_at >= :cutoff'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'ip_address' => self::clientIp(),
            'cutoff' => $cutoff,
        ]);

        if ((int)$stmt->fetchColumn() >= self::loginMaxAttempts()) {
            self::recordEvent('admin_login_throttled', null, ['email' => strtolower(trim($email))]);
            throw new RuntimeException('Too many failed login attempts. Please wait and try again.');
        }
    }

    public static function recordLoginAttempt(string $email, bool $success): void
    {
        self::ensureTables();

        $stmt = db()->prepare(
            'INSERT INTO login_attempts (email, ip_address, user_agent, success)
             VALUES (:email, :ip_address, :user_agent, :success)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'ip_address' => self::clientIp(),
            'user_agent' => self::userAgent(),
            'success' => $success ? 1 : 0,
        ]);

        if ($success) {
            $delete = db()->prepare(
                'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip_address AND success = 0'
            );
            $delete->execute([
                'email' => strtolower(trim($email)),
                'ip_address' => self::clientIp(),
            ]);
        }
    }

    public static function recordEvent(string $eventType, ?int $adminUserId = null, array $details = []): void
    {
        self::ensureTables();

        $json = $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES);
        $stmt = db()->prepare(
            'INSERT INTO security_events (event_type, admin_user_id, ip_address, user_agent, details)
             VALUES (:event_type, :admin_user_id, :ip_address, :user_agent, :details)'
        );
        $stmt->execute([
            'event_type' => substr($eventType, 0, 80),
            'admin_user_id' => $adminUserId,
            'ip_address' => self::clientIp(),
            'user_agent' => self::userAgent(),
            'details' => $json,
        ]);
    }

    public static function events(int $limit = 100): array
    {
        self::ensureTables();

        $limit = max(1, min(200, $limit));
        $stmt = db()->prepare('SELECT * FROM security_events ORDER BY created_at DESC LIMIT :limit_rows');
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function cleanupLoginAttempts(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - (7 * 24 * 60 * 60));
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);
    }

    private static function loginMaxAttempts(): int
    {
        return max(3, (int)app_config('security.login_max_attempts', 5));
    }

    private static function loginDecayMinutes(): int
    {
        return max(5, (int)app_config('security.login_decay_minutes', 15));
    }
}
