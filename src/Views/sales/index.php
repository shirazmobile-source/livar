<style>
.sales-action-cell{
    width:1%;
    white-space:nowrap;
}
.sales-action-group{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    min-width:132px;
}
.sales-action-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:74px;
    min-height:36px;
    padding:0 12px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface-2);
    color:var(--text);
    font-size:13px;
    font-weight:700;
    text-decoration:none;
    transition:transform .16s ease,border-color .16s ease,background .16s ease,box-shadow .16s ease;
}
.sales-action-btn:hover{
    transform:translateY(-1px);
}
.sales-action-btn:focus-visible{
    outline:none;
    box-shadow:0 0 0 3px rgba(255,85,0,.18);
}
.sales-action-btn.primary{
    border-color:rgba(255,85,0,.45);
    background:rgba(255,85,0,.08);
}
.sales-action-btn.secondary{
    color:var(--muted);
}
@media (max-width:899px){
    .sales-action-group{
        min-width:0;
        flex-direction:column;
        align-items:stretch;
    }
    .sales-action-btn{
        width:100%;
    }
}

.sales-toolbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:14px;
    flex-wrap:wrap;
}
.sales-filter-wrap{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.sales-filter-label{
    font-size:13px;
    font-weight:700;
    color:var(--muted);
}
.sales-filter-select{
    min-width:180px;
    height:40px;
    padding:0 40px 0 14px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface-2);
    color:var(--text);
    font-size:14px;
    font-weight:600;
}
.sales-filter-meta{
    font-size:12px;
    color:var(--muted);
}
.sales-empty-filter{
    display:none;
    padding:18px;
    border:1px dashed var(--border);
    border-radius:14px;
    text-align:center;
    color:var(--muted);
    margin-top:14px;
}
@media (max-width:899px){
    .sales-toolbar{
        align-items:stretch;
    }
    .sales-filter-wrap{
        width:100%;
        align-items:stretch;
        flex-direction:column;
    }
    .sales-filter-select{
        width:100%;
    }
}

</style>

<section class="page-head">
    <div>
        <h1>Sales</h1>
        <small>Issue invoices from a selected warehouse, then collect customer payments into a same-currency banking account.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/sales/create')) ?>" class="btn">New Sale</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <div class="sales-toolbar">
            <div class="sales-filter-wrap">
                <label for="sales-status-filter" class="sales-filter-label">Filter by collection</label>
                <select id="sales-status-filter" class="sales-filter-select" data-sales-status-filter>
                    <option value="all">All statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <div class="sales-filter-meta"><strong data-sales-visible-count><?= e((string) count($sales)) ?></strong> invoice(s) visible</div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Warehouse</th>
                    <th>Collection</th>
                    <th>Final</th>
                    <th>Received</th>
                    <th>Outstanding</th>
                    <th class="sales-action-cell">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($sales === []): ?>
                    <tr><td colspan="9">No sales recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <?php
                        $status = (string) ($sale['payment_status'] ?? 'unpaid');
                        $badge = $status === 'paid' ? 'green' : ($status === 'partial' ? 'orange' : '');
                        ?>
                        <tr data-sales-row data-status="<?= e($status) ?>">
                            <td><?= e($sale['invoice_no']) ?></td>
                            <td class="dt"><?= e(date_display($sale['invoice_date'])) ?></td>
                            <td><?= e($sale['customer_name']) ?></td>
                            <td><?= e($sale['warehouse_name'] ?? '—') ?></td>
                            <td><span class="badge <?= e($badge) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td class="dt">
                                <?= e(money_currency($sale['final_amount'], $sale['currency_code'] ?? 'AED')) ?><br>
                                <small>AED <?= e(money($sale['final_amount_aed'] ?? $sale['final_amount'])) ?></small>
                            </td>
                            <td class="dt"><?= e(money_currency($sale['received_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></td>
                            <td class="dt"><?= e(money_currency($sale['due_amount'] ?? 0, $sale['currency_code'] ?? 'AED')) ?></td>
                            <td class="sales-action-cell">
                                <div class="sales-action-group">
                                    <a href="<?= e(base_url('/sales/show?id=' . $sale['id'])) ?>" class="sales-action-btn primary">View</a>
                                    <?php if (($sale['can_receive_payment'] ?? false) === true): ?>
                                        <a href="<?= e(base_url('/sales/receipts/create?sale_id=' . $sale['id'])) ?>" class="sales-action-btn secondary">Receive</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sales-empty-filter" data-sales-empty>No invoices match this status filter.</div>
    </div>
</section>

<script>
(function(){
    const filter = document.querySelector('[data-sales-status-filter]');
    if (!filter) return;
    const rows = Array.from(document.querySelectorAll('[data-sales-row]'));
    const visibleCount = document.querySelector('[data-sales-visible-count]');
    const empty = document.querySelector('[data-sales-empty]');

    const applyFilter = () => {
        const value = String(filter.value || 'all').toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const status = String(row.getAttribute('data-status') || '').toLowerCase();
            const show = value === 'all' || status === value;
            row.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });
        if (visibleCount) visibleCount.textContent = String(visible);
        if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
    };

    filter.addEventListener('change', applyFilter);
    applyFilter();
})();
</script>
