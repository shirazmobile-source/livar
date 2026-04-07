<?php
$formTemplate = $formTemplate ?? [];
$formGlobal = $formGlobal ?? [];
$formCss = $formCss ?? '';
$paperSize = strtoupper((string) ($formTemplate['paper_size'] ?? 'A4'));
$orientation = strtolower((string) ($formTemplate['orientation'] ?? 'portrait'));
$documentTitle = $documentTitle ?? ($title ?? config('app.name'));
$companyName = trim((string) ($formGlobal['company_name'] ?? config('app.name')));
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle . ' | ' . $companyName) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/documents.css')) ?>">
    <style>
        @page { size: <?= e($paperSize . ' ' . $orientation) ?>; margin: 0; }
        <?= $formCss ?>
    </style>
</head>
<body class="doc-page doc-<?= e(strtolower(str_replace(' ', '-', $paperSize))) ?> doc-<?= e($orientation) ?>">
<div class="doc-toolbar no-print">
    <div>
        <strong><?= e($documentTitle) ?></strong>
        <small><?= e($companyName) ?></small>
        <div class="doc-print-guide">Print tip: use scale <strong>100%</strong> and browser margins <strong>None</strong> for the most accurate PDF/layout output.</div>
    </div>
    <div class="doc-toolbar-actions">
        <button type="button" class="btn secondary btn-sm" onclick="window.history.back()">Back</button>
        <button type="button" class="btn btn-sm" onclick="window.print()">Print / Save PDF</button>
    </div>
</div>
<div class="doc-canvas">
    <?= $content ?>
</div>
<?php if (($formTemplate['auto_print'] ?? false) === true): ?>
<script>
window.addEventListener('load', function () { window.print(); });
</script>
<?php endif; ?>
</body>
</html>
