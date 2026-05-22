<?php

declare(strict_types=1);

final class Migrator
{
    private PDO $pdo;
    private string $rootPath;

    public function __construct(PDO $pdo, string $rootPath)
    {
        $this->pdo = $pdo;
        $this->rootPath = rtrim($rootPath, '/\\');
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS database_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                checksum CHAR(64) NOT NULL,
                batch INT NOT NULL DEFAULT 1,
                ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function pendingMigrations(): array
    {
        $this->ensureTable();
        $ran = $this->appliedMigrationMap();
        $files = glob($this->rootPath . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($ran[$name])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Could not read migration: {$name}");
            }

            $pending[] = [
                'name' => $name,
                'path' => $file,
                'checksum' => hash('sha256', $sql),
                'dangerous' => self::containsDangerousSql($sql),
            ];
        }

        return $pending;
    }

    public function recentMigrations(int $limit = 20): array
    {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM database_migrations ORDER BY ran_at DESC, id DESC LIMIT :limit_rows');
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function runPending(bool $allowDangerous = false, ?callable $onMessage = null): array
    {
        $pending = $this->pendingMigrations();
        if (!$pending) {
            return [];
        }

        $dangerous = array_filter($pending, static fn (array $migration): bool => (bool)$migration['dangerous']);
        if ($dangerous && !$allowDangerous) {
            throw new RuntimeException('Dangerous SQL detected. Type RUN to confirm before running these migrations.');
        }

        $batch = $this->nextBatch();
        $ran = [];
        foreach ($pending as $migration) {
            $name = (string)$migration['name'];
            $this->message($onMessage, "Running {$name}...");
            $sql = file_get_contents((string)$migration['path']);
            if ($sql === false) {
                throw new RuntimeException("Could not read migration: {$name}");
            }

            foreach (self::splitSqlStatements($sql) as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                $this->pdo->exec($statement);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO database_migrations (migration, checksum, batch)
                 VALUES (:migration, :checksum, :batch)'
            );
            $stmt->execute([
                'migration' => $name,
                'checksum' => (string)$migration['checksum'],
                'batch' => $batch,
            ]);

            $ran[] = $name;
            $this->message($onMessage, "Done {$name}.");
        }

        return $ran;
    }

    public static function containsDangerousSql(string $sql): bool
    {
        return preg_match('/\bDROP\s+(TABLE|DATABASE)\b/i', $sql) === 1;
    }

    public static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($quote !== null) {
                if ($char === '\\' && !$escaped) {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote && !$escaped) {
                    $quote = null;
                }

                $escaped = false;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === ';') {
                $statements[] = substr($buffer, 0, -1);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function appliedMigrationMap(): array
    {
        $ran = [];
        foreach ($this->pdo->query('SELECT migration FROM database_migrations')->fetchAll() as $row) {
            $ran[(string)$row['migration']] = true;
        }

        return $ran;
    }

    private function nextBatch(): int
    {
        return ((int)$this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM database_migrations')->fetchColumn()) + 1;
    }

    private function message(?callable $onMessage, string $message): void
    {
        if ($onMessage !== null) {
            $onMessage($message);
        }
    }
}
