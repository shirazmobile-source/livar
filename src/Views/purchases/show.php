<?php
$paymentStatus = (string) ($purchase['payment_status'] ?? 'unpaid');
$paymentBadge = $paymentStatus === 'paid' ? 'green' : ($paymentStatus === 'partial' ? 'orange' : '');
$matchingBankAccounts = $matchingBankAccounts ?? [];
$payments = $payments ?? [];
?>
<section class="page-head invoice-head">
    <div>
        <h1>Purchase Invoice</h1>
        <small><?= e($purchase['invoice_no']) ?> • <?= e(date_display($purchase['invoice_date'])) ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/purchases')) ?>" class="btn secondary">Back</a>
        <?php if (in_array((string) ($purchase['receipt_status_display'] ?? ($purchase['receipt_status'] ?? 'pending')), ['pending', 'not_required'], true) && (int) ($purchase['payment_count'] ?? 0) === 0): ?>
            <a href="<?= e(base_url('/purchases/edit?id=' . $purchase['id'])) ?>" class="btn secondary">Edit Purchase</a>
        <?php endif; ?>
        <?php if (($purchase['can_pay_supplier'] ?? false) === true): ?>
            <a href="<?= e(base_url('/purchases/payments/create?purchase_id=' . $purchase['id'])) ?>" class="btn secondary">Pay Supplier</a>
        <?php endif; ?>
        <?php if (($purchase['can_receive_inventory'] ?? false) === true): ?>
            <a href="<?= e(base_url('/inventory/receipts/create?purchase_id=' . $purchase['id'])) ?>" class="btn">Receive to Warehouse</a>
        <?php endif; ?>
        <?php if (($canCreateReturn ?? false) === true): ?>
            <a href="<?= e(base_url('/purchases/returns/create?purchase_id=' . $purchase['id'])) ?>" class="btn">Purchase Return</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/documents/purchases/invoice?id=' . $purchase['id'])) ?>" class="btn" target="_blank" rel="noopener">Print / PDF</a>
    </div>
</section>

<?php if (in_array((string) ($purchase['receipt_status_display'] ?? ($purchase['receipt_status'] ?? 'pending')), ['pending', 'not_required'], true) && (int) ($purchase['payment_count'] ?? 0) === 0): ?>
    <div class="notice" style="margin-bottom:12px;">
        This purchase is still <strong>Pending</strong>. You can edit it until the first warehouse receipt or supplier payment is posted.
    </div>
<?php endif; ?>

<?php if (($purchase['can_pay_supplier'] ?? false) === true): ?>
    <div class="notice" style="margin-bottom:12px;">
        Supplier settlement follows the invoice currency exactly: <strong><?= e($purchase['currency_code'] ?? 'AED') ?></strong>. Payments must use a banking account with the same currency. Negative bank balances are allowed for supplier payments when needed.
    </div>
<?php endif; ?>

<section class="invoice-grid">
    <div class="card">
        <div class="card-h"><h2>Supplier</h2></div>
        <div class="card-b">
            <div class="invoice-meta"><strong><?= e($purchase['supplier_name']) ?></strong></div>
            <div class="invoice-meta"><?= e($purchase['supplier_mobile']) ?></div>
            <div class="invoice-meta"><?= e($purchase['supplier_address']) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Inventory Receipt</h2></div>
        <div class="card-b summary-card">
            <div><span>Status</span><strong><?= e(($purchase['receipt_status_display'] ?? 'pending') === 'not_required' ? 'Not Required' : ucfirst((string) ($purchase['receipt_status_display'] ?? ($purchase['receipt_status'] ?? 'pending')))) ?></strong></div>
            <div><span>Return Status</span><strong><?= e(($purchase['return_status'] ?? 'none') === 'none' ? 'None' : ucfirst((string) $purchase['return_status'])) ?></strong></div>
            <div><span>Base Accounting</span><strong>AED</strong></div>
            <div><span>Invoice Currency</span><strong><?= e(($purchase['currency_code'] ?? 'AED') . ' ' . ($purchase['currency_symbol'] ?? '')) ?></strong></div>
            <div><span>Rate Snapshot</span><strong><?= e(number_format((float) ($purchase['currency_rate_to_aed'] ?? 1), 4)) ?></strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Supplier Payment</h2></div>
        <div class="card-b summary-card">
            <div><span>Status</span><strong><span class="badge <?= e($paymentBadge) ?>"><?= e(ucfirst($paymentStatus)) ?></span></strong></div>
            <div><span>Paid</span><strong><?= e(money_currency($purchase['paid_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Due</span><strong><?= e(money_currency($purchase['due_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Paid AED</span><strong><?= e(money($purchase['paid_amount_aed'] ?? 0)) ?></strong></div>
            <div><span>Due AED</span><strong><?= e(money($purchase['due_amount_aed'] ?? 0)) ?></strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Summary</h2></div>
        <div class="card-b summary-card">
            <div><span>Invoice Final</span><strong><?= e(money_currency($purchase['final_amount'], $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Returned</span><strong><?= e(money_currency($purchase['returned_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div class="summary-final"><span>Net Payable</span><strong><?= e(money_currency($purchase['net_amount'] ?? $purchase['final_amount'], $purchase['currency_code'] ?? 'AED')) ?></strong></div>
            <div><span>Invoice AED</span><strong><?= e(money($purchase['final_amount_aed'] ?? $purchase['final_amount'])) ?></strong></div>
            <div><span>Returned AED</span><strong><?= e(money($purchase['returned_amount_aed'] ?? 0)) ?></strong></div>
            <div class="summary-final"><span>Net Payable AED</span><strong><?= e(money($purchase['net_amount_aed'] ?? $purchase['final_amount_aed'] ?? $purchase['final_amount'])) ?></strong></div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Items</h2></div>
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
                    <th>Received</th>
                    <th>Returned</th>
                    <th>Pending</th>
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
                    $displayUnitPrice = $pricingUnit === 'box' ? (float) ($item['unit_price'] ?? 0) * $unitsPerBox : (float) ($item['unit_price'] ?? 0);
                    $displayUnitPriceAed = $pricingUnit === 'box' ? (float) ($item['unit_price_aed'] ?? $item['unit_price'] ?? 0) * $unitsPerBox : (float) ($item['unit_price_aed'] ?? $item['unit_price'] ?? 0);
                    $pricingLabel = $pricingUnit === 'box' ? 'Box (' . money($unitsPerBox) . ' ' . ($item['unit'] ?? 'unit') . ')' : ucfirst($pricingUnit);
                    ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= e($item['product_code']) ?></td>
                        <td><?= e($pricingLabel) ?></td>
                        <td class="dt"><?= e(money($invoiceQty)) ?></td>
                        <td class="dt"><?= e(money($item['qty'])) ?></td>
                        <td class="dt"><?= e(money($item['received_qty'] ?? 0)) ?></td>
                        <td class="dt"><?= e(money($item['returned_qty'] ?? 0)) ?></td>
                        <td class="dt"><?= e(money($item['pending_qty'] ?? 0)) ?></td>
                        <td class="dt"><?= e(money_currency($displayUnitPrice, $purchase['currency_code'] ?? 'AED')) ?></td>
                        <td class="dt"><?= e(money_currency($item['total_price'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></td>
                        <td class="dt"><?= e(money($displayUnitPriceAed)) ?></td>
                        <td class="dt"><?= e(money($item['total_price_aed'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Supplier Payments</h2></div>
    <div class="card-b">
        <?php if (($purchase['can_pay_supplier'] ?? false) === true): ?>
            <div class="notice" style="margin-bottom:12px;">
                <?= count($matchingBankAccounts) > 0
                    ? 'Matching banking accounts are available for ' . e($purchase['currency_code'] ?? 'AED') . '. You can post a full or partial supplier payment now.'
                    : 'No active banking account exists in ' . e($purchase['currency_code'] ?? 'AED') . ' yet. The invoice can remain payable until the correct bank account is created.' ?>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Payment No</th>
                    <th>Date</th>
                    <th>Bank</th>
                    <th>Amount</th>
                    <th>AED</th>
                    <th>Reference</th>
                    <th>Created By</th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($payments === []): ?>
                    <tr><td colspan="8">No supplier payments have been posted for this purchase yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e($payment['payment_no']) ?></td>
                            <td class="dt"><?= e(date_display($payment['payment_date'])) ?></td>
                            <td><?= e(trim(($payment['bank_name'] ?? '') . ' / ' . ($payment['account_name'] ?? ''))) ?></td>
                            <td class="dt"><?= e(money_currency($payment['amount_currency'] ?? 0, $payment['currency_code'] ?? ($purchase['currency_code'] ?? 'AED'))) ?></td>
                            <td class="dt"><?= e(money($payment['amount_aed'] ?? 0)) ?></td>
                            <td><?= e($payment['reference_no'] ?: '—') ?></td>
                            <td><?= e($payment['created_by_name'] ?? '—') ?></td>
                            <td><?= e($payment['note'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Warehouse Receipts</h2></div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Warehouse</th>
                    <th>Total Qty</th>
                    <th>Created By</th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($receipts ?? []) === []): ?>
                    <tr><td colspan="7">No warehouse receipts posted yet.</td></tr>
                <?php else: ?>
                    <?php foreach (($receipts ?? []) as $receipt): ?>
                        <tr>
                            <td><?= e($receipt['receipt_no']) ?></td>
                            <td class="dt"><?= e(date_display($receipt['receipt_date'])) ?></td>
                            <td><?= e($receipt['warehouse_name']) ?></td>
                            <td class="dt"><?= e(money($receipt['total_qty'] ?? 0)) ?></td>
                            <td><?= e($receipt['created_by_name'] ?? '—') ?></td>
                            <td><?= e($receipt['note'] ?: '—') ?></td>
                            <td><a class="tab active" href="<?= e(base_url('/documents/inventory/receipt?id=' . $receipt['id'])) ?>" target="_blank" rel="noopener">Print Slip</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-h"><h2>Purchase Returns</h2></div>
    <div class="card-b">
        <?php if (($returnableWarehouses ?? []) !== []): ?>
            <div class="notice" style="margin-bottom:12px;">
                Returnable warehouses:
                <?php foreach (($returnableWarehouses ?? []) as $warehouse): ?>
                    <strong><?= e($warehouse['name']) ?></strong> (<?= e(money($warehouse['returnable_qty'] ?? 0)) ?>)
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Return No</th>
                    <th>Date</th>
                    <th>Warehouse</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>AED</th>
                    <th>Reason</th>
                    <th>Created By</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($returns ?? []) === []): ?>
                    <tr><td colspan="8">No purchase returns posted yet for this invoice.</td></tr>
                <?php else: ?>
                    <?php foreach (($returns ?? []) as $return): ?>
                        <tr>
                            <td><?= e($return['return_no']) ?></td>
                            <td class="dt"><?= e(date_display($return['return_date'])) ?></td>
                            <td><?= e($return['warehouse_name']) ?></td>
                            <td class="dt"><?= e(money($return['total_qty'] ?? 0)) ?></td>
                            <td class="dt"><?= e(money_currency($return['total_amount'] ?? 0, $return['currency_code'] ?? ($purchase['currency_code'] ?? 'AED'))) ?></td>
                            <td class="dt"><?= e(money($return['total_amount_aed'] ?? 0)) ?></td>
                            <td><?= e($return['reason'] ?: '—') ?></td>
                            <td><?= e($return['created_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
