<?php
$template = $formTemplate ?? [];
$global = $formGlobal ?? [];
$logo = trim((string) ($global['company_logo_url'] ?? ''));
if ($logo !== '' && preg_match('~^(https?:)?//|^data:|^/~', $logo) !== 1) { $logo = base_url('/' . ltrim($logo, '/')); }
$qrUrl = '';
if (($template['show_qr'] ?? false) && !empty($qrValue)) { $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode((string) $qrValue); }
?>
<section class="document-sheet doc-<?= e(strtolower((string) ($template['paper_size'] ?? 'A5'))) ?>">
<div class="doc-inner">
    <header class="doc-header">
        <div class="doc-brand">
            <?php if (($template['show_logo'] ?? true) && $logo !== ''): ?><img class="doc-logo" src="<?= e($logo) ?>" alt="Logo"><?php endif; ?>
            <div class="doc-company">
                <h1><?= e($template['title'] ?? 'Warehouse Issue') ?></h1>
                <h2><?= e($global['company_name'] ?? config('app.name')) ?></h2>
                <p class="muted">Outbound warehouse issue note generated from sales.</p>
            </div>
        </div>
        <div class="doc-header-side">
            <div class="doc-card"><div class="doc-meta-grid"><div><small>Sale No</small><strong><?= e($sale['invoice_no']) ?></strong></div><div><small>Date</small><strong><?= e(date_display((string) $sale['invoice_date'])) ?></strong></div><div><small>Warehouse</small><strong><?= e($sale['warehouse_name'] ?? '—') ?></strong></div><div><small>Customer</small><strong><?= e($sale['customer_name'] ?? '—') ?></strong></div></div></div>
            <?php if ($qrUrl !== ''): ?><div class="doc-card" style="display:flex;justify-content:space-between;align-items:center;gap:16px;"><div><small>Scan</small><strong class="doc-accent-text">Issue QR</strong></div><img class="doc-qr" src="<?= e($qrUrl) ?>" alt="QR"></div><?php endif; ?>
        </div>
    </header>
    <section class="doc-section"><h3 class="doc-section-title">Issue Lines</h3><div class="doc-table-wrap"><table class="doc-table"><thead><tr><th>Product</th><th>Code</th><th>Unit</th><th class="num">Base Qty</th><th class="num">Pricing Unit</th><th class="num">Invoice Qty</th></tr></thead><tbody><?php foreach (($items ?? []) as $item): ?><?php $pricingUnit = (string) ($item['pricing_unit'] ?? 'unit'); $unitsPerBox = max(1, (float) ($item['units_per_box'] ?? 1)); $invoiceQty = (float) (($item['display_qty'] ?? 0) > 0 ? $item['display_qty'] : ($item['qty'] ?? 0)); $pricingLabel = $pricingUnit === 'box' ? 'Box (' . money($unitsPerBox) . ' ' . ($item['unit'] ?? 'unit') . ')' : ucfirst($pricingUnit); ?><tr><td><?= e($item['product_name']) ?></td><td><?= e($item['product_code']) ?></td><td><?= e($item['unit']) ?></td><td class="num"><?= e(money($item['qty'] ?? 0)) ?></td><td class="num"><?= e($pricingLabel) ?></td><td class="num"><?= e(money($invoiceQty)) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <?php if (($template['show_notes'] ?? true) === true && !empty($sale['note'])): ?><section class="doc-section"><div class="doc-note"><small>Note</small><div><?= e($sale['note']) ?></div></div></section><?php endif; ?>
    <?php if (($template['show_signatures'] ?? true) === true): ?><section class="doc-section"><div class="doc-signatures"><div class="doc-signature-box"><small>Prepared By</small></div><div class="doc-signature-box"><small>Warehouse Officer</small></div><div class="doc-signature-box"><small>Driver / Receiver</small></div></div></section><?php endif; ?>
    <?php if (($template['show_footer'] ?? true) === true): ?><footer class="doc-footer"><div><?= e($template['footer_text'] ?? '') ?></div><div><?= e('Generated ' . date('Y-m-d H:i')) ?></div></footer><?php endif; ?>
</div>
</section>
