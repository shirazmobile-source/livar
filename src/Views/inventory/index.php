<section class="page-head">
    <div>
        <h1>Inventory</h1>
        <small>Manage warehouses, pending purchase receipts, stock by warehouse, and detailed movement reports.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/inventory/warehouses/create')) ?>" class="btn secondary">New Warehouse</a>
    </div>
</section>

<section class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/inventory')) ?>" class="report-filter inventory-filter-grid">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="field">
                <label>Warehouse</label>
                <select name="warehouse_id">
                    <option value="0">All warehouses</option>
                    <?php foreach (($warehouses ?? []) as $warehouse): ?>
                        <option value="<?= e((string) $warehouse['id']) ?>" <?= selected((string) $warehouse['id'], (string) ($warehouseId ?? 0)) ?>><?= e($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field">
                <label>Search</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Product, warehouse, note">
            </div>
            <div class="field" style="align-self:end;">
                <button type="submit" class="btn secondary">Apply</button>
            </div>
        </form>
    </div>
</section>

<section class="stats-grid inventory-stats-grid">
    <div class="stat-card"><small>Active Warehouses</small><strong><?= e((string) ($summary['warehouses'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Products In Stock</small><strong><?= e((string) ($summary['products_in_stock'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Pending Receipts</small><strong><?= e((string) ($summary['pending_receipts'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Pending Qty</small><strong><?= e(money($summary['pending_qty'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Warehouse Movements</small><strong><?= e((string) ($summary['movement_count'] ?? 0)) ?></strong></div>
    <div class="stat-card"><small>Low Stock Items</small><strong><?= e((string) ($summary['low_stock'] ?? 0)) ?></strong></div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-b">
        <div class="tabs settings-tabs">
            <a href="<?= e(base_url('/inventory?tab=overview&warehouse_id=' . (int) $warehouseId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'overview' ? 'active' : '') ?>">Overview</a>
            <a href="<?= e(base_url('/inventory?tab=warehouses&warehouse_id=' . (int) $warehouseId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'warehouses' ? 'active' : '') ?>">Warehouses</a>
            <a href="<?= e(base_url('/inventory?tab=stock&warehouse_id=' . (int) $warehouseId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'stock' ? 'active' : '') ?>">Stock Report</a>
            <a href="<?= e(base_url('/inventory?tab=movements&warehouse_id=' . (int) $warehouseId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'movements' ? 'active' : '') ?>">Movements</a>
            <a href="<?= e(base_url('/inventory?tab=pending&warehouse_id=' . (int) $warehouseId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'pending' ? 'active' : '') ?>">Pending Receipts</a>
        </div>
    </div>
</section>

<?php if ($tab === 'warehouses'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h">
            <h2>Warehouses</h2>
            <a href="<?= e(base_url('/inventory/warehouses/create')) ?>" class="btn secondary btn-sm">Create Warehouse</a>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>SKUs</th>
                        <th>On Hand</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($warehouses ?? []) as $warehouse): ?>
                        <tr>
                            <td><?= e($warehouse['code']) ?><?= (int) ($warehouse['is_default'] ?? 0) === 1 ? ' • Default' : '' ?></td>
                            <td><?= e($warehouse['name']) ?></td>
                            <td><?= e($warehouse['location'] ?: '—') ?></td>
                            <td><?= e($warehouse['manager_name'] ?: '—') ?></td>
                            <td><span class="badge <?= e(($warehouse['status'] ?? 'active') === 'active' ? 'green' : 'orange') ?>"><?= e(ucfirst((string) ($warehouse['status'] ?? 'active'))) ?></span></td>
                            <td class="dt"><?= e((string) ($warehouse['sku_count'] ?? 0)) ?></td>
                            <td class="dt"><?= e(money($warehouse['on_hand_qty'] ?? 0)) ?></td>
                            <td><a class="tab active" href="<?= e(base_url('/inventory/warehouses/edit?id=' . $warehouse['id'])) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php elseif ($tab === 'stock'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Warehouse Stock Report</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Unit</th>
                        <th>Warehouse Qty</th>
                        <th>Company Qty</th>
                        <th>Min</th>
                        <th>Purchase AED</th>
                        <th>Sale AED</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($stockRows ?? []) === []): ?>
                        <tr><td colspan="10">No warehouse stock rows found.</td></tr>
                    <?php else: ?>
                        <?php foreach (($stockRows ?? []) as $row): ?>
                            <tr>
                                <td><?= e($row['warehouse_name']) ?></td>
                                <td><?= e($row['product_name']) ?></td>
                                <td><?= e($row['product_code']) ?></td>
                                <td><?= e($row['unit']) ?></td>
                                <td class="dt"><?= e(money($row['qty'])) ?></td>
                                <td class="dt"><?= e(money($row['company_stock'])) ?></td>
                                <td class="dt"><?= e(money($row['min_stock'])) ?></td>
                                <td class="dt"><?= e(money($row['purchase_price'])) ?></td>
                                <td class="dt"><?= e(money($row['sale_price'])) ?></td>
                                <td><span class="badge <?= e(($row['stock_status'] ?? 'OK') === 'Low' ? 'orange' : 'green') ?>"><?= e($row['stock_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php elseif ($tab === 'movements'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Warehouse Movement Ledger</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Ref</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance After</th>
                        <th>Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($movements ?? []) === []): ?>
                        <tr><td colspan="9">No warehouse movements found for the selected filter.</td></tr>
                    <?php else: ?>
                        <?php foreach (($movements ?? []) as $movement): ?>
                            <tr>
                                <td><?= e(date_display($movement['created_at'], 'Y-m-d H:i')) ?></td>
                                <td><?= e($movement['warehouse_name']) ?></td>
                                <td><?= e($movement['product_name'] . ' (' . $movement['product_code'] . ')') ?></td>
                                <td><?= e(ucfirst((string) $movement['type'])) ?></td>
                                <td><?= e((string) ($movement['ref_type'] ?? '—') . ' #' . (string) ($movement['ref_id'] ?? '')) ?></td>
                                <td class="dt"><?= e(money($movement['qty_in'])) ?></td>
                                <td class="dt"><?= e(money($movement['qty_out'])) ?></td>
                                <td class="dt"><?= e(money($movement['balance_after'])) ?></td>
                                <td><?= e($movement['note'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php elseif ($tab === 'pending'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Pending Purchase Receipts</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Ordered Qty</th>
                        <th>Received Qty</th>
                        <th>Pending Qty</th>
                        <th>AED Final</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($pendingReceipts ?? []) === []): ?>
                        <tr><td colspan="9">No pending purchase receipts at the moment.</td></tr>
                    <?php else: ?>
                        <?php foreach (($pendingReceipts ?? []) as $purchase): ?>
                            <?php $status = (string) ($purchase['receipt_status'] ?? 'pending'); ?>
                            <tr>
                                <td><?= e($purchase['invoice_no']) ?></td>
                                <td class="dt"><?= e(date_display($purchase['invoice_date'])) ?></td>
                                <td><?= e($purchase['supplier_name']) ?></td>
                                <td><span class="badge <?= e($status === 'partial' ? 'orange' : '') ?>"><?= e(ucfirst($status)) ?></span></td>
                                <td class="dt"><?= e(money($purchase['ordered_qty'])) ?></td>
                                <td class="dt"><?= e(money($purchase['received_qty'])) ?></td>
                                <td class="dt"><?= e(money($purchase['pending_qty'])) ?></td>
                                <td class="dt"><?= e(money($purchase['final_amount_aed'])) ?></td>
                                <td>
                                    <div class="table-actions-inline">
                                        <a class="tab active" href="<?= e(base_url('/inventory/receipts/create?purchase_id=' . $purchase['id'])) ?>">Receive</a>
                                        <a class="tab" href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>">Open</a>
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
<?php else: ?>
    <section class="inventory-two-col">
        <section class="card">
            <div class="card-h"><h2>Warehouse Capacity Snapshot</h2></div>
            <div class="card-b">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Warehouse</th>
                            <th>SKUs</th>
                            <th>On Hand Qty</th>
                            <th>Cost Value AED</th>
                            <th>Sale Value AED</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($warehousePerformance ?? []) as $warehouse): ?>
                            <tr>
                                <td><?= e($warehouse['name']) ?></td>
                                <td class="dt"><?= e((string) ($warehouse['sku_count'] ?? 0)) ?></td>
                                <td class="dt"><?= e(money($warehouse['on_hand_qty'] ?? 0)) ?></td>
                                <td class="dt"><?= e(money($warehouse['stock_cost_aed'] ?? 0)) ?></td>
                                <td class="dt"><?= e(money($warehouse['stock_sale_value_aed'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-h"><h2>Next Actions</h2></div>
            <div class="card-b stack-gap-sm">
                <div class="note-box">
                    <strong>Receive pending purchases</strong>
                    <p>Purchases do not increase stock automatically anymore. Use the Pending Receipts tab to receive goods into a specific warehouse.</p>
                </div>
                <div class="note-box">
                    <strong>Issue sales from the correct warehouse</strong>
                    <p>Every new sale now deducts stock from the warehouse selected on the invoice header, keeping balances aligned.</p>
                </div>
                <div class="note-box">
                    <strong>Use warehouse stock for operational decisions</strong>
                    <p>The Stock Report tab shows the exact on-hand quantity per warehouse for each product, alongside min stock and AED values.</p>
                </div>
            </div>
        </section>
    </section>

    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Recent Warehouse Movements</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($movements ?? []) === []): ?>
                        <tr><td colspan="7">No warehouse movements posted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($movements, 0, 12) as $movement): ?>
                            <tr>
                                <td><?= e(date_display($movement['created_at'], 'Y-m-d H:i')) ?></td>
                                <td><?= e($movement['warehouse_name']) ?></td>
                                <td><?= e($movement['product_name']) ?></td>
                                <td><?= e(ucfirst((string) $movement['type'])) ?></td>
                                <td class="dt"><?= e(money($movement['qty_in'])) ?></td>
                                <td class="dt"><?= e(money($movement['qty_out'])) ?></td>
                                <td class="dt"><?= e(money($movement['balance_after'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
