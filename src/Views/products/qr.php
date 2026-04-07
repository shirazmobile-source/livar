<?php require __DIR__ . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1>Products / Items / QR Label</h1>
        <small>Use this label for shelves, packaging, or internal scanning workflows.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/products/edit?id=' . $product['id'])) ?>" class="btn secondary">Edit Item</a>
        <button type="button" class="btn" onclick="window.print()">Print QR</button>
    </div>
</section>

<section class="card qr-card-shell">
    <div class="card-b qr-card-body">
        <div class="qr-card">
            <img src="<?= e(qr_image_url((string) $payload, 280)) ?>" alt="QR code for <?= e($product['name']) ?>" class="qr-image-lg">
            <div class="qr-copy">
                <h2><?= e($product['name']) ?></h2>
                <p><strong>Code:</strong> <?= e($product['code']) ?></p>
                <p><strong>Category:</strong> <?= e($product['category_label']) ?></p>
                <p><strong>Unit:</strong> <?= e($product['unit_label']) ?></p>
                <p><strong>Encoded value:</strong> <code><?= e($payload) ?></code></p>
                <a class="btn secondary" href="<?= e(qr_image_url((string) $payload, 600)) ?>" target="_blank" rel="noopener">Open PNG</a>
            </div>
        </div>
    </div>
</section>
