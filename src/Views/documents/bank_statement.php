<?php
$template = $formTemplate ?? [];
$global = $formGlobal ?? [];
$logo = trim((string) ($global['company_logo_url'] ?? ''));
if ($logo !== '' && preg_match('~^(https?:)?//|^data:|^/~', $logo) !== 1) { $logo = base_url('/' . ltrim($logo, '/')); }
$rows = $statement['rows'] ?? [];
?>
<?php if (!empty($template['watermark_text'])): ?><div class="doc-watermark"><?= e($template['watermark_text']) ?></div><?php endif; ?>
<section class="document-sheet doc-<?= e(strtolower((string) ($template['paper_size'] ?? 'A4'))) ?>">
<div class="doc-inner">
    <header class="doc-header">
        <div class="doc-brand">
            <?php if (($template['show_logo'] ?? true) && $logo !== ''): ?><img class="doc-logo" src="<?= e($logo) ?>" alt="Logo"><?php endif; ?>
            <div class="doc-company">
                <h1><?= e($template['title'] ?? 'Bank Statement') ?></h1>
                <?php if (($template['show_company_info'] ?? true) === true): ?>
                    <h2><?= e($global['company_name'] ?? config('app.name')) ?></h2>
                    <?php if (!empty($global['company_address'])): ?><p class="muted doc-address"><?= e($global['company_address']) ?></p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="doc-header-side">
            <div class="doc-card">
                <div class="doc-meta-grid">
                    <div><small>Account</small><strong><?= e($account['account_name']) ?></strong></div>
                    <div><small>Bank</small><strong><?= e($account['bank_name']) ?></strong></div>
                    <div><small>Currency</small><strong><?= e($account['currency_code']) ?></strong></div>
                    <div><small>Type</small><strong><?= e(ucfirst((string) $account['account_type'])) ?></strong></div>
                    <div><small>From</small><strong><?= e(date_display((string) $from)) ?></strong></div>
                    <div><small>To</small><strong><?= e(date_display((string) $to)) ?></strong></div>
                </div>
            </div>
        </div>
    </header>

    <?php if (($template['show_summary'] ?? true) === true): ?>
        <section class="doc-section">
            <h3 class="doc-section-title">Statement Summary</h3>
            <div class="doc-kpis">
                <div class="doc-kpi"><small>Opening</small><strong><?= e(money_currency($statement['opening_balance_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></strong></div>
                <div class="doc-kpi"><small>Inflow</small><strong><?= e(money_currency($statement['inflow_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></strong></div>
                <div class="doc-kpi"><small>Outflow</small><strong><?= e(money_currency($statement['outflow_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></strong></div>
            </div>
        </section>
    <?php endif; ?>

    <section class="doc-section">
        <h3 class="doc-section-title">Ledger</h3>
        <div class="doc-table-wrap">
            <table class="doc-table">
                <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Counterparty</th><th class="num">In</th><th class="num">Out</th><th class="num">Balance</th><th>Note</th></tr></thead>
                <tbody>
                <tr>
                    <td><?= e(date_display((string) $from)) ?></td>
                    <td>Opening</td>
                    <td>—</td>
                    <td>Balance brought forward</td>
                    <td class="num">—</td>
                    <td class="num">—</td>
                    <td class="num"><?= e(money_currency($statement['opening_balance_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></td>
                    <td>—</td>
                </tr>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(date_display((string) ($row['txn_date'] ?? ''))) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', (string) ($row['type'] ?? 'entry')))) ?></td>
                        <td><?= e((string) ($row['reference_no'] ?? '—')) ?><?php if (!empty($row['related_account_name'])): ?><br><small><?= e('↔ ' . $row['related_account_name'] . ' (' . $row['related_account_code'] . ')') ?></small><?php endif; ?></td>
                        <td><?= e((string) (($row['counterparty'] ?? '') ?: '—')) ?></td>
                        <td class="num"><?= ($row['direction'] ?? '') === 'in' ? e(money_currency($row['amount_currency'] ?? 0, $account['currency_code'] ?? 'AED')) : '—' ?></td>
                        <td class="num"><?= ($row['direction'] ?? '') === 'out' ? e(money_currency($row['amount_currency'] ?? 0, $account['currency_code'] ?? 'AED')) : '—' ?></td>
                        <td class="num"><?= e(money_currency($row['running_balance_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></td>
                        <td><?= e((string) (($row['note'] ?? '') ?: '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (($template['show_footer'] ?? true) === true): ?><footer class="doc-footer"><div><?= e($template['footer_text'] ?? '') ?></div><div><?= e('Generated ' . date('Y-m-d H:i')) ?></div></footer><?php endif; ?>
</div>
</section>
