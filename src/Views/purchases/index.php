<section class="page-head">
    <div>
        <h1>Purchases</h1>
        <small>Register supplier invoices first. Then decide whether to keep the invoice payable or settle it from a same-currency banking account.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/purchases/create')) ?>" class="btn">New Purchase</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Receipt</th>
                    <th>Payment</th>
                    <th>Return</th>
                    <th>Due</th>
                    <th>Final</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($purchases === []): ?>
                    <tr><td colspan="9">No purchases recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($purchases as $purchase): ?>
                        <?php
                        $receiptStatus = (string) ($purchase['receipt_status'] ?? 'pending');
                        $receiptBadge = $receiptStatus === 'received' ? 'green' : ($receiptStatus === 'partial' ? 'orange' : '');
                        $paymentStatus = (string) ($purchase['payment_status'] ?? 'unpaid');
                        $paymentBadge = $paymentStatus === 'paid' ? 'green' : ($paymentStatus === 'partial' ? 'orange' : '');
                        $returnStatus = (string) ($purchase['return_status'] ?? 'none');
                        $returnBadge = $returnStatus === 'returned' ? 'green' : ($returnStatus === 'partial' ? 'orange' : '');
                        $isEditable = $receiptStatus === 'pending' && (int) ($purchase['payment_count'] ?? 0) === 0;
                        $canReturn = (float) ($purchase['received_qty'] ?? 0) > (float) ($purchase['returned_qty'] ?? 0);
                        ?>
                        <tr>
                            <td><?= e($purchase['invoice_no']) ?></td>
                            <td class="dt"><?= e(date_display($purchase['invoice_date'])) ?></td>
                            <td><?= e($purchase['supplier_name']) ?></td>
                            <td><span class="badge <?= e($receiptBadge) ?>"><?= e(ucfirst($receiptStatus)) ?></span></td>
                            <td><span class="badge <?= e($paymentBadge) ?>"><?= e(ucfirst($paymentStatus)) ?></span></td>
                            <td><span class="badge <?= e($returnBadge) ?>"><?= e($returnStatus === 'none' ? 'None' : ucfirst($returnStatus)) ?></span></td>
                            <td class="dt">
                                <?= e(money_currency($purchase['due_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?><br>
                                <small>AED <?= e(money($purchase['due_amount_aed'] ?? 0)) ?></small>
                            </td>
                            <td class="dt">
                                <?= e(money_currency($purchase['final_amount'], $purchase['currency_code'] ?? 'AED')) ?><br>
                                <small>AED <?= e(money($purchase['final_amount_aed'] ?? $purchase['final_amount'])) ?></small>
                            </td>
                            <td>
                                <div class="table-actions-inline">
                                    <a href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>" class="tab active">View</a>
                                    <?php if ($isEditable): ?>
                                        <a href="<?= e(base_url('/purchases/edit?id=' . $purchase['id'])) ?>" class="tab">Edit</a>
                                    <?php endif; ?>
                                    <?php if (($purchase['pending_qty'] ?? 0) > 0): ?>
                                        <a href="<?= e(base_url('/inventory/receipts/create?purchase_id=' . $purchase['id'])) ?>" class="tab">Receive</a>
                                    <?php endif; ?>
                                    <?php if (($purchase['can_pay_supplier'] ?? false) === true): ?>
                                        <a href="<?= e(base_url('/purchases/payments/create?purchase_id=' . $purchase['id'])) ?>" class="tab">Pay</a>
                                    <?php endif; ?>
                                    <?php if ($canReturn): ?>
                                        <a href="<?= e(base_url('/purchases/returns/create?purchase_id=' . $purchase['id'])) ?>" class="tab">Return</a>
                                    <?php endif; ?>
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
