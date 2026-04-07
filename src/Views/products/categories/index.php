<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1>Products / Category</h1>
        <small>Create, modify, and retire product categories. Category images help visual organization.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/products/categories')) ?>" class="inline-search">
            <input type="text" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search category name or slug">
            <button type="submit" class="btn secondary btn-sm">Search</button>
        </form>
        <a href="<?= e(base_url('/products/categories/create')) ?>" class="btn">New Category</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($categories ?? []) === []): ?>
                    <tr><td colspan="6">No categories found.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <?php if (!empty($category['image_path'])): ?>
                                    <img src="<?= e(public_upload_url($category['image_path'])) ?>" alt="<?= e($category['name']) ?>" class="table-thumb">
                                <?php else: ?>
                                    <div class="table-thumb table-thumb-empty">CAT</div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($category['name']) ?></td>
                            <td><code><?= e($category['slug']) ?></code></td>
                            <td><?= e((string) $category['items_count']) ?></td>
                            <td><span class="badge <?= e($category['status'] === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst($category['status'])) ?></span></td>
                            <td>
                                <div class="row-actions-tight">
                                    <a href="<?= e(base_url('/products/categories/edit?id=' . $category['id'])) ?>" class="tab active">Edit</a>
                                    <form method="post" action="<?= e(base_url('/products/categories/delete?id=' . $category['id'])) ?>" onsubmit="return confirm('Delete this category?');">
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
