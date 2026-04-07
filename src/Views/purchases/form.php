<?php
$errors = validation_errors();
$isEdit = isset($purchase) && is_array($purchase) && $purchase !== [];
$purchaseId = (int) ($purchase['id'] ?? 0);
$badgeLabel = $isEdit ? 'Pending • Editable' : 'Awaiting Receipt';
$submitLabel = $isEdit ? 'Update Purchase' : 'Save Purchase';
$defaultInvoiceDate = $isEdit ? (string) ($purchase['invoice_date'] ?? date('Y-m-d')) : date('Y-m-d');
$selectedSupplierId = (string) old('supplier_id', (string) ($purchase['supplier_id'] ?? ''));
$selectedCurrencyId = (string) old('currency_id', (string) ($purchase['currency_id'] ?? ($defaultCurrency['id'] ?? 0)));

$existingItems = $purchaseItems ?? [];
$oldProducts = (array) old('product_id', $existingItems !== [] ? array_map(static fn (array $item): string => (string) $item['product_id'], $existingItems) : ['']);
$oldQty = (array) old('qty', $existingItems !== [] ? array_map(static fn (array $item): string => (string) (($item['display_qty'] ?? 0) > 0 ? $item['display_qty'] : ($item['qty'] ?? '')), $existingItems) : ['1']);
$oldPrice = (array) old('unit_price', $existingItems !== [] ? array_map(static fn (array $item): string => (string) (((string) ($item['pricing_unit'] ?? 'unit') === 'box' && (float) ($item['units_per_box'] ?? 1) > 1)
    ? round((float) ($item['unit_price'] ?? 0) * (float) ($item['units_per_box'] ?? 1), 2)
    : (float) ($item['unit_price'] ?? 0)), $existingItems) : ['0']);
$oldPriceAed = (array) old('unit_price_aed_shadow', $existingItems !== [] ? array_map(static fn (array $item): string => (string) ($item['unit_price_aed'] ?? ''), $existingItems) : ['']);
$oldPricingUnit = (array) old('pricing_unit', $existingItems !== [] ? array_map(static fn (array $item): string => (string) ($item['pricing_unit'] ?? 'unit'), $existingItems) : ['unit']);
$oldUnitsPerBox = (array) old('units_per_box', $existingItems !== [] ? array_map(static fn (array $item): string => (string) ($item['units_per_box'] ?? 1), $existingItems) : ['1']);
$rowCount = max(count($oldProducts), 1);
?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>
            <?= $isEdit
                ? e('Pending purchases remain editable until the first warehouse receipt is posted. After any receipt activity, the purchase becomes read-only.')
                : e('Create a purchase invoice. Stock will remain pending until you receive it into a warehouse from Inventory.') ?>
        </small>
    </div>
    <div class="page-head-actions">
        <?php if ($isEdit): ?>
            <a href="<?= e(base_url('/purchases/show?id=' . $purchaseId)) ?>" class="btn secondary">Back to Invoice</a>
        <?php else: ?>
            <a href="<?= e(base_url('/purchases')) ?>" class="btn secondary">Back</a>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../partials/form_errors.php'; ?>

<form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap" data-invoice-form>
    <?= App\Core\Csrf::field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $purchaseId) ?>">
    <?php endif; ?>

    <section class="card">
        <div class="card-h">
            <h2>Purchase Header</h2>
            <span class="badge orange"><?= e($badgeLabel) ?></span>
        </div>
        <div class="card-b">
            <?php if ($isEdit): ?>
                <div class="notice" style="margin-bottom:12px;">
                    Editing is allowed only while this purchase stays in <strong>Pending</strong>. The invoice number remains fixed as
                    <strong><?= e((string) ($purchase['invoice_no'] ?? '')) ?></strong>.
                </div>
            <?php endif; ?>

            <div class="grid-2">
                <div class="field">
                    <label>Supplier</label>
                    <select name="supplier_id" required>
                        <option value="">Choose supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= e((string) $supplier['id']) ?>" <?= selected((string) $supplier['id'], $selectedSupplierId) ?>><?= e($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Invoice Date</label>
                    <input type="date" name="invoice_date" value="<?= e((string) old('invoice_date', $defaultInvoiceDate)) ?>" required>
                </div>
                <div class="field">
                    <label>Invoice Currency</label>
                    <select name="currency_id" data-invoice-currency-select required>
                        <?php foreach (($currencies ?? []) as $currency): ?>
                            <option
                                value="<?= e((string) $currency['id']) ?>"
                                data-rate="<?= e((string) $currency['rate_to_aed']) ?>"
                                data-code="<?= e((string) $currency['code']) ?>"
                                data-symbol="<?= e((string) $currency['symbol']) ?>"
                                <?= selected((string) $currency['id'], $selectedCurrencyId) ?>
                            >
                                <?= e($currency['code'] . ' — ' . $currency['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>All purchase pricing stays AED-based in the product master, then converts into the selected invoice currency.</small>
                </div>
                <div class="field conversion-card">
                    <label>Rate Snapshot</label>
                    <div class="conversion-box">
                        <div><span>Invoice Currency</span><strong data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></strong></div>
                        <div><span>1 Currency = AED</span><strong data-invoice-rate-view><?= e(number_format((float) ($defaultCurrency['rate_to_aed'] ?? 1), 4)) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="field">
                <label>Note</label>
                <textarea name="note" placeholder="Optional note"><?= e((string) old('note', (string) ($purchase['note'] ?? ''))) ?></textarea>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-h">
            <h2>Line Items</h2>
            <button type="button" class="btn secondary btn-sm" data-add-line data-mode="purchase">Add Row</button>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table line-items-table" data-line-items="purchase">
                    <thead>
                    <tr>
                        <th style="min-width:240px;">Product</th>
                        <th style="min-width:120px;">Current Stock</th>
                        <th style="min-width:140px;">Pricing Unit</th>
                        <th style="min-width:120px;">Invoice Qty</th>
                        <th style="min-width:170px;">Unit Price (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</th>
                        <th style="min-width:150px;">Line Total</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                    </thead>
                    <tbody data-lines>
                    <?php for ($i = 0; $i < $rowCount; $i++): ?>
                        <tr data-line-row>
                            <td>
                                <select name="product_id[]" data-product-select required>
                                    <option value="">Choose product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option
                                            value="<?= e((string) $product['id']) ?>"
                                            data-base-price-aed="<?= e((string) $product['purchase_price']) ?>"
                                            data-stock="<?= e((string) $product['current_stock']) ?>"
                                            data-units-per-box="<?= e((string) ($product['units_per_box'] ?? 1)) ?>"
                                            data-unit-label="<?= e((string) ($product['unit'] ?? 'Unit')) ?>"
                                            data-item-type="<?= e((string) ($product['item_type'] ?? 'inventory')) ?>"
                                            <?= selected((string) $product['id'], $oldProducts[$i] ?? '') ?>
                                        >
                                            <?= e($product['name'] . ' (' . $product['code'] . ')') ?><?= (($product['item_type'] ?? 'inventory') !== 'inventory') ? e(' — ' . (($product['item_type'] ?? '') === 'service' ? 'Service' : 'Non-Inventory')) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" value="" data-stock-display readonly placeholder="0.00"></td>
                            <td>
                                <select name="pricing_unit[]" data-pricing-unit>
                                    <option value="unit" <?= selected('unit', (string) ($oldPricingUnit[$i] ?? 'unit')) ?>>Unit</option>
                                    <option value="box" <?= selected('box', (string) ($oldPricingUnit[$i] ?? 'unit')) ?>>Box</option>
                                </select>
                                <input type="hidden" name="units_per_box[]" value="<?= e((string) ($oldUnitsPerBox[$i] ?? '1')) ?>" data-units-per-box-hidden>
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="qty[]" value="<?= e((string) ($oldQty[$i] ?? '1')) ?>" data-qty required></td>
                            <td>
                                <input type="number" step="0.01" min="0" name="unit_price[]" value="<?= e((string) ($oldPrice[$i] ?? '0')) ?>" data-price required>
                                <input type="hidden" name="unit_price_aed_shadow[]" value="<?= e((string) ($oldPriceAed[$i] ?? '')) ?>" data-price-aed>
                            </td>
                            <td><input type="text" value="0.00" data-line-total readonly></td>
                            <td><button type="button" class="btn ghost btn-sm" data-remove-line>Remove</button></td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <?php $oldDiscountAed = old('discount_amount_aed_shadow', (string) ($purchase['discount_amount_aed'] ?? '')); ?>
            <div class="totals-box">
                <div class="field">
                    <label>Discount Amount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" value="<?= e((string) old('discount_amount', (string) ($purchase['discount_amount'] ?? '0'))) ?>" data-discount>
                    <input type="hidden" name="discount_amount_aed_shadow" value="<?= e((string) $oldDiscountAed) ?>" data-discount-aed>
                </div>
                <div class="summary-card">
                    <div><span>Subtotal (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-subtotal>0.00</strong></div>
                    <div><span>Discount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-discount-view>0.00</strong></div>
                    <div class="summary-final"><span>Final Amount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-final-total>0.00</strong></div>
                    <div><span>Subtotal AED</span><strong data-subtotal-aed>0.00</strong></div>
                    <div><span>Discount AED</span><strong data-discount-view-aed>0.00</strong></div>
                    <div class="summary-final"><span>Final AED</span><strong data-final-total-aed>0.00</strong></div>
                </div>
            </div>
        </div>
    </section>

    <div class="sticky-submit">
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <?php if ($isEdit): ?>
            <a href="<?= e(base_url('/purchases/show?id=' . $purchaseId)) ?>" class="btn secondary">Cancel</a>
        <?php else: ?>
            <a href="<?= e(base_url('/purchases')) ?>" class="btn secondary">Cancel</a>
        <?php endif; ?>
    </div>
</form>
