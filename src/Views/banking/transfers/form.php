<?php $errors = validation_errors(); ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Transfers move value from one banking account to another. If currencies differ, the system converts through AED using the current stored currency rates.</small>
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
                    <label>Source Account</label>
                    <select name="source_account_id" required>
                        <option value="">Select source</option>
                        <?php foreach (($accounts ?? []) as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= selected((string) $account['id'], (string) old('source_account_id', $transfer['source_account_id'])) ?>><?= e($account['bank_name'] . ' / ' . $account['account_name'] . ' / ' . $account['currency_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Destination Account</label>
                    <select name="destination_account_id" required>
                        <option value="">Select destination</option>
                        <?php foreach (($accounts ?? []) as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= selected((string) $account['id'], (string) old('destination_account_id', $transfer['destination_account_id'])) ?>><?= e($account['bank_name'] . ' / ' . $account['account_name'] . ' / ' . $account['currency_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Transfer Date</label>
                    <input type="date" name="txn_date" value="<?= e((string) old('txn_date', $transfer['txn_date'])) ?>" required>
                </div>
                <div class="field">
                    <label>Amount (source account currency)</label>
                    <input type="number" step="0.01" min="0.01" name="amount_currency" value="<?= e((string) old('amount_currency', $transfer['amount_currency'])) ?>" required>
                </div>
                <div class="field">
                    <label>Reference No</label>
                    <input type="text" name="reference_no" value="<?= e((string) old('reference_no', $transfer['reference_no'])) ?>">
                </div>
            </div>

            <div class="field">
                <label>Note</label>
                <textarea name="note"><?= e((string) old('note', $transfer['note'])) ?></textarea>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Post Transfer</button>
                <a href="<?= e(base_url('/banking?tab=ledger')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
