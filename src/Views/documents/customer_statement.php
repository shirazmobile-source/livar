<?php
$template = $formTemplate ?? [];
$global = $formGlobal ?? [];
$logo = trim((string) ($global['company_logo_url'] ?? ''));
if ($logo !== '' && preg_match('~^(https?:)?//|^data:|^/~', $logo) !== 1) { $logo = base_url('/' . ltrim($logo, '/')); }
$statementSections = $statement['sections'] ?? [];
?>
<?php if (!empty($template['watermark_text'])): ?><div class="doc-watermark"><?= e($template['watermark_text']) ?></div><?php endif; ?>
<section class="document-sheet doc-<?= e(strtolower((string) ($template['paper_size'] ?? 'A4'))) ?>">
<div class="doc-inner">
    <header class="doc-header">
        <div class="doc-brand">
            <?php if (($template['show_logo'] ?? true) && $logo !== ''): ?><img class="doc-logo" src="<?= e($logo) ?>" alt="Logo"><?php endif; ?>
            <div class="doc-company">
                <h1><?= e($template['title'] ?? 'Customer Statement') ?></h1>
                <?php if (($template['show_company_info'] ?? true) === true): ?>
                    <h2><?= e($global['company_name'] ?? config('app.name')) ?></h2>
                    <?php if (!empty($global['company_address'])): ?><p class="muted doc-address"><?= e($global['company_address']) ?></p><?php endif; ?>
                    <p class="muted"><?= e(trim((string) (($global['company_phone'] ?? '') . '  ' . ($global['company_email'] ?? '')))) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="doc-header-side">
            <div class="doc-card">
                <div class="doc-meta-grid">
                    <div><small>Customer</small><strong><?= e((string) ($customer['name'] ?: ($customer['company_name'] ?: ($customer['person_name'] ?: $customer['code'])))) ?></strong></div>
                    <div><small>Code</small><strong><?= e((string) $customer['code']) ?></strong></div>
                    <div><small>From</small><strong><?= e(($from ?? '') !== '' ? date_display((string) $from) : 'Beginning') ?></strong></div>
                    <div><small>To</small><strong><?= e(($to ?? '') !== '' ? date_display((string) $to) : 'Latest') ?></strong></div>
                    <div><small>Currency Filter</small><strong><?= e($currencyCode === 'ALL' ? 'All currencies' : $currencyCode) ?></strong></div>
                    <div><small>Status</small><strong><?= e(ucfirst((string) ($customer['status'] ?? 'active'))) ?></strong></div>
                </div>
            </div>
        </div>
    </header>

    <?php if (($template['show_summary'] ?? true) === true && ($summaryByCurrency ?? []) !== []): ?>
        <section class="doc-section">
            <h3 class="doc-section-title">Current Position</h3>
            <div class="doc-kpis">
                <?php foreach ($summaryByCurrency as $summary): ?>
                    <div class="doc-kpi">
                        <small><?= e($summary['currency_code']) ?> Outstanding</small>
                        <strong><?= e(money_currency($summary['outstanding'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></strong>
                        <div class="muted">Sales <?= e(money_currency($summary['sales_total'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></div>
                        <div class="muted">Received <?= e(money_currency($summary['received_total'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($statementSections as $section): ?>
        <section class="doc-section">
            <h3 class="doc-section-title"><?= e($section['currency_code']) ?> Statement</h3>
            <div class="doc-summary" style="margin-bottom:12px;">
                <div><small>Opening</small><strong><?= e(money_currency($section['opening_balance'] ?? 0, $section['currency_code'])) ?></strong></div>
                <div><small>Debit</small><strong><?= e(money_currency($section['debit_total'] ?? 0, $section['currency_code'])) ?></strong></div>
                <div><small>Credit</small><strong><?= e(money_currency($section['credit_total'] ?? 0, $section['currency_code'])) ?></strong></div>
                <div class="final"><small>Closing</small><strong><?= e(money_currency($section['closing_balance'] ?? 0, $section['currency_code'])) ?></strong></div>
            </div>
            <div class="doc-table-wrap">
                <table class="doc-table">
                    <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Running Balance</th></tr></thead>
                    <tbody>
                    <?php if ((float) ($section['opening_balance'] ?? 0) !== 0.0): ?>
                        <tr><td><?= e(($from ?? '') !== '' ? date_display((string) $from) : '—') ?></td><td>Opening</td><td>—</td><td>Balance brought forward before the selected period.</td><td class="num">—</td><td class="num">—</td><td class="num"><?= e(money_currency($section['opening_balance'] ?? 0, $section['currency_code'])) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach (($section['rows'] ?? []) as $row): ?>
                        <tr>
                            <td><?= e(date_display((string) ($row['txn_date'] ?? ''))) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string) ($row['txn_type'] ?? 'entry')))) ?></td>
                            <td><?= e((string) ($row['reference_no'] ?? '—')) ?></td>
                            <td><?= e((string) ($row['description'] ?? '—')) ?></td>
                            <td class="num"><?= (float) ($row['debit_amount'] ?? 0) > 0 ? e(money_currency($row['debit_amount'], $section['currency_code'])) : '—' ?></td>
                            <td class="num"><?= (float) ($row['credit_amount'] ?? 0) > 0 ? e(money_currency($row['credit_amount'], $section['currency_code'])) : '—' ?></td>
                            <td class="num"><?= e(money_currency($row['running_balance'] ?? 0, $section['currency_code'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (($template['show_footer'] ?? true) === true): ?><footer class="doc-footer"><div><?= e($template['footer_text'] ?? '') ?></div><div><?= e('Generated ' . date('Y-m-d H:i')) ?></div></footer><?php endif; ?>
</div>
</section>
