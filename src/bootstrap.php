<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

ensure_directory(storage_path('logs'));
ensure_directory(storage_path('sessions'));
ensure_directory(storage_path('app'));
ensure_directory(storage_path('app/backups'));
ensure_directory(storage_path('app/update-packages'));
ensure_directory(storage_path('app/update-staging'));
ensure_directory(public_upload_path());
ensure_directory(public_upload_path('products'));
ensure_directory(public_upload_path('categories'));
ensure_directory(public_upload_path('customers'));
ensure_directory(public_upload_path('customer-documents'));

date_default_timezone_set((string) config('app.timezone', 'UTC'));

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', storage_path('logs/php-error.log'));

if (!config('app.debug', false)) {
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $exception): void {
    log_exception($exception);
    http_response_code(500);

    if (config('app.debug', false)) {
        echo '<pre style="white-space:pre-wrap;font-family:monospace;">' . e((string) $exception) . '</pre>';
        return;
    }

    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>The application encountered an error.</p>';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) config('session.name', 'lca_session'));

    if (is_dir(storage_path('sessions')) && is_writable(storage_path('sessions'))) {
        session_save_path(storage_path('sessions'));
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => session_cookie_path(),
        'domain' => '',
        'secure' => str_starts_with((string) config('app.url', detect_app_url()), 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
