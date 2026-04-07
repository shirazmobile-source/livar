<section class="page-head">
    <div>
        <h1>Customers</h1>
        <small>Maintain customer profiles and open each customer statement to review sales, receipts, and current outstanding receivable by currency.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/customers')) ?>" class="inline-search">
            <input type="text" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search code, customer, mobile, email, TRN, or country">
            <button type="submit" class="btn secondary btn-sm">Search</button>
        </form>
        <a href="<?= e(base_url('/customers/create')) ?>" class="btn">New Customer</a>
    </div>
</section>

<section class="stats-grid" style="margin-bottom:12px;">
    <div class="stat-card"><small>Total Customers</small><strong><?= e((string) ($summary['total_customers'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Active Customers</small><strong><?= e((string) ($summary['active_customers'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>With Sales</small><strong><?= e((string) ($summary['customers_with_sales'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Paying Customers</small><strong><?= e((string) ($summary['customers_paid_recently'] ?? 0)) ?></strong></div>
</section>

<section class="card">
    <div class="card-b">
        <div class="table-wrap">
            <table class="table customer-table">
                <thead>
                <tr>
                    <th>Profile</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Mobile</th>
                    <th>Sales</th>
                    <th>Last Activity</th>
                    <th>Status</th>
                    <th style="width: 220px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($customers ?? []) === []): ?>
                    <tr><td colspan="8">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <?php $displayName = $customer['display_name'] ?? $customer['name'] ?? $customer['code']; ?>
                        <tr>
                            <td>
                                <div class="table-thumb-wrap">
                                    <?php if (!empty($customer['profile_image_path'])): ?>
                                        <img src="<?= e(public_upload_url($customer['profile_image_path'])) ?>" alt="<?= e((string) $displayName) ?>" class="table-thumb">
                                    <?php else: ?>
                                        <div class="table-thumb table-thumb-empty"><?= e(strtoupper(substr((string) $displayName, 0, 1))) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= e((string) $displayName) ?></strong>
                                <div class="muted-xs"><?= e((string) ($customer['code'] ?? '')) ?></div>
                                <?php if (!empty($customer['email'])): ?><div class="muted-xs"><?= e((string) $customer['email']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= e(($customer['customer_type'] ?? 'individual') === 'business' ? 'orange' : 'blue') ?>">
                                    <?= e(($customer['customer_type'] ?? 'individual') === 'business' ? 'Business' : 'Individual') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e((string) ($customer['mobile'] ?: '—')) ?></strong>
                                <div class="muted-xs"><?= e((string) ($customer['country_name'] ?: 'Country not detected')) ?></div>
                            </td>
                            <td>
                                <strong><?= e((string) ((int) ($customer['sale_count'] ?? 0))) ?></strong>
                                <div class="muted-xs"><?= e((string) ($customer['trn_number'] ?: 'No TRN')) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($customer['last_sale_date'])): ?>
                                    <div><small>Sale</small> <strong><?= e(date_display((string) $customer['last_sale_date'])) ?></strong></div>
                                <?php else: ?>
                                    <div><small>Sale</small> <strong>—</strong></div>
                                <?php endif; ?>
                                <?php if (!empty($customer['last_receipt_date'])): ?>
                                    <div><small>Receipt</small> <strong><?= e(date_display((string) $customer['last_receipt_date'])) ?></strong></div>
                                <?php else: ?>
                                    <div><small>Receipt</small> <strong>—</strong></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= e(($customer['status'] ?? 'active') === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst((string) ($customer['status'] ?? 'active'))) ?></span></td>
                            <td>
                                <div class="table-actions-inline">
                                    <a href="<?= e(base_url('/customers/show?id=' . $customer['id'])) ?>" class="tab active">Statement</a>
                                    <a href="<?= e(base_url('/customers/edit?id=' . $customer['id'])) ?>" class="tab">Edit</a>
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
