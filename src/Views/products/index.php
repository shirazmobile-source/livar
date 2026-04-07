<?php require __DIR__ . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1>Products / Items</h1>
        <small>Manage inventory items, non-inventory items, services, fixed AED pricing, packaging data, and QR labels.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/products/create')) ?>" class="btn">New Product</a>
    </div>
</section>

<section class="kpi-grid dashboard-kpis products-kpis" style="margin-bottom:12px;">
    <div class="kpi-tile">
        <div class="kpi-icon">#</div>
        <div class="kpi-copy">
            <div class="kpi-label">Total Items</div>
            <div class="kpi-value"><?= e((string) ($summary['total_items'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">!</div>
        <div class="kpi-copy">
            <div class="kpi-label">Low Stock</div>
            <div class="kpi-value"><?= e((string) ($summary['low_stock_items'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">✓</div>
        <div class="kpi-copy">
            <div class="kpi-label">Active</div>
            <div class="kpi-value"><?= e((string) ($summary['active_items'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">0</div>
        <div class="kpi-copy">
            <div class="kpi-label">Out of Stock</div>
            <div class="kpi-value"><?= e((string) ($summary['out_of_stock_items'] ?? 0)) ?></div>
        </div>
    </div>
</section>

<section class="card stack-gap-sm">
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/products')) ?>" class="field-grid field-grid-3 products-filter-grid">
            <div class="field">
                <label>Smart Search</label>
                <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Code, item name, category, or unit">
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= selected('active', $filters['status'] ?? '') ?>>Active</option>
                    <option value="inactive" <?= selected('inactive', $filters['status'] ?? '') ?>>Inactive</option>
                </select>
            </div>
            <div class="field">
                <label>Stock View</label>
                <select name="stock">
                    <option value="">All stock states</option>
                    <option value="low" <?= selected('low', $filters['stock'] ?? '') ?>>Low stock</option>
                    <option value="out" <?= selected('out', $filters['stock'] ?? '') ?>>Out of stock</option>
                    <option value="healthy" <?= selected('healthy', $filters['stock'] ?? '') ?>>Healthy stock</option>
                </select>
            </div>
            <div class="field">
                <label>Category</label>
                <select name="category_id">
                    <option value="0">All categories</option>
                    <?php foreach (($categories ?? []) as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= selected((string) $category['id'], (string) ($filters['category_id'] ?? 0)) ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Item Type</label>
                <select name="item_type">
                    <option value="">All item types</option>
                    <option value="inventory" <?= selected('inventory', $filters['item_type'] ?? '') ?>>Inventory Item</option>
                    <option value="non_inventory" <?= selected('non_inventory', $filters['item_type'] ?? '') ?>>Non-Inventory Item</option>
                    <option value="service" <?= selected('service', $filters['item_type'] ?? '') ?>>Service</option>
                </select>
            </div>
            <div class="field">
                <label>Unit</label>
                <select name="unit_id">
                    <option value="0">All units</option>
                    <?php foreach (($units ?? []) as $unit): ?>
                        <option value="<?= e((string) $unit['id']) ?>" <?= selected((string) $unit['id'], (string) ($filters['unit_id'] ?? 0)) ?>><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row form-actions">
                <button type="submit" class="btn secondary">Apply Search</button>
                <a href="<?= e(base_url('/products')) ?>" class="btn ghost">Reset</a>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table product-table">
                <thead>
                <tr>
                    <th>Preview</th>
                    <th>Code</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Pricing Base</th>
                    <th>Purchase</th>
                    <th>Sale</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($products ?? []) === []): ?>
                    <tr><td colspan="12">No items matched the current search.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="table-thumb-wrap">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?= e(public_upload_url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>" class="table-thumb">
                                    <?php else: ?>
                                        <div class="table-thumb table-thumb-empty">IMG</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= e($product['code']) ?></strong>
                                <div class="muted-xs">QR ready</div>
                            </td>
                            <td>
                                <strong><?= e($product['name']) ?></strong>
                                <div class="muted-xs">AED base: <?= e(money($product['sale_price'])) ?></div>
                                <?php if (($product['item_type'] ?? 'inventory') === 'inventory'): ?>
                                    <div class="muted-xs">Box: <?= e(money($product['units_per_box'] ?? 1)) ?> <?= e($product['unit_label']) ?></div>
                                    <?php if ((float) ($product['cbm_per_carton'] ?? 0) > 0): ?>
                                        <div class="muted-xs">CBM/carton: <?= e(number_format((float) $product['cbm_per_carton'], 6, '.', '')) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="muted-xs">No warehouse stock movement</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $itemType = (string) ($product['item_type'] ?? 'inventory'); ?>
                                <span class="badge <?= e($itemType === 'inventory' ? 'green' : ($itemType === 'service' ? 'orange' : '')) ?>">
                                    <?= e($itemType === 'inventory' ? 'Inventory' : ($itemType === 'service' ? 'Service' : 'Non-Inventory')) ?>
                                </span>
                            </td>
                            <td><?= e($product['category_label']) ?></td>
                            <td><?= e($product['unit_label']) ?></td>
                            <td><strong>AED</strong><div class="muted-xs">Fixed base</div></td>
                            <td>
                                <strong><?= e(money($product['purchase_price'])) ?></strong>
                                <div class="muted-xs">Base in AED</div>
                            </td>
                            <td>
                                <strong><?= e(money($product['sale_price'])) ?></strong>
                                <div class="muted-xs">Base in AED</div>
                            </td>
                            <td>
                                <?php if (($product['item_type'] ?? 'inventory') === 'inventory'): ?>
                                    <strong><?= e(money($product['current_stock'])) ?></strong>
                                    <div class="muted-xs">Min <?= e(money($product['min_stock'])) ?></div>
                                <?php else: ?>
                                    <strong>—</strong>
                                    <div class="muted-xs">No stock tracking</div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= e($product['status'] === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst($product['status'])) ?></span></td>
                            <td>
                                <div class="row-actions-tight">
                                    <a href="<?= e(base_url('/products/qr?id=' . $product['id'])) ?>" class="tab">QR</a>
                                    <a href="<?= e(base_url('/products/edit?id=' . $product['id'])) ?>" class="tab active">Edit</a>
                                    <form method="post" action="<?= e(base_url('/products/delete?id=' . $product['id'])) ?>" onsubmit="return confirm('Delete this product? This action cannot be undone.');" style="display:inline;">
                                        <?= App\Core\Csrf::field() ?>
                                        <button type="submit" class="tab" style="background:transparent;cursor:pointer;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
