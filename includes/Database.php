<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = app_config('database');
        $db = self::normalizeConfig(is_array($db) ? $db : []);
        $dsn = self::dsn($db);

        try {
            self::$pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed. Check Dokploy database environment variables.');
        }

        return self::$pdo;
    }

    public static function driver(): string
    {
        $db = app_config('database');
        $db = self::normalizeConfig(is_array($db) ? $db : []);
        return (string)($db['driver'] ?? 'pgsql');
    }

    private static function normalizeConfig(array $db): array
    {
        $url = (string)($db['url'] ?? '');
        if ($url === '') {
            return $db;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme'])) {
            return $db;
        }

        $scheme = strtolower((string)$parts['scheme']);
        $db['driver'] = in_array($scheme, ['postgres', 'postgresql'], true) ? 'pgsql' : $scheme;
        $db['host'] = (string)($parts['host'] ?? ($db['host'] ?? 'localhost'));
        $db['port'] = (int)($parts['port'] ?? ($db['port'] ?? 5432));
        $db['name'] = isset($parts['path']) ? ltrim((string)$parts['path'], '/') : ($db['name'] ?? '');
        $db['user'] = isset($parts['user']) ? urldecode((string)$parts['user']) : ($db['user'] ?? '');
        $db['pass'] = isset($parts['pass']) ? urldecode((string)$parts['pass']) : ($db['pass'] ?? '');

        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (isset($query['sslmode'])) {
                $db['sslmode'] = (string)$query['sslmode'];
            }
        }

        return $db;
    }

    private static function dsn(array $db): string
    {
        $driver = (string)($db['driver'] ?? 'pgsql');
        if ($driver !== 'pgsql') {
            throw new RuntimeException('Only PostgreSQL is supported in this Docker deployment.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $db['host'] ?? 'localhost',
            (int)($db['port'] ?? 5432),
            $db['name'] ?? ''
        );

        $sslmode = (string)($db['sslmode'] ?? '');
        if ($sslmode !== '') {
            $dsn .= ';sslmode=' . $sslmode;
        }

        $connectTimeout = (int)($db['connect_timeout'] ?? 10);
        if ($connectTimeout > 0) {
            $dsn .= ';connect_timeout=' . $connectTimeout;
        }

        return $dsn;
    }
}
