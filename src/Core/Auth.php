<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $permissions = normalize_permissions($user['permissions'] ?? '[]');

        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'is_primary' => (int) ($user['is_primary'] ?? 0) === 1,
            'permissions' => $permissions,
            'last_login_at' => $user['last_login_at'] ?? null,
        ];

        $update = $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = :ip, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'id' => (int) $user['id'],
        ]);

        $_SESSION['auth_user']['last_login_at'] = date('Y-m-d H:i:s');

        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['auth_user']['id']) ? (int) $_SESSION['auth_user']['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
    }

    public static function isPrimary(): bool
    {
        return (bool) (self::user()['is_primary'] ?? false);
    }

    public static function permissions(): array
    {
        return normalize_permissions(self::user()['permissions'] ?? []);
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        if (self::isPrimary()) {
            return true;
        }

        return in_array($permission, self::permissions(), true);
    }

    public static function any(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function homePath(): string
    {
        $map = [
            'dashboard' => '/',
            'customers' => '/customers',
            'suppliers' => '/suppliers',
            'products' => '/products',
            'inventory' => '/inventory',
            'banking' => '/banking',
            'purchases' => '/purchases',
            'sales' => '/sales',
            'reports' => '/settings/reports',
            'settings.overview' => '/settings',
            'settings.backup' => '/settings/backup',
            'settings.update' => '/settings/update',
            'settings.users' => '/settings/users',
            'settings.media' => '/settings/media',
            'settings.forms' => '/settings/forms',
            'settings.theme' => '/settings/theme',
        ];

        foreach ($map as $permission => $path) {
            if (self::can($permission)) {
                return $path;
            }
        }

        return '/login';
    }

    public static function refresh(): void
    {
        if (!self::check()) {
            return;
        }

        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => self::id()]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || ($user['status'] ?? 'inactive') !== 'active') {
            self::logout();
            return;
        }

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'is_primary' => (int) ($user['is_primary'] ?? 0) === 1,
            'permissions' => normalize_permissions($user['permissions'] ?? '[]'),
            'last_login_at' => $user['last_login_at'] ?? null,
        ];
    }
}
