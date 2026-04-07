<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class InventoryService
{
    public static function purchase(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $invoiceNo = InvoiceNumber::generate('purchases', 'invoice_no', 'PUR');

            $headerStatement = $pdo->prepare('
                INSERT INTO purchases (
                    supplier_id, invoice_no, invoice_date,
                    currency_id, currency_code, currency_symbol, currency_rate_to_aed,
                    total_amount, discount_amount, final_amount,
                    total_amount_aed, discount_amount_aed, final_amount_aed,
                    receipt_status,
                    note, created_by, created_at
                ) VALUES (
                    :supplier_id, :invoice_no, :invoice_date,
                    :currency_id, :currency_code, :currency_symbol, :currency_rate_to_aed,
                    :total_amount, :discount_amount, :final_amount,
                    :total_amount_aed, :discount_amount_aed, :final_amount_aed,
                    :receipt_status,
                    :note, :created_by, NOW()
                )
            ');

            $headerStatement->execute([
                'supplier_id' => $payload['supplier_id'],
                'invoice_no' => $invoiceNo,
                'invoice_date' => $payload['invoice_date'],
                'currency_id' => $payload['currency_id'],
                'currency_code' => $payload['currency_code'],
                'currency_symbol' => $payload['currency_symbol'],
                'currency_rate_to_aed' => $payload['currency_rate_to_aed'],
                'total_amount' => $payload['total_amount'],
                'discount_amount' => $payload['discount_amount'],
                'final_amount' => $payload['final_amount'],
                'total_amount_aed' => $payload['total_amount_aed'],
                'discount_amount_aed' => $payload['discount_amount_aed'],
                'final_amount_aed' => $payload['final_amount_aed'],
                'receipt_status' => 'pending',
                'note' => $payload['note'],
                'created_by' => $payload['created_by'],
            ]);

            $purchaseId = (int) $pdo->lastInsertId();

            $itemStatement = $pdo->prepare('
                INSERT INTO purchase_items (
                    purchase_id, product_id, display_qty, pricing_unit, units_per_box,
                    qty, unit_price, unit_price_aed, total_price, total_price_aed, created_at, updated_at
                ) VALUES (
                    :purchase_id, :product_id, :display_qty, :pricing_unit, :units_per_box,
                    :qty, :unit_price, :unit_price_aed, :total_price, :total_price_aed, NOW(), NOW()
                )
            ');

            $productStatement = $pdo->prepare('SELECT id, COALESCE(item_type, "inventory") AS item_type FROM products WHERE id = :id LIMIT 1');
            $productUpdate = $pdo->prepare('
                UPDATE products
                SET purchase_price = :purchase_price,
                    purchase_price_display = :purchase_price_display,
                    updated_at = NOW()
                WHERE id = :id
            ');

            foreach ($payload['items'] as $item) {
                $productStatement->execute(['id' => $item['product_id']]);
                $product = $productStatement->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException('One of the selected products could not be found.');
                }

                $itemStatement->execute([
                    'purchase_id' => $purchaseId,
                    'product_id' => $item['product_id'],
                    'display_qty' => $item['display_qty'] ?? $item['qty'],
                    'pricing_unit' => $item['pricing_unit'] ?? 'unit',
                    'units_per_box' => $item['units_per_box'] ?? 1,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'unit_price_aed' => $item['unit_price_aed'],
                    'total_price' => $item['total_price'],
                    'total_price_aed' => $item['total_price_aed'],
                ]);

                $productUpdate->execute([
                    'purchase_price' => $item['unit_price_aed'],
                    'purchase_price_display' => $item['unit_price_aed'],
                    'id' => $item['product_id'],
                ]);
            }

            $pdo->commit();

            return $purchaseId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }


    public static function updatePendingPurchase(int $purchaseId, array $payload): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $purchaseStatement = $pdo->prepare('SELECT * FROM purchases WHERE id = :id LIMIT 1 FOR UPDATE');
            $purchaseStatement->execute(['id' => $purchaseId]);
            $purchase = $purchaseStatement->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                throw new RuntimeException('Purchase invoice not found.');
            }

            if ((string) ($purchase['receipt_status'] ?? 'pending') !== 'pending') {
                throw new RuntimeException('Only purchases that are still pending can be edited.');
            }

            $receiptCountStatement = $pdo->prepare('SELECT COUNT(*) FROM inventory_receipts WHERE purchase_id = :purchase_id');
            $receiptCountStatement->execute(['purchase_id' => $purchaseId]);
            $receiptCount = (int) $receiptCountStatement->fetchColumn();
            if ($receiptCount > 0) {
                throw new RuntimeException('This purchase is locked because a warehouse receipt has already been posted.');
            }

            $receivedQtyStatement = $pdo->prepare('SELECT COALESCE(SUM(received_qty), 0) FROM purchase_items WHERE purchase_id = :purchase_id');
            $receivedQtyStatement->execute(['purchase_id' => $purchaseId]);
            $receivedQty = (float) $receivedQtyStatement->fetchColumn();
            if ($receivedQty > 0.0001) {
                throw new RuntimeException('This purchase is locked because warehouse receipt activity has already started.');
            }

            $existingItemsStatement = $pdo->prepare('SELECT product_id FROM purchase_items WHERE purchase_id = :purchase_id');
            $existingItemsStatement->execute(['purchase_id' => $purchaseId]);
            $oldProductIds = array_map(static fn (array $row): int => (int) $row['product_id'], $existingItemsStatement->fetchAll(PDO::FETCH_ASSOC));

            $headerStatement = $pdo->prepare('
                UPDATE purchases
                SET supplier_id = :supplier_id,
                    invoice_date = :invoice_date,
                    currency_id = :currency_id,
                    currency_code = :currency_code,
                    currency_symbol = :currency_symbol,
                    currency_rate_to_aed = :currency_rate_to_aed,
                    total_amount = :total_amount,
                    discount_amount = :discount_amount,
                    final_amount = :final_amount,
                    total_amount_aed = :total_amount_aed,
                    discount_amount_aed = :discount_amount_aed,
                    final_amount_aed = :final_amount_aed,
                    note = :note,
                    updated_at = NOW()
                WHERE id = :id
            ');

            $headerStatement->execute([
                'supplier_id' => $payload['supplier_id'],
                'invoice_date' => $payload['invoice_date'],
                'currency_id' => $payload['currency_id'],
                'currency_code' => $payload['currency_code'],
                'currency_symbol' => $payload['currency_symbol'],
                'currency_rate_to_aed' => $payload['currency_rate_to_aed'],
                'total_amount' => $payload['total_amount'],
                'discount_amount' => $payload['discount_amount'],
                'final_amount' => $payload['final_amount'],
                'total_amount_aed' => $payload['total_amount_aed'],
                'discount_amount_aed' => $payload['discount_amount_aed'],
                'final_amount_aed' => $payload['final_amount_aed'],
                'note' => $payload['note'],
                'id' => $purchaseId,
            ]);

            $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = :purchase_id')->execute(['purchase_id' => $purchaseId]);

            $itemStatement = $pdo->prepare('
                INSERT INTO purchase_items (
                    purchase_id, product_id, display_qty, pricing_unit, units_per_box,
                    qty, unit_price, unit_price_aed, total_price, total_price_aed, created_at, updated_at
                ) VALUES (
                    :purchase_id, :product_id, :display_qty, :pricing_unit, :units_per_box,
                    :qty, :unit_price, :unit_price_aed, :total_price, :total_price_aed, NOW(), NOW()
                )
            ');

            $productStatement = $pdo->prepare('SELECT id, COALESCE(item_type, "inventory") AS item_type FROM products WHERE id = :id LIMIT 1');
            $newProductIds = [];

            foreach ($payload['items'] as $item) {
                $productStatement->execute(['id' => $item['product_id']]);
                $product = $productStatement->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException('One of the selected products could not be found.');
                }

                $itemStatement->execute([
                    'purchase_id' => $purchaseId,
                    'product_id' => $item['product_id'],
                    'display_qty' => $item['display_qty'] ?? $item['qty'],
                    'pricing_unit' => $item['pricing_unit'] ?? 'unit',
                    'units_per_box' => $item['units_per_box'] ?? 1,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'unit_price_aed' => $item['unit_price_aed'],
                    'total_price' => $item['total_price'],
                    'total_price_aed' => $item['total_price_aed'],
                ]);

                $newProductIds[] = (int) $item['product_id'];
            }

            self::refreshProductPurchasePrices($pdo, array_merge($oldProductIds, $newProductIds));

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function receivePurchase(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $purchaseStatement = $pdo->prepare('SELECT * FROM purchases WHERE id = :id LIMIT 1');
            $purchaseStatement->execute(['id' => $payload['purchase_id']]);
            $purchase = $purchaseStatement->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Purchase invoice not found.');
            }

            $warehouseStatement = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id AND status = "active" LIMIT 1');
            $warehouseStatement->execute(['id' => $payload['warehouse_id']]);
            $warehouse = $warehouseStatement->fetch(PDO::FETCH_ASSOC);
            if (!$warehouse) {
                throw new RuntimeException('Selected warehouse is not available.');
            }

            $receiptNo = InvoiceNumber::generate('inventory_receipts', 'receipt_no', 'REC');
            $receiptHeader = $pdo->prepare('
                INSERT INTO inventory_receipts (
                    purchase_id, warehouse_id, receipt_no, receipt_date, note, created_by, created_at, updated_at
                ) VALUES (
                    :purchase_id, :warehouse_id, :receipt_no, :receipt_date, :note, :created_by, NOW(), NOW()
                )
            ');
            $receiptHeader->execute([
                'purchase_id' => $payload['purchase_id'],
                'warehouse_id' => $payload['warehouse_id'],
                'receipt_no' => $receiptNo,
                'receipt_date' => $payload['receipt_date'],
                'note' => $payload['note'],
                'created_by' => $payload['created_by'],
            ]);
            $receiptId = (int) $pdo->lastInsertId();

            $purchaseItemStatement = $pdo->prepare('
                SELECT pi.*, pr.name AS product_name, pr.current_stock, pr.purchase_price, COALESCE(pr.item_type, "inventory") AS item_type
                FROM purchase_items pi
                INNER JOIN products pr ON pr.id = pi.product_id
                WHERE pi.id = :id AND pi.purchase_id = :purchase_id
                LIMIT 1
            ');
            $receiptItemInsert = $pdo->prepare('
                INSERT INTO inventory_receipt_items (
                    receipt_id, purchase_item_id, product_id, qty, unit_cost_aed, created_at, updated_at
                ) VALUES (
                    :receipt_id, :purchase_item_id, :product_id, :qty, :unit_cost_aed, NOW(), NOW()
                )
            ');
            $purchaseItemUpdate = $pdo->prepare('UPDATE purchase_items SET received_qty = :received_qty, updated_at = NOW() WHERE id = :id');
            $productUpdate = $pdo->prepare('UPDATE products SET current_stock = :stock, updated_at = NOW() WHERE id = :id');
            $companyMovement = $pdo->prepare('
                INSERT INTO stock_movements (
                    product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at
                ) VALUES (
                    :product_id, "receipt", "inventory_receipt", :ref_id, :qty_in, 0, :balance_after, :note, NOW(), NOW()
                )
            ');
            $warehouseStockSelect = $pdo->prepare('SELECT id, qty FROM warehouse_stocks WHERE warehouse_id = :warehouse_id AND product_id = :product_id LIMIT 1');
            $warehouseStockInsert = $pdo->prepare('
                INSERT INTO warehouse_stocks (warehouse_id, product_id, qty, created_at, updated_at)
                VALUES (:warehouse_id, :product_id, :qty, NOW(), NOW())
            ');
            $warehouseStockUpdate = $pdo->prepare('UPDATE warehouse_stocks SET qty = :qty, updated_at = NOW() WHERE id = :id');
            $warehouseMovement = $pdo->prepare('
                INSERT INTO warehouse_movements (
                    warehouse_id, product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_by, created_at, updated_at
                ) VALUES (
                    :warehouse_id, :product_id, "receipt", "inventory_receipt", :ref_id, :qty_in, 0, :balance_after, :note, :created_by, NOW(), NOW()
                )
            ');

            $receivedSomething = false;
            foreach ($payload['lines'] as $line) {
                $purchaseItemStatement->execute([
                    'id' => $line['purchase_item_id'],
                    'purchase_id' => $payload['purchase_id'],
                ]);
                $purchaseItem = $purchaseItemStatement->fetch(PDO::FETCH_ASSOC);
                if (!$purchaseItem) {
                    throw new RuntimeException('One of the purchase items could not be found.');
                }

                $pendingQty = (float) $purchaseItem['qty'] - (float) ($purchaseItem['received_qty'] ?? 0);
                $receiveQty = (float) $line['qty'];

                if ($receiveQty <= 0) {
                    continue;
                }

                if ($receiveQty > $pendingQty + 0.0001) {
                    throw new RuntimeException('Receive quantity cannot exceed the pending quantity for product: ' . $purchaseItem['product_name']);
                }

                $receivedSomething = true;
                $newReceivedQty = (float) ($purchaseItem['received_qty'] ?? 0) + $receiveQty;
                $newCompanyStock = (float) $purchaseItem['current_stock'] + $receiveQty;

                $warehouseStockSelect->execute([
                    'warehouse_id' => $payload['warehouse_id'],
                    'product_id' => $purchaseItem['product_id'],
                ]);
                $stockRow = $warehouseStockSelect->fetch(PDO::FETCH_ASSOC);
                $newWarehouseBalance = (float) ($stockRow['qty'] ?? 0) + $receiveQty;

                if ($stockRow) {
                    $warehouseStockUpdate->execute([
                        'qty' => $newWarehouseBalance,
                        'id' => $stockRow['id'],
                    ]);
                } else {
                    $warehouseStockInsert->execute([
                        'warehouse_id' => $payload['warehouse_id'],
                        'product_id' => $purchaseItem['product_id'],
                        'qty' => $newWarehouseBalance,
                    ]);
                }

                $receiptItemInsert->execute([
                    'receipt_id' => $receiptId,
                    'purchase_item_id' => $purchaseItem['id'],
                    'product_id' => $purchaseItem['product_id'],
                    'qty' => $receiveQty,
                    'unit_cost_aed' => $purchaseItem['unit_price_aed'] ?? $purchaseItem['purchase_price'],
                ]);

                $purchaseItemUpdate->execute([
                    'received_qty' => $newReceivedQty,
                    'id' => $purchaseItem['id'],
                ]);

                $productUpdate->execute([
                    'stock' => $newCompanyStock,
                    'id' => $purchaseItem['product_id'],
                ]);

                $companyMovement->execute([
                    'product_id' => $purchaseItem['product_id'],
                    'ref_id' => $receiptId,
                    'qty_in' => $receiveQty,
                    'balance_after' => $newCompanyStock,
                    'note' => 'Warehouse receipt ' . $receiptNo . ' for purchase ' . ($purchase['invoice_no'] ?? ''),
                ]);

                $warehouseMovement->execute([
                    'warehouse_id' => $payload['warehouse_id'],
                    'product_id' => $purchaseItem['product_id'],
                    'ref_id' => $receiptId,
                    'qty_in' => $receiveQty,
                    'balance_after' => $newWarehouseBalance,
                    'note' => 'Purchase receipt ' . ($purchase['invoice_no'] ?? ''),
                    'created_by' => $payload['created_by'],
                ]);
            }

            if (!$receivedSomething) {
                throw new RuntimeException('Enter at least one valid quantity to receive.');
            }

            $remaining = (float) $pdo->query('SELECT COALESCE(SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN (pi.qty - COALESCE(pi.received_qty, 0)) ELSE 0 END), 0) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = ' . (int) $payload['purchase_id'])->fetchColumn();
            $receiptStatus = $remaining <= 0.0001 ? 'received' : 'partial';
            $purchaseUpdate = $pdo->prepare('UPDATE purchases SET receipt_status = :receipt_status, updated_at = NOW() WHERE id = :id');
            $purchaseUpdate->execute([
                'receipt_status' => $receiptStatus,
                'id' => $payload['purchase_id'],
            ]);

            $pdo->commit();

            return $receiptId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }


    public static function returnPurchase(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $purchaseStatement = $pdo->prepare('SELECT * FROM purchases WHERE id = :id LIMIT 1 FOR UPDATE');
            $purchaseStatement->execute(['id' => $payload['purchase_id']]);
            $purchase = $purchaseStatement->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                throw new RuntimeException('Purchase invoice not found.');
            }

            $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                throw new RuntimeException('Please choose a warehouse for this purchase return.');
            }

            $warehouseStatement = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
            $warehouseStatement->execute(['id' => $warehouseId]);
            $warehouse = $warehouseStatement->fetch(PDO::FETCH_ASSOC);
            if (!$warehouse) {
                throw new RuntimeException('Selected warehouse could not be found.');
            }

            $receivedQtyStatement = $pdo->prepare('SELECT COALESCE(SUM(received_qty), 0) FROM purchase_items WHERE purchase_id = :purchase_id');
            $receivedQtyStatement->execute(['purchase_id' => $payload['purchase_id']]);
            $receivedQty = (float) $receivedQtyStatement->fetchColumn();
            if ($receivedQty <= 0.0001) {
                throw new RuntimeException('This purchase has not been received into inventory yet. A purchase return is allowed only after receipt.');
            }

            $returnNo = InvoiceNumber::generate('purchase_returns', 'return_no', 'PRN');

            $headerInsert = $pdo->prepare('
                INSERT INTO purchase_returns (
                    purchase_id, warehouse_id, return_no, return_date,
                    currency_id, currency_code, currency_symbol, currency_rate_to_aed,
                    total_qty, total_amount, total_amount_aed,
                    reason, note, created_by, created_at, updated_at
                ) VALUES (
                    :purchase_id, :warehouse_id, :return_no, :return_date,
                    :currency_id, :currency_code, :currency_symbol, :currency_rate_to_aed,
                    0, 0, 0,
                    :reason, :note, :created_by, NOW(), NOW()
                )
            ');
            $headerInsert->execute([
                'purchase_id' => $payload['purchase_id'],
                'warehouse_id' => $warehouseId,
                'return_no' => $returnNo,
                'return_date' => $payload['return_date'],
                'currency_id' => $purchase['currency_id'] ?? null,
                'currency_code' => $purchase['currency_code'] ?? 'AED',
                'currency_symbol' => $purchase['currency_symbol'] ?? 'د.إ',
                'currency_rate_to_aed' => $purchase['currency_rate_to_aed'] ?? 1,
                'reason' => $payload['reason'] ?? '',
                'note' => $payload['note'] ?? '',
                'created_by' => $payload['created_by'] ?? null,
            ]);
            $purchaseReturnId = (int) $pdo->lastInsertId();

            $purchaseItemStatement = $pdo->prepare('
                SELECT pi.*, pr.name AS product_name, pr.code AS product_code, pr.current_stock
                FROM purchase_items pi
                INNER JOIN products pr ON pr.id = pi.product_id
                WHERE pi.id = :id AND pi.purchase_id = :purchase_id
                LIMIT 1
            ');
            $receivedInWarehouseStatement = $pdo->prepare('
                SELECT COALESCE(SUM(iri.qty), 0)
                FROM inventory_receipt_items iri
                INNER JOIN inventory_receipts ir ON ir.id = iri.receipt_id
                WHERE ir.purchase_id = :purchase_id
                  AND ir.warehouse_id = :warehouse_id
                  AND iri.purchase_item_id = :purchase_item_id
            ');
            $returnedInWarehouseStatement = $pdo->prepare('
                SELECT COALESCE(SUM(pri.qty), 0)
                FROM purchase_return_items pri
                INNER JOIN purchase_returns prt ON prt.id = pri.purchase_return_id
                WHERE prt.purchase_id = :purchase_id
                  AND prt.warehouse_id = :warehouse_id
                  AND pri.purchase_item_id = :purchase_item_id
            ');
            $warehouseStockSelect = $pdo->prepare('SELECT id, qty FROM warehouse_stocks WHERE warehouse_id = :warehouse_id AND product_id = :product_id LIMIT 1 FOR UPDATE');
            $warehouseStockUpdate = $pdo->prepare('UPDATE warehouse_stocks SET qty = :qty, updated_at = NOW() WHERE id = :id');
            $productUpdate = $pdo->prepare('UPDATE products SET current_stock = :stock, updated_at = NOW() WHERE id = :id');
            $purchaseItemUpdate = $pdo->prepare('UPDATE purchase_items SET returned_qty = :returned_qty, updated_at = NOW() WHERE id = :id');
            $returnItemInsert = $pdo->prepare('
                INSERT INTO purchase_return_items (
                    purchase_return_id, purchase_item_id, product_id,
                    qty, unit_price, unit_price_aed, total_price, total_price_aed,
                    created_at, updated_at
                ) VALUES (
                    :purchase_return_id, :purchase_item_id, :product_id,
                    :qty, :unit_price, :unit_price_aed, :total_price, :total_price_aed,
                    NOW(), NOW()
                )
            ');
            $companyMovement = $pdo->prepare('
                INSERT INTO stock_movements (
                    product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at
                ) VALUES (
                    :product_id, "purchase_return", "purchase_return", :ref_id, 0, :qty_out, :balance_after, :note, NOW(), NOW()
                )
            ');
            $warehouseMovement = $pdo->prepare('
                INSERT INTO warehouse_movements (
                    warehouse_id, product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_by, created_at, updated_at
                ) VALUES (
                    :warehouse_id, :product_id, "return", "purchase_return", :ref_id, 0, :qty_out, :balance_after, :note, :created_by, NOW(), NOW()
                )
            ');

            $returnedSomething = false;
            $totalQty = 0.0;
            $totalAmount = 0.0;
            $totalAmountAed = 0.0;

            foreach ($payload['lines'] as $line) {
                $returnQty = (float) ($line['qty'] ?? 0);
                if ($returnQty <= 0) {
                    continue;
                }

                $purchaseItemStatement->execute([
                    'id' => $line['purchase_item_id'],
                    'purchase_id' => $payload['purchase_id'],
                ]);
                $purchaseItem = $purchaseItemStatement->fetch(PDO::FETCH_ASSOC);
                if (!$purchaseItem) {
                    throw new RuntimeException('One of the purchase items could not be found.');
                }

                $receivedInWarehouseStatement->execute([
                    'purchase_id' => $payload['purchase_id'],
                    'warehouse_id' => $warehouseId,
                    'purchase_item_id' => $purchaseItem['id'],
                ]);
                $receivedInWarehouse = (float) $receivedInWarehouseStatement->fetchColumn();

                $returnedInWarehouseStatement->execute([
                    'purchase_id' => $payload['purchase_id'],
                    'warehouse_id' => $warehouseId,
                    'purchase_item_id' => $purchaseItem['id'],
                ]);
                $returnedInWarehouse = (float) $returnedInWarehouseStatement->fetchColumn();

                $historyReturnable = max(0.0, $receivedInWarehouse - $returnedInWarehouse);

                $warehouseStockSelect->execute([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $purchaseItem['product_id'],
                ]);
                $warehouseStock = $warehouseStockSelect->fetch(PDO::FETCH_ASSOC);
                $warehouseQty = (float) ($warehouseStock['qty'] ?? 0);
                $companyQty = (float) ($purchaseItem['current_stock'] ?? 0);
                $allowedQty = min($historyReturnable, $warehouseQty, $companyQty);

                if ($allowedQty <= 0.0001) {
                    throw new RuntimeException('No returnable stock is currently available in warehouse ' . ($warehouse['name'] ?? '') . ' for product: ' . $purchaseItem['product_name']);
                }

                if ($returnQty > $allowedQty + 0.0001) {
                    throw new RuntimeException('Return quantity cannot exceed the available returnable stock for product: ' . $purchaseItem['product_name']);
                }

                if (!$warehouseStock) {
                    throw new RuntimeException('Warehouse stock record was not found for product: ' . $purchaseItem['product_name']);
                }

                $returnedSomething = true;
                $newWarehouseQty = $warehouseQty - $returnQty;
                $newCompanyQty = $companyQty - $returnQty;
                $newReturnedQty = (float) ($purchaseItem['returned_qty'] ?? 0) + $returnQty;
                $lineTotal = round((float) ($purchaseItem['unit_price'] ?? 0) * $returnQty, 2);
                $lineTotalAed = round((float) ($purchaseItem['unit_price_aed'] ?? 0) * $returnQty, 2);

                $returnItemInsert->execute([
                    'purchase_return_id' => $purchaseReturnId,
                    'purchase_item_id' => $purchaseItem['id'],
                    'product_id' => $purchaseItem['product_id'],
                    'qty' => $returnQty,
                    'unit_price' => $purchaseItem['unit_price'],
                    'unit_price_aed' => $purchaseItem['unit_price_aed'] ?? $purchaseItem['unit_price'],
                    'total_price' => $lineTotal,
                    'total_price_aed' => $lineTotalAed,
                ]);

                $warehouseStockUpdate->execute([
                    'qty' => $newWarehouseQty,
                    'id' => $warehouseStock['id'],
                ]);

                $productUpdate->execute([
                    'stock' => $newCompanyQty,
                    'id' => $purchaseItem['product_id'],
                ]);

                $purchaseItemUpdate->execute([
                    'returned_qty' => $newReturnedQty,
                    'id' => $purchaseItem['id'],
                ]);

                $companyMovement->execute([
                    'product_id' => $purchaseItem['product_id'],
                    'ref_id' => $purchaseReturnId,
                    'qty_out' => $returnQty,
                    'balance_after' => $newCompanyQty,
                    'note' => 'Purchase return ' . $returnNo . ' against purchase ' . ($purchase['invoice_no'] ?? ''),
                ]);

                $warehouseMovement->execute([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $purchaseItem['product_id'],
                    'ref_id' => $purchaseReturnId,
                    'qty_out' => $returnQty,
                    'balance_after' => $newWarehouseQty,
                    'note' => 'Purchase return ' . ($purchase['invoice_no'] ?? ''),
                    'created_by' => $payload['created_by'] ?? null,
                ]);

                $totalQty += $returnQty;
                $totalAmount += $lineTotal;
                $totalAmountAed += $lineTotalAed;
            }

            if (!$returnedSomething) {
                throw new RuntimeException('Enter at least one valid quantity to return.');
            }

            $headerUpdate = $pdo->prepare('
                UPDATE purchase_returns
                SET total_qty = :total_qty,
                    total_amount = :total_amount,
                    total_amount_aed = :total_amount_aed,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $headerUpdate->execute([
                'total_qty' => round($totalQty, 2),
                'total_amount' => round($totalAmount, 2),
                'total_amount_aed' => round($totalAmountAed, 2),
                'id' => $purchaseReturnId,
            ]);

            $returnedTotalsStatement = $pdo->prepare('SELECT COALESCE(SUM(returned_qty), 0) FROM purchase_items WHERE purchase_id = :purchase_id');
            $returnedTotalsStatement->execute(['purchase_id' => $payload['purchase_id']]);
            $returnedQty = (float) $returnedTotalsStatement->fetchColumn();

            $returnStatus = 'none';
            if ($returnedQty > 0.0001) {
                $returnStatus = $returnedQty >= $receivedQty - 0.0001 ? 'returned' : 'partial';
            }

            $purchaseStatusUpdate = $pdo->prepare('UPDATE purchases SET return_status = :return_status, updated_at = NOW() WHERE id = :id');
            $purchaseStatusUpdate->execute([
                'return_status' => $returnStatus,
                'id' => $payload['purchase_id'],
            ]);

            $pdo->commit();

            return $purchaseReturnId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function sale(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $invoiceNo = InvoiceNumber::generate('sales', 'invoice_no', 'SAL');

            $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                throw new RuntimeException('Please choose a warehouse for this sale.');
            }

            $warehouseStatement = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id AND status = "active" LIMIT 1');
            $warehouseStatement->execute(['id' => $warehouseId]);
            $warehouse = $warehouseStatement->fetch(PDO::FETCH_ASSOC);
            if (!$warehouse) {
                throw new RuntimeException('Selected warehouse is not available.');
            }

            $headerStatement = $pdo->prepare('
                INSERT INTO sales (
                    customer_id, warehouse_id, invoice_no, invoice_date,
                    currency_id, currency_code, currency_symbol, currency_rate_to_aed,
                    total_amount, discount_amount, final_amount,
                    total_amount_aed, discount_amount_aed, final_amount_aed,
                    payment_status, note, created_by, created_at
                ) VALUES (
                    :customer_id, :warehouse_id, :invoice_no, :invoice_date,
                    :currency_id, :currency_code, :currency_symbol, :currency_rate_to_aed,
                    :total_amount, :discount_amount, :final_amount,
                    :total_amount_aed, :discount_amount_aed, :final_amount_aed,
                    :payment_status, :note, :created_by, NOW()
                )
            ');

            $headerStatement->execute([
                'customer_id' => $payload['customer_id'],
                'warehouse_id' => $warehouseId,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $payload['invoice_date'],
                'currency_id' => $payload['currency_id'],
                'currency_code' => $payload['currency_code'],
                'currency_symbol' => $payload['currency_symbol'],
                'currency_rate_to_aed' => $payload['currency_rate_to_aed'],
                'total_amount' => $payload['total_amount'],
                'discount_amount' => $payload['discount_amount'],
                'final_amount' => $payload['final_amount'],
                'total_amount_aed' => $payload['total_amount_aed'],
                'discount_amount_aed' => $payload['discount_amount_aed'],
                'final_amount_aed' => $payload['final_amount_aed'],
                'payment_status' => $payload['payment_status'],
                'note' => $payload['note'],
                'created_by' => $payload['created_by'],
            ]);

            $saleId = (int) $pdo->lastInsertId();

            $itemStatement = $pdo->prepare('
                INSERT INTO sale_items (
                    sale_id, product_id, display_qty, pricing_unit, units_per_box,
                    qty, unit_price, unit_price_aed, cost_price, total_price, total_price_aed, created_at, updated_at
                ) VALUES (
                    :sale_id, :product_id, :display_qty, :pricing_unit, :units_per_box,
                    :qty, :unit_price, :unit_price_aed, :cost_price, :total_price, :total_price_aed, NOW(), NOW()
                )
            ');

            $productStatement = $pdo->prepare('SELECT id, name, purchase_price, current_stock, COALESCE(item_type, "inventory") AS item_type FROM products WHERE id = :id LIMIT 1');
            $warehouseStockSelect = $pdo->prepare('SELECT id, qty FROM warehouse_stocks WHERE warehouse_id = :warehouse_id AND product_id = :product_id LIMIT 1');
            $warehouseStockUpdate = $pdo->prepare('UPDATE warehouse_stocks SET qty = :qty, updated_at = NOW() WHERE id = :id');
            $productUpdate = $pdo->prepare('UPDATE products SET current_stock = :stock, updated_at = NOW() WHERE id = :id');
            $movementInsert = $pdo->prepare('
                INSERT INTO stock_movements (
                    product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at
                ) VALUES (
                    :product_id, "sale", "sale", :ref_id, 0, :qty_out, :balance_after, :note, NOW(), NOW()
                )
            ');
            $warehouseMovementInsert = $pdo->prepare('
                INSERT INTO warehouse_movements (
                    warehouse_id, product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_by, created_at, updated_at
                ) VALUES (
                    :warehouse_id, :product_id, "sale", "sale", :ref_id, 0, :qty_out, :balance_after, :note, :created_by, NOW(), NOW()
                )
            ');

            foreach ($payload['items'] as $item) {
                $productStatement->execute(['id' => $item['product_id']]);
                $product = $productStatement->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException('One of the selected products could not be found.');
                }

                $isInventoryItem = (string) ($product['item_type'] ?? 'inventory') === 'inventory';
                $newWarehouseQty = null;
                $newCompanyStock = (float) ($product['current_stock'] ?? 0);
                $warehouseStock = null;

                if ($isInventoryItem) {
                    $warehouseStockSelect->execute([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $item['product_id'],
                    ]);
                    $warehouseStock = $warehouseStockSelect->fetch(PDO::FETCH_ASSOC);
                    $warehouseQty = (float) ($warehouseStock['qty'] ?? 0);

                    if ($warehouseQty < (float) $item['qty']) {
                        throw new RuntimeException('Insufficient stock in warehouse ' . ($warehouse['name'] ?? '') . ' for product: ' . $product['name']);
                    }

                    if ((float) $product['current_stock'] < (float) $item['qty']) {
                        throw new RuntimeException('Insufficient company stock for product: ' . $product['name']);
                    }

                    $newWarehouseQty = $warehouseQty - (float) $item['qty'];
                    $newCompanyStock = (float) $product['current_stock'] - (float) $item['qty'];
                }

                $itemStatement->execute([
                    'sale_id' => $saleId,
                    'product_id' => $item['product_id'],
                    'display_qty' => $item['display_qty'] ?? $item['qty'],
                    'pricing_unit' => $item['pricing_unit'] ?? 'unit',
                    'units_per_box' => $item['units_per_box'] ?? 1,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'unit_price_aed' => $item['unit_price_aed'],
                    'cost_price' => $product['purchase_price'],
                    'total_price' => $item['total_price'],
                    'total_price_aed' => $item['total_price_aed'],
                ]);

                if ($isInventoryItem) {
                    $warehouseStockUpdate->execute([
                        'qty' => $newWarehouseQty,
                        'id' => $warehouseStock['id'],
                    ]);

                    $productUpdate->execute([
                        'stock' => $newCompanyStock,
                        'id' => $item['product_id'],
                    ]);

                    $movementInsert->execute([
                        'product_id' => $item['product_id'],
                        'ref_id' => $saleId,
                        'qty_out' => $item['qty'],
                        'balance_after' => $newCompanyStock,
                        'note' => 'Sales invoice ' . $invoiceNo,
                    ]);

                    $warehouseMovementInsert->execute([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $item['product_id'],
                        'ref_id' => $saleId,
                        'qty_out' => $item['qty'],
                        'balance_after' => $newWarehouseQty,
                        'note' => 'Sales invoice ' . $invoiceNo,
                        'created_by' => $payload['created_by'],
                    ]);
                }
            }

            $pdo->commit();

            return $saleId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function refreshProductPurchasePrices(PDO $pdo, array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int) $value, $productIds), static fn (int $value): bool => $value > 0)));
        if ($productIds === []) {
            return;
        }

        $latestPriceStatement = $pdo->prepare('
            SELECT pi.unit_price_aed
            FROM purchase_items pi
            INNER JOIN purchases p ON p.id = pi.purchase_id
            WHERE pi.product_id = :product_id
            ORDER BY p.invoice_date DESC, p.id DESC, pi.id DESC
            LIMIT 1
        ');
        $productUpdate = $pdo->prepare('
            UPDATE products
            SET purchase_price = :purchase_price,
                purchase_price_display = :purchase_price_display,
                updated_at = NOW()
            WHERE id = :id
        ');

        foreach ($productIds as $productId) {
            $latestPriceStatement->execute(['product_id' => $productId]);
            $latestPrice = $latestPriceStatement->fetchColumn();
            if ($latestPrice === false || $latestPrice === null) {
                continue;
            }

            $price = round((float) $latestPrice, 2);
            $productUpdate->execute([
                'purchase_price' => $price,
                'purchase_price_display' => $price,
                'id' => $productId,
            ]);
        }
    }

}
