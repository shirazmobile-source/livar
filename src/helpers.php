<?php

declare(strict_types=1);

function project_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function storage_path(string $path = ''): string
{
    $base = project_path('storage');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function public_path(string $path = ''): string
{
    $base = project_path('public');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function public_upload_path(string $path = ''): string
{
    $base = public_path('uploads');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function env_value(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $envPath = project_path('.env');

        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                $env[$name] = $value;
            }
        }
    }

    return $env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

function detect_app_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = trim(dirname($scriptName), '/');

    return rtrim($scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : ''), '/');
}

function app_base_path(): string
{
    $url = parse_url((string) config('app.url', detect_app_url()));
    $path = $url['path'] ?? '';

    if ($path === '' || $path === '/') {
        return '';
    }

    return '/' . trim($path, '/');
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = [
            'app.name' => env_value('APP_NAME', 'LCA'),
            'app.env' => env_value('APP_ENV', 'production'),
            'app.debug' => filter_var(env_value('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
            'app.url' => rtrim((string) env_value('APP_URL', detect_app_url()), '/'),
            'app.timezone' => env_value('TIMEZONE', 'UTC'),
            'db.host' => env_value('DB_HOST', 'localhost'),
            'db.port' => env_value('DB_PORT', '3306'),
            'db.name' => env_value('DB_NAME', ''),
            'db.user' => env_value('DB_USER', ''),
            'db.pass' => env_value('DB_PASS', ''),
            'session.name' => env_value('SESSION_NAME', 'lca_session'),
        ];
    }

    return $config[$key] ?? $default;
}

function base_url(string $path = ''): string
{
    $root = rtrim((string) config('app.url', detect_app_url()), '/');

    if ($path === '' || $path === '/') {
        return $root;
    }

    return $root . '/' . ltrim($path, '/');
}

function install_url(): string
{
    return rtrim(detect_app_url(), '/') . '/install.php';
}

function installation_complete(): bool
{
    return is_file(project_path('.env')) && is_file(storage_path('app/installed.lock'));
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $basePath = app_base_path();

    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }

    $path = '/' . trim($path, '/');

    return $path === '//' ? '/' : $path;
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : base_url($path);
    header('Location: ' . $target);
    exit;
}

function e(string|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): string|null
{
    if (!isset($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function with_old(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function validation_errors(?array $errors = null): array
{
    if ($errors !== null) {
        $_SESSION['_errors'] = $errors;
        return $errors;
    }

    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);

    return $errors;
}

function money(float|int|string|null $value): string
{
    return number_format((float) $value, 2);
}

function money_currency(float|int|string|null $value, ?string $code = 'AED'): string
{
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        $code = 'AED';
    }

    return $code . ' ' . money($value);
}

function date_display(?string $value, string $format = 'Y-m-d'): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTime($value))->format($format);
    } catch (Throwable) {
        return (string) $value;
    }
}

function active_menu(array|string $paths): string
{
    $paths = (array) $paths;
    $current = request_path();

    foreach ($paths as $path) {
        $path = '/' . trim($path, '/');

        if ($path === '//') {
            $path = '/';
        }

        if ($current === $path || ($path !== '/' && str_starts_with($current, rtrim($path, '/') . '/'))) {
            return 'active';
        }
    }

    return '';
}



function csrf_field(): string
{
    $token = \App\Core\Csrf::token();
    return '<input type="hidden" name="_token" value="' . e($token) . '">';
}
function selected(string $value, mixed $current): string
{
    return (string) $current === $value ? 'selected' : '';
}

function checked(mixed $value, mixed $current): string
{
    return (string) $current === (string) $value ? 'checked' : '';
}

function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function public_upload_url(?string $relativePath): string
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
        return '';
    }

    return base_url('/' . ltrim($relativePath, '/'));
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item';
}

function qr_image_url(string $payload, int $size = 180): string
{
    $size = max(80, min(600, $size));

    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=0&data=' . rawurlencode($payload);
}

function remove_public_file(?string $relativePath): void
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
        return;
    }

    $absolutePath = public_path($relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function log_exception(Throwable $exception): void
{
    ensure_directory(storage_path('logs'));
    $line = sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    file_put_contents(storage_path('logs/app-error.log'), $line, FILE_APPEND);
}

function session_cookie_path(): string
{
    $path = app_base_path();
    return $path === '' ? '/' : $path;
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($char === "'" && !$inDouble) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
        } elseif ($char === '"' && !$inSingle) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statements[] = trim($buffer);
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function format_bytes(int|float|null $bytes): string
{
    $size = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;

    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return number_format($size, $index === 0 ? 0 : 2) . ' ' . $units[$index];
}

function app_version(): string
{
    static $version = null;

    if ($version === null) {
        $version = 'v1.5.0';
        $versionFile = project_path('VERSION');
        if (is_file($versionFile)) {
            $value = trim((string) file_get_contents($versionFile));
            if ($value !== '') {
                $version = $value;
            }
        }
    }

    return $version;
}

function app_footer_label(): string
{
    return 'LiVAR® Centralized Accounting ' . app_version();
}

function permission_catalog(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'description' => 'Access the operational dashboard and KPI widgets.'],
        'customers' => ['label' => 'Customers', 'description' => 'Open customer records and maintain customer data.'],
        'suppliers' => ['label' => 'Suppliers', 'description' => 'Open supplier records and maintain supplier data.'],
        'products' => ['label' => 'Products', 'description' => 'Open product records, pricing, stock details, categories, units, and currencies.'],
        'inventory' => ['label' => 'Inventory', 'description' => 'Manage warehouses, stock balances, receipts, and warehouse movement reports.'],
        'banking' => ['label' => 'Banking', 'description' => 'Create bank, cash, and wallet accounts, then track balances, manual entries, and internal transfers.'],
        'purchases' => ['label' => 'Purchases', 'description' => 'Create and review purchase invoices.'],
        'sales' => ['label' => 'Sales', 'description' => 'Create and review sales invoices.'],
        'reports' => ['label' => 'Settings / Reports', 'description' => 'Open operational reports for sales, purchasing, inventory, and profitability.'],
        'settings.overview' => ['label' => 'Settings / Overview', 'description' => 'Open the settings landing page and module directory.'],
        'settings.backup' => ['label' => 'Settings / Backup', 'description' => 'Create, download, and restore full site backups.'],
        'settings.update' => ['label' => 'Settings / Update', 'description' => 'Upload and apply core ZIP update packages.'],
        'settings.users' => ['label' => 'Settings / Users', 'description' => 'Create, update, delete, and control user accounts.'],
        'settings.media' => ['label' => 'Settings / Media', 'description' => 'Review uploaded images and documents, edit media metadata, and safely detach or delete files.'],
        'settings.forms' => ['label' => 'Settings / Forms', 'description' => 'Manage unified print and PDF form templates for invoices, statements, and warehouse slips.'],
        'settings.theme' => ['label' => 'Settings / Theme', 'description' => 'Customize light and dark theme tokens, advanced CSS, and restore the default theme.'],
    ];
}

function permission_groups(): array
{
    return [
        'Operations' => ['dashboard', 'customers', 'suppliers', 'products', 'inventory', 'banking', 'purchases', 'sales'],
        'Settings' => ['reports', 'settings.overview', 'settings.backup', 'settings.update', 'settings.users', 'settings.media', 'settings.forms', 'settings.theme'],
    ];
}

function permission_label(string $permission): string
{
    $catalog = permission_catalog();
    return $catalog[$permission]['label'] ?? $permission;
}

function all_permission_keys(): array
{
    return array_keys(permission_catalog());
}

function normalize_permissions(array|string|null $permissions): array
{
    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        $permissions = is_array($decoded) ? $decoded : [];
    }

    $permissions = array_map(static fn ($value): string => trim((string) $value), (array) $permissions);
    $permissions = array_values(array_unique(array_filter($permissions, static fn (string $value): bool => $value !== '')));

    return array_values(array_filter($permissions, static fn (string $permission): bool => in_array($permission, all_permission_keys(), true)));
}

function encode_permissions(array $permissions): string
{
    return json_encode(normalize_permissions($permissions), JSON_UNESCAPED_SLASHES);
}

function permission_summary(array|string|null $permissions, int $limit = 3): string
{
    $permissions = normalize_permissions($permissions);

    if ($permissions === []) {
        return 'No access assigned';
    }

    if (count($permissions) === count(all_permission_keys())) {
        return 'Full access';
    }

    $labels = array_map(static fn (string $permission): string => permission_label($permission), $permissions);

    if ($limit > 0 && count($labels) > $limit) {
        $visible = array_slice($labels, 0, $limit);
        $remaining = count($labels) - count($visible);

        return implode(', ', $visible) . ' +' . $remaining . ' more';
    }

    return implode(', ', $labels);
}

