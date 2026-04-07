<section class="page-head">
    <div>
        <h1>Form Builder</h1>
        <small>Create one controlled print system for invoices, statements, and warehouse slips. Each template is shared across preview, print, and PDF export.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/settings')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<?php require __DIR__ . '/../partials/nav.php'; ?>

<section class="card" style="margin-bottom:12px;">
    <div class="card-h"><h2>Global Header</h2><span class="badge green">Revision <?= e((string) ($revision ?? 'core-default')) ?></span></div>
    <div class="card-b">
        <div class="grid-3">
            <div><small>Company</small><div><strong><?= e((string) ($global['company_name'] ?? config('app.name'))) ?></strong></div><div><?= e((string) ($global['company_tagline'] ?? '')) ?></div></div>
            <div><small>Contact</small><div><?= e((string) (($global['company_phone'] ?? '') ?: '—')) ?></div><div><?= e((string) (($global['company_email'] ?? '') ?: '—')) ?></div></div>
            <div><small>Header Note</small><div><?= e((string) (($global['header_note'] ?? '') ?: '—')) ?></div></div>
        </div>
    </div>
</section>

<section class="stats-grid">
    <?php foreach (($types ?? []) as $type => $meta): ?>
        <?php $template = $templates[$type] ?? []; ?>
        <div class="card">
            <div class="card-h"><h2><?= e($meta['label']) ?></h2><span class="badge"><?= e(strtoupper((string) ($template['paper_size'] ?? 'A4')) . ' / ' . ucfirst((string) ($template['orientation'] ?? 'portrait'))) ?></span></div>
            <div class="card-b">
                <p style="min-height:46px;"><?= e($meta['description']) ?></p>
                <div class="grid-2" style="margin-bottom:12px;">
                    <div><small>Title</small><div><strong><?= e((string) ($template['title'] ?? $meta['label'])) ?></strong></div></div>
                    <div><small>Accent</small><div><span class="badge" style="background:<?= e((string) ($template['accent_color'] ?? '#111827')) ?>;color:#fff;"><?= e((string) ($template['accent_color'] ?? '#111827')) ?></span></div></div>
                </div>
                <div class="row" style="gap:8px; flex-wrap:wrap; margin-bottom:12px;">
                    <?php foreach (['show_logo' => 'Logo', 'show_summary' => 'Summary', 'show_qr' => 'QR', 'show_terms' => 'Terms', 'show_signatures' => 'Signatures'] as $flag => $label): ?>
                        <span class="badge <?= e(!empty($template[$flag]) ? 'green' : '') ?>"><?= e($label . ': ' . (!empty($template[$flag]) ? 'On' : 'Off')) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($template['layout_enabled'])): ?><span class="badge green">Visual designer</span><?php endif; ?>
                </div>
                <a class="btn secondary" href="<?= e(base_url('/settings/forms/edit?type=' . $type)) ?>"><?= !empty($template['layout_enabled']) ? 'Edit Template + Layout' : 'Edit Template' ?></a>
            </div>
        </div>
    <?php endforeach; ?>
</section>
