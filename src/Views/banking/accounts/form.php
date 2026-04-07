<?php $errors = validation_errors(); ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Create a banking account once, then keep the ledger clean through entries and transfers.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/banking?tab=accounts')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require __DIR__ . '/../../partials/form_errors.php'; ?>

        <?php if (($lockedFinancialFields ?? false) === true): ?>
            <div class="notice" style="margin-bottom:12px;">
                Currency and opening balance are locked because this account already has banking transactions. You can still update the account identity, status, and notes safely.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url($action)) ?>">
            <?= App\Core\Csrf::field() ?>

            <div class="field-grid field-grid-2">
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $account['code'])) ?>" required>
                </div>
                <div class="field">
                    <label>Account Type</label>
                    <?php $selectedType = (string) old('account_type', $account['account_type']); ?>
                    <select name="account_type">
                        <option value="bank" <?= selected('bank', $selectedType) ?>>Bank Account</option>
                        <option value="cash" <?= selected('cash', $selectedType) ?>>Cash Box</option>
                        <option value="wallet" <?= selected('wallet', $selectedType) ?>>Wallet</option>
                    </select>
                </div>
                <div class="field">
                    <label>Bank / Provider</label>
                    <input type="text" name="bank_name" value="<?= e((string) old('bank_name', $account['bank_name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Account Name</label>
                    <input type="text" name="account_name" value="<?= e((string) old('account_name', $account['account_name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Currency</label>
                    <?php $selectedCurrency = (string) old('currency_id', $account['currency_id']); ?>
                    <select name="currency_id" <?= ($lockedFinancialFields ?? false) ? 'disabled' : '' ?>>
                        <?php foreach (($currencies ?? []) as $currency): ?>
                            <option value="<?= e((string) $currency['id']) ?>" <?= selected((string) $currency['id'], $selectedCurrency) ?>><?= e($currency['code'] . ' — ' . $currency['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small><?= e(((string) old('currency_code', $account['currency_code'])) === 'AED' ? 'Local AED account' : 'Foreign currency account') ?></small>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $account['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $account['status'])) ?>>Inactive</option>
                    </select>
                </div>
                <div class="field">
                    <label>Opening Balance (account currency)</label>
                    <input type="number" step="0.01" name="opening_balance_currency" value="<?= e((string) old('opening_balance_currency', $account['opening_balance_currency'])) ?>" <?= ($lockedFinancialFields ?? false) ? 'readonly' : '' ?>>
                </div>
                <div class="field">
                    <label>Account Number</label>
                    <input type="text" name="account_number" value="<?= e((string) old('account_number', $account['account_number'])) ?>">
                </div>
                <div class="field">
                    <label>IBAN</label>
                    <input type="text" name="iban" value="<?= e((string) old('iban', $account['iban'])) ?>">
                </div>
                <div class="field">
                    <label>SWIFT Code</label>
                    <input type="text" name="swift_code" value="<?= e((string) old('swift_code', $account['swift_code'])) ?>">
                </div>
            </div>

            <div class="field">
                <label>Notes</label>
                <textarea name="notes"><?= e((string) old('notes', $account['notes'])) ?></textarea>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Save Account</button>
                <a href="<?= e(base_url('/banking?tab=accounts')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
