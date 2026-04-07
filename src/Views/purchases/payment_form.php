<?php
$bankAccounts = $bankAccounts ?? [];
$payments = $payments ?? [];
$entry = $entry ?? [];
$dueAmount = (float) ($purchase['due_amount'] ?? 0);
$dueAmountAed = (float) ($purchase['due_amount_aed'] ?? 0);
?>
<section class="page-head invoice-head">
    <div>
        <h1>Supplier Payment</h1>
        <small><?= e($purchase['invoice_no']) ?> • <?= e($purchase['supplier_name']) ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>" class="btn secondary">Back to Purchase</a>
    </div>
</section>

<div class="notice" style="margin-bottom:12px;">
    Settlement must follow the purchase invoice currency exactly: <strong><?= e($purchase['currency_code'] ?? 'AED') ?></strong>. Only same-currency banking accounts can be used. Supplier payments may push the chosen bank account into a negative balance when needed.
</div>

<section class="invoice-grid">
    <div class="card">
        <div class="card-h"><h2>Purchase</h2></div>
        <div class="card-b summary-card">
            <div><span>Invoice Final</span><strong><?= e(money_currency($purchase['final_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Returned</span><strong><?= e(money_currency($purchase['returned_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Net Payable</span><strong><?= e(money_currency($purchase['net_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Paid</span><strong><?= e(money_currency($purchase['paid_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div class="summary-final"><span>Outstanding</span><strong><?= e(money_currency($dueAmount, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Outstanding AED</span><strong><?= e(money($dueAmountAed)) ?></strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Banking Match</h2></div>
        <div class="card-b">
            <?php if ($bankAccounts === []): ?>
                <div class="notice">No active banking account exists in <?= e($purchase['currency_code'] ?? 'AED') ?> yet. You can keep this invoice payable now and settle it later.</div>
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
            <div class="notice">This purchase is already fully settled. No further supplier payment is required.</div>
        </div>
    </section>
<?php else: ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Post Supplier Settlement</h2></div>
        <div class="card-b">
            <form method="post" action="<?= e(base_url($action)) ?>">
                <?= function_exists('csrf_field') ? csrf_field() : '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">' ?>
                <input type="hidden" name="purchase_id" value="<?= e((string) ($entry['purchase_id'] ?? $purchase['id'])) ?>">

                <div class="form-grid two-col">
                    <div>
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" value="<?= e((string) ($entry['payment_date'] ?? date('Y-m-d'))) ?>">
                    </div>
                    <div>
                        <label>Outstanding Amount (<?= e($purchase['currency_code'] ?? 'AED') ?>)</label>
                        <input type="text" value="<?= e(money($dueAmount)) ?>" readonly>
                    </div>
                    <div>
                        <label>Payment Amount (<?= e($purchase['currency_code'] ?? 'AED') ?>)</label>
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
                    <button type="submit" name="payment_action" value="credit" class="btn secondary">Keep as Payable</button>
                    <button type="submit" name="payment_action" value="pay" class="btn" <?= $bankAccounts === [] ? 'disabled' : '' ?>>Record Payment</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Payment History</h2></div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Payment No</th>
                    <th>Date</th>
                    <th>Bank</th>
                    <th>Amount</th>
                    <th>AED</th>
                    <th>Reference</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($payments === []): ?>
                    <tr><td colspan="7">No supplier payments have been posted for this purchase yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e($payment['payment_no']) ?></td>
                            <td class="dt"><?= e(date_display($payment['payment_date'])) ?></td>
                            <td><?= e(trim(($payment['bank_name'] ?? '') . ' / ' . ($payment['account_name'] ?? ''))) ?></td>
                            <td class="dt"><?= e(money_currency($payment['amount_currency'] ?? 0, $payment['currency_code'] ?? ($purchase['currency_code'] ?? 'AED'))) ?></td>
                            <td class="dt"><?= e(money($payment['amount_aed'] ?? 0)) ?></td>
                            <td><?= e($payment['reference_no'] ?: '—') ?></td>
                            <td><?= e($payment['note'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
