<?php

declare(strict_types=1);

namespace App\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class UpdateManager
{
    public function listHistory(): array
    {
        $history = [];
        $path = $this->historyPath();
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        }

        usort($history, static fn (array $a, array $b): int => strcmp((string) ($b['installed_at'] ?? ''), (string) ($a['installed_at'] ?? '')));

        return $history;
    }

    public function applyPackage(string $uploadedPath, string $originalName, array $meta = []): array
    {
        $this->ensureZipSupport();

        if (!is_file($uploadedPath)) {
            throw new RuntimeException('The uploaded update file is missing.');
        }

        ensure_directory($this->packagesDir());
        ensure_directory($this->stagingDir());

        $packageId = date('Ymd-His') . '-' . $this->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $storedPackagePath = $this->packagesDir() . '/' . $packageId . '.zip';

        if (!copy($uploadedPath, $storedPackagePath)) {
            throw new RuntimeException('The update package could not be stored on the server.');
        }

        $safetyBackup = (new BackupManager())->createFullBackup('pre-update-safety', [
            'created_by' => $meta['created_by'] ?? 'system',
            'mode' => 'pre-update',
        ]);

        $stageDir = $this->stagingDir() . '/' . $packageId;
        ensure_directory($stageDir);

        $zip = new ZipArchive();
        if ($zip->open($storedPackagePath) !== true) {
            throw new RuntimeException('The update ZIP file could not be opened.');
        }

        if (!$zip->extractTo($stageDir)) {
            $zip->close();
            throw new RuntimeException('The update ZIP file could not be extracted.');
        }
        $zip->close();

        $sourceRoot = $this->detectPackageRoot($stageDir);
        $this->assertLooksLikeProject($sourceRoot);

        $fileCount = $this->copyTree($sourceRoot, project_path());
        $executedSql = $this->runOptionalSqlUpdates($sourceRoot);
        $migrationsRun = $this->runProjectMigrations();

        $entry = [
            'id' => $packageId,
            'package_name' => basename($originalName),
            'installed_at' => date(DATE_ATOM),
            'installed_by' => $meta['created_by'] ?? 'system',
            'status' => 'success',
            'files_applied' => $fileCount,
            'sql_scripts_run' => $executedSql,
            'migrations_run' => count($migrationsRun),
            'migration_files' => $migrationsRun,
            'safety_backup_id' => $safetyBackup['id'] ?? null,
        ];

        $history = $this->listHistory();
        array_unshift($history, $entry);
        file_put_contents($this->historyPath(), json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $entry;
    }

    private function runOptionalSqlUpdates(string $sourceRoot): int
    {
        $candidates = [
            $sourceRoot . '/update.sql',
            $sourceRoot . '/database/update.sql',
        ];

        $executed = 0;
        $pdo = Database::connection();

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }

            foreach (split_sql_statements((string) file_get_contents($path)) as $statement) {
                if (trim($statement) === '') {
                    continue;
                }
                $pdo->exec($statement);
            }

            $executed++;
        }

        return $executed;
    }

    private function runProjectMigrations(): array
    {
        $migrator = new Migrator(Database::connection());
        return $migrator->runPending();
    }

    private function copyTree(string $source, string $destination): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolute = $item->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen($source))), '/');
            if ($relative === '' || $this->shouldSkipDuringUpdate($relative)) {
                continue;
            }

            $target = $destination . '/' . $relative;

            if ($item->isDir()) {
                ensure_directory($target);
                continue;
            }

            ensure_directory(dirname($target));
            if (!copy($absolute, $target)) {
                throw new RuntimeException('Could not update file: ' . $relative);
            }
            $count++;
        }

        return $count;
    }

    private function detectPackageRoot(string $stageDir): string
    {
        $items = array_values(array_filter(scandir($stageDir) ?: [], static fn (string $item): bool => $item !== '.' && $item !== '..'));

        if (count($items) === 1 && is_dir($stageDir . '/' . $items[0])) {
            return $stageDir . '/' . $items[0];
        }

        return $stageDir;
    }

    private function assertLooksLikeProject(string $root): void
    {
        $looksValid = is_file($root . '/index.php') && is_dir($root . '/public') && is_dir($root . '/src');
        if (!$looksValid) {
            throw new RuntimeException('This ZIP file does not look like a valid LCA core update package.');
        }
    }

    private function shouldSkipDuringUpdate(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $protected = [
            '.env',
            'storage/app/backups/',
            'storage/app/update-packages/',
            'storage/app/update-staging/',
            'storage/logs/',
            'storage/sessions/',
        ];

        foreach ($protected as $prefix) {
            if ($relativePath === rtrim($prefix, '/')) {
                return true;
            }

            if (str_ends_with($prefix, '/') && str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function packagesDir(): string
    {
        $path = storage_path('app/update-packages');
        ensure_directory($path);
        return $path;
    }

    private function stagingDir(): string
    {
        $path = storage_path('app/update-staging');
        ensure_directory($path);
        return $path;
    }

    private function historyPath(): string
    {
        ensure_directory(storage_path('app'));
        return storage_path('app/update-history.json');
    }

    private function ensureZipSupport(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZipArchive extension is required for core updates.');
        }
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'package';
        return trim($value, '-') ?: 'package';
    }
}
