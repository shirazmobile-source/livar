<section class="page-head">
    <div>
        <h1>Supplier Statement</h1>
        <small>Review purchases, returns, payments, and current outstanding payable for <?= e($supplier['name']) ?>.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/documents/suppliers/statement?id=' . $supplier['id'] . '&currency=' . urlencode((string) $currencyCode) . '&from=' . urlencode((string) ($from ?? '')) . '&to=' . urlencode((string) ($to ?? '')))) ?>" class="btn" target="_blank" rel="noopener">Print / PDF</a>
        <a href="<?= e(base_url('/suppliers/edit?id=' . $supplier['id'])) ?>" class="btn secondary">Edit Supplier</a>
        <a href="<?= e(base_url('/suppliers')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <div class="grid-3">
            <div>
                <small>Supplier</small>
                <div><strong><?= e($supplier['name']) ?></strong></div>
                <div><?= e($supplier['code']) ?></div>
            </div>
            <div>
                <small>Contact</small>
                <div><?= e($supplier['mobile'] ?: '—') ?></div>
                <div><?= e($supplier['email'] ?: '—') ?></div>
            </div>
            <div>
                <small>Status</small>
                <div><span class="badge <?= e(($supplier['status'] ?? 'active') === 'active' ? 'green' : 'orange') ?>"><?= e(ucfirst((string) ($supplier['status'] ?? 'active'))) ?></span></div>
                <div><?= e($supplier['address'] ?: 'No address recorded') ?></div>
            </div>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/suppliers/show')) ?>" class="report-filter inventory-filter-grid">
            <input type="hidden" name="id" value="<?= e((string) $supplier['id']) ?>">
            <div class="field">
                <label>Currency</label>
                <select name="currency">
                    <option value="ALL" <?= selected('ALL', $currencyCode) ?>>All currencies</option>
                    <?php foreach (($currencyOptions ?? []) as $code): ?>
                        <option value="<?= e($code) ?>" <?= selected($code, $currencyCode) ?>><?= e($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>From</label>
                <input type="date" name="from" value="<?= e((string) ($from ?? '')) ?>">
            </div>
            <div class="field">
                <label>To</label>
                <input type="date" name="to" value="<?= e((string) ($to ?? '')) ?>">
            </div>
            <div class="field" style="align-self:end;">
                <button type="submit" class="btn secondary">Apply</button>
            </div>
        </form>
    </div>
</section>

<?php if (($summaryByCurrency ?? []) !== []): ?>
    <section class="stats-grid" style="margin-bottom:12px;">
        <?php foreach ($summaryByCurrency as $summary): ?>
            <div class="stat-card">
                <small><?= e($summary['currency_code']) ?> Current Position</small>
                <strong><?= e(money_currency($summary['outstanding'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></strong>
                <div style="margin-top:8px; font-size:12px; line-height:1.5;">
                    <div>Purchases: <?= e(money_currency($summary['purchase_total'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></div>
                    <div>Returns: <?= e(money_currency($summary['return_total'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></div>
                    <div>Paid: <?= e(money_currency($summary['paid_total'] ?? 0, $summary['currency_code'] ?? 'AED')) ?></div>
                    <div>Open Invoices: <?= e((string) ($summary['open_invoice_count'] ?? 0)) ?> / <?= e((string) ($summary['invoice_count'] ?? 0)) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <section class="card" style="margin-bottom:12px;">
        <div class="card-b">No purchase, return, or payment history exists for this supplier yet.</div>
    </section>
<?php endif; ?>

<?php if (($statementSections ?? []) === []): ?>
    <section class="card">
        <div class="card-b">No statement lines match the selected filter.</div>
    </section>
<?php else: ?>
    <?php foreach ($statementSections as $section): ?>
        <section class="card" style="margin-bottom:12px;">
            <div class="card-h">
                <h2><?= e($section['currency_code']) ?> Statement</h2>
                <div class="row" style="gap:8px; flex-wrap:wrap;">
                    <span class="badge green">Closing <?= e(money_currency($section['closing_balance'] ?? 0, $section['currency_code'])) ?></span>
                    <?php if ((float) ($section['opening_balance'] ?? 0) !== 0.0): ?>
                        <span class="badge orange">Opening <?= e(money_currency($section['opening_balance'] ?? 0, $section['currency_code'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-b">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Running Balance</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ((float) ($section['opening_balance'] ?? 0) !== 0.0): ?>
                            <tr>
                                <td><?= e(($from ?? '') !== '' ? date_display((string) $from) : '—') ?></td>
                                <td>Opening</td>
                                <td>—</td>
                                <td>Balance brought forward before the selected date range.</td>
                                <td class="dt">—</td>
                                <td class="dt">—</td>
                                <td class="dt"><?= e(money_currency($section['opening_balance'] ?? 0, $section['currency_code'])) ?></td>
                                <td>—</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach (($section['rows'] ?? []) as $row): ?>
                            <tr>
                                <td><?= e(date_display((string) ($row['txn_date'] ?? ''))) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) ($row['txn_type'] ?? 'entry')))) ?></td>
                                <td><?= e((string) ($row['reference_no'] ?? '—')) ?></td>
                                <td><?= e((string) ($row['description'] ?? '—')) ?></td>
                                <td class="dt"><?= (float) ($row['debit_amount'] ?? 0) > 0 ? e(money_currency($row['debit_amount'], $section['currency_code'])) : '—' ?></td>
                                <td class="dt"><?= (float) ($row['credit_amount'] ?? 0) > 0 ? e(money_currency($row['credit_amount'], $section['currency_code'])) : '—' ?></td>
                                <td class="dt"><?= e(money_currency($row['running_balance'] ?? 0, $section['currency_code'])) ?></td>
                                <td><a class="tab active" href="<?= e(base_url((string) ($row['link'] ?? '/purchases'))) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="4">Filtered Totals</th>
                            <th class="dt"><?= e(money_currency($section['debit_total'] ?? 0, $section['currency_code'])) ?></th>
                            <th class="dt"><?= e(money_currency($section['credit_total'] ?? 0, $section['currency_code'])) ?></th>
                            <th class="dt"><?= e(money_currency($section['closing_balance'] ?? 0, $section['currency_code'])) ?></th>
                            <th></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
