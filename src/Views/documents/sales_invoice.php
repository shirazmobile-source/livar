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
$paymentStatus = (string) ($sale['payment_status'] ?? 'unpaid');
$badgeClass = $paymentStatus === 'paid' ? 'green' : ($paymentStatus === 'partial' ? 'orange' : 'red');
$sheetClasses = trim('document-sheet doc-' . e($paper) . ($orientation === 'landscape' ? ' doc-landscape' : ''));
$companyName = (string) ($global['company_name'] ?? config('app.name'));
$normalizedCompanyName = strtolower(preg_replace('/[^a-z0-9]+/i', '', str_replace(['®', '™'], '', $companyName)) ?? '');
$isLivarBrand = $normalizedCompanyName === 'livar';
$companyTagline = (string) ($global['company_tagline'] ?? '');
$companyAddress = trim((string) ($global['company_address'] ?? ''));
$companyPhone = trim((string) ($global['company_phone'] ?? ''));
$companyEmail = trim((string) ($global['company_email'] ?? ''));
$companyTrn = trim((string) ($global['company_trn'] ?? ''));
$currencyCode = (string) ($sale['currency_code'] ?? 'AED');
$currencySymbol = trim((string) ($sale['currency_symbol'] ?? ''));
$currencyLabel = trim($currencyCode . ($currencySymbol !== '' ? ' ' . $currencySymbol : ''));
$showNotes = (($template['show_notes'] ?? true) === true) && !empty($sale['note']);
$showTerms = (($template['show_terms'] ?? true) === true) && !empty($template['terms_text']);
$notesText = trim((string) ($sale['note'] ?? ''));
if ($showTerms) {
    $notesText = trim($notesText . ($notesText !== '' ? "\n\n" : '') . (string) ($template['terms_text'] ?? ''));
}
?>
<?php if (!empty($template['watermark_text'])): ?><div class="doc-watermark"><?= e($template['watermark_text']) ?></div><?php endif; ?>
<section class="<?= $sheetClasses ?>" data-doc-type="sales_invoice" data-doc-layout="stack">
    <div class="doc-inner">
        <article class="sales-print sales-print--<?= e($variant) ?>">
            <header class="sales-print__top">
                <div class="sales-print__brand">
                    <div class="sales-print__brand-row">
                        <?php if (($template['show_logo'] ?? true) && $logo !== ''): ?>
                            <img class="sales-print__logo" src="<?= e($logo) ?>" alt="Logo">
                        <?php endif; ?>
                        <div class="sales-print__company">
                            <h1 class="sales-print__company-name<?= $isLivarBrand ? ' is-livar' : '' ?>"><?php if ($isLivarBrand): ?><span class="sales-print__company-wordmark">LiVAR</span><sup>®</sup><?php else: ?><?= e($companyName) ?><?php endif; ?></h1>
                            <?php if ($companyTagline !== ''): ?><div class="sales-print__tagline"><?= e($companyTagline) ?></div><?php endif; ?>
                            <?php if ($companyAddress !== ''): ?><div class="sales-print__line"><?= e($companyAddress) ?></div><?php endif; ?>
                            <?php if ($companyPhone !== '' || $companyEmail !== ''): ?><div class="sales-print__line"><?= e(trim($companyPhone . ($companyPhone !== '' && $companyEmail !== '' ? ' ' : '') . $companyEmail)) ?></div><?php endif; ?>
                            <?php if ($companyTrn !== ''): ?><div class="sales-print__line">TRN: <?= e($companyTrn) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="sales-print__party-wrap <?= $qrUrl !== '' ? 'has-qr' : '' ?> <?= e($qrPosition) ?>">
                    <div class="sales-print__party">
                        <h2>Bill To</h2>
                        <div class="sales-print__party-card">
                            <div class="sales-print__party-name"><?= e($sale['customer_name'] ?: 'Customer') ?></div>
                            <div><?= e($sale['customer_mobile'] ?: '—') ?></div>
                            <div><?= e($sale['customer_email'] ?? '—') ?></div>
                            <div><?= e($sale['customer_address'] ?: 'No address recorded') ?></div>
                        </div>
                    </div>
                    <?php if ($qrUrl !== ''): ?>
                        <div class="sales-print__qr"><img src="<?= e($qrUrl) ?>" alt="QR"></div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="sales-print__rule"></div>

            <section class="sales-print__meta-summary" style="display:block;">
                <div class="sales-print__meta-grid">
                    <div class="sales-print__meta-item"><span>Invoice No</span><strong><?= e($sale['invoice_no']) ?></strong></div>
                    <div class="sales-print__meta-item"><span>Date</span><strong><?= e(date_display((string) $sale['invoice_date'])) ?></strong></div>
                    <div class="sales-print__meta-item"><span>Warehouse</span><strong><?= e($sale['warehouse_name'] ?? '—') ?></strong></div>
                    <div class="sales-print__meta-item"><span>Status</span><strong><span class="badge-inline <?= e($badgeClass) ?>"><?= e(ucfirst($paymentStatus)) ?></span></strong></div>
                </div>
            </section>

            <section class="sales-print__items-block">
                <h3 class="sales-print__section-title">Items</h3>
                <div class="sales-print__table-wrap">
                    <table class="sales-print__table">
                        <colgroup>
                            <col class="col-item">
                            <col class="col-qty">
                            <col class="col-unit-price">
                            <col class="col-line-total">
                            <col class="col-aed-total">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit Price</th>
                                <th class="num">Line Total</th>
                                <th class="num">AED Total</th>
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
                                    <div class="sales-print__item-name"><?= e($item['product_name']) ?></div>
                                    <div class="sales-print__item-meta">Code: <?= e($item['product_code']) ?></div>
                                    <div class="sales-print__item-meta">Pricing: <?= e($pricingLabel) ?><?php if ($pricingUnit === 'box'): ?> · <?= e(money($unitsPerBox)) ?> <?= e($item['unit']) ?> / box<?php endif; ?></div>
                                    <?php if (!empty($item['unit'])): ?><div class="sales-print__item-meta">Base Unit: <?= e($item['unit']) ?></div><?php endif; ?>
                                </td>
                                <td class="num"><?= e(money($invoiceQty)) ?></td>
                                <td class="num"><?= e(money_currency($displayUnitPrice, $currencyCode)) ?></td>
                                <td class="num"><?= e(money_currency($item['total_price'] ?? 0, $currencyCode)) ?></td>
                                <td class="num"><?= e(money($item['total_price_aed'] ?? $item['total_price'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="sales-print__bottom">
                <div class="sales-print__note-box">
                    <div class="sales-print__note-label">Note</div>
                    <div class="sales-print__note-text"><?= e($notesText !== '' ? $notesText : '—') ?></div>
                </div>
                <div class="sales-print__totals-box">
                    <div class="sales-print__totals-grid">
                        <div><span>Sub Total</span><strong><?= e(money_currency($sale['total_amount'] ?? 0, $currencyCode)) ?></strong></div>
                        <div><span>Discount</span><strong><?= e(money_currency($sale['discount_amount'] ?? 0, $currencyCode)) ?></strong></div>
                        <div><span>Total AED</span><strong><?= e(money($sale['final_amount_aed'] ?? 0)) ?></strong></div>
                        <div><span>Received AED</span><strong><?= e(money($sale['received_amount_aed'] ?? 0)) ?></strong></div>
                        <div class="full"><span>Net Due</span><strong><?= e(money_currency($sale['due_amount'] ?? 0, $currencyCode)) ?></strong></div>
                    </div>
                </div>
            </section>

            <?php if (($template['show_signatures'] ?? true) === true): ?>
                <section class="sales-print__signatures">
                    <div class="sales-print__sign-col">Prepared By</div>
                    <div class="sales-print__sign-col">Approved By</div>
                    <div class="sales-print__sign-col">Customer Signature</div>
                </section>
            <?php endif; ?>

            <?php if (($template['show_footer'] ?? true) === true): ?>
                <footer class="sales-print__footer">
                    <div><?= e($template['footer_text'] ?? '') ?></div>
                    <div><?= e('Generated ' . date('Y-m-d H:i')) ?></div>
                </footer>
            <?php endif; ?>
        </article>
    </div>
</section>
