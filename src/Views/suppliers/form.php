<?php $errors = validation_errors(); ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Maintain supplier information.</small>
    </div>
    <div class="page-head-actions">
        <?php if (!empty($supplier['id'])): ?>
            <a href="<?= e(base_url('/suppliers/show?id=' . $supplier['id'])) ?>" class="btn secondary">Statement</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/suppliers')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require __DIR__ . '/../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url($action)) ?>">
            <?= App\Core\Csrf::field() ?>

            <div class="grid-2">
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $supplier['code'])) ?>" required>
                </div>
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= e((string) old('name', $supplier['name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Mobile</label>
                    <input type="text" name="mobile" value="<?= e((string) old('mobile', $supplier['mobile'])) ?>">
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e((string) old('phone', $supplier['phone'])) ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e((string) old('email', $supplier['email'])) ?>">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $supplier['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $supplier['status'])) ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Address</label>
                <textarea name="address"><?= e((string) old('address', $supplier['address'])) ?></textarea>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="<?= e(base_url('/suppliers')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
