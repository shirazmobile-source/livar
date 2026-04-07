<?php use App\Core\ThemeManager; ?>
<?php
$themeMode = ThemeManager::currentMode();
$themeCss = ThemeManager::compiledCss();
$themeRevision = ThemeManager::revision();
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
<body class="auth-page">
<main class="auth-shell">
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
    <footer class="app-footer app-footer-auth"><?= e(app_footer_label()) ?></footer>
</main>
<script src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
</body>
</html>
