<section class="page-head">
    <div>
        <h1>Dashboard</h1>
        <small>Operational overview for sales, purchasing, stock, and profitability.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/sales/create')) ?>" class="btn">New Sale</a>
        <a href="<?= e(base_url('/purchases/create')) ?>" class="btn secondary">New Purchase</a>
    </div>
</section>

<section class="kpi-grid dashboard-kpis">
    <div class="kpi-tile"><div class="kpi-icon">$</div><div class="kpi-copy"><div class="kpi-label">Sales Today</div><div class="kpi-value"><?= e(money($stats['today_sales'])) ?></div></div></div>
    <div class="kpi-tile"><div class="kpi-icon">+</div><div class="kpi-copy"><div class="kpi-label">Purchases Today</div><div class="kpi-value"><?= e(money($stats['today_purchases'])) ?></div></div></div>
    <div class="kpi-tile"><div class="kpi-icon">👥</div><div class="kpi-copy"><div class="kpi-label">Customers</div><div class="kpi-value"><?= e((string) $stats['customers']) ?></div></div></div>
    <div class="kpi-tile"><div class="kpi-icon">⚠</div><div class="kpi-copy"><div class="kpi-label">Low Stock</div><div class="kpi-value"><?= e((string) $stats['low_stock']) ?></div></div></div>
    <div class="kpi-tile"><div class="kpi-icon">▣</div><div class="kpi-copy"><div class="kpi-label">Products</div><div class="kpi-value"><?= e((string) $stats['products']) ?></div></div></div>
    <div class="kpi-tile"><div class="kpi-icon">↗</div><div class="kpi-copy"><div class="kpi-label">Monthly Profit</div><div class="kpi-value"><?= e(money($stats['monthly_profit'])) ?></div></div></div>
</section>

<div class="dashboard-grid">
    <section class="card">
        <div class="card-h">
            <h2>Recent Sales</h2>
            <a href="<?= e(base_url('/sales')) ?>" class="tab active">View all</a>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentSales === []): ?>
                        <tr><td colspan="5">No sales recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><a href="<?= e(base_url('/sales/show?id=' . $sale['id'])) ?>"><?= e($sale['invoice_no']) ?></a></td>
                                <td class="dt"><?= e(date_display($sale['invoice_date'])) ?></td>
                                <td><?= e($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td><span class="badge <?= e($sale['payment_status'] === 'paid' ? 'green' : 'orange') ?>"><?= e(ucfirst($sale['payment_status'])) ?></span></td>
                                <td class="dt"><?= e(money($sale['final_amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-h">
            <h2>Recent Purchases</h2>
            <a href="<?= e(base_url('/purchases')) ?>" class="tab active">View all</a>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentPurchases === []): ?>
                        <tr><td colspan="4">No purchases recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPurchases as $purchase): ?>
                            <tr>
                                <td><a href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>"><?= e($purchase['invoice_no']) ?></a></td>
                                <td class="dt"><?= e(date_display($purchase['invoice_date'])) ?></td>
                                <td><?= e($purchase['supplier_name'] ?? 'Unknown') ?></td>
                                <td class="dt"><?= e(money($purchase['final_amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<section class="card" style="margin-top:12px;">
    <div class="card-h">
        <h2>Top Products — Last 30 Days</h2>
        <a href="<?= e(base_url('/settings/reports?tab=top-products')) ?>" class="tab active">Open reports</a>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Sold Qty</th>
                    <th>Sales Value</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($topProducts === []): ?>
                    <tr><td colspan="3">No product activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($topProducts as $product): ?>
                        <tr>
                            <td><?= e($product['name']) ?></td>
                            <td class="dt"><?= e(money($product['sold_qty'])) ?></td>
                            <td class="dt"><?= e(money($product['sold_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
