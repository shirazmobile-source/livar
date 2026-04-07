<section class="page-head">
    <div>
        <h1>Suppliers</h1>
        <small>Maintain supplier records and open each supplier statement to review purchases, returns, payments, and current payable balance.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/suppliers')) ?>" class="inline-search">
            <input type="text" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search suppliers">
            <button type="submit" class="btn secondary btn-sm">Search</button>
        </form>
        <a href="<?= e(base_url('/suppliers/create')) ?>" class="btn">New Supplier</a>
    </div>
</section>

<section class="stats-grid" style="margin-bottom:12px;">
    <div class="stat-card"><small>Total Suppliers</small><strong><?= e((string) ($summary['total_suppliers'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Active Suppliers</small><strong><?= e((string) ($summary['active_suppliers'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>With Purchases</small><strong><?= e((string) ($summary['suppliers_with_purchases'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Paid Suppliers</small><strong><?= e((string) ($summary['suppliers_paid_recently'] ?? 0)) ?></strong></div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Purchases</th>
                    <th>Last Activity</th>
                    <th>Status</th>
                    <th style="width: 220px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($suppliers === []): ?>
                    <tr><td colspan="8">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?= e($supplier['code']) ?></td>
                            <td>
                                <strong><?= e($supplier['name']) ?></strong><br>
                                <small><?= e($supplier['phone'] ?: ($supplier['address'] ?: 'No extra details')) ?></small>
                            </td>
                            <td><?= e($supplier['mobile']) ?></td>
                            <td><?= e($supplier['email']) ?></td>
                            <td class="dt"><?= e((string) ($supplier['purchase_count'] ?? 0)) ?></td>
                            <td>
                                <?php if (!empty($supplier['last_purchase_date'])): ?>
                                    <div><small>Purchase</small> <strong><?= e(date_display((string) $supplier['last_purchase_date'])) ?></strong></div>
                                <?php else: ?>
                                    <div><small>Purchase</small> <strong>—</strong></div>
                                <?php endif; ?>
                                <?php if (!empty($supplier['last_payment_date'])): ?>
                                    <div><small>Payment</small> <strong><?= e(date_display((string) $supplier['last_payment_date'])) ?></strong></div>
                                <?php else: ?>
                                    <div><small>Payment</small> <strong>—</strong></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= e($supplier['status'] === 'active' ? 'green' : 'orange') ?>"><?= e(ucfirst((string) $supplier['status'])) ?></span></td>
                            <td>
                                <div class="table-actions-inline">
                                    <a href="<?= e(base_url('/suppliers/show?id=' . $supplier['id'])) ?>" class="tab active">Statement</a>
                                    <a href="<?= e(base_url('/suppliers/edit?id=' . $supplier['id'])) ?>" class="tab">Edit</a>
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
