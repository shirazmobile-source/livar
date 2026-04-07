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
                <h1><?= e($template['title'] ?? 'Warehouse Receipt') ?></h1>
                <h2><?= e($global['company_name'] ?? config('app.name')) ?></h2>
                <p class="muted">Inbound receipt note for purchase inventory.</p>
            </div>
        </div>
        <div class="doc-header-side">
            <div class="doc-card"><div class="doc-meta-grid"><div><small>Receipt No</small><strong><?= e($receipt['receipt_no']) ?></strong></div><div><small>Date</small><strong><?= e(date_display((string) $receipt['receipt_date'])) ?></strong></div><div><small>Warehouse</small><strong><?= e(($receipt['warehouse_name'] ?? '') . ' (' . ($receipt['warehouse_code'] ?? '') . ')') ?></strong></div><div><small>Purchase Ref</small><strong><?= e($receipt['purchase_invoice_no'] ?? '—') ?></strong></div></div></div>
            <?php if ($qrUrl !== ''): ?><div class="doc-card" style="display:flex;justify-content:space-between;align-items:center;gap:16px;"><div><small>Scan</small><strong class="doc-accent-text">Receipt QR</strong></div><img class="doc-qr" src="<?= e($qrUrl) ?>" alt="QR"></div><?php endif; ?>
        </div>
    </header>
    <section class="doc-section"><h3 class="doc-section-title">From Supplier</h3><div class="doc-card doc-address"><strong><?= e($receipt['supplier_name'] ?? '—') ?></strong><div><?= e($receipt['supplier_mobile'] ?? '—') ?></div><div><?= e($receipt['supplier_address'] ?? '—') ?></div></div></section>
    <section class="doc-section"><h3 class="doc-section-title">Receipt Lines</h3><div class="doc-table-wrap"><table class="doc-table"><thead><tr><th>Product</th><th>Code</th><th>Unit</th><th class="num">Qty</th><th class="num">Unit Cost AED</th></tr></thead><tbody><?php foreach (($items ?? []) as $item): ?><tr><td><?= e($item['product_name']) ?></td><td><?= e($item['product_code']) ?></td><td><?= e($item['unit']) ?></td><td class="num"><?= e(money($item['qty'] ?? 0)) ?></td><td class="num"><?= e(money($item['unit_cost_aed'] ?? 0)) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <?php if (($template['show_notes'] ?? true) === true && !empty($receipt['note'])): ?><section class="doc-section"><div class="doc-note"><small>Note</small><div><?= e($receipt['note']) ?></div></div></section><?php endif; ?>
    <?php if (($template['show_signatures'] ?? true) === true): ?><section class="doc-section"><div class="doc-signatures"><div class="doc-signature-box"><small>Prepared By</small></div><div class="doc-signature-box"><small>Warehouse Officer</small></div><div class="doc-signature-box"><small>Receiver Signature</small></div></div></section><?php endif; ?>
    <?php if (($template['show_footer'] ?? true) === true): ?><footer class="doc-footer"><div><?= e($template['footer_text'] ?? '') ?></div><div><?= e('Generated ' . date('Y-m-d H:i')) ?></div></footer><?php endif; ?>
</div>
</section>
