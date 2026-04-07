<?php
$bankAccounts = $bankAccounts ?? [];
$receipts = $receipts ?? [];
$entry = $entry ?? [];
$dueAmount = (float) ($sale['due_amount'] ?? 0);
$dueAmountAed = (float) ($sale['due_amount_aed'] ?? 0);
?>
<section class="page-head invoice-head">
    <div>
        <h1>Customer Receipt</h1>
        <small><?= e($sale['invoice_no']) ?> • <?= e($sale['customer_name']) ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/sales/show?id=' . $sale['id'])) ?>" class="btn secondary">Back to Sale</a>
    </div>
</section>

<div class="notice" style="margin-bottom:12px;">
    Collection must follow the sales invoice currency exactly: <strong><?= e($sale['currency_code'] ?? 'AED') ?></strong>. Only same-currency banking accounts can receive this customer payment.
</div>

<section class="invoice-grid">
    <div class="card">
        <div class="card-h"><h2>Sale</h2></div>
        <div class="card-b summary-card">
            <div><span>Invoice Final</span><strong><?= e(money_currency($sale['final_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Received</span><strong><?= e(money_currency($sale['received_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Net Receivable</span><strong><?= e(money_currency($sale['net_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Outstanding</span><strong><?= e(money_currency($dueAmount, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Outstanding AED</span><strong><?= e(money($dueAmountAed)) ?></strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Banking Match</h2></div>
        <div class="card-b">
            <?php if ($bankAccounts === []): ?>
                <div class="notice">No active banking account exists in <?= e($sale['currency_code'] ?? 'AED') ?> yet. You can keep this invoice receivable now and collect it later.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th>Bank</th>
                            <th>Account</th>
                            <th>Balance</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bankAccounts as $account): ?>
                            <tr>
                                <td><?= e($account['code']) ?></td>
                                <td><?= e($account['bank_name']) ?></td>
                                <td><?= e($account['account_name']) ?></td>
                                <td class="dt"><?= e(money_currency($account['current_balance_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($dueAmount <= 0.009): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-b">
            <div class="notice">This sale is already fully settled. No further customer receipt is required.</div>
        </div>
    </section>
<?php else: ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Post Customer Receipt</h2></div>
        <div class="card-b">
            <form method="post" action="<?= e(base_url($action)) ?>">
                <?= function_exists('csrf_field') ? csrf_field() : '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">' ?>
                <input type="hidden" name="sale_id" value="<?= e((string) ($entry['sale_id'] ?? $sale['id'])) ?>">

                <div class="form-grid two-col">
                    <div>
                        <label>Receipt Date</label>
                        <input type="date" name="receipt_date" value="<?= e((string) ($entry['receipt_date'] ?? date('Y-m-d'))) ?>">
                    </div>
                    <div>
                        <label>Outstanding Amount (<?= e($sale['currency_code'] ?? 'AED') ?>)</label>
                        <input type="text" value="<?= e(money($dueAmount)) ?>" readonly>
                    </div>
                    <div>
                        <label>Receipt Amount (<?= e($sale['currency_code'] ?? 'AED') ?>)</label>
                        <input type="number" step="0.01" min="0" name="amount_currency" value="<?= e((string) ($entry['amount_currency'] ?? number_format($dueAmount, 2, '.', ''))) ?>">
                    </div>
                    <div>
                        <label>Banking Account (same currency)</label>
                        <select name="bank_account_id">
                            <option value="">Choose account</option>
                            <?php foreach ($bankAccounts as $account): ?>
                                <option value="<?= e((string) $account['id']) ?>" <?= selected((string) $account['id'], (string) ($entry['bank_account_id'] ?? '')) ?>>
                                    <?= e($account['code'] . ' • ' . $account['bank_name'] . ' / ' . $account['account_name'] . ' • Bal ' . money_currency($account['current_balance_currency'] ?? 0, $account['currency_code'] ?? 'AED')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Reference No</label>
                        <input type="text" name="reference_no" value="<?= e((string) ($entry['reference_no'] ?? '')) ?>" maxlength="80">
                    </div>
                    <div>
                        <label>Note</label>
                        <textarea name="note" rows="3"><?= e((string) ($entry['note'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="form-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" name="receipt_action" value="credit" class="btn secondary">Keep as Receivable</button>
                    <button type="submit" name="receipt_action" value="receive" class="btn" <?= $bankAccounts === [] ? 'disabled' : '' ?>>Record Receipt</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Receipt History</h2></div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Bank</th>
                    <th>Amount</th>
                    <th>AED</th>
                    <th>Reference</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($receipts === []): ?>
                    <tr><td colspan="7">No customer receipts have been posted for this sale yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><?= e($receipt['receipt_no']) ?></td>
                            <td class="dt"><?= e(date_display($receipt['receipt_date'])) ?></td>
                            <td><?= e(trim(($receipt['bank_name'] ?? '') . ' / ' . ($receipt['account_name'] ?? ''))) ?></td>
                            <td class="dt"><?= e(money_currency($receipt['amount_currency'] ?? 0, $receipt['currency_code'] ?? ($sale['currency_code'] ?? 'AED'))) ?></td>
                            <td class="dt"><?= e(money($receipt['amount_aed'] ?? 0)) ?></td>
                            <td><?= e($receipt['reference_no'] ?: '—') ?></td>
                            <td><?= e($receipt['note'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
