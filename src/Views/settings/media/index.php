<?php
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'link' => 'all', 'type' => 'all'];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'linked' => 0, 'unlinked' => 0, 'images' => 0, 'documents' => 0];
$items = is_array($items ?? null) ? $items : [];

$get = static function (array $item, array $keys, mixed $default = ''): mixed {
    foreach ($keys as $key) {
        if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
            return $item[$key];
        }
    }

    return $default;
};
?>
<style>
.media-kpis{
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
    margin-bottom:12px;
}
.media-kpis .kpi-tile{
    min-height:92px;
}
.media-kpis .kpi-value{
    font-size:28px;
}
@media (max-width: 1180px){
    .media-kpis{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}
@media (max-width: 820px){
    .media-kpis{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media (max-width: 560px){
    .media-kpis{
        grid-template-columns:1fr;
    }
}
</style>
<section class="page-head">
    <div>
        <h1>Setting / Media</h1>
        <small>Review uploaded files, see where each media file is linked, and manage metadata from one dedicated page.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/settings/media')) ?>" class="inline-search">
            <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search file name, title, notes, path, or linked record">
            <select name="link">
                <option value="all" <?= selected('all', (string) ($filters['link'] ?? 'all')) ?>>All links</option>
                <option value="linked" <?= selected('linked', (string) ($filters['link'] ?? 'all')) ?>>Linked only</option>
                <option value="unlinked" <?= selected('unlinked', (string) ($filters['link'] ?? 'all')) ?>>Unlinked only</option>
            </select>
            <select name="type">
                <option value="all" <?= selected('all', (string) ($filters['type'] ?? 'all')) ?>>All files</option>
                <option value="image" <?= selected('image', (string) ($filters['type'] ?? 'all')) ?>>Images</option>
                <option value="document" <?= selected('document', (string) ($filters['type'] ?? 'all')) ?>>Documents</option>
            </select>
            <button class="btn secondary" type="submit">Filter</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../partials/nav.php'; ?>

<section class="kpi-grid media-kpis">
    <div class="kpi-tile">
        <div class="kpi-icon">#</div>
        <div class="kpi-copy">
            <div class="kpi-label">Total Media</div>
            <div class="kpi-value"><?= e((string) ($summary['total'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">↗</div>
        <div class="kpi-copy">
            <div class="kpi-label">Linked Files</div>
            <div class="kpi-value"><?= e((string) ($summary['linked'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">⊘</div>
        <div class="kpi-copy">
            <div class="kpi-label">Unlinked Files</div>
            <div class="kpi-value"><?= e((string) ($summary['unlinked'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">◧</div>
        <div class="kpi-copy">
            <div class="kpi-label">Images</div>
            <div class="kpi-value"><?= e((string) ($summary['images'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-tile">
        <div class="kpi-icon">≣</div>
        <div class="kpi-copy">
            <div class="kpi-label">Documents</div>
            <div class="kpi-value"><?= e((string) ($summary['documents'] ?? 0)) ?></div>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-h settings-actions">
        <div>
            <h2>Media Library</h2>
            <small>Review linked usage, metadata, file location, and cleanup candidates in one place.</small>
        </div>
        <span class="badge blue"><?= e((string) count($items)) ?> file(s)</span>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 320px;">File</th>
                        <th style="width: 150px;">Type</th>
                        <th>Linked To</th>
                        <th>Metadata</th>
                        <th style="width: 150px;">Updated</th>
                        <th style="width: 170px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="6">No media files found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $isImage = (bool) ($item['is_image'] ?? false);
                        $publicUrl = (string) $get($item, ['public_url', 'url'], '');
                        $fileName = (string) $get($item, ['file_name', 'filename', 'name'], basename((string) $get($item, ['path'], 'file')));
                        $directory = (string) $get($item, ['directory'], '');
                        $extension = strtoupper((string) $get($item, ['extension', 'ext'], ''));
                        $sizeLabel = (string) $get($item, ['size_label'], '');
                        $dimensions = (string) $get($item, ['dimensions'], '');
                        $group = (string) $get($item, ['group', 'type'], 'file');
                        $links = is_array($item['links'] ?? null) ? $item['links'] : [];
                        $title = trim((string) $get($item, ['title'], ''));
                        $altText = trim((string) $get($item, ['alt_text'], ''));
                        $notes = trim((string) $get($item, ['notes'], ''));
                        $modifiedAt = (string) $get($item, ['modified_at', 'updated_at', 'meta_updated_at'], '');
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex; gap:12px; align-items:flex-start;">
                                    <div class="table-thumb-wrap">
                                        <?php if ($isImage && $publicUrl !== ''): ?>
                                            <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">
                                                <img src="<?= e($publicUrl) ?>" alt="<?= e($title !== '' ? $title : $fileName) ?>" class="table-thumb">
                                            </a>
                                        <?php else: ?>
                                            <div class="table-thumb-empty"><?= e($extension !== '' ? $extension : 'FILE') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="min-width:0;">
                                        <strong style="display:block; word-break:break-word;"><?= e($fileName) ?></strong>
                                        <small style="display:block; word-break:break-word;"><?= e((string) $get($item, ['path'], '')) ?></small>
                                        <?php if ($directory !== ''): ?><small style="display:block;">Folder: <?= e($directory) ?></small><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= e($group === 'image' ? 'green' : 'orange') ?>"><?= e(ucfirst($group)) ?></span>
                                <?php if ($extension !== ''): ?><small style="display:block; margin-top:6px;"><?= e($extension) ?></small><?php endif; ?>
                                <?php if ($sizeLabel !== ''): ?><small style="display:block;"><?= e($sizeLabel) ?></small><?php endif; ?>
                                <?php if ($dimensions !== ''): ?><small style="display:block;"><?= e($dimensions) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($links === []): ?>
                                    <span class="badge red">Not linked</span>
                                <?php else: ?>
                                    <?php foreach ($links as $link): ?>
                                        <div style="margin-bottom:8px;">
                                            <span class="badge green"><?= e((string) ($link['source_label'] ?? 'Linked')) ?></span>
                                            <div style="margin-top:6px;">
                                                <?php if (!empty($link['record_url'])): ?>
                                                    <a href="<?= e((string) $link['record_url']) ?>"><strong><?= e((string) ($link['record_label'] ?? ('Record #' . (int) ($link['record_id'] ?? 0)))) ?></strong></a>
                                                <?php else: ?>
                                                    <strong><?= e((string) ($link['record_label'] ?? ('Record #' . (int) ($link['record_id'] ?? 0)))) ?></strong>
                                                <?php endif; ?>
                                                <?php if (!empty($link['document_name'])): ?><small style="display:block;"><?= e((string) $link['document_name']) ?></small><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($title !== ''): ?><small style="display:block;"><strong>Title:</strong> <?= e($title) ?></small><?php endif; ?>
                                <?php if ($altText !== ''): ?><small style="display:block;"><strong>Alt:</strong> <?= e($altText) ?></small><?php endif; ?>
                                <?php if ($notes !== ''): ?><small style="display:block; white-space:pre-wrap;"><?= e($notes) ?></small><?php endif; ?>
                                <?php if ($title === '' && $altText === '' && $notes === ''): ?><small>No metadata</small><?php endif; ?>
                            </td>
                            <td class="dt"><?= e($modifiedAt !== '' ? $modifiedAt : '—') ?></td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <a href="<?= e(base_url('/settings/media/edit?path=' . rawurlencode((string) $get($item, ['path'], '')))) ?>" class="btn secondary">Edit</a>
                                    <form method="post" action="<?= e(base_url('/settings/media/delete?path=' . rawurlencode((string) $get($item, ['path'], '')))) ?>" onsubmit="return confirm('Delete this media file? This will also detach any linked references.');">
                                        <?= App\Core\Csrf::field() ?>
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
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
