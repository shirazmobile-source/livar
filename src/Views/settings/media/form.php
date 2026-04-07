<div class="media-page">
<section class="page-head">
    <div>
        <h1>Setting / Media / Edit</h1>
        <small>Update the media title, alt text, and internal notes without changing the linked records or file path.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/settings/media')) ?>" class="btn secondary">Back to Media</a>
    </div>
</section>

<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<section class="card">
    <div class="card-b">
        <div class="media-edit-grid">
            <div class="media-edit-preview">
                <?php if (!empty($media['is_image'])): ?>
                    <img src="<?= e((string) ($media['public_url'] ?? '')) ?>" alt="<?= e((string) (($media['alt_text'] ?? '') !== '' ? $media['alt_text'] : ($media['title'] ?? $media['file_name'] ?? 'Media'))) ?>" class="media-edit-image">
                <?php else: ?>
                    <a href="<?= e((string) ($media['public_url'] ?? '')) ?>" target="_blank" rel="noopener" class="media-doc-thumb media-doc-thumb-large">PDF</a>
                <?php endif; ?>

                <div class="notice" style="margin-top:12px;">
                    <strong><?= e((string) ($media['file_name'] ?? 'Media file')) ?></strong><br>
                    <small><?= e((string) ($media['path'] ?? '')) ?></small><br>
                    <small><?= e((string) ($media['size_label'] ?? '0 B')) ?><?= !empty($media['dimensions']) ? ' · ' . e((string) $media['dimensions']) : '' ?></small>
                </div>

                <div class="stack-gap-sm">
                    <a href="<?= e((string) ($media['public_url'] ?? '')) ?>" class="btn secondary btn-sm" target="_blank" rel="noopener">Open File</a>
                </div>
            </div>

            <div>
                <form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap">
                    <?= App\Core\Csrf::field() ?>
                    <div class="field">
                        <label>Media Title</label>
                        <input type="text" name="title" value="<?= e((string) ($media['title'] ?? '')) ?>" placeholder="Friendly title for internal use">
                    </div>
                    <div class="field">
                        <label>Alt Text</label>
                        <input type="text" name="alt_text" value="<?= e((string) ($media['alt_text'] ?? '')) ?>" placeholder="Accessibility and descriptive alt text">
                    </div>
                    <div class="field">
                        <label>Internal Notes</label>
                        <textarea name="notes" rows="5" placeholder="Optional notes about how this media should be used"><?= e((string) ($media['notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="row form-actions">
                        <button type="submit" class="btn">Save Media Details</button>
                        <a href="<?= e(base_url('/settings/media')) ?>" class="btn ghost">Cancel</a>
                    </div>
                </form>

                <section class="card" style="margin-top:12px;">
                    <div class="card-h">
                        <div>
                            <h2>Linked Records</h2>
                            <small>This shows exactly where the selected file is currently used.</small>
                        </div>
                    </div>
                    <div class="card-b">
                        <?php if (($media['links'] ?? []) === []): ?>
                            <div class="alert">
                                This file is currently not linked to any customer, product, category, or customer document.
                            </div>
                        <?php else: ?>
                            <div class="media-link-stack">
                                <?php foreach (($media['links'] ?? []) as $link): ?>
                                    <div class="media-link-chip media-link-card">
                                        <div class="row" style="justify-content:space-between;align-items:flex-start;">
                                            <div>
                                                <span class="badge blue"><?= e((string) ($link['source_label'] ?? 'Linked record')) ?></span>
                                                <div style="margin-top:8px;"><strong><?= e((string) ($link['record_label'] ?? 'Record')) ?></strong></div>
                                                <?php if (!empty($link['document_name'])): ?><div class="muted-xs"><?= e((string) $link['document_name']) ?></div><?php endif; ?>
                                            </div>
                                            <a href="<?= e((string) ($link['record_url'] ?? '#')) ?>" class="tab active">Open</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>

</div>
