<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path, ?string $success = null, ?string $error = null): never
    {
        if ($success !== null) {
            flash('success', $success);
        }

        if ($error !== null) {
            flash('error', $error);
        }

        redirect($path);
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }
    }

    protected function requirePermission(string $permission, ?string $message = null): void
    {
        $this->requireAuth();

        if (Auth::can($permission)) {
            return;
        }

        $fallback = Auth::homePath();
        if ($fallback === '/login') {
            $fallback = '/';
        }

        $this->redirect($fallback, null, $message ?? 'You do not have permission to open this section.');
    }

    protected function requireAdmin(): void
    {
        $this->requirePermission('settings.users', 'Administrator access is required for this section.');
    }

    protected function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}
