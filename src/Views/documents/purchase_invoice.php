<?php
$template = $formTemplate ?? [];
$global = $formGlobal ?? [];
$logo = trim((string) ($global['company_logo_url'] ?? ''));
if ($logo !== '' && preg_match('~^(https?:)?//|^data:|^/~', $logo) !== 1) {
    $logo = base_url('/' . ltrim($logo, '/'));
}
$paper = strtolower((string) ($template['paper_size'] ?? 'A4'));
$orientation = (string) ($template['orientation'] ?? 'portrait');
$variant = (string) ($template['layout_variant'] ?? 'classic');
if (!in_array($variant, ['classic', 'compact', 'modern'], true)) {
    $variant = 'classic';
}
$qrPosition = (string) ($template['qr_position'] ?? 'header_right');
if (!in_array($qrPosition, ['header_right', 'meta_bottom'], true)) {
    $qrPosition = 'header_right';
}
$qrUrl = '';
if (($template['show_qr'] ?? false) && !empty($qrValue)) {
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode((string) $qrValue);
}
$paymentStatus = (string) ($purchase['payment_status'] ?? 'unpaid');
$paymentBadge = $paymentStatus === 'paid' ? 'green' : ($paymentStatus === 'partial' ? 'orange' : 'red');
$sheetClasses = trim('document-sheet doc-' . e($paper) . ($orientation === 'landscape' ? ' doc-landscape' : ''));
$companyContacts = trim((string) implode('  ', array_filter([(string) ($global['company_phone'] ?? ''), (string) ($global['company_email'] ?? '')])));
?>
<?php if (!empty($template['watermark_text'])): ?><div class="doc-watermark"><?= e($template['watermark_text']) ?></div><?php endif; ?>
<section class="<?= $sheetClasses ?>" data-doc-type="purchase_invoice" data-doc-layout="stack">
    <div class="doc-inner">
        <article class="doc-invoice doc-invoice--<?= e($variant) ?>">
            <header class="doc-invoice-header doc-invoice-header--<?= e($variant) ?>">
                <div class="doc-invoice-brand-panel">
                    <div class="doc-brand doc-brand--invoice">
                        <?php if (($template['show_logo'] ?? true) && $logo !== ''): ?>
                            <img class="doc-logo doc-logo--plain" src="<?= e($logo) ?>" alt="Logo">
                        <?php endif; ?>
                        <div class="doc-company">
                            <h1><?= e($template['title'] ?? 'Purchase Invoice') ?></h1>
                            <?php if (($template['show_company_info'] ?? true) === true): ?>
                                <h2><?= e($global['company_name'] ?? config('app.name')) ?></h2>
                                <?php if (!empty($global['company_tagline'])): ?><p class="muted"><?= e($global['company_tagline']) ?></p><?php endif; ?>
                                <?php if (!empty($global['company_address'])): ?><p class="muted doc-address"><?= e($global['company_address']) ?></p><?php endif; ?>
                                <?php if ($companyContacts !== ''): ?><p class="muted"><?= e($companyContacts) ?></p><?php endif; ?>
                                <?php if (!empty($global['company_trn'])): ?><p class="muted">TRN: <?= e($global['company_trn']) ?></p><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($global['header_note'])): ?><p class="muted"><?= e($global['header_note']) ?></p><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="doc-invoice-aside doc-invoice-aside--<?= e($qrPosition) ?>">
                    <div class="doc-card doc-invoice-meta-card">
                        <div class="doc-meta-grid">
                            <div><small>Invoice No</small><strong><?= e($purchase['invoice_no']) ?></strong></div>
                            <div><small>Date</small><strong><?= e(date_display((string) $purchase['invoice_date'])) ?></strong></div>
                            <div><small>Receipt Status</small><strong><?= e(ucfirst((string) ($purchase['receipt_status_display'] ?? 'pending'))) ?></strong></div>
                            <div><small>Payment</small><strong><span class="badge-inline <?= e($paymentBadge) ?>"><?= e(ucfirst($paymentStatus)) ?></span></strong></div>
                            <div><small>Currency</small><strong><?= e(($purchase['currency_code'] ?? 'AED') . ' ' . ($purchase['currency_symbol'] ?? '')) ?></strong></div>
                            <?php if (($template['show_currency_rate'] ?? true) === true): ?>
                                <div><small>Rate Snapshot</small><strong><?= e(number_format((float) ($purchase['currency_rate_to_aed'] ?? 1), 4)) ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($qrUrl !== ''): ?>
                        <div class="doc-invoice-qr-box">
                            <img class="doc-qr doc-qr-plain" src="<?= e($qrUrl) ?>" alt="QR">
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <section class="doc-invoice-top doc-invoice-top--<?= e($variant) ?>">
                <section class="doc-section">
                    <h3 class="doc-section-title">Supplier</h3>
                    <div class="doc-card doc-address">
                        <strong><?= e($purchase['supplier_name'] ?: 'Supplier') ?></strong>
                        <div><?= e($purchase['supplier_mobile'] ?: '—') ?></div>
                        <div><?= e($purchase['supplier_email'] ?? '—') ?></div>
                        <div><?= e($purchase['supplier_address'] ?: 'No address recorded') ?></div>
                    </div>
                </section>
                <?php if (($template['show_summary'] ?? true) === true): ?>
                    <section class="doc-section">
                        <h3 class="doc-section-title">Summary</h3>
                        <div class="doc-kpis doc-kpis--invoice">
                            <div class="doc-kpi"><small>Invoice Final</small><strong><?= e(money_currency($purchase['final_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                            <div class="doc-kpi"><small>Returned</small><strong><?= e(money_currency($purchase['returned_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                            <div class="doc-kpi"><small>Net Payable</small><strong><?= e(money_currency($purchase['net_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                        </div>
                    </section>
                <?php endif; ?>
            </section>

            <section class="doc-section">
                <h3 class="doc-section-title">Items</h3>
                <div class="doc-table-wrap">
                    <table class="doc-table doc-table-stable doc-table-purchase">
                        <colgroup>
                            <col class="col-item">
                            <col class="col-qty">
                            <col class="col-qty">
                            <col class="col-qty">
                            <col class="col-unit-price">
                            <col class="col-line-total">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th class="num">Ordered</th>
                            <th class="num">Received</th>
                            <th class="num">Returned</th>
                            <th class="num">Unit Price</th>
                            <th class="num">Line Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($items ?? []) as $item): ?>
                            <?php
                            $pricingUnit = (string) ($item['pricing_unit'] ?? 'unit');
                            $unitsPerBox = max(1, (float) ($item['units_per_box'] ?? 1));
                            $invoiceQty = (float) (($item['display_qty'] ?? 0) > 0 ? $item['display_qty'] : ($item['qty'] ?? 0));
                            $displayUnitPrice = $pricingUnit === 'box' ? (float) ($item['unit_price'] ?? 0) * $unitsPerBox : (float) ($item['unit_price'] ?? 0);
                            $pricingLabel = $pricingUnit === 'box' ? 'Box' : ((string) ($item['unit'] ?? 'Unit'));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['product_name']) ?></strong>
                                    <small>Code: <?= e($item['product_code']) ?></small>
                                    <small>Pricing: <?= e($pricingLabel) ?><?php if ($pricingUnit === 'box'): ?> · <?= e(money($unitsPerBox)) ?> <?= e($item['unit']) ?> / box<?php endif; ?></small>
                                    <?php if (!empty($item['unit'])): ?><small>Base Unit: <?= e($item['unit']) ?></small><?php endif; ?>
                                </td>
                                <td class="num"><?= e(money($invoiceQty)) ?></td>
                                <td class="num"><?= e(money($item['received_qty'] ?? 0)) ?></td>
                                <td class="num"><?= e(money($item['returned_qty'] ?? 0)) ?></td>
                                <td class="num"><?= e(money_currency($displayUnitPrice, $purchase['currency_code'] ?? 'AED')) ?></td>
                                <td class="num"><?= e(money_currency($item['total_price'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php
            $showNotes = (($template['show_notes'] ?? true) === true) && !empty($purchase['note']);
            $showTerms = (($template['show_terms'] ?? true) === true) && !empty($template['terms_text']);
            ?>
            <section class="doc-invoice-bottom doc-invoice-bottom--<?= e($variant) ?>">
                <?php if ($showNotes || $showTerms): ?>
                    <div class="doc-invoice-notes">
                        <?php if ($showNotes): ?><div class="doc-note"><small>Note</small><div><?= e($purchase['note']) ?></div></div><?php endif; ?>
                        <?php if ($showTerms): ?><div class="doc-terms" style="margin-top:14px;"><small>Terms</small><div><?= e($template['terms_text']) ?></div></div><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="doc-summary doc-summary--totals">
                    <div><small>Sub Total</small><strong><?= e(money_currency($purchase['total_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                    <div><small>Discount</small><strong><?= e(money_currency($purchase['discount_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                    <div><small>Returned</small><strong><?= e(money_currency($purchase['returned_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                    <div><small>Paid</small><strong><?= e(money_currency($purchase['paid_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                    <div class="final"><small>Net Due</small><strong><?= e(money_currency($purchase['due_amount'] ?? 0, $purchase['currency_code'] ?? 'AED')) ?></strong></div>
                </div>
            </section>

            <?php if (($template['show_signatures'] ?? true) === true): ?>
                <section class="doc-section">
                    <div class="doc-signatures">
                        <div class="doc-signature-box"><small>Prepared By</small></div>
                        <div class="doc-signature-box"><small>Approved By</small></div>
                        <div class="doc-signature-box"><small>Supplier Signature</small></div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (($template['show_footer'] ?? true) === true): ?>
                <footer class="doc-footer">
                    <div><?= e($template['footer_text'] ?? '') ?></div>
                    <div><?= e('Generated ' . date('Y-m-d H:i')) ?></div>
                </footer>
            <?php endif; ?>
        </article>
    </div>
</section>
