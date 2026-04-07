<?php $errors = validation_errors(); ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Use entries for deposits, withdrawals, and manual adjustments. AED equivalent is captured automatically at save time using the current currency rate.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/banking?tab=ledger')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require __DIR__ . '/../../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url($action)) ?>">
            <?= App\Core\Csrf::field() ?>

            <div class="field-grid field-grid-2">
                <div class="field">
                    <label>Banking Account</label>
                    <select name="bank_account_id" required>
                        <option value="">Select account</option>
                        <?php foreach (($accounts ?? []) as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= selected((string) $account['id'], (string) old('bank_account_id', $entry['bank_account_id'])) ?>><?= e($account['bank_name'] . ' / ' . $account['account_name'] . ' / ' . $account['currency_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Entry Date</label>
                    <input type="date" name="txn_date" value="<?= e((string) old('txn_date', $entry['txn_date'])) ?>" required>
                </div>
                <div class="field">
                    <label>Entry Type</label>
                    <select name="type">
                        <option value="deposit" <?= selected('deposit', old('type', $entry['type'])) ?>>Deposit</option>
                        <option value="withdrawal" <?= selected('withdrawal', old('type', $entry['type'])) ?>>Withdrawal</option>
                        <option value="adjustment_in" <?= selected('adjustment_in', old('type', $entry['type'])) ?>>Adjustment In</option>
                        <option value="adjustment_out" <?= selected('adjustment_out', old('type', $entry['type'])) ?>>Adjustment Out</option>
                    </select>
                </div>
                <div class="field">
                    <label>Amount (account currency)</label>
                    <input type="number" step="0.01" min="0.01" name="amount_currency" value="<?= e((string) old('amount_currency', $entry['amount_currency'])) ?>" required>
                </div>
                <div class="field">
                    <label>Reference No</label>
                    <input type="text" name="reference_no" value="<?= e((string) old('reference_no', $entry['reference_no'])) ?>">
                </div>
                <div class="field">
                    <label>Counterparty</label>
                    <input type="text" name="counterparty" value="<?= e((string) old('counterparty', $entry['counterparty'])) ?>" placeholder="Optional payee or source">
                </div>
            </div>

            <div class="field">
                <label>Note</label>
                <textarea name="note"><?= e((string) old('note', $entry['note'])) ?></textarea>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Post Entry</button>
                <a href="<?= e(base_url('/banking?tab=ledger')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
