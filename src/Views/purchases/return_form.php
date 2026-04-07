<?php
$purchaseId = (int) ($purchase['id'] ?? 0);
$selectedWarehouseId = (int) ($selectedWarehouse['id'] ?? 0);
?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small><?= e($purchase['invoice_no'] ?? '') ?> • Return against received stock only. The system will deduct both warehouse stock and company stock immediately when this document is posted.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/purchases/show?id=' . $purchaseId)) ?>" class="btn secondary">Back to Purchase</a>
    </div>
</section>

<?php require __DIR__ . '/../partials/form_errors.php'; ?>

<div class="notice" style="margin-bottom:12px;">
    Rules: a purchase return can be posted only after receipt, only from a warehouse that actually received the items, and only up to the quantity still available on hand in that warehouse.
</div>

<section class="card" style="margin-bottom:12px;">
    <div class="card-h"><h2>Return Context</h2></div>
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/purchases/returns/create')) ?>" class="grid-2">
            <input type="hidden" name="purchase_id" value="<?= e((string) $purchaseId) ?>">
            <div class="field">
                <label>Warehouse</label>
                <select name="warehouse_id" onchange="this.form.submit()">
                    <?php foreach (($warehouses ?? []) as $warehouse): ?>
                        <option value="<?= e((string) $warehouse['id']) ?>" <?= selected((string) $warehouse['id'], (string) $selectedWarehouseId) ?>>
                            <?= e($warehouse['name'] . ' (' . money($warehouse['returnable_qty'] ?? 0) . ' available)') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field conversion-card">
                <label>Warehouse Summary</label>
                <div class="conversion-box">
                    <div><span>Warehouse</span><strong><?= e(($selectedWarehouse['name'] ?? '—')) ?></strong></div>
                    <div><span>Received</span><strong><?= e(money($selectedWarehouse['received_qty'] ?? 0)) ?></strong></div>
                    <div><span>Already Returned</span><strong><?= e(money($selectedWarehouse['returned_qty'] ?? 0)) ?></strong></div>
                    <div><span>Still Returnable</span><strong><?= e(money($selectedWarehouse['returnable_qty'] ?? 0)) ?></strong></div>
                </div>
            </div>
        </form>
    </div>
</section>

<form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap">
    <?= App\Core\Csrf::field() ?>
    <input type="hidden" name="purchase_id" value="<?= e((string) $purchaseId) ?>">
    <input type="hidden" name="warehouse_id" value="<?= e((string) $selectedWarehouseId) ?>">

    <section class="card">
        <div class="card-h"><h2>Return Header</h2></div>
        <div class="card-b">
            <div class="grid-2">
                <div class="field">
                    <label>Return Date</label>
                    <input type="date" name="return_date" value="<?= e((string) old('return_date', date('Y-m-d'))) ?>" required>
                </div>
                <div class="field conversion-card">
                    <label>Invoice Currency</label>
                    <div class="conversion-box">
                        <div><span>Currency</span><strong><?= e(($purchase['currency_code'] ?? 'AED') . ' ' . ($purchase['currency_symbol'] ?? '')) ?></strong></div>
                        <div><span>Rate Snapshot</span><strong><?= e(number_format((float) ($purchase['currency_rate_to_aed'] ?? 1), 4)) ?></strong></div>
                        <div><span>Accounting Base</span><strong>AED</strong></div>
                    </div>
                </div>
                <div class="field">
                    <label>Return Reason</label>
                    <input type="text" name="reason" value="<?= e((string) old('reason', '')) ?>" placeholder="Damaged, mismatch, expired, supplier recall..." required>
                </div>
                <div class="field">
                    <label>Note</label>
                    <textarea name="note" placeholder="Optional internal note"><?= e((string) old('note', '')) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-h"><h2>Return Lines</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Unit</th>
                        <th>Received in Warehouse</th>
                        <th>Already Returned</th>
                        <th>Warehouse Stock</th>
                        <th>Allowed Return</th>
                        <th>Unit Price</th>
                        <th>AED Unit</th>
                        <th>Return Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($items ?? []) as $index => $item): ?>
                        <tr>
                            <td>
                                <?= e($item['product_name']) ?>
                                <input type="hidden" name="purchase_item_id[]" value="<?= e((string) $item['purchase_item_id']) ?>">
                            </td>
                            <td><?= e($item['product_code']) ?></td>
                            <td><?= e($item['unit']) ?></td>
                            <td class="dt"><?= e(money($item['received_qty_in_warehouse'] ?? 0)) ?></td>
                            <td class="dt"><?= e(money($item['returned_qty_in_warehouse'] ?? 0)) ?></td>
                            <td class="dt"><?= e(money($item['warehouse_stock_qty'] ?? 0)) ?></td>
                            <td class="dt"><strong><?= e(money($item['available_return_qty'] ?? 0)) ?></strong></td>
                            <td class="dt"><?= e(money_currency($item['unit_price'], $purchase['currency_code'] ?? 'AED')) ?></td>
                            <td class="dt"><?= e(money($item['unit_price_aed'] ?? $item['unit_price'])) ?></td>
                            <td>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="<?= e((string) ($item['available_return_qty'] ?? 0)) ?>"
                                    name="return_qty[]"
                                    value="<?= e((string) (((array) old('return_qty', []))[$index] ?? '0')) ?>"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="sticky-submit">
        <button type="submit" class="btn">Post Purchase Return</button>
        <a href="<?= e(base_url('/purchases/show?id=' . $purchaseId)) ?>" class="btn secondary">Cancel</a>
    </div>
</form>
