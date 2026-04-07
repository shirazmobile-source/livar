<?php require __DIR__ . '/../partials/form_errors.php'; ?>
<?php $oldReceiveQty = (array) old('receive_qty', []); ?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small><?= e($purchase['invoice_no']) ?> • Receive purchased quantities into the selected warehouse.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>" class="btn secondary">Back</a>
    </div>
</section>

<form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap">
    <?= App\Core\Csrf::field() ?>

    <section class="card">
        <div class="card-h"><h2>Receipt Header</h2></div>
        <div class="card-b">
            <div class="grid-2">
                <div class="field">
                    <label>Warehouse</label>
                    <select name="warehouse_id" required>
                        <option value="">Choose warehouse</option>
                        <?php foreach (($warehouses ?? []) as $warehouse): ?>
                            <option value="<?= e((string) $warehouse['id']) ?>" <?= selected((string) $warehouse['id'], (string) old('warehouse_id', (string) ($warehouses[0]['id'] ?? ''))) ?>><?= e($warehouse['name'] . ' (' . $warehouse['code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Receipt Date</label>
                    <input type="date" name="receipt_date" value="<?= e((string) old('receipt_date', date('Y-m-d'))) ?>" required>
                </div>
            </div>
            <div class="field">
                <label>Note</label>
                <textarea name="note" placeholder="Optional warehouse receipt note"><?= e((string) old('note')) ?></textarea>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-h"><h2>Pending Purchase Lines</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Unit</th>
                        <th>Ordered</th>
                        <th>Received</th>
                        <th>Pending</th>
                        <th>Receive Now</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($items ?? []) as $index => $item): ?>
                        <tr>
                            <td>
                                <?= e($item['product_name']) ?>
                                <input type="hidden" name="purchase_item_id[]" value="<?= e((string) $item['id']) ?>">
                            </td>
                            <td><?= e($item['product_code']) ?></td>
                            <td><?= e($item['unit']) ?></td>
                            <td class="dt"><?= e(money($item['qty'])) ?></td>
                            <td class="dt"><?= e(money($item['received_qty'] ?? 0)) ?></td>
                            <td class="dt"><?= e(money($item['pending_qty'])) ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="<?= e((string) $item['pending_qty']) ?>" name="receive_qty[]" value="<?= e((string) ($oldReceiveQty[$index] ?? $item['pending_qty'])) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="sticky-submit">
        <button type="submit" class="btn">Post Receipt</button>
        <a href="<?= e(base_url('/purchases/show?id=' . $purchase['id'])) ?>" class="btn secondary">Cancel</a>
    </div>
</form>
