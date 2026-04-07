<?php $errors = validation_errors(); ?>
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Rate to AED defines the product price conversion into the system base currency.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/products/currencies')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require dirname(__DIR__, 2) . '/partials/form_errors.php'; ?>
        <form method="post" action="<?= e(base_url($action)) ?>" class="field-grid">
            <?= App\Core\Csrf::field() ?>
            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= e((string) old('name', $currency['name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $currency['code'])) ?>" placeholder="USD" required>
                </div>
                <div class="field">
                    <label>Symbol</label>
                    <input type="text" name="symbol" value="<?= e((string) old('symbol', $currency['symbol'])) ?>" placeholder="$" required>
                </div>
            </div>
            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Rate to AED</label>
                    <input type="number" step="0.00000001" min="0.00000001" name="rate_to_aed" value="<?= e((string) old('rate_to_aed', $currency['rate_to_aed'])) ?>" required>
                    <small>Example: 1 USD = 3.65 AED, so the rate should be 3.65.</small>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $currency['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $currency['status'])) ?>>Inactive</option>
                    </select>
                </div>
                <div class="field">
                    <label>Default Currency</label>
                    <label class="checkbox-line"><input type="checkbox" name="is_default" value="1" <?= checked('1', old('is_default', (string) $currency['is_default'])) ?>> Make this the catalog default currency</label>
                </div>
            </div>
            <div class="row form-actions">
                <button type="submit" class="btn">Save Currency</button>
                <a href="<?= e(base_url('/products/currencies')) ?>" class="btn ghost">Cancel</a>
            </div>
        </form>
    </div>
</section>
