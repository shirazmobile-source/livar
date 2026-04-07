<?php use App\Core\Auth; ?>
<?php use App\Core\ThemeManager; ?>
<?php
$settingsUrl = Auth::can('settings.overview')
    ? '/settings'
    : (Auth::can('reports')
        ? '/settings/reports'
        : (Auth::can('settings.backup')
            ? '/settings/backup'
            : (Auth::can('settings.update')
                ? '/settings/update'
                : (Auth::can('settings.users')
                    ? '/settings/users'
                    : (Auth::can('settings.media')
                        ? '/settings/media'
                        : '/settings/theme')))));
$showSettingsMenu = Auth::can('reports') || Auth::any(['settings.overview', 'settings.backup', 'settings.update', 'settings.users', 'settings.media', 'settings.forms', 'settings.theme']);
$userSettingsUrl = Auth::can('settings.users') ? '/settings/users' : $settingsUrl;
$themeMode = ThemeManager::currentMode();
$themeCss = ThemeManager::compiledCss();
$themeRevision = ThemeManager::revision();
$dashboardUrl = Auth::can('dashboard') ? '/' : '/sales';
?>
<!doctype html>
<html lang="en" dir="ltr" data-theme="<?= e($themeMode) ?>" data-default-theme="<?= e($themeMode) ?>" data-theme-revision="<?= e($themeRevision) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? config('app.name')) . ' | ' . config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/livar-ui-kit.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')) ?>">
    <?php if ($themeCss !== ''): ?><style id="livar-theme-custom"><?= $themeCss ?></style><?php endif; ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner topbar-wide topbar-balanced">
        <a class="brand brand-link topbar-brand-slot" href="<?= e(base_url($dashboardUrl)) ?>" aria-label="<?= e(config('app.name')) ?> dashboard">
            <span class="brand-dot"></span>
            <span><?= e(config('app.name')) ?></span>
        </a>

        <nav class="nav-desktop topbar-nav-slot">
            <?php if (Auth::can('customers')): ?><a class="<?= e(active_menu('/customers')) ?>" href="<?= e(base_url('/customers')) ?>">Customers</a><?php endif; ?>
            <?php if (Auth::can('suppliers')): ?><a class="<?= e(active_menu('/suppliers')) ?>" href="<?= e(base_url('/suppliers')) ?>">Suppliers</a><?php endif; ?>
            <?php if (Auth::can('products')): ?><a class="<?= e(active_menu('/products')) ?>" href="<?= e(base_url('/products')) ?>">Products</a><?php endif; ?>
            <?php if (Auth::can('inventory')): ?><a class="<?= e(active_menu('/inventory')) ?>" href="<?= e(base_url('/inventory')) ?>">Inventory</a><?php endif; ?>
            <?php if (Auth::can('purchases')): ?><a class="<?= e(active_menu('/purchases')) ?>" href="<?= e(base_url('/purchases')) ?>">Purchases</a><?php endif; ?>
            <?php if (Auth::can('sales')): ?><a class="<?= e(active_menu('/sales')) ?>" href="<?= e(base_url('/sales')) ?>">Sales</a><?php endif; ?>
        </nav>

        <div class="row-topbar-actions topbar-actions-slot">
            <?php if (Auth::can('banking')): ?>
                <a class="btn btn-sm secondary topbar-icon-link topbar-banking-link <?= e(active_menu('/banking')) ?>" href="<?= e(base_url('/banking')) ?>" title="Banking" aria-label="Banking">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 3 3 7.5v1h18v-1L12 3Zm-7 7v7h2v-7H5Zm4 0v7h2v-7H9Zm4 0v7h2v-7h-2Zm4 0v7h2v-7h-2ZM3 19v2h18v-2H3Z" fill="currentColor"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if ($showSettingsMenu): ?>
                <a class="btn btn-sm secondary topbar-icon-link topbar-settings-link <?= e(active_menu(['/settings', '/reports'])) ?>" href="<?= e(base_url($settingsUrl)) ?>" title="Settings" aria-label="Settings">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M19.14 12.94a7.43 7.43 0 0 0 .05-.94 7.43 7.43 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.36 7.36 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 13.9 2h-3.8a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.71 8.48a.5.5 0 0 0 .12.64l2.03 1.58a7.43 7.43 0 0 0-.05.94 7.43 7.43 0 0 0 .05.94L2.83 14.16a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.71 1.63.94l.36 2.54a.5.5 0 0 0 .49.42h3.8a.5.5 0 0 0 .49-.42l.36-2.54c.58-.23 1.13-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z" fill="currentColor"/>
                    </svg>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm secondary theme-toggle-icon topbar-icon-link" data-theme-toggle aria-label="Theme">
                <span class="theme-icon" data-theme-icon aria-hidden="true">☾</span>
            </button>
            <?php if ($showSettingsMenu): ?>
                <a class="userpill-link" href="<?= e(base_url($userSettingsUrl)) ?>" title="Open user settings">
                    <div class="userpill">
                        <span class="avatar avatar-letter"><?= e(strtoupper(substr((string) (Auth::user()['name'] ?? 'U'), 0, 1))) ?></span>
                        <div class="user-copy">
                            <strong><?= e(Auth::user()['name'] ?? 'User') ?></strong>
                            <small><?= e(Auth::user()['role'] ?? 'staff') ?></small>
                        </div>
                    </div>
                </a>
            <?php else: ?>
                <div class="userpill">
                    <span class="avatar avatar-letter"><?= e(strtoupper(substr((string) (Auth::user()['name'] ?? 'U'), 0, 1))) ?></span>
                    <div class="user-copy">
                        <strong><?= e(Auth::user()['name'] ?? 'User') ?></strong>
                        <small><?= e(Auth::user()['role'] ?? 'staff') ?></small>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(base_url('/logout')) ?>">
                <?= App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-sm ghost">Sign out</button>
            </form>
        </div>
    </div>
</header>

<main class="app-container">
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
</main>

<div class="mobile-dock">
    <?php if (Auth::can('sales')): ?><a href="<?= e(base_url('/sales/create')) ?>" class="btn">New Sale</a><?php endif; ?>
    <?php if (Auth::can('purchases')): ?><a href="<?= e(base_url('/purchases/create')) ?>" class="btn secondary">New Purchase</a><?php endif; ?>
</div>

<footer class="app-footer"><?= e(app_footer_label()) ?></footer>
<script src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
</body>
</html>
