<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\FormTemplateManager;
use PDO;

final class DocumentController extends Controller
{
    public function forms(): void
    {
        $this->requirePermission('settings.forms');

        $types = FormTemplateManager::types();
        $payload = FormTemplateManager::payload();

        $this->render('settings/forms/index', [
            'title' => 'Setting / Forms',
            'types' => $types,
            'global' => $payload['global'],
            'templates' => $payload['templates'],
            'revision' => $payload['revision'],
        ]);
    }

    public function editForm(): void
    {
        $this->requirePermission('settings.forms');

        $type = trim((string) ($_GET['type'] ?? 'sales_invoice'));
        $types = FormTemplateManager::types();
        if (!isset($types[$type])) {
            $this->redirect('/settings/forms', null, 'Requested form template was not found.');
        }

        $meta = FormTemplateManager::documentMeta($type);
        $this->render('settings/forms/edit', [
            'title' => 'Setting / Forms / ' . ($meta['definition']['label'] ?? ucfirst($type)),
            'type' => $type,
            'types' => $types,
            'definition' => $meta['definition'],
            'global' => $meta['global'],
            'template' => $meta['template'],
            'revision' => $meta['revision'],
            'visualDesigner' => (bool) ($meta['visual_designer'] ?? false),
            'widgetCatalog' => $meta['widget_catalog'] ?? [],
            'defaultLayout' => $meta['default_layout'] ?? ['canvas_height' => 1180, 'widgets' => []],
            'action' => '/settings/forms/update?type=' . urlencode($type),
            'resetAction' => '/settings/forms/reset?type=' . urlencode($type),
            'invoiceLayoutVariants' => $meta['invoice_layout_variants'] ?? [],
            'invoiceQrPositions' => $meta['invoice_qr_positions'] ?? [],
        ]);
    }

    public function updateForm(): void
    {
        $this->requirePermission('settings.forms');
        $this->verifyCsrf();

        $type = trim((string) ($_GET['type'] ?? ''));
        try {
            FormTemplateManager::save($type, [
                'global' => $_POST['global'] ?? [],
                'template' => $_POST['template'] ?? [],
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/forms/edit?type=' . urlencode($type), null, $exception->getMessage());
        }

        $this->redirect('/settings/forms/edit?type=' . urlencode($type), 'Form template saved successfully. Print / PDF views now use the updated design.');
    }

    public function resetForm(): void
    {
        $this->requirePermission('settings.forms');
        $this->verifyCsrf();

        $type = trim((string) ($_GET['type'] ?? ''));
        try {
            FormTemplateManager::reset($type);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/forms/edit?type=' . urlencode($type), null, $exception->getMessage());
        }

        $this->redirect('/settings/forms/edit?type=' . urlencode($type), 'The selected form template was restored to its original defaults.');
    }

    public function salesInvoice(): void
    {
        $this->requirePermission('sales');

        $saleId = (int) ($_GET['id'] ?? 0);
        $sale = $this->findSale($saleId);
        if (!$sale) {
            $this->redirect('/sales', null, 'Sales invoice not found.');
        }

        $statement = Database::connection()->prepare('
            SELECT si.*, pr.name AS product_name, pr.code AS product_code, pr.unit
            FROM sale_items si
            INNER JOIN products pr ON pr.id = si.product_id
            WHERE si.sale_id = :sale_id
            ORDER BY si.id ASC
        ');
        $statement->execute(['sale_id' => $saleId]);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->renderDocument('sales_invoice', 'documents/sales_invoice', [
            'documentTitle' => 'Sales Invoice',
            'sale' => $sale,
            'items' => $items,
            'receipts' => $this->listSaleReceipts($saleId),
            'qrValue' => base_url('/sales/show?id=' . $saleId),
        ]);
    }

    public function purchaseInvoice(): void
    {
        $this->requirePermission('purchases');

        $purchaseId = (int) ($_GET['id'] ?? 0);
        $purchase = $this->findPurchase($purchaseId);
        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $itemsStmt = Database::connection()->prepare('
            SELECT pi.*, pr.name AS product_name, pr.code AS product_code, pr.unit
            FROM purchase_items pi
            INNER JOIN products pr ON pr.id = pi.product_id
            WHERE pi.purchase_id = :purchase_id
            ORDER BY pi.id ASC
        ');
        $itemsStmt->execute(['purchase_id' => $purchaseId]);

        $this->renderDocument('purchase_invoice', 'documents/purchase_invoice', [
            'documentTitle' => 'Purchase Invoice',
            'purchase' => $purchase,
            'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
            'payments' => $this->listPurchasePayments($purchaseId),
            'receipts' => $this->listInventoryReceipts($purchaseId),
            'returns' => $this->listPurchaseReturns($purchaseId),
            'qrValue' => base_url('/purchases/show?id=' . $purchaseId),
        ]);
    }

    public function customerStatement(): void
    {
        $this->requirePermission('customers');

        $customerId = (int) ($_GET['id'] ?? 0);
        $customer = $this->findCustomer($customerId);
        if (!$customer) {
            $this->redirect('/customers', null, 'Customer not found.');
        }

        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $currencyCode = strtoupper(trim((string) ($_GET['currency'] ?? 'ALL')));
        if ($currencyCode === '') {
            $currencyCode = 'ALL';
        }

        $this->renderDocument('customer_statement', 'documents/customer_statement', [
            'documentTitle' => 'Customer Statement',
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'currencyCode' => $currencyCode,
            'summaryByCurrency' => $this->customerSummaryByCurrency($customerId),
            'statement' => $this->customerStatementData($customerId, $from, $to, $currencyCode),
        ]);
    }

    public function supplierStatement(): void
    {
        $this->requirePermission('suppliers');

        $supplierId = (int) ($_GET['id'] ?? 0);
        $supplier = $this->findSupplier($supplierId);
        if (!$supplier) {
            $this->redirect('/suppliers', null, 'Supplier not found.');
        }

        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $currencyCode = strtoupper(trim((string) ($_GET['currency'] ?? 'ALL')));
        if ($currencyCode === '') {
            $currencyCode = 'ALL';
        }

        $this->renderDocument('supplier_statement', 'documents/supplier_statement', [
            'documentTitle' => 'Supplier Statement',
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'currencyCode' => $currencyCode,
            'summaryByCurrency' => $this->supplierSummaryByCurrency($supplierId),
            'statement' => $this->supplierStatementData($supplierId, $from, $to, $currencyCode),
        ]);
    }

    public function bankStatement(): void
    {
        $this->requirePermission('banking');

        $accountId = (int) ($_GET['account_id'] ?? 0);
        $account = $this->findBankAccount($accountId);
        if (!$account) {
            $this->redirect('/banking?tab=accounts', null, 'Banking account not found.');
        }

        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $search = trim((string) ($_GET['q'] ?? ''));

        $statement = $this->bankStatementData($accountId, $from, $to, $search, $account);

        $this->renderDocument('bank_statement', 'documents/bank_statement', [
            'documentTitle' => 'Bank Statement',
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'statement' => $statement,
        ]);
    }

    public function inventoryReceipt(): void
    {
        $this->requirePermission('inventory');

        $receiptId = (int) ($_GET['id'] ?? 0);
        $receipt = $this->findInventoryReceipt($receiptId);
        if (!$receipt) {
            $this->redirect('/inventory', null, 'Inventory receipt not found.');
        }

        $items = $this->listInventoryReceiptItems($receiptId);
        $this->renderDocument('inventory_receipt', 'documents/inventory_receipt', [
            'documentTitle' => 'Warehouse Receipt',
            'receipt' => $receipt,
            'items' => $items,
            'qrValue' => base_url('/documents/inventory/receipt?id=' . $receiptId),
        ]);
    }

    public function inventoryIssue(): void
    {
        $this->requirePermission('sales');

        $saleId = (int) ($_GET['sale_id'] ?? 0);
        $sale = $this->findSale($saleId);
        if (!$sale) {
            $this->redirect('/sales', null, 'Sales invoice not found.');
        }

        $statement = Database::connection()->prepare('
            SELECT si.*, pr.name AS product_name, pr.code AS product_code, pr.unit
            FROM sale_items si
            INNER JOIN products pr ON pr.id = si.product_id
            WHERE si.sale_id = :sale_id
            ORDER BY si.id ASC
        ');
        $statement->execute(['sale_id' => $saleId]);

        $this->renderDocument('inventory_issue', 'documents/inventory_issue', [
            'documentTitle' => 'Warehouse Issue',
            'sale' => $sale,
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'qrValue' => base_url('/documents/inventory/issue?sale_id=' . $saleId),
        ]);
    }

    private function renderDocument(string $type, string $view, array $data): void
    {
        $meta = FormTemplateManager::documentMeta($type);
        $this->render($view, $data + [
            'formType' => $type,
            'formMeta' => $meta,
            'formGlobal' => $meta['global'],
            'formTemplate' => $meta['template'],
            'formCss' => FormTemplateManager::compiledCss($type),
        ], 'print');
    }

    private function findSale(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                s.*,
                c.name AS customer_name,
                c.mobile AS customer_mobile,
                c.address AS customer_address,
                c.email AS customer_email,
                w.name AS warehouse_name,
                COALESCE((SELECT SUM(sr.amount_currency) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS received_amount,
                COALESCE((SELECT SUM(sr.amount_aed) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS received_amount_aed,
                COALESCE((SELECT COUNT(*) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS receipt_count
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN warehouses w ON w.id = s.warehouse_id
            WHERE s.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $sale = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }

        $legacyStatus = (string) ($sale['payment_status'] ?? 'pending');
        $receiptCount = (int) ($sale['receipt_count'] ?? 0);
        $receivedAmount = round((float) ($sale['received_amount'] ?? 0), 2);
        $receivedAmountAed = round((float) ($sale['received_amount_aed'] ?? 0), 2);
        $netAmount = round((float) ($sale['final_amount'] ?? 0), 2);
        $netAmountAed = round((float) ($sale['final_amount_aed'] ?? $sale['final_amount'] ?? 0), 2);
        if ($receiptCount === 0 && $receivedAmount <= 0.009 && $legacyStatus === 'paid') {
            $receivedAmount = $netAmount;
            $receivedAmountAed = $netAmountAed;
        }
        $dueAmount = max(0.0, round($netAmount - $receivedAmount, 2));
        $dueAmountAed = max(0.0, round($netAmountAed - $receivedAmountAed, 2));
        $paymentStatus = 'unpaid';
        if ($dueAmount <= 0.009) {
            $paymentStatus = 'paid';
        } elseif ($receivedAmount > 0.009) {
            $paymentStatus = 'partial';
        }
        $sale['net_amount'] = $netAmount;
        $sale['net_amount_aed'] = $netAmountAed;
        $sale['received_amount'] = $receivedAmount;
        $sale['received_amount_aed'] = $receivedAmountAed;
        $sale['due_amount'] = $dueAmount;
        $sale['due_amount_aed'] = $dueAmountAed;
        $sale['payment_status'] = $paymentStatus;

        return $sale;
    }

    private function listSaleReceipts(int $saleId): array
    {
        $statement = Database::connection()->prepare('
            SELECT sr.*, ba.bank_name, ba.account_name, u.name AS created_by_name
            FROM sale_receipts sr
            INNER JOIN bank_accounts ba ON ba.id = sr.bank_account_id
            LEFT JOIN users u ON u.id = sr.created_by
            WHERE sr.sale_id = :sale_id
            ORDER BY sr.receipt_date DESC, sr.id DESC
        ');
        $statement->execute(['sale_id' => $saleId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findPurchase(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                p.*, s.name AS supplier_name, s.mobile AS supplier_mobile, s.address AS supplier_address, s.email AS supplier_email,
                COALESCE((SELECT SUM(prt.total_amount) FROM purchase_returns prt WHERE prt.purchase_id = p.id), 0) AS returned_amount,
                COALESCE((SELECT SUM(prt.total_amount_aed) FROM purchase_returns prt WHERE prt.purchase_id = p.id), 0) AS returned_amount_aed,
                COALESCE((SELECT SUM(pp.amount_currency) FROM purchase_payments pp WHERE pp.purchase_id = p.id), 0) AS paid_amount,
                COALESCE((SELECT SUM(pp.amount_aed) FROM purchase_payments pp WHERE pp.purchase_id = p.id), 0) AS paid_amount_aed,
                COALESCE((SELECT COUNT(*) FROM purchase_payments pp WHERE pp.purchase_id = p.id), 0) AS payment_count,
                COALESCE((SELECT COUNT(*) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id AND COALESCE(pr.item_type, "inventory") = "inventory"), 0) AS inventory_item_count,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN pi.qty ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS inventory_ordered_qty,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN COALESCE(pi.received_qty, 0) ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS inventory_received_qty,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN (pi.qty - COALESCE(pi.received_qty, 0)) ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS inventory_pending_qty
            FROM purchases p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $purchase = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$purchase) {
            return null;
        }

        $returnedAmount = round((float) ($purchase['returned_amount'] ?? 0), 2);
        $returnedAmountAed = round((float) ($purchase['returned_amount_aed'] ?? 0), 2);
        $paidAmount = round((float) ($purchase['paid_amount'] ?? 0), 2);
        $paidAmountAed = round((float) ($purchase['paid_amount_aed'] ?? 0), 2);
        $netAmount = max(0.0, round((float) ($purchase['final_amount'] ?? 0) - $returnedAmount, 2));
        $netAmountAed = max(0.0, round((float) ($purchase['final_amount_aed'] ?? $purchase['final_amount'] ?? 0) - $returnedAmountAed, 2));
        $dueAmount = max(0.0, round($netAmount - $paidAmount, 2));
        $dueAmountAed = max(0.0, round($netAmountAed - $paidAmountAed, 2));
        $paymentStatus = 'unpaid';
        if ($dueAmount <= 0.009) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0.009) {
            $paymentStatus = 'partial';
        }
        $purchase['returned_amount'] = $returnedAmount;
        $purchase['returned_amount_aed'] = $returnedAmountAed;
        $purchase['net_amount'] = $netAmount;
        $purchase['net_amount_aed'] = $netAmountAed;
        $purchase['paid_amount'] = $paidAmount;
        $purchase['paid_amount_aed'] = $paidAmountAed;
        $purchase['due_amount'] = $dueAmount;
        $purchase['due_amount_aed'] = $dueAmountAed;
        $purchase['payment_status'] = $paymentStatus;
        $inventoryLineCount = (int) ($purchase['inventory_item_count'] ?? 0);
        $inventoryReceivedQty = round((float) ($purchase['inventory_received_qty'] ?? 0), 2);
        $inventoryPendingQty = round((float) ($purchase['inventory_pending_qty'] ?? 0), 2);
        $derivedReceiptStatus = 'pending';
        if ($inventoryLineCount <= 0) {
            $derivedReceiptStatus = 'not_required';
        } elseif ($inventoryPendingQty <= 0.009 && $inventoryReceivedQty > 0.009) {
            $derivedReceiptStatus = 'received';
        } elseif ($inventoryReceivedQty > 0.009) {
            $derivedReceiptStatus = 'partial';
        }
        $purchase['receipt_status_display'] = $derivedReceiptStatus;

        return $purchase;
    }

    private function listPurchasePayments(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT pp.*, ba.bank_name, ba.account_name, u.name AS created_by_name
            FROM purchase_payments pp
            INNER JOIN bank_accounts ba ON ba.id = pp.bank_account_id
            LEFT JOIN users u ON u.id = pp.created_by
            WHERE pp.purchase_id = :purchase_id
            ORDER BY pp.payment_date DESC, pp.id DESC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listInventoryReceipts(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT ir.*, w.name AS warehouse_name, u.name AS created_by_name,
                   COALESCE((SELECT SUM(iri.qty) FROM inventory_receipt_items iri WHERE iri.receipt_id = ir.id), 0) AS total_qty
            FROM inventory_receipts ir
            INNER JOIN warehouses w ON w.id = ir.warehouse_id
            LEFT JOIN users u ON u.id = ir.created_by
            WHERE ir.purchase_id = :purchase_id
            ORDER BY ir.receipt_date DESC, ir.id DESC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listPurchaseReturns(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT prt.*, w.name AS warehouse_name, u.name AS created_by_name,
                   COALESCE((SELECT SUM(pri.qty) FROM purchase_return_items pri WHERE pri.purchase_return_id = prt.id), 0) AS total_qty
            FROM purchase_returns prt
            INNER JOIN warehouses w ON w.id = prt.warehouse_id
            LEFT JOIN users u ON u.id = prt.created_by
            WHERE prt.purchase_id = :purchase_id
            ORDER BY prt.return_date DESC, prt.id DESC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findCustomer(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function customerSummaryByCurrency(int $customerId): array
    {
        $statement = Database::connection()->prepare('
            SELECT s.id, s.invoice_no, s.invoice_date, s.currency_code, s.final_amount, s.payment_status,
                   COALESCE((SELECT SUM(sr.amount_currency) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS received_amount,
                   COALESCE((SELECT COUNT(*) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS receipt_count
            FROM sales s
            WHERE s.customer_id = :customer_id
            ORDER BY s.invoice_date ASC, s.id ASC
        ');
        $statement->execute(['customer_id' => $customerId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $summary = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) ($row['currency_code'] ?? 'AED'));
            if (!isset($summary[$code])) {
                $summary[$code] = [
                    'currency_code' => $code,
                    'invoice_count' => 0,
                    'open_invoice_count' => 0,
                    'sales_total' => 0.0,
                    'received_total' => 0.0,
                    'outstanding' => 0.0,
                ];
            }
            $saleAmount = round((float) ($row['final_amount'] ?? 0), 2);
            $receivedAmount = round((float) ($row['received_amount'] ?? 0), 2);
            $receiptCount = (int) ($row['receipt_count'] ?? 0);
            $legacyStatus = (string) ($row['payment_status'] ?? 'pending');
            if ($receiptCount === 0 && $receivedAmount <= 0.009 && $legacyStatus === 'paid') {
                $receivedAmount = $saleAmount;
            }
            $outstanding = max(0.0, round($saleAmount - $receivedAmount, 2));
            $summary[$code]['invoice_count']++;
            if ($outstanding > 0.009) {
                $summary[$code]['open_invoice_count']++;
            }
            $summary[$code]['sales_total'] += $saleAmount;
            $summary[$code]['received_total'] += $receivedAmount;
            $summary[$code]['outstanding'] += $outstanding;
        }
        ksort($summary);
        return $summary;
    }

    private function customerStatementData(int $customerId, string $from, string $to, string $currencyCode): array
    {
        $params = ['customer_id' => $customerId];
        $currencyFilterSql = '';
        if ($currencyCode !== 'ALL') {
            $currencyFilterSql = ' AND txn.currency_code = :currency_code';
            $params['currency_code'] = $currencyCode;
        }
        $toSql = '';
        if ($to !== '') {
            $toSql = ' AND txn.txn_date <= :to_date';
            $params['to_date'] = $to;
        }
        $sql = '
            SELECT *
            FROM (
                SELECT s.currency_code, s.invoice_date AS txn_date, COALESCE(s.created_at, CONCAT(s.invoice_date, " 00:00:00")) AS created_at,
                       "sale" AS txn_type, 1 AS sort_order, s.invoice_no AS reference_no,
                       CONCAT("Sales invoice • ", s.invoice_no) AS description,
                       s.final_amount AS debit_amount, 0.00 AS credit_amount, s.id AS sale_id, s.id AS entity_id
                FROM sales s
                WHERE s.customer_id = :customer_id

                UNION ALL

                SELECT sr.currency_code, sr.receipt_date AS txn_date, COALESCE(sr.created_at, CONCAT(sr.receipt_date, " 00:00:00")) AS created_at,
                       "receipt" AS txn_type, 2 AS sort_order, sr.receipt_no AS reference_no,
                       CONCAT("Customer receipt • ", sr.receipt_no, " / ", s.invoice_no) AS description,
                       0.00 AS debit_amount, sr.amount_currency AS credit_amount, sr.sale_id AS sale_id, sr.id AS entity_id
                FROM sale_receipts sr
                INNER JOIN sales s ON s.id = sr.sale_id
                WHERE s.customer_id = :customer_id

                UNION ALL

                SELECT s.currency_code, s.invoice_date AS txn_date, COALESCE(s.created_at, CONCAT(s.invoice_date, " 00:00:00")) AS created_at,
                       "legacy_receipt" AS txn_type, 3 AS sort_order, CONCAT("LEGACY-", s.invoice_no) AS reference_no,
                       CONCAT("Legacy settlement • ", s.invoice_no) AS description,
                       0.00 AS debit_amount, s.final_amount AS credit_amount, s.id AS sale_id, s.id AS entity_id
                FROM sales s
                WHERE s.customer_id = :customer_id
                  AND s.payment_status = "paid"
                  AND NOT EXISTS (SELECT 1 FROM sale_receipts sr WHERE sr.sale_id = s.id)
            ) txn
            WHERE 1 = 1' . $currencyFilterSql . $toSql . '
            ORDER BY txn.currency_code ASC, txn.txn_date ASC, txn.sort_order ASC, txn.created_at ASC, txn.entity_id ASC
        ';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $runningByCurrency = [];
        $openingByCurrency = [];
        $groupedRows = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) ($row['currency_code'] ?? 'AED'));
            $runningByCurrency[$code] = $runningByCurrency[$code] ?? 0.0;
            $debit = round((float) ($row['debit_amount'] ?? 0), 2);
            $credit = round((float) ($row['credit_amount'] ?? 0), 2);
            $effect = round($debit - $credit, 2);
            $txnDate = (string) ($row['txn_date'] ?? '');
            if ($from !== '' && $txnDate !== '' && strcmp($txnDate, $from) < 0) {
                $runningByCurrency[$code] = round($runningByCurrency[$code] + $effect, 2);
                $openingByCurrency[$code] = $runningByCurrency[$code];
                continue;
            }
            $runningByCurrency[$code] = round($runningByCurrency[$code] + $effect, 2);
            $row['running_balance'] = $runningByCurrency[$code];
            $row['debit_amount'] = $debit;
            $row['credit_amount'] = $credit;
            $row['link'] = '/sales/show?id=' . (int) ($row['sale_id'] ?? 0);
            $groupedRows[$code][] = $row;
        }
        return $this->composeStatementSections($groupedRows, $openingByCurrency, $runningByCurrency, $from, $to, $currencyCode);
    }

    private function findSupplier(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function supplierSummaryByCurrency(int $supplierId): array
    {
        $statement = Database::connection()->prepare('
            SELECT p.id, p.invoice_no, p.invoice_date, p.currency_code, p.final_amount,
                   COALESCE((SELECT SUM(prt.total_amount) FROM purchase_returns prt WHERE prt.purchase_id = p.id), 0) AS returned_amount,
                   COALESCE((SELECT SUM(pp.amount_currency) FROM purchase_payments pp WHERE pp.purchase_id = p.id), 0) AS paid_amount
            FROM purchases p
            WHERE p.supplier_id = :supplier_id
            ORDER BY p.invoice_date ASC, p.id ASC
        ');
        $statement->execute(['supplier_id' => $supplierId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $summary = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) ($row['currency_code'] ?? 'AED'));
            if (!isset($summary[$code])) {
                $summary[$code] = [
                    'currency_code' => $code,
                    'invoice_count' => 0,
                    'open_invoice_count' => 0,
                    'purchase_total' => 0.0,
                    'return_total' => 0.0,
                    'paid_total' => 0.0,
                    'outstanding' => 0.0,
                ];
            }
            $purchaseAmount = round((float) ($row['final_amount'] ?? 0), 2);
            $returnedAmount = round((float) ($row['returned_amount'] ?? 0), 2);
            $paidAmount = round((float) ($row['paid_amount'] ?? 0), 2);
            $outstanding = max(0.0, round(max(0.0, $purchaseAmount - $returnedAmount) - $paidAmount, 2));
            $summary[$code]['invoice_count']++;
            if ($outstanding > 0.009) {
                $summary[$code]['open_invoice_count']++;
            }
            $summary[$code]['purchase_total'] += $purchaseAmount;
            $summary[$code]['return_total'] += $returnedAmount;
            $summary[$code]['paid_total'] += $paidAmount;
            $summary[$code]['outstanding'] += $outstanding;
        }
        ksort($summary);
        return $summary;
    }

    private function supplierStatementData(int $supplierId, string $from, string $to, string $currencyCode): array
    {
        $params = ['supplier_id' => $supplierId];
        $currencyFilterSql = '';
        if ($currencyCode !== 'ALL') {
            $currencyFilterSql = ' AND txn.currency_code = :currency_code';
            $params['currency_code'] = $currencyCode;
        }
        $toSql = '';
        if ($to !== '') {
            $toSql = ' AND txn.txn_date <= :to_date';
            $params['to_date'] = $to;
        }
        $sql = '
            SELECT *
            FROM (
                SELECT p.currency_code, p.invoice_date AS txn_date, COALESCE(p.created_at, CONCAT(p.invoice_date, " 00:00:00")) AS created_at,
                       "purchase" AS txn_type, 1 AS sort_order, p.invoice_no AS reference_no,
                       CONCAT("Purchase invoice • ", p.invoice_no) AS description,
                       p.final_amount AS debit_amount, 0.00 AS credit_amount, p.id AS purchase_id, p.id AS entity_id
                FROM purchases p
                WHERE p.supplier_id = :supplier_id

                UNION ALL

                SELECT prt.currency_code, prt.return_date AS txn_date, COALESCE(prt.created_at, CONCAT(prt.return_date, " 00:00:00")) AS created_at,
                       "purchase_return" AS txn_type, 2 AS sort_order, prt.return_no AS reference_no,
                       CONCAT("Purchase return • ", prt.return_no, " / ", p.invoice_no) AS description,
                       0.00 AS debit_amount, prt.total_amount AS credit_amount, prt.purchase_id AS purchase_id, prt.id AS entity_id
                FROM purchase_returns prt
                INNER JOIN purchases p ON p.id = prt.purchase_id
                WHERE p.supplier_id = :supplier_id

                UNION ALL

                SELECT pp.currency_code, pp.payment_date AS txn_date, COALESCE(pp.created_at, CONCAT(pp.payment_date, " 00:00:00")) AS created_at,
                       "payment" AS txn_type, 3 AS sort_order, pp.payment_no AS reference_no,
                       CONCAT("Supplier payment • ", pp.payment_no, " / ", p.invoice_no) AS description,
                       0.00 AS debit_amount, pp.amount_currency AS credit_amount, pp.purchase_id AS purchase_id, pp.id AS entity_id
                FROM purchase_payments pp
                INNER JOIN purchases p ON p.id = pp.purchase_id
                WHERE p.supplier_id = :supplier_id
            ) txn
            WHERE 1 = 1' . $currencyFilterSql . $toSql . '
            ORDER BY txn.currency_code ASC, txn.txn_date ASC, txn.sort_order ASC, txn.created_at ASC, txn.entity_id ASC
        ';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $runningByCurrency = [];
        $openingByCurrency = [];
        $groupedRows = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) ($row['currency_code'] ?? 'AED'));
            $runningByCurrency[$code] = $runningByCurrency[$code] ?? 0.0;
            $debit = round((float) ($row['debit_amount'] ?? 0), 2);
            $credit = round((float) ($row['credit_amount'] ?? 0), 2);
            $effect = round($debit - $credit, 2);
            $txnDate = (string) ($row['txn_date'] ?? '');
            if ($from !== '' && $txnDate !== '' && strcmp($txnDate, $from) < 0) {
                $runningByCurrency[$code] = round($runningByCurrency[$code] + $effect, 2);
                $openingByCurrency[$code] = $runningByCurrency[$code];
                continue;
            }
            $runningByCurrency[$code] = round($runningByCurrency[$code] + $effect, 2);
            $row['running_balance'] = $runningByCurrency[$code];
            $row['debit_amount'] = $debit;
            $row['credit_amount'] = $credit;
            $row['link'] = '/purchases/show?id=' . (int) ($row['purchase_id'] ?? 0);
            $groupedRows[$code][] = $row;
        }
        return $this->composeStatementSections($groupedRows, $openingByCurrency, $runningByCurrency, $from, $to, $currencyCode);
    }

    private function composeStatementSections(array $groupedRows, array $openingByCurrency, array $runningByCurrency, string $from, string $to, string $currencyCode): array
    {
        $sectionCodes = array_unique(array_merge(array_keys($groupedRows), array_keys($openingByCurrency)));
        sort($sectionCodes);
        $sections = [];
        foreach ($sectionCodes as $code) {
            $rowsForCode = $groupedRows[$code] ?? [];
            $openingBalance = round((float) ($openingByCurrency[$code] ?? 0), 2);
            $debitTotal = 0.0;
            $creditTotal = 0.0;
            foreach ($rowsForCode as $row) {
                $debitTotal += (float) ($row['debit_amount'] ?? 0);
                $creditTotal += (float) ($row['credit_amount'] ?? 0);
            }
            $sections[] = [
                'currency_code' => $code,
                'opening_balance' => $openingBalance,
                'debit_total' => round($debitTotal, 2),
                'credit_total' => round($creditTotal, 2),
                'closing_balance' => round((float) ($runningByCurrency[$code] ?? $openingBalance), 2),
                'rows' => $rowsForCode,
            ];
        }
        return [
            'sections' => $sections,
            'meta' => [
                'from' => $from,
                'to' => $to,
                'currency_code' => $currencyCode,
                'section_count' => count($sections),
            ],
        ];
    }

    private function findBankAccount(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM bank_accounts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function bankStatementData(int $accountId, string $from, string $to, string $search, array $account): array
    {
        $pdo = Database::connection();
        $params = ['account_id' => $accountId, 'from' => $from, 'to' => $to];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (COALESCE(bt.reference_no, "") LIKE :search OR COALESCE(bt.counterparty, "") LIKE :search OR COALESCE(bt.note, "") LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $statement = $pdo->prepare('
            SELECT bt.*, rba.account_name AS related_account_name, rba.code AS related_account_code
            FROM banking_transactions bt
            LEFT JOIN bank_accounts rba ON rba.id = bt.related_bank_account_id
            WHERE bt.bank_account_id = :account_id
              AND DATE(bt.txn_date) BETWEEN :from AND :to' . $searchSql . '
            ORDER BY bt.txn_date ASC, bt.id ASC
        ');
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $openingBalanceCurrency = round((float) ($account['opening_balance_currency'] ?? 0), 2);
        $openingBalanceAed = round((float) ($account['opening_balance_aed'] ?? 0), 2);
        $priorStatement = $pdo->prepare('
            SELECT type, amount_currency, amount_aed
            FROM banking_transactions
            WHERE bank_account_id = :account_id
              AND DATE(txn_date) < :from
            ORDER BY txn_date ASC, id ASC
        ');
        $priorStatement->execute(['account_id' => $accountId, 'from' => $from]);
        foreach ($priorStatement->fetchAll(PDO::FETCH_ASSOC) as $prior) {
            $sign = $this->bankTxnSign((string) ($prior['type'] ?? 'deposit'));
            $openingBalanceCurrency = round($openingBalanceCurrency + ((float) ($prior['amount_currency'] ?? 0) * $sign), 2);
            $openingBalanceAed = round($openingBalanceAed + ((float) ($prior['amount_aed'] ?? 0) * $sign), 2);
        }

        $runningCurrency = $openingBalanceCurrency;
        $runningAed = $openingBalanceAed;
        $inflowCurrency = 0.0;
        $outflowCurrency = 0.0;
        $inflowAed = 0.0;
        $outflowAed = 0.0;

        foreach ($rows as &$row) {
            $sign = $this->bankTxnSign((string) ($row['type'] ?? 'deposit'));
            $amountCurrency = round((float) ($row['amount_currency'] ?? 0), 2);
            $amountAed = round((float) ($row['amount_aed'] ?? 0), 2);
            if ($sign > 0) {
                $inflowCurrency += $amountCurrency;
                $inflowAed += $amountAed;
            } else {
                $outflowCurrency += $amountCurrency;
                $outflowAed += $amountAed;
            }
            $runningCurrency = round($runningCurrency + ($amountCurrency * $sign), 2);
            $runningAed = round($runningAed + ($amountAed * $sign), 2);
            $row['running_balance_currency'] = $runningCurrency;
            $row['running_balance_aed'] = $runningAed;
            $row['direction'] = $sign > 0 ? 'in' : 'out';
        }
        unset($row);

        return [
            'opening_balance_currency' => $openingBalanceCurrency,
            'opening_balance_aed' => $openingBalanceAed,
            'inflow_currency' => round($inflowCurrency, 2),
            'outflow_currency' => round($outflowCurrency, 2),
            'inflow_aed' => round($inflowAed, 2),
            'outflow_aed' => round($outflowAed, 2),
            'closing_balance_currency' => $runningCurrency,
            'closing_balance_aed' => $runningAed,
            'rows' => $rows,
        ];
    }

    private function bankTxnSign(string $type): int
    {
        return in_array($type, ['deposit', 'adjustment_in', 'transfer_in', 'sale_receipt'], true) ? 1 : -1;
    }

    private function findInventoryReceipt(int $receiptId): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT ir.*, p.invoice_no AS purchase_invoice_no, p.invoice_date AS purchase_invoice_date,
                   s.name AS supplier_name, s.mobile AS supplier_mobile, s.address AS supplier_address,
                   w.name AS warehouse_name, w.code AS warehouse_code, w.location AS warehouse_location,
                   u.name AS created_by_name
            FROM inventory_receipts ir
            INNER JOIN purchases p ON p.id = ir.purchase_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            INNER JOIN warehouses w ON w.id = ir.warehouse_id
            LEFT JOIN users u ON u.id = ir.created_by
            WHERE ir.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $receiptId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function listInventoryReceiptItems(int $receiptId): array
    {
        $statement = Database::connection()->prepare('
            SELECT iri.*, pr.code AS product_code, pr.name AS product_name, pr.unit
            FROM inventory_receipt_items iri
            INNER JOIN products pr ON pr.id = iri.product_id
            WHERE iri.receipt_id = :receipt_id
            ORDER BY iri.id ASC
        ');
        $statement->execute(['receipt_id' => $receiptId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
