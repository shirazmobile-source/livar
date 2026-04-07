<?php
$errors = validation_errors();
$selectedWarehouseId = (string) old('warehouse_id', '');
$selectedWarehouseLabel = 'Choose warehouse';
foreach (($warehouses ?? []) as $warehouseOption) {
    if ((string) ($warehouseOption['id'] ?? '') === $selectedWarehouseId) {
        $selectedWarehouseLabel = trim((string) ($warehouseOption['name'] ?? 'Choose warehouse'));
        $warehouseCode = trim((string) ($warehouseOption['code'] ?? ''));
        if ($warehouseCode !== '') {
            $selectedWarehouseLabel .= ' (' . $warehouseCode . ')';
        }
        break;
    }
}
$selectedCurrencyId = (string) old('currency_id', (string) ($defaultCurrency['id'] ?? 0));
$selectedCustomerId = (string) old('customer_id', '');
$selectedCustomerName = 'Choose customer';
foreach (($customers ?? []) as $customerOption) {
    if ((string) ($customerOption['id'] ?? '') === $selectedCustomerId) {
        $selectedCustomerName = trim((string) ($customerOption['name'] ?? 'Choose customer'));
        break;
    }
}
$oldProducts = array_values((array) old('product_id', ['']));
$oldQty = array_values((array) old('qty', ['1']));
$oldPrice = array_values((array) old('unit_price', ['0']));
$oldPriceAed = array_values((array) old('unit_price_aed_shadow', ['']));
$oldPricingUnit = array_values((array) old('pricing_unit', ['unit']));
$oldUnitsPerBox = array_values((array) old('units_per_box', ['1']));
$rowCount = max(count($oldProducts), 1);
$oldDiscount = (string) old('discount_amount', '0');
$oldDiscountAed = (string) old('discount_amount_aed_shadow', '');
$hasSelectedProducts = false;
foreach ($oldProducts as $oldProductId) {
    if ((string) $oldProductId !== '') {
        $hasSelectedProducts = true;
        break;
    }
}
$openStep = 'warehouse';
if ((string) old('customer_id') !== '') {
    $openStep = 'customer';
} elseif ($hasSelectedProducts && $selectedWarehouseId !== '') {
    $openStep = ((string) old('note') !== '' || (string) old('invoice_date') !== '' || (string) old('discount_amount') !== '') ? 'pricing' : 'items';
} elseif ($selectedWarehouseId !== '') {
    $openStep = 'items';
}
?>

<style>
.sales-create-wizard{
    display:grid;
    gap:18px;
    max-width:1320px;
    margin:0 auto;
}
.sales-hidden-calc{
    display:none !important;
}
.sales-wizard-shell{
    border:1px solid var(--line);
    border-radius:24px;
    background:linear-gradient(180deg, color-mix(in srgb, var(--panel) 96%, transparent), color-mix(in srgb, var(--panel-2) 96%, transparent));
    box-shadow:0 20px 52px rgba(0,0,0,.16);
    overflow:hidden;
}
.sales-wizard-topbar{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:18px;
    padding:22px 24px;
    border-bottom:1px solid var(--line);
    background:color-mix(in srgb, var(--panel) 88%, transparent);
}
.sales-wizard-topbar h2{
    margin:0 0 6px;
    font-size:22px;
}
.sales-wizard-topbar p{
    margin:0;
    color:var(--muted);
    max-width:760px;
}
.sales-stepper{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    justify-content:flex-end;
}
.sales-step-pill{
    min-width:150px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--panel-2) 82%, transparent);
    color:var(--muted);
}
.sales-step-pill small,
.sales-step-pill strong{
    display:block;
}
.sales-step-pill strong{
    margin-top:4px;
    color:var(--text);
    font-size:14px;
}
.sales-step-pill.is-active{
    border-color:color-mix(in srgb, var(--accent) 56%, var(--line));
    box-shadow:0 0 0 1px color-mix(in srgb, var(--accent) 24%, transparent) inset;
}
.sales-step-pill.is-complete{
    border-color:rgba(24,163,107,.45);
}
.sales-step-panel{
    display:none;
    padding:24px;
}
.sales-step-panel.is-active{
    display:block;
}
.sales-panel-card,
.sales-item-board,
.sales-summary-card,
.sales-detail-panel{
    border:1px solid var(--line);
    border-radius:20px;
    background:color-mix(in srgb, var(--panel) 92%, transparent);
}
.sales-panel-card,
.sales-detail-panel,
.sales-summary-card{
    padding:20px;
}
.sales-panel-card h3,
.sales-item-board-head h3,
.sales-detail-panel h3,
.sales-summary-card h4{
    margin:0;
    font-size:18px;
}
.sales-panel-card p,
.sales-item-board-head p,
.sales-detail-panel p,
.sales-summary-card p{
    margin:8px 0 0;
    color:var(--muted);
}
.sales-step-actions{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-top:20px;
}
.sales-step-actions .group{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}
.sales-step-hint{
    color:var(--muted);
    font-size:13px;
}
.sales-context-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:12px;
    margin-bottom:16px;
}
.sales-context-chip{
    border:1px solid var(--line);
    border-radius:16px;
    padding:14px 16px;
    background:color-mix(in srgb, var(--panel-2) 82%, transparent);
}
.sales-context-chip span{
    display:block;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    margin-bottom:6px;
}
.sales-context-chip strong{
    display:block;
    font-size:15px;
}
.sales-warehouse-stage{
    max-width:760px;
    margin:0 auto;
}
.sales-warehouse-stage .sales-panel-card{
    text-align:center;
    display:grid;
    gap:18px;
}
.sales-warehouse-selector{
    display:flex;
    justify-content:center;
}
.sales-warehouse-selector select{
    width:min(100%, 460px);
    border-radius:18px;
    border:1px solid color-mix(in srgb, var(--accent) 26%, var(--line));
    background:color-mix(in srgb, var(--panel-2) 94%, transparent);
    color:var(--text);
    font-size:20px;
    font-weight:800;
    text-align:center;
    text-align-last:center;
    padding:16px 52px 16px 18px;
    box-shadow:0 14px 30px rgba(0,0,0,.18);
}
.sales-warehouse-selector select:focus{
    outline:none;
    border-color:color-mix(in srgb, var(--accent) 60%, var(--line));
    box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 16%, transparent), 0 16px 32px rgba(0,0,0,.18);
}
.sales-warehouse-caption{
    margin:0;
    color:var(--muted);
    font-size:13px;
}
.sales-stage-status{
    margin:0;
    color:var(--muted);
    font-size:13px;
}
.sales-stage-status strong{
    color:var(--text);
}
.sales-item-board{
    overflow:visible;
}
.sales-item-board .card-b{
    overflow:visible;
    padding:0;
}
.sales-item-board-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:16px 18px;
    border-bottom:1px solid var(--line);
    background:color-mix(in srgb, var(--panel-2) 90%, transparent);
}
.sales-item-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}
.sales-item-table-wrap{
    overflow:visible;
}
.sales-item-entry-table{
    width:100%;
    min-width:0;
    table-layout:fixed;
    border-collapse:separate;
    border-spacing:0;
}
.sales-item-entry-table thead th{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--muted);
    padding:14px;
    background:color-mix(in srgb, var(--panel) 90%, transparent);
    border-bottom:1px solid var(--line);
}
.sales-item-entry-table tbody td{
    padding:14px;
    vertical-align:top;
    border-bottom:1px solid var(--line);
    overflow:visible;
}
.sales-item-entry-table tbody tr:last-child td{
    border-bottom:none;
}
.sales-item-entry-table th:nth-child(1){width:40%}
.sales-item-entry-table th:nth-child(2){width:14%}
.sales-item-entry-table th:nth-child(3){width:10%}
.sales-item-entry-table th:nth-child(4){width:14%}
.sales-item-entry-table th:nth-child(5){width:12%}
.sales-item-entry-table th:nth-child(6){width:10%}
.sales-row-stack{
    display:grid;
    gap:10px;
}
.sales-picker{position:relative}
.sales-picker-hidden{
    position:absolute !important;
    opacity:0 !important;
    pointer-events:none !important;
    width:1px !important;
    height:1px !important;
    inset:0 auto auto 0;
}
.sales-picker-input{
    width:100%;
    border-radius:14px;
    padding:14px 16px;
    font-size:15px;
    border:1px solid color-mix(in srgb, var(--accent) 22%, var(--line));
    background:color-mix(in srgb, var(--panel-2) 94%, transparent);
    color:var(--text);
}
.sales-picker-input:focus{
    outline:none;
    border-color:color-mix(in srgb, var(--accent) 58%, var(--line));
    box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}
.sales-picker-menu{
    position:absolute;
    left:0;
    right:0;
    top:calc(100% + 10px);
    background:rgba(10, 12, 16, .76);
    border:1px solid color-mix(in srgb, var(--line) 78%, rgba(255,255,255,.16));
    border-radius:18px;
    box-shadow:0 24px 56px rgba(0,0,0,.32), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(18px) saturate(135%);
    -webkit-backdrop-filter:blur(18px) saturate(135%);
    padding:10px;
    display:none;
    z-index:60;
}
.sales-picker-menu.is-open{display:block}
.sales-picker-option{
    width:100%;
    text-align:left;
    border:1px solid transparent;
    border-radius:14px;
    padding:12px 13px;
    background:rgba(255,255,255,.025);
    color:inherit;
    display:grid;
    gap:4px;
    cursor:pointer;
    transition:background .18s ease, border-color .18s ease;
}
.sales-picker-option:hover,
.sales-picker-option.is-active{
    background:color-mix(in srgb, var(--accent) 14%, rgba(255,255,255,.06));
    border-color:color-mix(in srgb, var(--accent) 28%, var(--line));
}
.sales-picker-line{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}
.sales-picker-line strong{font-size:14px}
.sales-picker-sub,
.sales-picker-stock,
.sales-row-meta,
.sales-inline-muted{
    color:color-mix(in srgb, var(--muted) 86%, #ffffff 14%);
    font-size:12px;
}
.sales-picker-stock.is-good{color:#18a36b}
.sales-picker-stock.is-low{color:#e6a23c}
.sales-picker-stock.is-bad{color:#ef5350}
.sales-row-meta{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}
.sales-tag{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    padding:4px 9px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--panel) 94%, transparent);
    font-size:11px;
    font-weight:700;
    letter-spacing:.02em;
}
.sales-tag.good{border-color:rgba(24,163,107,.35); color:#18a36b}
.sales-tag.low{border-color:rgba(230,162,60,.35); color:#e6a23c}
.sales-tag.bad{border-color:rgba(239,83,80,.35); color:#ef5350}
.sales-stock-box,
.sales-amount-box{
    min-height:58px;
    border-radius:14px;
    padding:12px 14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--panel-2) 86%, transparent);
}
.sales-stock-box strong,
.sales-amount-box strong{
    display:block;
    font-size:16px;
    margin-top:4px;
}
.sales-stock-box span,
.sales-amount-box span{
    display:block;
    color:var(--muted);
    font-size:12px;
}
.sales-inline-controls{
    display:grid;
    gap:10px;
}
.sales-inline-controls input[type="number"],
.sales-inline-controls input[type="date"],
.sales-inline-controls textarea,
.sales-inline-controls select,
.sales-detail-panel input[type="date"],
.sales-detail-panel textarea,
.sales-detail-panel select{
    width:100%;
}
.sales-warehouse-rule{
    margin:14px 18px 0;
    border-left:3px solid color-mix(in srgb, var(--accent) 40%, transparent);
    background:color-mix(in srgb, var(--accent) 9%, transparent);
    color:var(--muted);
    border-radius:0 14px 14px 0;
    padding:12px 14px;
}
.sales-detail-grid{
    display:grid;
    grid-template-columns:minmax(0, 1.15fr) minmax(340px, .85fr);
    gap:18px;
}
.sales-detail-grid .grid-2{gap:14px}
.sales-pricing-preview-table{
    width:100%;
    border-collapse:collapse;
}
.sales-pricing-preview-table th,
.sales-pricing-preview-table td{
    padding:10px 0;
    border-bottom:1px solid var(--line);
    text-align:left;
}
.sales-pricing-preview-table th:last-child,
.sales-pricing-preview-table td:last-child,
.sales-pricing-preview-table .is-num{
    text-align:right;
}
.sales-pricing-preview-table tbody tr:last-child td{
    border-bottom:none;
}
.sales-summary-list{
    display:grid;
    gap:12px;
}
.sales-summary-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}
.sales-summary-row span{color:var(--muted)}
.sales-summary-row strong{font-size:15px}
.sales-summary-row.is-total{
    margin-top:8px;
    padding-top:12px;
    border-top:1px solid var(--line);
}
.sales-summary-row.is-total strong{font-size:18px}
.sales-review-list{
    display:grid;
    gap:10px;
}
.sales-review-item{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:10px 0;
    border-bottom:1px solid var(--line);
}
.sales-review-item:last-child{border-bottom:none}
.sales-review-item strong{display:block}
.sales-review-item small{color:var(--muted)}
.sales-review-empty{
    color:var(--muted);
    font-size:13px;
}
.sales-readonly-note{
    border-left:3px solid color-mix(in srgb, var(--accent) 42%, transparent);
    padding:12px 14px;
    border-radius:0 14px 14px 0;
    background:color-mix(in srgb, var(--accent) 10%, transparent);
    color:var(--muted);
}
.sales-key-value{
    display:grid;
    gap:12px;
}
.sales-key-value .row{
    display:flex;
    justify-content:space-between;
    gap:12px;
}
.sales-key-value .row span{color:var(--muted)}
.sales-key-value .row strong{text-align:right}
.sales-final-review{
    display:grid;
    gap:14px;
}
@media (max-width: 1180px){
    .sales-item-entry-table thead{display:none}
    .sales-item-entry-table,
    .sales-item-entry-table tbody,
    .sales-item-entry-table tr,
    .sales-item-entry-table td{display:block; width:100%}
    .sales-item-entry-table tr{padding:12px 0; border-bottom:1px solid var(--line)}
    .sales-item-entry-table tbody tr:last-child{border-bottom:none}
    .sales-item-entry-table tbody td{border-bottom:none; padding:10px 14px}
}
@media (max-width: 1024px){
    .sales-context-grid,
    .sales-detail-grid{grid-template-columns:1fr}
    .sales-stepper{justify-content:flex-start}
}
@media (max-width: 760px){
    .sales-wizard-topbar,
    .sales-step-actions{flex-direction:column; align-items:flex-start}
    .sales-picker-menu{backdrop-filter:blur(14px) saturate(125%); -webkit-backdrop-filter:blur(14px) saturate(125%)}
}
</style>

<?php require __DIR__ . '/../partials/form_errors.php'; ?>

<form method="post" action="<?= e(base_url($action)) ?>" class="sales-create-wizard" data-invoice-form data-sale-wizard data-initial-step="<?= e($openStep) ?>" novalidate>
    <?= App\Core\Csrf::field() ?>

    <select name="warehouse_id" data-warehouse-select hidden>
        <option value="">Choose warehouse</option>
        <?php foreach (($warehouses ?? []) as $warehouse): ?>
            <option value="<?= e((string) $warehouse['id']) ?>" <?= selected((string) $warehouse['id'], $selectedWarehouseId) ?>><?= e($warehouse['name'] . ' (' . $warehouse['code'] . ')') ?></option>
        <?php endforeach; ?>
    </select>

    <section class="sales-wizard-shell">

        <div class="sales-step-panel" data-step-panel="warehouse">
            <div class="sales-warehouse-stage">
                <div class="sales-panel-card">
                    <div class="sales-warehouse-selector">
                        <select data-warehouse-select-proxy aria-label="Working Warehouse selector">
                            <option value="">Choose warehouse</option>
                            <?php foreach (($warehouses ?? []) as $warehouse): ?>
                                <option value="<?= e((string) $warehouse['id']) ?>" <?= selected((string) $warehouse['id'], $selectedWarehouseId) ?>><?= e($warehouse['name'] . ' (' . $warehouse['code'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="sales-warehouse-caption" data-summary-warehouse>Current: <?= e($selectedWarehouseLabel) ?></p>
                    <p class="sales-stage-status" data-warehouse-stage-message>Choose a warehouse to move automatically to the item slide.</p>
                </div>
            </div>

            <div class="sales-step-actions">
                <div class="group">
                    <a href="<?= e(base_url('/sales')) ?>" class="btn secondary">Back</a>
                </div>
                <div class="group">
                    <button type="button" class="btn" data-step-next="items">Continue</button>
                </div>
            </div>
        </div>

        <div class="sales-step-panel" data-step-panel="items">
            <div class="sales-context-grid">
                <div class="sales-context-chip">
                    <span>Working Warehouse</span>
                    <strong data-summary-warehouse-chip><?= e($selectedWarehouseLabel) ?></strong>
                </div>
                <div class="sales-context-chip">
                    <span>Rule</span>
                    <strong>Only in-stock items from the working warehouse</strong>
                </div>
                <div class="sales-context-chip">
                    <span>Flow</span>
                    <strong>Items → Pricing → Customer</strong>
                </div>
            </div>

            <div class="sales-item-board card">
                <div class="card-b">
                    <div class="sales-item-board-head">
                        <div>
                            <h3>Choose invoice items</h3>
                            <p>Search only inside the selected warehouse. Items with zero stock in this warehouse do not appear in the list.</p>
                        </div>
                        <div class="sales-item-actions" style="width:100%; justify-content:flex-end;">
                            <button type="button" class="btn secondary btn-sm" data-add-line data-mode="sale">Add Row</button>
                        </div>
                    </div>

                    <div class="sales-item-table-wrap">
                        <table class="sales-item-entry-table line-items-table" data-line-items="sale">
                            <thead>
                            <tr>
                                <th>Item Details</th>
                                <th>Stock on Hand</th>
                                <th>Quantity</th>
                                <th>Rate (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody data-lines>
                            <?php for ($i = 0; $i < $rowCount; $i++): ?>
                                <tr data-line-row>
                                    <td>
                                        <div class="sales-row-stack">
                                            <div class="sales-picker" data-product-picker>
                                                <select name="product_id[]" data-product-select class="sales-picker-hidden">
                                                    <option value="">Choose product</option>
                                                    <?php foreach ($products as $product): ?>
                                                        <?php $stockMap = $warehouseStocks[(int) $product['id']] ?? []; ?>
                                                        <option
                                                            value="<?= e((string) $product['id']) ?>"
                                                            data-name="<?= e((string) $product['name']) ?>"
                                                            data-code="<?= e((string) $product['code']) ?>"
                                                            data-base-price-aed="<?= e((string) $product['sale_price']) ?>"
                                                            data-stock="<?= e((string) $product['current_stock']) ?>"
                                                            data-stock-map="<?= e(json_encode($stockMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                                                            data-units-per-box="<?= e((string) ($product['units_per_box'] ?? 1)) ?>"
                                                            data-unit-label="<?= e((string) ($product['unit'] ?? 'Unit')) ?>"
                                                            data-item-type="<?= e((string) ($product['item_type'] ?? 'inventory')) ?>"
                                                            <?= selected((string) $product['id'], $oldProducts[$i] ?? '') ?>
                                                        >
                                                            <?= e($product['name'] . ' (' . $product['code'] . ')') ?><?= (($product['item_type'] ?? 'inventory') !== 'inventory') ? e(' — ' . (($product['item_type'] ?? '') === 'service' ? 'Service' : 'Non-Inventory')) : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" value="" class="sales-picker-input" data-product-search placeholder="Type product name or code inside selected warehouse">
                                                <div class="sales-picker-menu" data-product-results data-picker-menu></div>
                                            </div>
                                            <div class="sales-row-meta" data-product-meta>
                                                <span class="sales-inline-muted">Choose an item to see SKU, stock, unit and pack explanation.</span>
                                            </div>
                                            <div class="sales-inline-muted" data-row-rule>Pricing Unit was removed from the table. This line now explains the sell unit and pack size directly inside the item details.</div>
                                            <input type="hidden" name="pricing_unit[]" value="<?= e((string) ($oldPricingUnit[$i] ?? 'unit')) ?>" data-pricing-unit-hidden>
                                            <input type="hidden" name="units_per_box[]" value="<?= e((string) ($oldUnitsPerBox[$i] ?? '1')) ?>" data-units-per-box-hidden>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sales-stock-box">
                                            <span>Selected warehouse</span>
                                            <strong data-stock-badge>0.00</strong>
                                            <input type="text" value="" data-stock-display readonly placeholder="0.00" hidden>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sales-inline-controls">
                                            <input type="number" step="0.01" min="0.01" name="qty[]" value="<?= e((string) ($oldQty[$i] ?? '1')) ?>" data-qty required>
                                            <span class="sales-inline-muted" data-qty-hint>Invoice quantity in base sell unit</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sales-inline-controls">
                                            <input type="number" step="0.01" min="0" name="unit_price[]" value="<?= e((string) ($oldPrice[$i] ?? '0')) ?>" data-price required>
                                            <input type="hidden" name="unit_price_aed_shadow[]" value="<?= e((string) ($oldPriceAed[$i] ?? '')) ?>" data-price-aed>
                                            <span class="sales-inline-muted">Editable sell rate</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sales-amount-box">
                                            <span>Line total</span>
                                            <strong data-line-total-box>0.00</strong>
                                            <input type="text" value="0.00" data-line-total readonly hidden>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn ghost btn-sm" data-remove-line>Remove</button>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sales-warehouse-rule" data-warehouse-items-hint>
                        Working Warehouse rule: only items with available stock in the selected warehouse can be added to this sale.
                    </div>

                    <div class="sales-hidden-calc" aria-hidden="true">
                        <input type="number" step="0.01" min="0" name="discount_amount" value="<?= e($oldDiscount) ?>" data-discount>
                        <input type="hidden" name="discount_amount_aed_shadow" value="<?= e($oldDiscountAed) ?>" data-discount-aed>
                        <div data-subtotal>0.00</div>
                        <div data-discount-view>0.00</div>
                        <div data-final-total>0.00</div>
                        <div data-subtotal-aed>0.00</div>
                        <div data-discount-view-aed>0.00</div>
                        <div data-final-total-aed>0.00</div>
                    </div>
                </div>
            </div>

            <div class="sales-step-actions">
                <div class="group">
                    <button type="button" class="btn secondary" data-step-back="warehouse">Back</button>
                </div>
                <div class="group">
                    <a href="<?= e(base_url('/sales')) ?>" class="btn secondary">Cancel</a>
                    <button type="button" class="btn" data-step-next="pricing">Next</button>
                </div>
            </div>
        </div>

        <div class="sales-step-panel" data-step-panel="pricing">
            <div class="sales-detail-grid">
                <div class="sales-detail-panel">
                    <h3>Pricing table &amp; invoice note</h3>
                    <p>Review invoice currency, date, discount, and full invoice notes before the customer is chosen.</p>

                    <div class="grid-2">
                        <div class="field">
                            <label>Invoice Date</label>
                            <input type="date" name="invoice_date" value="<?= e((string) old('invoice_date', date('Y-m-d'))) ?>" data-invoice-date required>
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
                            <small>Product prices stay AED-based in the master list and convert into the selected invoice currency.</small>
                        </div>
                        <div class="field">
                            <label>Discount Amount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</label>
                            <input type="number" step="0.01" min="0" value="<?= e($oldDiscount) ?>" data-discount-visible>
                            <small>This value feeds the invoice totals below.</small>
                        </div>
                        <div class="field">
                            <label>Exchange Rate</label>
                            <div class="sales-readonly-note">
                                1 <span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span> = AED <strong data-invoice-rate-view><?= e(number_format((float) ($defaultCurrency['rate_to_aed'] ?? 1), 4)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="field" style="margin-top:12px;">
                        <label>Invoice Note / Description</label>
                        <textarea name="note" data-invoice-note placeholder="Optional note for the invoice or internal reference"><?= e((string) old('note')) ?></textarea>
                    </div>
                </div>

                <div class="sales-detail-panel">
                    <h3>Pricing review</h3>
                    <p>This table is generated from the item slide and updates live.</p>
                    <div class="table-wrap">
                        <table class="sales-pricing-preview-table">
                            <thead>
                            <tr>
                                <th>Item</th>
                                <th class="is-num">Qty</th>
                                <th class="is-num">Rate</th>
                                <th class="is-num">Amount</th>
                            </tr>
                            </thead>
                            <tbody data-pricing-review-body>
                                <tr><td colspan="4" class="sales-review-empty">No items selected yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="sales-summary-list" style="margin-top:14px;">
                        <div class="sales-summary-row"><span>Subtotal (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-step-subtotal>0.00</strong></div>
                        <div class="sales-summary-row"><span>Discount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-step-discount>0.00</strong></div>
                        <div class="sales-summary-row is-total"><span>Final Amount (<span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span>)</span><strong data-step-final>0.00</strong></div>
                        <div class="sales-summary-row"><span>Subtotal AED</span><strong data-step-subtotal-aed>0.00</strong></div>
                        <div class="sales-summary-row"><span>Discount AED</span><strong data-step-discount-aed>0.00</strong></div>
                        <div class="sales-summary-row is-total"><span>Final AED</span><strong data-step-final-aed>0.00</strong></div>
                    </div>
                </div>
            </div>

            <div class="sales-step-actions">
                <div class="group">
                    <button type="button" class="btn secondary" data-step-back="items">Back</button>
                </div>
                <div class="group">
                    <a href="<?= e(base_url('/sales')) ?>" class="btn secondary">Cancel</a>
                    <button type="button" class="btn" data-step-next="customer">Next</button>
                </div>
            </div>
        </div>

        <div class="sales-step-panel" data-step-panel="customer">
            <div class="sales-detail-grid">
                <div class="sales-detail-panel">
                    <h3>Customer &amp; finalization</h3>
                    <p>Select the customer and confirm the final invoice context before saving.</p>

                    <div class="field">
                        <label>Customer</label>
                        <div class="sales-picker" data-customer-picker>
                            <select name="customer_id" data-customer-select class="sales-picker-hidden">
                                <option value="">Choose customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= e((string) $customer['id']) ?>" <?= selected((string) $customer['id'], old('customer_id')) ?>><?= e($customer['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" class="sales-picker-input" data-customer-search value="<?= e($selectedCustomerName !== 'Choose customer' ? $selectedCustomerName : '') ?>" placeholder="Type customer name to search the list">
                            <div class="sales-picker-menu" data-customer-results data-picker-menu></div>
                        </div>
                        <div class="sales-row-meta" data-customer-meta>
                            <span class="sales-inline-muted"><?= e($selectedCustomerName !== 'Choose customer' ? ('Selected customer: ' . $selectedCustomerName) : 'Start typing the customer name to search and select from the list.') ?></span>
                        </div>
                    </div>

                    <div class="sales-readonly-note" style="margin-top:12px;">
                        Saving this invoice will keep the Working Warehouse fixed to <strong data-summary-warehouse-final><?= e($selectedWarehouseLabel) ?></strong>.
                    </div>

                    <div class="sales-key-value" style="margin-top:16px;">
                        <div class="row"><span>Working Warehouse</span><strong data-summary-warehouse-final><?= e($selectedWarehouseLabel) ?></strong></div>
                        <div class="row"><span>Invoice Date</span><strong data-final-invoice-date><?= e((string) old('invoice_date', date('Y-m-d'))) ?></strong></div>
                        <div class="row"><span>Invoice Currency</span><strong><span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span></strong></div>
                    </div>
                </div>

                <div class="sales-detail-panel">
                    <h3>Final review before save</h3>
                    <div class="sales-final-review">
                        <div class="sales-review-list" data-final-review-list></div>
                        <div class="sales-summary-list">
                            <div class="sales-summary-row is-total"><span>Final amount</span><strong><span data-final-review-total>0.00</span> <span data-invoice-currency-code><?= e((string) ($defaultCurrency['code'] ?? 'AED')) ?></span></strong></div>
                            <div class="sales-summary-row"><span>Final AED</span><strong data-final-review-total-aed>0.00</strong></div>
                        </div>
                        <div class="sales-readonly-note" data-final-note-preview>
                            No invoice note added.
                        </div>
                    </div>
                </div>
            </div>

            <div class="sales-step-actions">
                <div class="group">
                    <button type="button" class="btn secondary" data-step-back="pricing">Back</button>
                </div>
                <div class="group">
                    <a href="<?= e(base_url('/sales')) ?>" class="btn secondary">Cancel</a>
                    <button type="submit" class="btn">Save Sale</button>
                </div>
            </div>
        </div>
    </section>
</form>

<script>
(function(){
    const form = document.querySelector('[data-sale-wizard]');
    if (!form) return;

    const steps = ['warehouse', 'items', 'pricing', 'customer'];
    const panels = Array.from(form.querySelectorAll('[data-step-panel]'));
    const pills = Array.from(form.querySelectorAll('[data-step-pill]'));
    const warehouseSelect = form.querySelector('[data-warehouse-select]');
    const warehouseProxySelect = form.querySelector('[data-warehouse-select-proxy]');
    const currencySelect = form.querySelector('[data-invoice-currency-select]');
    const invoiceDateInput = form.querySelector('[data-invoice-date]');
    const customerSelect = form.querySelector('[data-customer-select]');
    const customerSearchInput = form.querySelector('[data-customer-search]');
    const customerResultsMenu = form.querySelector('[data-customer-results]');
    const customerMeta = form.querySelector('[data-customer-meta]');
    const discountHiddenInput = form.querySelector('[data-discount]');
    const discountVisibleInput = form.querySelector('[data-discount-visible]');
    const noteInput = form.querySelector('[data-invoice-note]');
    const warehouseStageMessage = form.querySelector('[data-warehouse-stage-message]');
    const warehouseItemsHint = form.querySelector('[data-warehouse-items-hint]');
    const pricingReviewBody = form.querySelector('[data-pricing-review-body]');
    const finalReviewList = form.querySelector('[data-final-review-list]');
    const finalNotePreview = form.querySelector('[data-final-note-preview]');
    const lineTable = form.querySelector('.line-items-table');
    const lineBody = lineTable ? lineTable.querySelector('[data-lines]') : null;
    const initialStep = steps.includes(form.dataset.initialStep) ? form.dataset.initialStep : 'warehouse';
    const activeMenuClass = 'is-open';

    const number = (value) => Number(value || 0).toFixed(2);
    const currentStep = () => form.dataset.currentStep || 'warehouse';
    const selectedWarehouseId = () => Number(warehouseSelect?.value || 0);
    const selectedWarehouseLabel = () => warehouseSelect?.options[warehouseSelect.selectedIndex]?.textContent?.trim() || 'Choose warehouse';
    const invoiceCode = () => currencySelect?.options[currencySelect.selectedIndex]?.dataset.code || 'AED';
    const invoiceRate = () => Math.max(Number(currencySelect?.options[currencySelect.selectedIndex]?.dataset.rate || 1), 0.00000001);

    const stockClass = (stock) => {
        if (stock === null) return 'good';
        if (stock <= 0) return 'bad';
        if (stock < 5) return 'low';
        return 'good';
    };

    const stockForOption = (option) => {
        if (!option) return 0;
        if ((option.dataset.itemType || 'inventory') !== 'inventory') return null;
        const warehouseId = selectedWarehouseId();
        if (warehouseId <= 0) return 0;
        const stockMapRaw = option.dataset.stockMap || '';
        if (stockMapRaw) {
            try {
                const parsed = JSON.parse(stockMapRaw);
                if (parsed && Object.prototype.hasOwnProperty.call(parsed, String(warehouseId))) {
                    return Number(parsed[String(warehouseId)] || 0);
                }
            } catch (error) {
                return 0;
            }
        }
        return 0;
    };

    const optionIsEligible = (option) => {
        if (!option || !option.value) return false;
        if ((option.dataset.itemType || 'inventory') !== 'inventory') return true;
        return stockForOption(option) > 0;
    };

    const syncWarehouseLabels = () => {
        const label = selectedWarehouseLabel();
        form.querySelectorAll('[data-summary-warehouse], [data-summary-warehouse-chip], [data-summary-warehouse-final]').forEach((node) => {
            node.textContent = node.dataset.summaryWarehouseFinal !== undefined ? label : ('Current: ' + label);
        });
        form.querySelectorAll('[data-summary-warehouse-chip]').forEach((node) => {
            node.textContent = label;
        });
        form.querySelectorAll('[data-summary-warehouse-final]').forEach((node) => {
            node.textContent = label;
        });
        if (warehouseStageMessage) {
            warehouseStageMessage.innerHTML = selectedWarehouseId() > 0
                ? '<strong>Ready:</strong> ' + label + ' is active. Only this warehouse stock can be sold.'
                : 'Choose a warehouse to move automatically to the item slide.';
        }
    };

    const clearRow = (row) => {
        const select = row.querySelector('[data-product-select]');
        const search = row.querySelector('[data-product-search]');
        const qty = row.querySelector('[data-qty]');
        const price = row.querySelector('[data-price]');
        const priceAed = row.querySelector('[data-price-aed]');
        const lineTotal = row.querySelector('[data-line-total]');
        const pricingUnitHidden = row.querySelector('[data-pricing-unit-hidden]');
        const unitsPerBoxHidden = row.querySelector('[data-units-per-box-hidden]');
        if (select) select.value = '';
        if (search) search.value = '';
        if (qty) qty.value = '1';
        if (price) {
            price.value = '0';
            delete price.dataset.userEdited;
        }
        if (priceAed) priceAed.value = '';
        if (lineTotal) lineTotal.value = '0.00';
        if (pricingUnitHidden) pricingUnitHidden.value = 'unit';
        if (unitsPerBoxHidden) unitsPerBoxHidden.value = '1';
    };

    const syncDiscountVisibleFromHidden = () => {
        if (!discountVisibleInput || !discountHiddenInput) return;
        if (document.activeElement === discountVisibleInput) return;
        discountVisibleInput.value = String(discountHiddenInput.value || '0');
    };

    const pushDiscountToHidden = () => {
        if (!discountVisibleInput || !discountHiddenInput) return;
        discountHiddenInput.value = String(Number(discountVisibleInput.value || 0));
        discountHiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const syncVisibleTotals = () => {
        const readText = (selector) => form.querySelector(selector)?.textContent || '0.00';
        const hiddenDiscount = form.querySelector('[data-discount-view]')?.textContent || '0.00';
        const hiddenSubtotal = form.querySelector('[data-subtotal]')?.textContent || '0.00';
        const hiddenFinal = form.querySelector('[data-final-total]')?.textContent || '0.00';
        const hiddenSubtotalAed = form.querySelector('[data-subtotal-aed]')?.textContent || '0.00';
        const hiddenDiscountAed = form.querySelector('[data-discount-view-aed]')?.textContent || '0.00';
        const hiddenFinalAed = form.querySelector('[data-final-total-aed]')?.textContent || '0.00';

        const map = {
            '[data-step-subtotal]': hiddenSubtotal,
            '[data-step-discount]': hiddenDiscount,
            '[data-step-final]': hiddenFinal,
            '[data-step-subtotal-aed]': hiddenSubtotalAed,
            '[data-step-discount-aed]': hiddenDiscountAed,
            '[data-step-final-aed]': hiddenFinalAed,
            '[data-final-review-total]': hiddenFinal,
            '[data-final-review-total-aed]': hiddenFinalAed,
        };

        Object.entries(map).forEach(([selector, value]) => {
            form.querySelectorAll(selector).forEach((node) => {
                node.textContent = value;
            });
        });

        form.querySelectorAll('[data-final-invoice-date]').forEach((node) => {
            node.textContent = invoiceDateInput?.value || '';
        });

        syncDiscountVisibleFromHidden();
    };

    const displayProductLabel = (option) => {
        if (!option || !option.value) return '';
        const name = option.dataset.name || option.textContent || '';
        return name.trim();
    };

    const syncRowChrome = (row) => {
        const select = row.querySelector('[data-product-select]');
        const option = select?.options[select.selectedIndex];
        const search = row.querySelector('[data-product-search]');
        const meta = row.querySelector('[data-product-meta]');
        const stockBadge = row.querySelector('[data-stock-badge]');
        const stockInput = row.querySelector('[data-stock-display]');
        const amountInput = row.querySelector('[data-line-total]');
        const amountBox = row.querySelector('[data-line-total-box]');
        const qtyHint = row.querySelector('[data-qty-hint]');
        const rowRule = row.querySelector('[data-row-rule]');
        const unitsPerBoxHidden = row.querySelector('[data-units-per-box-hidden]');
        const pricingUnitHidden = row.querySelector('[data-pricing-unit-hidden]');
        const stock = stockForOption(option);
        const unitsPerBox = Number(option?.dataset.unitsPerBox || unitsPerBoxHidden?.value || 1);
        const unitLabel = option?.dataset.unitLabel || 'unit';
        const itemType = (option?.dataset.itemType || 'inventory');

        if (pricingUnitHidden) pricingUnitHidden.value = 'unit';
        if (unitsPerBoxHidden) unitsPerBoxHidden.value = unitsPerBox > 0 ? String(unitsPerBox) : '1';

        if (search) {
            search.value = option && option.value ? displayProductLabel(option) : '';
        }

        if (stockBadge) {
            stockBadge.textContent = stock === null ? 'N/A' : number(stock);
            stockBadge.className = 'sales-tag ' + stockClass(stock);
        }
        if (stockInput) {
            stockInput.value = stock === null ? 'N/A' : number(stock);
        }
        if (amountInput && amountBox) {
            amountBox.textContent = amountInput.value || '0.00';
        }
        if (qtyHint) {
            qtyHint.textContent = itemType === 'inventory'
                ? ('Invoice quantity in ' + unitLabel)
                : 'Invoice quantity';
        }
        if (rowRule) {
            if (!option || !option.value) {
                rowRule.textContent = 'Pricing Unit was removed from the table. This line now explains the sell unit and pack size directly inside the item details.';
            } else if (itemType !== 'inventory') {
                rowRule.textContent = 'This item does not use warehouse stock tracking.';
            } else {
                rowRule.textContent = unitsPerBox > 1
                    ? ('Sell unit: ' + unitLabel + ' · Pack info: 1 box = ' + number(unitsPerBox).replace(/\.00$/, '') + ' ' + unitLabel)
                    : ('Sell unit: ' + unitLabel + ' · No separate box conversion on this sales screen.');
            }
        }
        if (meta) {
            if (option && option.value) {
                const typeLabel = itemType.replace('-', ' ');
                const tags = [
                    '<span class="sales-tag">SKU ' + (option.dataset.code || '-') + '</span>',
                    '<span class="sales-tag">' + typeLabel + '</span>',
                    '<span class="sales-tag">Sell unit: ' + unitLabel + '</span>'
                ];
                if (itemType === 'inventory' && unitsPerBox > 1) {
                    tags.push('<span class="sales-tag">Pack: 1 box = ' + number(unitsPerBox).replace(/\.00$/, '') + ' ' + unitLabel + '</span>');
                }
                tags.push('<span class="sales-inline-muted">' + (stock === null ? 'No stock tracking' : ('Available in working warehouse: ' + number(stock))) + '</span>');
                meta.innerHTML = tags.join('');
            } else {
                meta.innerHTML = '<span class="sales-inline-muted">Choose an item to see SKU, stock, unit and pack explanation.</span>';
            }
        }
    };

    const closeMenus = (exceptContainer = null) => {
        form.querySelectorAll('[data-picker-menu]').forEach((menu) => {
            if (exceptContainer && exceptContainer.contains(menu)) return;
            menu.classList.remove(activeMenuClass);
        });
    };

    const buildMenu = (row, query = '') => {
        const select = row.querySelector('[data-product-select]');
        const menu = row.querySelector('[data-product-results]');
        if (!select || !menu) return;
        if (selectedWarehouseId() <= 0) {
            menu.innerHTML = '<div class="sales-inline-muted" style="padding:10px 12px;">Choose the Working Warehouse first.</div>';
            return;
        }

        const q = query.trim().toLowerCase();
        const options = Array.from(select.options).filter((option) => option.value && optionIsEligible(option));
        const matches = options.filter((option) => {
            const haystack = [option.dataset.name, option.dataset.code, option.textContent].join(' ').toLowerCase();
            return !q || haystack.includes(q);
        }).slice(0, 10);

        if (!matches.length) {
            menu.innerHTML = '<div class="sales-inline-muted" style="padding:10px 12px;">No matching in-stock items found in the selected warehouse.</div>';
            return;
        }

        menu.innerHTML = matches.map((option, index) => {
            const stock = stockForOption(option);
            const price = (Number(option.dataset.basePriceAed || 0) / invoiceRate()).toFixed(2);
            const type = (option.dataset.itemType || 'inventory').replace('-', ' ');
            const stockLabel = stock === null ? 'No stock tracking' : (number(stock) + ' available');
            return '<button type="button" class="sales-picker-option' + (index === 0 ? ' is-active' : '') + '" data-product-option="' + option.value + '">'
                + '<div class="sales-picker-line"><strong>' + (option.dataset.name || '') + '</strong><span class="sales-picker-stock ' + ('is-' + stockClass(stock)) + '">' + stockLabel + '</span></div>'
                + '<div class="sales-picker-line"><span class="sales-picker-sub">SKU: ' + (option.dataset.code || '-') + ' · ' + type + '</span><span class="sales-picker-sub">Rate: ' + invoiceCode() + ' ' + price + '</span></div>'
                + '</button>';
        }).join('');
    };

    const openMenu = (row) => {
        const menu = row.querySelector('[data-product-results]');
        if (!menu) return;
        buildMenu(row, row.querySelector('[data-product-search]')?.value || '');
        menu.classList.add(activeMenuClass);
    };

    const setProductValue = (row, value) => {
        const select = row.querySelector('[data-product-select]');
        if (!select) return;
        const option = Array.from(select.options).find((entry) => entry.value === value);
        if (option && !optionIsEligible(option)) {
            return;
        }
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        window.setTimeout(() => {
            syncRowChrome(row);
            refreshReviewTables();
        }, 0);
        closeMenus();
    };

    const ensureRowReady = (row) => {
        if (!row || row.dataset.itemRowReady === '1') return;
        row.dataset.itemRowReady = '1';
        syncRowChrome(row);
        const search = row.querySelector('[data-product-search]');
        if (search) {
            search.addEventListener('focus', () => {
                closeMenus(row);
                openMenu(row);
            });
            search.addEventListener('input', () => {
                closeMenus(row);
                openMenu(row);
            });
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeMenus();
            });
        }
    };

    const pruneRowsForWarehouse = () => {
        let cleared = 0;
        form.querySelectorAll('[data-line-row]').forEach((row) => {
            const select = row.querySelector('[data-product-select]');
            const option = select?.options[select.selectedIndex];
            if (option && option.value && !optionIsEligible(option)) {
                clearRow(row);
                cleared += 1;
            }
            window.setTimeout(() => syncRowChrome(row), 0);
        });
        return cleared;
    };

    const refreshWarehouseHint = (cleared = 0) => {
        if (!warehouseItemsHint) return;
        if (selectedWarehouseId() <= 0) {
            warehouseItemsHint.textContent = 'Choose a Working Warehouse first. Item search stays locked to that warehouse.';
            return;
        }
        const sampleSelect = form.querySelector('[data-product-select]');
        const eligibleCount = sampleSelect
            ? Array.from(sampleSelect.options).filter((option) => option.value && optionIsEligible(option)).length
            : 0;
        warehouseItemsHint.textContent = cleared > 0
            ? (cleared + ' selected row(s) were cleared because they are not available in ' + selectedWarehouseLabel() + '. Available items now: ' + eligibleCount + '.')
            : ('Working Warehouse rule active for ' + selectedWarehouseLabel() + '. Available items now: ' + eligibleCount + '.');
    };

    const selectedCustomerLabel = () => customerSelect?.options[customerSelect.selectedIndex]?.textContent?.trim() || 'Choose customer';

    const syncCustomerPicker = () => {
        const label = selectedCustomerLabel();
        if (customerSearchInput && document.activeElement !== customerSearchInput) {
            customerSearchInput.value = customerSelect?.value ? label : '';
        }
        if (customerMeta) {
            customerMeta.innerHTML = customerSelect?.value
                ? '<span class="sales-tag">Selected customer</span><span class="sales-inline-muted">' + label + '</span>'
                : '<span class="sales-inline-muted">Start typing the customer name to search and select from the list.</span>';
        }
    };

    const buildCustomerMenu = (query = '') => {
        if (!customerResultsMenu || !customerSelect) return;
        const q = query.trim().toLowerCase();
        const options = Array.from(customerSelect.options).filter((option) => option.value);
        const matches = options.filter((option) => {
            const haystack = [option.textContent].join(' ').toLowerCase();
            return !q || haystack.includes(q);
        }).slice(0, 10);

        if (!matches.length) {
            customerResultsMenu.innerHTML = '<div class="sales-inline-muted" style="padding:10px 12px;">No customer matched your search.</div>';
            return;
        }

        customerResultsMenu.innerHTML = matches.map((option, index) => {
            return '<button type="button" class="sales-picker-option' + (index === 0 ? ' is-active' : '') + '" data-customer-option="' + option.value + '">'
                + '<div class="sales-picker-line"><strong>' + (option.textContent || '') + '</strong></div>'
                + '<div class="sales-picker-line"><span class="sales-picker-sub">Select this customer for the invoice</span></div>'
                + '</button>';
        }).join('');
    };

    const openCustomerMenu = () => {
        if (!customerResultsMenu) return;
        buildCustomerMenu(customerSearchInput?.value || '');
        customerResultsMenu.classList.add(activeMenuClass);
    };

    const setCustomerValue = (value) => {
        if (!customerSelect) return;
        const option = Array.from(customerSelect.options).find((entry) => entry.value === value);
        if (!option) return;
        customerSelect.value = value;
        customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        window.setTimeout(() => {
            syncCustomerPicker();
            refreshReviewTables();
        }, 0);
        closeMenus();
    };

    const refreshReviewTables = () => {
        const rows = Array.from(form.querySelectorAll('[data-line-row]'));
        const items = rows.map((row) => {
            const select = row.querySelector('[data-product-select]');
            const option = select?.options[select.selectedIndex];
            if (!option || !option.value) return null;
            return {
                name: option.dataset.name || option.textContent,
                code: option.dataset.code || '-',
                qty: Number(row.querySelector('[data-qty]')?.value || 0),
                rate: Number(row.querySelector('[data-price]')?.value || 0),
                amount: row.querySelector('[data-line-total]')?.value || '0.00',
                stock: stockForOption(option),
                unitLabel: option.dataset.unitLabel || 'unit'
            };
        }).filter(Boolean);

        if (pricingReviewBody) {
            if (!items.length) {
                pricingReviewBody.innerHTML = '<tr><td colspan="4" class="sales-review-empty">No items selected yet.</td></tr>';
            } else {
                pricingReviewBody.innerHTML = items.map((item) => '<tr>'
                    + '<td><strong>' + item.name + '</strong><br><small>SKU ' + item.code + '</small></td>'
                    + '<td class="is-num">' + item.qty.toFixed(2).replace(/\.00$/, '') + '</td>'
                    + '<td class="is-num">' + item.rate.toFixed(2) + '</td>'
                    + '<td class="is-num">' + item.amount + '</td>'
                    + '</tr>').join('');
            }
        }

        if (finalReviewList) {
            if (!items.length) {
                finalReviewList.innerHTML = '<div class="sales-review-empty">No items selected yet.</div>';
            } else {
                finalReviewList.innerHTML = items.map((item) => '<div class="sales-review-item"><div><strong>' + item.name + '</strong><small>SKU ' + item.code + ' · Qty ' + item.qty.toFixed(2).replace(/\.00$/, '') + ' ' + item.unitLabel + '</small></div><strong>' + item.amount + ' ' + invoiceCode() + '</strong></div>').join('');
            }
        }

        if (finalNotePreview) {
            const note = (noteInput?.value || '').trim();
            finalNotePreview.textContent = note !== '' ? note : 'No invoice note added.';
        }

        syncVisibleTotals();
    };

    const activateStep = (step) => {
        const targetStep = steps.includes(step) ? step : 'warehouse';
        form.dataset.currentStep = targetStep;
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.stepPanel === targetStep));
        const currentIndex = steps.indexOf(targetStep);
        pills.forEach((pill) => {
            const pillIndex = steps.indexOf(pill.dataset.stepPill || 'warehouse');
            pill.classList.toggle('is-active', pillIndex === currentIndex);
            pill.classList.toggle('is-complete', pillIndex < currentIndex);
        });
        refreshReviewTables();
    };

    const validateWarehouseStep = () => {
        if (selectedWarehouseId() > 0) return true;
        window.alert('Please choose the Working Warehouse first.');
        warehouseProxySelect?.focus();
        activateStep('warehouse');
        return false;
    };

    const validItemRows = () => Array.from(form.querySelectorAll('[data-line-row]')).filter((row) => {
        const select = row.querySelector('[data-product-select]');
        const option = select?.options[select.selectedIndex];
        const qty = Number(row.querySelector('[data-qty]')?.value || 0);
        const price = Number(row.querySelector('[data-price]')?.value || 0);
        return !!option?.value && qty > 0 && price >= 0;
    });

    const validateItemStep = () => {
        if (!validateWarehouseStep()) return false;
        const rows = validItemRows();
        if (!rows.length) {
            window.alert('Add at least one item from the selected warehouse before continuing.');
            form.querySelector('[data-product-search]')?.focus();
            activateStep('items');
            return false;
        }
        for (const row of rows) {
            const select = row.querySelector('[data-product-select]');
            const option = select?.options[select.selectedIndex];
            const qty = Number(row.querySelector('[data-qty]')?.value || 0);
            const stock = stockForOption(option);
            if (option && !optionIsEligible(option)) {
                window.alert('The selected item is not available in the current Working Warehouse: ' + (option.dataset.name || 'Item'));
                activateStep('items');
                return false;
            }
            if ((option?.dataset.itemType || 'inventory') === 'inventory' && stock !== null && qty - stock > 0.00001) {
                window.alert('Quantity exceeds available stock in the Working Warehouse for: ' + (option.dataset.name || 'Item'));
                activateStep('items');
                return false;
            }
        }
        return true;
    };

    const validatePricingStep = () => {
        if (!validateItemStep()) return false;
        if (!invoiceDateInput?.value) {
            window.alert('Please choose the invoice date.');
            activateStep('pricing');
            invoiceDateInput?.focus();
            return false;
        }
        if (!currencySelect?.value) {
            window.alert('Please choose the invoice currency.');
            activateStep('pricing');
            currencySelect?.focus();
            return false;
        }
        return true;
    };

    const validateCustomerStep = () => {
        if (!validatePricingStep()) return false;
        if (!customerSelect?.value) {
            window.alert('Please choose the customer before saving the invoice.');
            activateStep('customer');
            customerSearchInput?.focus();
            return false;
        }
        return true;
    };

    const goToStep = (step) => {
        if (step === 'items' && !validateWarehouseStep()) return;
        if (step === 'pricing' && !validateItemStep()) return;
        if (step === 'customer' && !validatePricingStep()) return;
        activateStep(step);
    };

    form.querySelectorAll('[data-line-row]').forEach(ensureRowReady);
    if (customerSearchInput) {
        customerSearchInput.addEventListener('focus', () => {
            closeMenus(customerSearchInput.closest('[data-customer-picker]'));
            openCustomerMenu();
        });
        customerSearchInput.addEventListener('input', () => {
            closeMenus(customerSearchInput.closest('[data-customer-picker]'));
            openCustomerMenu();
        });
        customerSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMenus();
        });
    }
    if (lineBody && window.MutationObserver) {
        new MutationObserver(() => {
            form.querySelectorAll('[data-line-row]').forEach(ensureRowReady);
            refreshReviewTables();
        }).observe(lineBody, { childList: true, subtree: true });
    }

    form.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const row = target.closest('[data-line-row]');
        if (row && target.matches('[data-product-select], [data-qty], [data-price]')) {
            window.setTimeout(() => {
                syncRowChrome(row);
                refreshReviewTables();
            }, 0);
        }

        if (target === warehouseProxySelect) {
            if (warehouseSelect) {
                warehouseSelect.value = warehouseProxySelect.value || '';
                warehouseSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncWarehouseLabels();
            const cleared = pruneRowsForWarehouse();
            refreshWarehouseHint(cleared);
            refreshReviewTables();
            if (warehouseProxySelect.value) {
                window.setTimeout(() => {
                    activateStep('items');
                    form.querySelector('[data-product-search]')?.focus();
                }, 120);
            }
            return;
        }

        if (target === warehouseSelect) {
            if (warehouseProxySelect && warehouseProxySelect.value !== warehouseSelect.value) {
                warehouseProxySelect.value = warehouseSelect.value;
            }
            syncWarehouseLabels();
            const cleared = pruneRowsForWarehouse();
            refreshWarehouseHint(cleared);
            refreshReviewTables();
            return;
        }

        if (target === currencySelect) {
            window.setTimeout(refreshReviewTables, 0);
            return;
        }

        if (target === customerSelect) {
            syncCustomerPicker();
            refreshReviewTables();
            return;
        }

        if (target === invoiceDateInput || target === noteInput) {
            refreshReviewTables();
        }
    });

    form.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const row = target.closest('[data-line-row]');
        if (row && target.matches('[data-qty], [data-price]')) {
            window.setTimeout(() => {
                syncRowChrome(row);
                refreshReviewTables();
            }, 0);
        }

        if (target === discountVisibleInput) {
            pushDiscountToHidden();
            window.setTimeout(refreshReviewTables, 0);
            return;
        }

        if (target === noteInput) {
            refreshReviewTables();
        }
    });

    form.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const productOptionButton = target.closest('[data-product-option]');
        if (productOptionButton instanceof HTMLElement) {
            const row = productOptionButton.closest('[data-line-row]');
            if (!row) return;
            setProductValue(row, productOptionButton.getAttribute('data-product-option') || '');
            row.querySelector('[data-product-search]')?.blur();
            return;
        }

        const customerOptionButton = target.closest('[data-customer-option]');
        if (customerOptionButton instanceof HTMLElement) {
            setCustomerValue(customerOptionButton.getAttribute('data-customer-option') || '');
            customerSearchInput?.blur();
            return;
        }

        if (target.matches('[data-step-next]')) {
            const step = target.getAttribute('data-step-next') || 'warehouse';
            if (step === 'items' && validateWarehouseStep()) activateStep('items');
            if (step === 'pricing') goToStep('pricing');
            if (step === 'customer') goToStep('customer');
            return;
        }

        if (target.matches('[data-step-back]')) {
            const step = target.getAttribute('data-step-back') || 'warehouse';
            activateStep(step);
            return;
        }

        if (!target.closest('[data-product-picker]') && !target.closest('[data-customer-picker]')) {
            closeMenus();
        }
    });

    form.addEventListener('submit', (event) => {
        // Native browser validation can block submit on visually-hidden picker selects
        // before this wizard gets a chance to clean empty rows. The wizard owns validation.
        pushDiscountToHidden();
        if (!validateCustomerStep()) {
            event.preventDefault();
            return;
        }
        form.querySelectorAll('[data-line-row]').forEach((row) => {
            const select = row.querySelector('[data-product-select]');
            const hasProduct = !!select?.value;
            row.querySelectorAll('select, input, textarea').forEach((field) => {
                if (!(field instanceof HTMLElement)) return;
                if (field === warehouseSelect || field === warehouseProxySelect || field === currencySelect || field === customerSelect || field === invoiceDateInput || field === noteInput || field === discountVisibleInput || field === discountHiddenInput) return;
                if (field.getAttribute('name') === 'warehouse_id' || field.getAttribute('name') === 'customer_id' || field.getAttribute('name') === 'invoice_date' || field.getAttribute('name') === 'currency_id' || field.getAttribute('name') === 'note' || field.getAttribute('name') === 'discount_amount' || field.getAttribute('name') === 'discount_amount_aed_shadow') return;
                if (field.closest('[data-line-row]') !== row) return;
                if (!hasProduct) {
                    field.disabled = true;
                    if (field instanceof HTMLInputElement && field.hasAttribute('required')) field.removeAttribute('required');
                    if (field instanceof HTMLSelectElement && field.hasAttribute('required')) field.removeAttribute('required');
                } else {
                    field.disabled = false;
                }
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof HTMLElement)) return;
        if (!form.contains(event.target)) closeMenus();
    });

    syncWarehouseLabels();
    syncCustomerPicker();
    const prunedOnLoad = selectedWarehouseId() > 0 ? pruneRowsForWarehouse() : 0;
    refreshWarehouseHint(prunedOnLoad);
    syncDiscountVisibleFromHidden();
    const safeInitialStep = selectedWarehouseId() > 0 ? initialStep : 'warehouse';
    activateStep(safeInitialStep);
    window.setTimeout(() => {
        form.querySelectorAll('[data-line-row]').forEach((row) => syncRowChrome(row));
        refreshReviewTables();
    }, 0);
})();
</script>
