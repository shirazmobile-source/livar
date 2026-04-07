<?php $errors = validation_errors(); ?>
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Units should stay concise and reusable across the catalog.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/products/units')) ?>" class="btn secondary">Back</a>
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
                    <input type="text" name="name" value="<?= e((string) old('name', $unit['name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $unit['code'])) ?>" placeholder="PCS">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $unit['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $unit['status'])) ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row form-actions">
                <button type="submit" class="btn">Save Unit</button>
                <a href="<?= e(base_url('/products/units')) ?>" class="btn ghost">Cancel</a>
            </div>
        </form>
    </div>
</section>
