<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\InventoryService;
use App\Core\Validator;
use PDO;
use RuntimeException;

final class InventoryController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('inventory');

        $tab = trim((string) ($_GET['tab'] ?? 'overview'));
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
        $search = trim((string) ($_GET['q'] ?? ''));

        $pdo = Database::connection();
        $warehouses = $pdo->query('
            SELECT w.*, 
                COUNT(DISTINCT ws.product_id) AS sku_count,
                COALESCE(SUM(ws.qty), 0) AS on_hand_qty
            FROM warehouses w
            LEFT JOIN warehouse_stocks ws ON ws.warehouse_id = w.id
            GROUP BY w.id
            ORDER BY w.is_default DESC, w.name ASC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'warehouses' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM warehouses WHERE status = "active"', []),
            'products_in_stock' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM products WHERE current_stock > 0', []),
            'pending_receipts' => (int) $this->scalar($pdo, 'SELECT COUNT(DISTINCT purchase_id) FROM purchase_items WHERE qty > COALESCE(received_qty, 0)', []),
            'pending_qty' => $this->scalar($pdo, 'SELECT COALESCE(SUM(qty - COALESCE(received_qty, 0)), 0) FROM purchase_items WHERE qty > COALESCE(received_qty, 0)', []),
            'movement_count' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM warehouse_movements WHERE DATE(created_at) BETWEEN :from AND :to', compact('from', 'to')),
            'low_stock' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM products WHERE current_stock <= min_stock', []),
        ];

        $stockWhere = [];
        $stockParams = [];
        if ($warehouseId > 0) {
            $stockWhere[] = 'ws.warehouse_id = :warehouse_id';
            $stockParams['warehouse_id'] = $warehouseId;
        }
        if ($search !== '') {
            $stockWhere[] = '(p.name LIKE :search OR p.code LIKE :search OR w.name LIKE :search)';
            $stockParams['search'] = '%' . $search . '%';
        }

        $stockSql = '
            SELECT
                ws.id,
                ws.warehouse_id,
                ws.product_id,
                ws.qty,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                p.code AS product_code,
                p.name AS product_name,
                p.category,
                p.unit,
                p.min_stock,
                p.current_stock AS company_stock,
                p.purchase_price,
                p.sale_price,
                CASE WHEN ws.qty <= p.min_stock THEN "Low" ELSE "OK" END AS stock_status
            FROM warehouse_stocks ws
            INNER JOIN warehouses w ON w.id = ws.warehouse_id
            INNER JOIN products p ON p.id = ws.product_id
        ';
        if ($stockWhere !== []) {
            $stockSql .= ' WHERE ' . implode(' AND ', $stockWhere);
        }
        $stockSql .= ' ORDER BY w.name ASC, p.name ASC';
        $stockStatement = $pdo->prepare($stockSql);
        $stockStatement->execute($stockParams);
        $stockRows = $stockStatement->fetchAll(PDO::FETCH_ASSOC);

        $movementWhere = ['DATE(wm.created_at) BETWEEN :from AND :to'];
        $movementParams = compact('from', 'to');
        if ($warehouseId > 0) {
            $movementWhere[] = 'wm.warehouse_id = :warehouse_id';
            $movementParams['warehouse_id'] = $warehouseId;
        }
        if ($search !== '') {
            $movementWhere[] = '(p.name LIKE :search OR p.code LIKE :search OR w.name LIKE :search OR wm.ref_type LIKE :search OR COALESCE(wm.note, "") LIKE :search)';
            $movementParams['search'] = '%' . $search . '%';
        }

        $movements = $this->fetchAll($pdo, '
            SELECT wm.*, w.name AS warehouse_name, p.name AS product_name, p.code AS product_code
            FROM warehouse_movements wm
            INNER JOIN warehouses w ON w.id = wm.warehouse_id
            INNER JOIN products p ON p.id = wm.product_id
            WHERE ' . implode(' AND ', $movementWhere) . '
            ORDER BY wm.id DESC
            LIMIT 300
        ', $movementParams);

        $pendingReceipts = $this->fetchAll($pdo, '
            SELECT
                p.id,
                p.invoice_no,
                p.invoice_date,
                p.receipt_status,
                s.name AS supplier_name,
                COALESCE(SUM(pi.qty), 0) AS ordered_qty,
                COALESCE(SUM(COALESCE(pi.received_qty, 0)), 0) AS received_qty,
                COALESCE(SUM(pi.qty - COALESCE(pi.received_qty, 0)), 0) AS pending_qty,
                COALESCE(p.final_amount_aed, p.final_amount) AS final_amount_aed
            FROM purchases p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            INNER JOIN purchase_items pi ON pi.purchase_id = p.id
            INNER JOIN products pr ON pr.id = pi.product_id
            WHERE COALESCE(pr.item_type, "inventory") = "inventory" AND pi.qty > COALESCE(pi.received_qty, 0)
            GROUP BY p.id, p.invoice_no, p.invoice_date, p.receipt_status, s.name, p.final_amount_aed, p.final_amount
            ORDER BY p.invoice_date DESC, p.id DESC
        ', []);

        $warehousePerformance = $this->fetchAll($pdo, '
            SELECT
                w.id,
                w.name,
                COUNT(DISTINCT ws.product_id) AS sku_count,
                COALESCE(SUM(ws.qty), 0) AS on_hand_qty,
                COALESCE(SUM(ws.qty * p.purchase_price), 0) AS stock_cost_aed,
                COALESCE(SUM(ws.qty * p.sale_price), 0) AS stock_sale_value_aed
            FROM warehouses w
            LEFT JOIN warehouse_stocks ws ON ws.warehouse_id = w.id
            LEFT JOIN products p ON p.id = ws.product_id
            GROUP BY w.id, w.name
            ORDER BY w.is_default DESC, w.name ASC
        ', []);

        $this->render('inventory/index', [
            'title' => 'Inventory',
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'warehouseId' => $warehouseId,
            'search' => $search,
            'warehouses' => $warehouses,
            'summary' => $summary,
            'stockRows' => $stockRows,
            'movements' => $movements,
            'pendingReceipts' => $pendingReceipts,
            'warehousePerformance' => $warehousePerformance,
        ]);
    }

    public function createWarehouse(): void
    {
        $this->requirePermission('inventory');

        $this->render('inventory/warehouses/form', [
            'title' => 'Inventory / New Warehouse',
            'action' => '/inventory/warehouses/store',
            'warehouse' => [
                'code' => 'WH-' . date('ymdHis'),
                'name' => '',
                'location' => '',
                'manager_name' => '',
                'phone' => '',
                'status' => 'active',
                'notes' => '',
                'is_default' => 0,
            ],
        ]);
    }

    public function storeWarehouse(): void
    {
        $this->requirePermission('inventory');
        $this->verifyCsrf();

        $input = $this->normalizeWarehouseInput($_POST);
        with_old($input);
        $errors = $this->validateWarehouse($input);

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/inventory/warehouses/create');
        }

        $pdo = Database::connection();
        if ((int) $input['is_default'] === 1) {
            $pdo->exec('UPDATE warehouses SET is_default = 0');
        }

        $statement = $pdo->prepare('
            INSERT INTO warehouses (code, name, location, manager_name, phone, status, notes, is_default, created_at, updated_at)
            VALUES (:code, :name, :location, :manager_name, :phone, :status, :notes, :is_default, NOW(), NOW())
        ');

        try {
            $statement->execute($input);
        } catch (\Throwable) {
            validation_errors(['code' => ['This warehouse code already exists.']]);
            $this->redirect('/inventory/warehouses/create');
        }

        clear_old();
        $this->redirect('/inventory?tab=warehouses', 'Warehouse created successfully.');
    }

    public function editWarehouse(): void
    {
        $this->requirePermission('inventory');

        $warehouse = $this->findWarehouse((int) ($_GET['id'] ?? 0));
        if (!$warehouse) {
            $this->redirect('/inventory?tab=warehouses', null, 'Warehouse not found.');
        }

        $this->render('inventory/warehouses/form', [
            'title' => 'Inventory / Edit Warehouse',
            'action' => '/inventory/warehouses/update?id=' . (int) $warehouse['id'],
            'warehouse' => $warehouse,
        ]);
    }

    public function updateWarehouse(): void
    {
        $this->requirePermission('inventory');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $warehouse = $this->findWarehouse($id);
        if (!$warehouse) {
            $this->redirect('/inventory?tab=warehouses', null, 'Warehouse not found.');
        }

        $input = $this->normalizeWarehouseInput($_POST, $warehouse);
        with_old($input);
        $errors = $this->validateWarehouse($input, $id);

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/inventory/warehouses/edit?id=' . $id);
        }

        $pdo = Database::connection();
        if ((int) $input['is_default'] === 1) {
            $pdo->exec('UPDATE warehouses SET is_default = 0');
        }

        $statement = $pdo->prepare('
            UPDATE warehouses
            SET code = :code,
                name = :name,
                location = :location,
                manager_name = :manager_name,
                phone = :phone,
                status = :status,
                notes = :notes,
                is_default = :is_default,
                updated_at = NOW()
            WHERE id = :id
        ');

        try {
            $statement->execute($input + ['id' => $id]);
        } catch (\Throwable) {
            validation_errors(['code' => ['This warehouse code already exists.']]);
            $this->redirect('/inventory/warehouses/edit?id=' . $id);
        }

        clear_old();
        $this->redirect('/inventory?tab=warehouses', 'Warehouse updated successfully.');
    }

    public function createReceipt(): void
    {
        $this->requirePermission('inventory');

        $purchase = $this->findPurchase((int) ($_GET['purchase_id'] ?? 0));
        if (!$purchase) {
            $this->redirect('/inventory?tab=pending', null, 'Purchase invoice not found.');
        }

        $items = $this->fetchAll(Database::connection(), '
            SELECT
                pi.*,
                pr.name AS product_name,
                pr.code AS product_code,
                pr.unit,
                (pi.qty - COALESCE(pi.received_qty, 0)) AS pending_qty
            FROM purchase_items pi
            INNER JOIN products pr ON pr.id = pi.product_id
            WHERE pi.purchase_id = :id AND COALESCE(pr.item_type, "inventory") = "inventory" AND pi.qty > COALESCE(pi.received_qty, 0)
            ORDER BY pi.id ASC
        ', ['id' => (int) $purchase['id']]);

        if ($items === []) {
            $this->redirect('/purchases/show?id=' . (int) $purchase['id'], null, 'This purchase has already been fully received into inventory.');
        }

        $warehouses = Database::connection()->query('SELECT * FROM warehouses WHERE status = "active" ORDER BY is_default DESC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
        if ($warehouses === []) {
            $this->redirect('/inventory?tab=warehouses', null, 'Create at least one active warehouse before receiving purchases.');
        }

        $this->render('inventory/receipt_form', [
            'title' => 'Inventory / Receive Purchase',
            'purchase' => $purchase,
            'items' => $items,
            'warehouses' => $warehouses,
            'action' => '/inventory/receipts/store?purchase_id=' . (int) $purchase['id'],
        ]);
    }

    public function storeReceipt(): void
    {
        $this->requirePermission('inventory');
        $this->verifyCsrf();

        $purchase = $this->findPurchase((int) ($_GET['purchase_id'] ?? 0));
        if (!$purchase) {
            $this->redirect('/inventory?tab=pending', null, 'Purchase invoice not found.');
        }

        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
        $receiptDate = trim((string) ($_POST['receipt_date'] ?? date('Y-m-d')));
        $note = trim((string) ($_POST['note'] ?? ''));
        $itemIds = array_map('intval', (array) ($_POST['purchase_item_id'] ?? []));
        $quantities = (array) ($_POST['receive_qty'] ?? []);

        $lines = [];
        foreach ($itemIds as $index => $purchaseItemId) {
            $qty = (float) ($quantities[$index] ?? 0);
            if ($purchaseItemId > 0 && $qty > 0) {
                $lines[] = [
                    'purchase_item_id' => $purchaseItemId,
                    'qty' => $qty,
                ];
            }
        }

        if ($warehouseId <= 0) {
            validation_errors(['warehouse_id' => ['Please choose a warehouse.']]);
            with_old($_POST);
            $this->redirect('/inventory/receipts/create?purchase_id=' . (int) $purchase['id']);
        }

        if ($lines === []) {
            validation_errors(['receive_qty' => ['Enter at least one quantity to receive into stock.']]);
            with_old($_POST);
            $this->redirect('/inventory/receipts/create?purchase_id=' . (int) $purchase['id']);
        }

        try {
            InventoryService::receivePurchase([
                'purchase_id' => (int) $purchase['id'],
                'warehouse_id' => $warehouseId,
                'receipt_date' => $receiptDate,
                'note' => $note,
                'created_by' => Auth::id(),
                'lines' => $lines,
            ]);
        } catch (\Throwable $exception) {
            validation_errors(['receipt' => [$exception->getMessage()]]);
            with_old($_POST);
            $this->redirect('/inventory/receipts/create?purchase_id=' . (int) $purchase['id']);
        }

        clear_old();
        $this->redirect('/purchases/show?id=' . (int) $purchase['id'], 'Inventory receipt posted successfully.');
    }

    private function validateWarehouse(array $input, int $ignoreId = 0): array
    {
        $errors = Validator::make($input, [
            'code' => ['required', 'max:60'],
            'name' => ['required', 'max:160'],
            'location' => ['max:190'],
            'manager_name' => ['max:120'],
            'phone' => ['max:60'],
            'status' => ['required', 'max:20'],
            'notes' => ['max:500'],
        ]);

        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $input['code']]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);
        if ($existing && (int) $existing['id'] !== $ignoreId) {
            $errors['code'][] = 'This warehouse code already exists.';
        }

        return $errors;
    }

    private function normalizeWarehouseInput(array $source, array $fallback = []): array
    {
        return [
            'code' => trim((string) ($source['code'] ?? ($fallback['code'] ?? ''))),
            'name' => trim((string) ($source['name'] ?? ($fallback['name'] ?? ''))),
            'location' => trim((string) ($source['location'] ?? ($fallback['location'] ?? ''))),
            'manager_name' => trim((string) ($source['manager_name'] ?? ($fallback['manager_name'] ?? ''))),
            'phone' => trim((string) ($source['phone'] ?? ($fallback['phone'] ?? ''))),
            'status' => trim((string) ($source['status'] ?? ($fallback['status'] ?? 'active'))),
            'notes' => trim((string) ($source['notes'] ?? ($fallback['notes'] ?? ''))),
            'is_default' => (int) (($source['is_default'] ?? ($fallback['is_default'] ?? 0)) ? 1 : 0),
        ];
    }

    private function findWarehouse(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $warehouse = $statement->fetch(PDO::FETCH_ASSOC);

        return $warehouse ?: null;
    }

    private function findPurchase(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('
            SELECT p.*, s.name AS supplier_name
            FROM purchases p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $purchase = $statement->fetch(PDO::FETCH_ASSOC);

        return $purchase ?: null;
    }

    private function scalar(PDO $pdo, string $sql, array $params): float
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return (float) ($statement->fetchColumn() ?: 0);
    }

    private function fetchAll(PDO $pdo, string $sql, array $params): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
