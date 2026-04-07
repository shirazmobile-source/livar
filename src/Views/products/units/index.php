<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1>Products / Unit</h1>
        <small>Create and maintain measurement units used by product items.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/products/units')) ?>" class="inline-search">
            <input type="text" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search unit name or code">
            <button type="submit" class="btn secondary btn-sm">Search</button>
        </form>
        <a href="<?= e(base_url('/products/units/create')) ?>" class="btn">New Unit</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($units ?? []) === []): ?>
                    <tr><td colspan="5">No units found.</td></tr>
                <?php else: ?>
                    <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><?= e($unit['name']) ?></td>
                            <td><code><?= e((string) ($unit['code'] ?: '—')) ?></code></td>
                            <td><?= e((string) $unit['items_count']) ?></td>
                            <td><span class="badge <?= e($unit['status'] === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst($unit['status'])) ?></span></td>
                            <td>
                                <div class="row-actions-tight">
                                    <a href="<?= e(base_url('/products/units/edit?id=' . $unit['id'])) ?>" class="tab active">Edit</a>
                                    <form method="post" action="<?= e(base_url('/products/units/delete?id=' . $unit['id'])) ?>" onsubmit="return confirm('Delete this unit?');">
                                        <?= App\Core\Csrf::field() ?>
                                        <button type="submit" class="tab">Delete</button>
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
