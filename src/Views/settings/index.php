<section class="page-head">
    <div>
        <h1>Setting</h1>
        <small>System administration sections with their own dedicated pages.</small>
    </div>
</section>

<?php require __DIR__ . '/partials/nav.php'; ?>

<section class="card">
    <div class="card-h">
        <div>
            <h2>Settings Modules</h2>
            <small>Select a module to open its own page.</small>
        </div>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th style="width: 180px;">Module</th>
                    <th>Description</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width: 140px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($modules ?? []) === []): ?>
                    <tr><td colspan="4">No settings modules are available for your account.</td></tr>
                <?php else: ?>
                    <?php foreach ($modules as $module): ?>
                        <tr>
                            <td><strong><?= e($module['title']) ?></strong></td>
                            <td><?= e($module['description']) ?></td>
                            <td><span class="badge green"><?= e($module['status']) ?></span></td>
                            <td><a class="tab active" href="<?= e(base_url($module['url'])) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
