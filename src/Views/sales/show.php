<?php
$paymentStatus = (string) ($sale['payment_status'] ?? 'unpaid');
$paymentBadge = $paymentStatus === 'paid' ? 'green' : ($paymentStatus === 'partial' ? 'orange' : '');
$matchingBankAccounts = $matchingBankAccounts ?? [];
$receipts = $receipts ?? [];
?>
<section class="page-head invoice-head">
    <div>
        <h1>Sales Invoice</h1>
        <small><?= e($sale['invoice_no']) ?> • <?= e(date_display($sale['invoice_date'])) ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/sales')) ?>" class="btn secondary">Back</a>
        <?php if (($sale['can_receive_payment'] ?? false) === true): ?>
            <a href="<?= e(base_url('/sales/receipts/create?sale_id=' . $sale['id'])) ?>" class="btn secondary">Receive Payment</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/documents/inventory/issue?sale_id=' . $sale['id'])) ?>" class="btn secondary" target="_blank" rel="noopener">Issue Slip</a>
        <a href="<?= e(base_url('/documents/sales/invoice?id=' . $sale['id'])) ?>" class="btn" target="_blank" rel="noopener">Print / PDF</a>
    </div>
</section>

<section class="invoice-grid">
    <div class="card">
        <div class="card-h"><h2>Customer</h2></div>
        <div class="card-b">
            <div class="invoice-meta"><strong><?= e($sale['customer_name']) ?></strong></div>
            <div class="invoice-meta"><?= e($sale['customer_mobile']) ?></div>
            <div class="invoice-meta"><?= e($sale['customer_address']) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Warehouse & Currency</h2></div>
        <div class="card-b summary-card">
            <div><span>Warehouse</span><strong><?= e($sale['warehouse_name'] ?? '—') ?></strong></div>
            <div><span>Currency</span><strong><?= e(($sale['currency_code'] ?? 'AED') . ' ' . ($sale['currency_symbol'] ?? '')) ?></strong></div>
            <div><span>Rate Snapshot</span><strong><?= e(number_format((float) ($sale['currency_rate_to_aed'] ?? 1), 4)) ?></strong></div>
            <div><span>Collection Status</span><strong><span class="badge <?= e($paymentBadge) ?>"><?= e(ucfirst($paymentStatus)) ?></span></strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Summary</h2></div>
        <div class="card-b summary-card">
            <div><span>Invoice Final</span><strong><?= e(money_currency($sale['final_amount'], $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Received</span><strong><?= e(money_currency($sale['received_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div class="summary-final"><span>Outstanding</span><strong><?= e(money_currency($sale['due_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Invoice AED</span><strong><?= e(money($sale['final_amount_aed'] ?? $sale['final_amount'])) ?></strong></div>
            <div><span>Received AED</span><strong><?= e(money($sale['received_amount_aed'] ?? 0)) ?></strong></div>
            <div class="summary-final"><span>Outstanding AED</span><strong><?= e(money($sale['due_amount_aed'] ?? 0)) ?></strong></div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h">
        <h2>Items</h2>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Pricing Unit</th>
                    <th>Invoice Qty</th>
                    <th>Base Qty</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                    <th>AED Unit</th>
                    <th>AED Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $pricingUnit = (string) ($item['pricing_unit'] ?? 'unit');
                    $unitsPerBox = max(1, (float) ($item['units_per_box'] ?? 1));
                    $invoiceQty = (float) (($item['display_qty'] ?? 0) > 0 ? $item['display_qty'] : ($item['qty'] ?? 0));
                    $displayUnitPrice = $pricingUnit === 'box'
                        ? (float) ($item['unit_price'] ?? 0) * $unitsPerBox
                        : (float) ($item['unit_price'] ?? 0);
                    $displayUnitPriceAed = $pricingUnit === 'box'
                        ? (float) ($item['unit_price_aed'] ?? $item['unit_price'] ?? 0) * $unitsPerBox
                        : (float) ($item['unit_price_aed'] ?? $item['unit_price'] ?? 0);
                    $pricingLabel = $pricingUnit === 'box' ? 'Box' : ((string) ($item['unit'] ?? 'Unit'));
                    ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= e($item['product_code']) ?></td>
                        <td>
                            <strong><?= e($pricingLabel) ?></strong>
                            <?php if ($pricingUnit === 'box'): ?>
                                <div class="muted-xs"><?= e(money($unitsPerBox)) ?> <?= e($item['unit']) ?> / box</div>
                            <?php endif; ?>
                        </td>
                        <td class="dt"><?= e(money($invoiceQty)) ?></td>
                        <td class="dt"><?= e(money($item['qty'])) ?> <?= e($item['unit']) ?></td>
                        <td class="dt"><?= e(money_currency($displayUnitPrice, $sale['currency_code'] ?? 'AED')) ?></td>
                        <td class="dt"><?= e(money_currency($item['total_price'], $sale['currency_code'] ?? 'AED')) ?></td>
                        <td class="dt"><?= e(money($displayUnitPriceAed)) ?></td>
                        <td class="dt"><?= e(money($item['total_price_aed'] ?? $item['total_price'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($sale['note'])): ?>
            <div class="note-box">
                <strong>Note</strong>
                <p><?= e($sale['note']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Customer Receipts</h2></div>
    <div class="card-b">
        <?php if (($sale['can_receive_payment'] ?? false) === true): ?>
            <div class="notice" style="margin-bottom:12px;">
                <?= count($matchingBankAccounts) > 0
                    ? 'Matching banking accounts are available for ' . e($sale['currency_code'] ?? 'AED') . '. You can post a full or partial customer receipt now.'
                    : 'No active banking account exists in ' . e($sale['currency_code'] ?? 'AED') . ' yet. The invoice can remain receivable until the correct bank account is created.' ?>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Bank</th>
                    <th>Amount</th>
                    <th>AED</th>
                    <th>Reference</th>
                    <th>Created By</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($receipts === []): ?>
                    <tr><td colspan="8">No customer receipts have been posted for this sale yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><?= e($receipt['receipt_no']) ?></td>
                            <td class="dt"><?= e(date_display($receipt['receipt_date'])) ?></td>
                            <td><?= e(trim(($receipt['bank_name'] ?? '') . ' / ' . ($receipt['account_name'] ?? ''))) ?></td>
                            <td class="dt"><?= e(money_currency($receipt['amount_currency'] ?? 0, $receipt['currency_code'] ?? ($sale['currency_code'] ?? 'AED'))) ?></td>
                            <td class="dt"><?= e(money($receipt['amount_aed'] ?? 0)) ?></td>
                            <td><?= e($receipt['reference_no'] ?: '—') ?></td>
                            <td><?= e($receipt['created_by_name'] ?? '—') ?></td>
                            <td><?= e($receipt['note'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
