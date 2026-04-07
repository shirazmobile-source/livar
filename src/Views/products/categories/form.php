<?php $errors = validation_errors(); ?>
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Category images are optional and can be updated later.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/products/categories')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require dirname(__DIR__, 2) . '/partials/form_errors.php'; ?>
        <form method="post" action="<?= e(base_url($action)) ?>" enctype="multipart/form-data" class="field-grid">
            <?= App\Core\Csrf::field() ?>
            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= e((string) old('name', $category['name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= e((string) old('slug', $category['slug'])) ?>" placeholder="auto-generated if left blank">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $category['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $category['status'])) ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="field-grid field-grid-2 product-media-grid">
                <div class="field">
                    <label>Category Image</label>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <?php if (!empty($category['image_path'])): ?>
                        <div class="media-preview-block" style="margin-top:10px;">
                            <img src="<?= e(public_upload_url($category['image_path'])) ?>" alt="<?= e($category['name']) ?>" class="product-preview-image">
                            <label class="checkbox-line"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-preview-empty">Recommended for quick visual category recognition in catalog pages.</div>
            </div>
            <div class="row form-actions">
                <button type="submit" class="btn">Save Category</button>
                <a href="<?= e(base_url('/products/categories')) ?>" class="btn ghost">Cancel</a>
            </div>
        </form>
    </div>
</section>
