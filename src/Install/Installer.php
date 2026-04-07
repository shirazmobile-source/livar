<?php

declare(strict_types=1);

namespace App\Install;

use App\Core\Migrator;
use PDO;
use PDOException;
use RuntimeException;

final class Installer
{
    public function requirements(): array
    {
        return [
            'php_81' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'zip' => class_exists(\ZipArchive::class),
            'root_writable' => is_writable(project_path()),
            'storage_writable' => is_writable(storage_path()) || (!file_exists(storage_path()) && is_writable(project_path())),
        ];
    }

    public function install(array $data): array
    {
        $appName = trim((string) ($data['app_name'] ?? 'LCA'));
        $appUrl = rtrim(trim((string) ($data['app_url'] ?? detect_app_url())), '/');
        $dbHost = trim((string) ($data['db_host'] ?? 'localhost'));
        $dbPort = trim((string) ($data['db_port'] ?? '3306'));
        $dbName = trim((string) ($data['db_name'] ?? ''));
        $dbUser = trim((string) ($data['db_user'] ?? ''));
        $dbPass = (string) ($data['db_pass'] ?? '');
        $adminName = trim((string) ($data['admin_name'] ?? 'System Administrator'));
        $adminEmail = trim((string) ($data['admin_email'] ?? 'admin@example.com'));
        $adminPassword = (string) ($data['admin_password'] ?? '');
        $timezone = trim((string) ($data['timezone'] ?? 'Asia/Dubai'));
        $seedSample = isset($data['seed_sample']) && (string) $data['seed_sample'] === '1';

        $errors = [];

        if ($dbName === '') {
            $errors['db_name'][] = 'Database name is required.';
        }

        if ($dbUser === '') {
            $errors['db_user'][] = 'Database username is required.';
        }

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'][] = 'A valid admin email is required.';
        }

        if (strlen($adminPassword) < 8) {
            $errors['admin_password'][] = 'Admin password must be at least 8 characters.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        ensure_directory(storage_path('logs'));
        ensure_directory(storage_path('sessions'));
        ensure_directory(storage_path('app'));
        ensure_directory(storage_path('app/backups'));
        ensure_directory(storage_path('app/update-packages'));
        ensure_directory(storage_path('app/update-staging'));

        $pdo = $this->connectAndPrepareDatabase($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        $this->runSqlFile($pdo, project_path('database/schema.sql'));
        $this->runSqlFile($pdo, project_path('database/seed.sql'));
        $this->seedAdmin($pdo, $adminName, $adminEmail, $adminPassword);

        if ($seedSample) {
            $this->runSqlFile($pdo, project_path('database/sample_seed.sql'));
        }

        $migrator = new Migrator($pdo);
        $migrator->ensureRepository();
        $migrator->markAllAsApplied();

        $env = $this->buildEnv($appName, $appUrl, $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $timezone);

        if (file_put_contents(project_path('.env'), $env) === false) {
            throw new RuntimeException('Unable to write the .env file. Please check file permissions.');
        }

        $lockPayload = json_encode([
            'installed_at' => date(DATE_ATOM),
            'app_url' => $appUrl,
            'db_name' => $dbName,
            'admin_email' => $adminEmail,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents(storage_path('app/installed.lock'), $lockPayload);

        return ['ok' => true];
    }

    private function connectAndPrepareDatabase(string $host, string $port, string $dbName, string $user, string $pass): PDO
    {
        $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        try {
            $server = new PDO($serverDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Could not connect to MySQL server: ' . $exception->getMessage(), 0, $exception);
        }

        $quotedDb = sprintf('`%s`', str_replace('`', '``', $dbName));

        try {
            $server->exec("CREATE DATABASE IF NOT EXISTS {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $exception) {
            // Some shared hosts do not allow CREATE DATABASE. Continue and try to use the database if it already exists.
        }

        try {
            $server->exec("USE {$quotedDb}");
        } catch (PDOException $exception) {
            throw new RuntimeException('The installer could not use the selected database. Create it in cPanel first, then run the installer again.', 0, $exception);
        }

        return $server;
    }

    private function runSqlFile(PDO $pdo, string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('SQL file not found: ' . $path);
        }

        foreach (split_sql_statements((string) file_get_contents($path)) as $statement) {
            if (trim($statement) === '') {
                continue;
            }

            $pdo->exec($statement);
        }
    }

    private function seedAdmin(PDO $pdo, string $name, string $email, string $password): void
    {
        $pdo->exec('DELETE FROM users');

        $statement = $pdo->prepare('
            INSERT INTO users (name, email, password, role, status, permissions, is_primary, created_at, updated_at)
            VALUES (:name, :email, :password, "admin", "active", :permissions, 1, NOW(), NOW())
        ');

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'permissions' => json_encode(all_permission_keys(), JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function buildEnv(
        string $appName,
        string $appUrl,
        string $dbHost,
        string $dbPort,
        string $dbName,
        string $dbUser,
        string $dbPass,
        string $timezone
    ): string {
        $safeAppName = str_replace('"', '\\"', $appName);

        return <<<ENV
APP_NAME="{$safeAppName}"
APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}

DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

SESSION_NAME=lca_session
TIMEZONE={$timezone}

ENV;
    }
}
