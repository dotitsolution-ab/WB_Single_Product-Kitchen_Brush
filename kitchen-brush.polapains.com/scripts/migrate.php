<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/Migrator.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migration runner is CLI only.');
}

$force = in_array('--force', $argv, true);
$env = read_env_file($root . '/.env');

$host = require_env($env, 'DB_HOST');
$port = (int)($env['DB_PORT'] ?? 3306);
$database = require_env($env, 'DB_DATABASE');
$username = require_env($env, 'DB_USERNAME');
$password = require_env($env, 'DB_PASSWORD');
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$migrator = new Migrator($pdo, $root);
$pending = $migrator->pendingMigrations();

if (!$pending) {
    echo "No pending migrations.\n";
    exit(0);
}

$dangerous = array_filter($pending, static fn (array $migration): bool => (bool)$migration['dangerous']);
if ($dangerous && !$force) {
    echo "Dangerous SQL detected in pending migrations (DROP TABLE / DROP DATABASE):\n";
    foreach ($dangerous as $migration) {
        echo "- {$migration['name']}\n";
    }
    echo "Type RUN to execute these migrations: ";
    $answer = trim((string)fgets(STDIN));
    if ($answer !== 'RUN') {
        echo "Skipped migrations.\n";
        exit(1);
    }
}

$migrator->runPending($force || $dangerous !== [], static function (string $message): void {
    echo $message . "\n";
});

echo "Migrations complete.\n";

function read_env_file(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing .env file. Copy .env.example to .env and set DB credentials.');
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }

    return $values;
}

function require_env(array $env, string $key): string
{
    $value = trim((string)($env[$key] ?? ''));
    if ($value === '') {
        throw new RuntimeException("Missing {$key} in .env");
    }

    return $value;
}
