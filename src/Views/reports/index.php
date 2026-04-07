<section class="page-head">
    <div>
        <h1>Setting / Reports</h1>
        <small>Filter operational data by date and review stock, sales, purchases, and profitability. This page now lives under Settings, and all totals are shown in AED base.</small>
    </div>
</section>

<?php require __DIR__ . '/../settings/partials/nav.php'; ?>

<section class="card">
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/settings/reports')) ?>" class="report-filter">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="field">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field actions-field">
                <button type="submit" class="btn">Apply Filter</button>
            </div>
        </form>

        <div class="tabs report-tabs">
            <a href="<?= e(base_url('/settings/reports?tab=overview&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'overview' ? 'active' : '') ?>">Overview</a>
            <a href="<?= e(base_url('/settings/reports?tab=sales&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'sales' ? 'active' : '') ?>">Sales</a>
            <a href="<?= e(base_url('/settings/reports?tab=purchases&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'purchases' ? 'active' : '') ?>">Purchases</a>
            <a href="<?= e(base_url('/settings/reports?tab=inventory&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'inventory' ? 'active' : '') ?>">Inventory</a>
            <a href="<?= e(base_url('/settings/reports?tab=customers&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'customers' ? 'active' : '') ?>">Customers</a>
            <a href="<?= e(base_url('/settings/reports?tab=suppliers&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'suppliers' ? 'active' : '') ?>">Suppliers</a>
            <a href="<?= e(base_url('/settings/reports?tab=top-products&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'top-products' ? 'active' : '') ?>">Top Products</a>
            <a href="<?= e(base_url('/settings/reports?tab=movements&from=' . $from . '&to=' . $to)) ?>" class="tab <?= e($tab === 'movements' ? 'active' : '') ?>">Movements</a>
        </div>
    </div>
</section>

<?php if ($tab === 'overview'): ?>
    <section class="kpi-grid dashboard-kpis" style="margin-top:12px;">
        <div class="kpi-tile"><div class="kpi-icon">$</div><div class="kpi-copy"><div class="kpi-label">Sales Total (AED)</div><div class="kpi-value"><?= e(money($overview['sales_total'])) ?></div></div></div>
        <div class="kpi-tile"><div class="kpi-icon">+</div><div class="kpi-copy"><div class="kpi-label">Purchase Total (AED)</div><div class="kpi-value"><?= e(money($overview['purchase_total'])) ?></div></div></div>
        <div class="kpi-tile"><div class="kpi-icon">↗</div><div class="kpi-copy"><div class="kpi-label">Gross Profit (AED)</div><div class="kpi-value"><?= e(money($overview['gross_profit'])) ?></div></div></div>
        <div class="kpi-tile"><div class="kpi-icon">#</div><div class="kpi-copy"><div class="kpi-label">Sales Count</div><div class="kpi-value"><?= e((string) (int) $overview['sales_count']) ?></div></div></div>
        <div class="kpi-tile"><div class="kpi-icon">#</div><div class="kpi-copy"><div class="kpi-label">Purchase Count</div><div class="kpi-value"><?= e((string) (int) $overview['purchase_count']) ?></div></div></div>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:12px;">
    <div class="card-b">
        <?php if ($tab === 'overview' || $tab === 'sales'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Invoice Count</th><th>Total Amount (AED)</th></tr></thead>
                    <tbody>
                    <?php foreach ($salesSummary as $row): ?>
                        <tr>
                            <td class="dt"><?= e(date_display($row['invoice_date'])) ?></td>
                            <td class="dt"><?= e((string) $row['invoice_count']) ?></td>
                            <td class="dt"><?= e(money($row['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($salesSummary === []): ?><tr><td colspan="3">No sales data in the selected period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'purchases'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Invoice Count</th><th>Total Amount (AED)</th></tr></thead>
                    <tbody>
                    <?php foreach ($purchaseSummary as $row): ?>
                        <tr>
                            <td class="dt"><?= e(date_display($row['invoice_date'])) ?></td>
                            <td class="dt"><?= e((string) $row['invoice_count']) ?></td>
                            <td class="dt"><?= e(money($row['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($purchaseSummary === []): ?><tr><td colspan="3">No purchase data in the selected period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'inventory'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Code</th><th>Product</th><th>Category</th><th>Unit</th><th>Purchase (AED)</th><th>Sale (AED)</th><th>Stock</th><th>Min. Stock</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($inventory as $row): ?>
                        <tr>
                            <td><?= e($row['code']) ?></td>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['category']) ?></td>
                            <td><?= e($row['unit']) ?></td>
                            <td class="dt"><?= e(money($row['purchase_price'])) ?></td>
                            <td class="dt"><?= e(money($row['sale_price'])) ?></td>
                            <td class="dt"><?= e(money($row['current_stock'])) ?></td>
                            <td class="dt"><?= e(money($row['min_stock'])) ?></td>
                            <td><span class="badge <?= e($row['stock_status'] === 'Low' ? 'red' : 'green') ?>"><?= e($row['stock_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'customers'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Customer</th><th>Invoice Count</th><th>Total Sales (AED)</th></tr></thead>
                    <tbody>
                    <?php foreach ($customerSales as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="dt"><?= e((string) $row['invoice_count']) ?></td>
                            <td class="dt"><?= e(money($row['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($customerSales === []): ?><tr><td colspan="3">No customer sales data available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'suppliers'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Supplier</th><th>Invoice Count</th><th>Total Purchases (AED)</th></tr></thead>
                    <tbody>
                    <?php foreach ($supplierPurchases as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="dt"><?= e((string) $row['invoice_count']) ?></td>
                            <td class="dt"><?= e(money($row['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($supplierPurchases === []): ?><tr><td colspan="3">No supplier purchase data available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'top-products'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Product</th><th>Sold Qty</th><th>Sales Amount (AED)</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="dt"><?= e(money($row['sold_qty'])) ?></td>
                            <td class="dt"><?= e(money($row['sold_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($topProducts === []): ?><tr><td colspan="3">No product sales data available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'movements'): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Created At</th><th>Product</th><th>Type</th><th>Qty In</th><th>Qty Out</th><th>Balance</th><th>Note</th></tr></thead>
                    <tbody>
                    <?php foreach ($stockMovements as $row): ?>
                        <tr>
                            <td class="dt"><?= e(date_display($row['created_at'], 'Y-m-d H:i')) ?></td>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= e(ucfirst($row['type'])) ?></td>
                            <td class="dt"><?= e(money($row['qty_in'])) ?></td>
                            <td class="dt"><?= e(money($row['qty_out'])) ?></td>
                            <td class="dt"><?= e(money($row['balance_after'])) ?></td>
                            <td><?= e($row['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($stockMovements === []): ?><tr><td colspan="7">No stock movement records found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
