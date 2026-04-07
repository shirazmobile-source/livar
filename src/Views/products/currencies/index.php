<?php require dirname(__DIR__) . '/partials/nav.php'; ?>
<section class="page-head">
    <div>
        <h1>Products / Currency</h1>
        <small>Manage catalog currencies and their exchange rate against AED.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/products/currencies')) ?>" class="inline-search">
            <input type="text" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search currency name, code, or symbol">
            <button type="submit" class="btn secondary btn-sm">Search</button>
        </form>
        <a href="<?= e(base_url('/products/currencies/create')) ?>" class="btn">New Currency</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Currency</th>
                    <th>Symbol</th>
                    <th>Rate to AED</th>
                    <th>Default</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($currencies ?? []) === []): ?>
                    <tr><td colspan="7">No currencies found.</td></tr>
                <?php else: ?>
                    <?php foreach ($currencies as $currency): ?>
                        <tr>
                            <td>
                                <strong><?= e($currency['code']) ?></strong>
                                <div class="muted-xs"><?= e($currency['name']) ?></div>
                            </td>
                            <td><?= e($currency['symbol']) ?></td>
                            <td><?= e(number_format((float) $currency['rate_to_aed'], 8)) ?></td>
                            <td><?= !empty($currency['is_default']) ? '<span class="badge green">Default</span>' : '—' ?></td>
                            <td><?= e((string) $currency['items_count']) ?></td>
                            <td><span class="badge <?= e($currency['status'] === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst($currency['status'])) ?></span></td>
                            <td>
                                <div class="row-actions-tight">
                                    <a href="<?= e(base_url('/products/currencies/edit?id=' . $currency['id'])) ?>" class="tab active">Edit</a>
                                    <form method="post" action="<?= e(base_url('/products/currencies/delete?id=' . $currency['id'])) ?>" onsubmit="return confirm('Delete this currency?');">
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
