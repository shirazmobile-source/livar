<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function ensureRepository(): void
    {
        $this->pdo()->exec('
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(190) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function pendingMigrations(): array
    {
        $this->ensureRepository();

        $applied = array_flip($this->appliedMigrations());
        $pending = [];

        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            if (!isset($applied[$name])) {
                $pending[] = $path;
            }
        }

        return $pending;
    }

    public function runPending(): array
    {
        $this->ensureRepository();

        $executed = [];

        foreach ($this->pendingMigrations() as $path) {
            $name = basename($path);
            $sql = (string) file_get_contents($path);

            $this->pdo()->beginTransaction();

            try {
                foreach (split_sql_statements($sql) as $statement) {
                    if (trim($statement) === '') {
                        continue;
                    }

                    $this->pdo()->exec($statement);
                }

                $insert = $this->pdo()->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, NOW())');
                $insert->execute(['migration' => $name]);

                $this->pdo()->commit();
                $executed[] = $name;
            } catch (Throwable $exception) {
                if ($this->pdo()->inTransaction()) {
                    $this->pdo()->rollBack();
                }

                throw new RuntimeException('Migration failed: ' . $name . ' - ' . $exception->getMessage(), 0, $exception);
            }
        }

        return $executed;
    }

    public function markAllAsApplied(): void
    {
        $this->ensureRepository();

        $insert = $this->pdo()->prepare('INSERT IGNORE INTO schema_migrations (migration, applied_at) VALUES (:migration, NOW())');

        foreach ($this->migrationFiles() as $path) {
            $insert->execute(['migration' => basename($path)]);
        }
    }

    private function appliedMigrations(): array
    {
        $statement = $this->pdo()->query('SELECT migration FROM schema_migrations ORDER BY migration ASC');

        return array_map(
            static fn (array $row): string => (string) $row['migration'],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function migrationFiles(): array
    {
        $files = glob(project_path('database/migrations/*.sql')) ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter($files, static fn (string $path): bool => is_file($path)));
    }

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->pdo = Database::connection();

        return $this->pdo;
    }
}
