<?php require __DIR__ . '/../../partials/form_errors.php'; ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Create and maintain warehouse master data for inventory receiving, issuing, and reporting.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/inventory?tab=warehouses')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap-sm">
            <?= App\Core\Csrf::field() ?>
            <div class="field-grid field-grid-2">
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $warehouse['code'] ?? '')) ?>" required>
                </div>
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= e((string) old('name', $warehouse['name'] ?? '')) ?>" required>
                </div>
                <div class="field">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= e((string) old('location', $warehouse['location'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label>Manager Name</label>
                    <input type="text" name="manager_name" value="<?= e((string) old('manager_name', $warehouse['manager_name'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e((string) old('phone', $warehouse['phone'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $warehouse['status'] ?? 'active')) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $warehouse['status'] ?? 'active')) ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Notes</label>
                <textarea name="notes" placeholder="Dock instructions, access notes, coverage area, etc."><?= e((string) old('notes', $warehouse['notes'] ?? '')) ?></textarea>
            </div>

            <label class="checkbox-line"><input type="checkbox" name="is_default" value="1" <?= checked('1', (string) old('is_default', (string) ($warehouse['is_default'] ?? 0))) ?>> Make this the default warehouse</label>

            <div class="row form-actions">
                <button type="submit" class="btn">Save Warehouse</button>
                <a href="<?= e(base_url('/inventory?tab=warehouses')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
