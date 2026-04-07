<?php $errors = validation_errors(); ?>
<?php require __DIR__ . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Configure inventory items, non-inventory items, and services with AED base pricing.</small>
    </div>
    <div class="page-head-actions">
        <?php if (!empty($product['id'])): ?>
            <a href="<?= e(base_url('/products/qr?id=' . $product['id'])) ?>" class="btn secondary">Open QR</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/products')) ?>" class="btn ghost">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require __DIR__ . '/../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url($action)) ?>" enctype="multipart/form-data" class="field-grid">
            <?= App\Core\Csrf::field() ?>

            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $product['code'])) ?>" required data-product-code>
                    <?php if (!empty($generatedCode ?? '')): ?>
                        <input type="hidden" name="generated_code_seed" value="<?= e((string) ($generatedCode ?? '')) ?>">
                    <?php endif; ?>
                    <small>New items get a short random code like <code>Li-A7Q9</code>. You can still edit it manually if needed.</small>
                </div>
                <div class="field">
                    <label>Item Name</label>
                    <input type="text" name="name" value="<?= e((string) old('name', $product['name'])) ?>" required>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $product['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $product['status'])) ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Item Type</label>
                    <select name="item_type" data-item-type-select>
                        <option value="inventory" <?= selected('inventory', old('item_type', $product['item_type'] ?? 'inventory')) ?>>Inventory Item</option>
                        <option value="non_inventory" <?= selected('non_inventory', old('item_type', $product['item_type'] ?? 'inventory')) ?>>Non-Inventory Item</option>
                        <option value="service" <?= selected('service', old('item_type', $product['item_type'] ?? 'inventory')) ?>>Service</option>
                    </select>
                    <small>Inventory items go through warehouse stock. Services and non-inventory items stay out of stock and are used for charges, costs, and service income.</small>
                </div>
                <div class="field">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="0">Select category</option>
                        <?php foreach (($categories ?? []) as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>" <?= selected((string) $category['id'], (string) old('category_id', $product['category_id'] ?? 0)) ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Unit</label>
                    <select name="unit_id" required>
                        <option value="0">Select unit</option>
                        <?php foreach (($units ?? []) as $unit): ?>
                            <option value="<?= e((string) $unit['id']) ?>" <?= selected((string) $unit['id'], (string) old('unit_id', $product['unit_id'] ?? 0)) ?>><?= e($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Currency</label>
                    <input type="text" value="AED — UAE Dirham" readonly>
                    <input type="hidden" name="currency_id" value="<?= e((string) ($product['currency_id'] ?? 0)) ?>">
                    <small>Product master pricing is fixed in AED. Currency conversion is handled only inside invoices.</small>
                </div>
            </div>

            <div class="field-grid field-grid-3">
                <div class="field">
                    <label>Purchase Price (AED)</label>
                    <input type="number" step="0.01" min="0" name="purchase_price_display" value="<?= e((string) old('purchase_price_display', $product['purchase_price_display'])) ?>" required data-product-aed-purchase>
                </div>
                <div class="field">
                    <label>Sale Price (AED)</label>
                    <input type="number" step="0.01" min="0" name="sale_price_display" value="<?= e((string) old('sale_price_display', $product['sale_price_display'])) ?>" required data-product-aed-sale>
                </div>
                <div class="field conversion-card">
                    <label>AED Conversion Preview</label>
                    <div class="conversion-box">
                        <div><span>Purchase AED</span><strong data-purchase-aed><?= e(money(old('purchase_price_display', $product['purchase_price_display'] ?? 0))) ?></strong></div>
                        <div><span>Sale AED</span><strong data-sale-aed><?= e(money(old('sale_price_display', $product['sale_price_display'] ?? 0))) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="media-card packaging-card" data-inventory-only-card>
                <div class="media-card-head">
                    <h3>Carton & Box Details</h3>
                    <small>Keep packaging data on the product master. CBM is calculated automatically from the carton dimensions.</small>
                </div>

                <div class="field-grid field-grid-2 packaging-meta-grid">
                    <div class="field field-span-2">
                        <label>Carton Dimensions (cm)</label>
                        <div class="dimension-grid">
                            <input type="number" step="0.01" min="0" name="carton_length_cm" value="<?= e((string) old('carton_length_cm', $product['carton_length_cm'] ?? '0')) ?>" placeholder="Length" data-carton-length>
                            <input type="number" step="0.01" min="0" name="carton_width_cm" value="<?= e((string) old('carton_width_cm', $product['carton_width_cm'] ?? '0')) ?>" placeholder="Width" data-carton-width>
                            <input type="number" step="0.01" min="0" name="carton_height_cm" value="<?= e((string) old('carton_height_cm', $product['carton_height_cm'] ?? '0')) ?>" placeholder="Height" data-carton-height>
                        </div>
                        <small>Length × Width × Height. Use centimeters for correct CBM calculation.</small>
                    </div>
                    <div class="field">
                        <label>Gross Weight (kg)</label>
                        <input type="number" step="0.001" min="0" name="gross_weight_kg" value="<?= e((string) old('gross_weight_kg', $product['gross_weight_kg'] ?? '0')) ?>">
                    </div>
                    <div class="field">
                        <label>Units per Box</label>
                        <input type="number" step="1" min="1" name="units_per_box" value="<?= e((string) old('units_per_box', $product['units_per_box'] ?? '1')) ?>" required data-units-per-box-master>
                        <small>This value drives automatic box pricing inside purchase and sales invoices.</small>
                    </div>
                    <div class="field">
                        <label>CBM (Per Carton)</label>
                        <input type="text" value="<?= e(number_format((float) old('cbm_per_carton', $product['cbm_per_carton'] ?? 0), 6, '.', '')) ?>" readonly data-cbm-output>
                        <input type="hidden" name="cbm_per_carton" value="<?= e(number_format((float) old('cbm_per_carton', $product['cbm_per_carton'] ?? 0), 6, '.', '')) ?>" data-cbm-hidden>
                    </div>
                </div>
            </div>

            <div class="field-grid field-grid-2 product-media-grid">
                <div class="media-card">
                    <div class="media-card-head">
                        <h3>Product Image</h3>
                        <small>Optional product preview for lists and edit pages.</small>
                    </div>
                    <div class="field" style="margin-bottom:12px;">
                        <label>Product Image</label>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                    </div>
                    <div class="media-preview-block">
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="<?= e(public_upload_url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>" class="product-preview-image">
                            <label class="checkbox-line"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                        <?php else: ?>
                            <div class="product-preview-empty">No product image uploaded.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="media-card">
                    <div class="media-card-head">
                        <h3>QR Preview</h3>
                        <small>The QR label uses the product code. You can open the label after saving the item.</small>
                    </div>
                    <div class="qr-preview-box">
                        <img src="<?= e(qr_image_url((string) old('code', $product['code']), 180)) ?>" alt="QR preview" data-qr-preview>
                        <code data-qr-value><?= e((string) old('code', $product['code'])) ?></code>
                    </div>
                </div>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Save Item</button>
                <a href="<?= e(base_url('/products')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>

        <?php if (!empty($product['id'])): ?>
            <form method="post" action="<?= e(base_url('/products/delete?id=' . (int) $product['id'])) ?>" onsubmit="return confirm('Delete this product? This action cannot be undone.');" style="margin-top:16px; display:flex; justify-content:flex-end;">
                <?= App\Core\Csrf::field() ?>
                <button type="submit" class="btn ghost">Delete Item</button>
            </form>
        <?php endif; ?>
    </div>
</section>
