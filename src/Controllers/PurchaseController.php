<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\FormHelper;
use App\Core\InventoryService;
use PDO;
use RuntimeException;

final class PurchaseController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('purchases');

        $pdo = Database::connection();
        $statement = $pdo->query('
            SELECT
                p.*,
                s.name AS supplier_name,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN pi.qty ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS ordered_qty,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN COALESCE(pi.received_qty, 0) ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS received_qty,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN COALESCE(pi.returned_qty, 0) ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS returned_qty,
                COALESCE((SELECT SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN (pi.qty - COALESCE(pi.received_qty, 0)) ELSE 0 END) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = p.id), 0) AS pending_qty,
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
            ORDER BY p.id DESC
        ');
        $purchases = array_map(fn (array $purchase): array => $this->augmentPurchaseFinancials($purchase), $statement->fetchAll(PDO::FETCH_ASSOC));

        $this->render('purchases/index', [
            'title' => 'Purchases',
            'purchases' => $purchases,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('purchases');

        $this->render('purchases/form', [
            'title' => 'New Purchase',
            'action' => '/purchases/store',
            'suppliers' => $this->listSuppliers(),
            'products' => $this->listProducts(),
            'currencies' => $this->listCurrencies(),
            'defaultCurrency' => $this->resolveCurrency((int) old('currency_id', 0)),
            'purchase' => null,
            'purchaseItems' => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('purchases');
        $this->verifyCsrf();

        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $invoiceDate = trim((string) ($_POST['invoice_date'] ?? date('Y-m-d')));
        $discountAmount = (float) ($_POST['discount_amount'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        $currency = $this->resolveCurrency((int) ($_POST['currency_id'] ?? 0));
        $rate = max(0.00000001, (float) ($currency['rate_to_aed'] ?? 1));

        try {
            $items = FormHelper::normalizeItems(
                (array) ($_POST['product_id'] ?? []),
                (array) ($_POST['qty'] ?? []),
                (array) ($_POST['unit_price'] ?? []),
                (array) ($_POST['pricing_unit'] ?? []),
                (array) ($_POST['units_per_box'] ?? [])
            );

            if ($supplierId <= 0) {
                throw new RuntimeException('Please choose a supplier.');
            }

            $items = FormHelper::applyCurrencyRate($items, $rate);
            $totals = FormHelper::computeTotalsWithRate($items, $discountAmount, $rate);

            $purchaseId = InventoryService::purchase([
                'supplier_id' => $supplierId,
                'invoice_date' => $invoiceDate,
                'total_amount' => $totals['total_amount'],
                'discount_amount' => $totals['discount_amount'],
                'final_amount' => $totals['final_amount'],
                'total_amount_aed' => $totals['total_amount_aed'],
                'discount_amount_aed' => $totals['discount_amount_aed'],
                'final_amount_aed' => $totals['final_amount_aed'],
                'currency_id' => (int) ($currency['id'] ?? 0),
                'currency_code' => (string) ($currency['code'] ?? 'AED'),
                'currency_symbol' => (string) ($currency['symbol'] ?? 'د.إ'),
                'currency_rate_to_aed' => $rate,
                'note' => $note,
                'created_by' => Auth::id(),
                'items' => $items,
            ]);

            $this->redirect('/purchases/payments/create?purchase_id=' . $purchaseId, 'Purchase recorded successfully. Now choose whether to keep it payable or post a supplier payment from a same-currency bank account.');
        } catch (\Throwable $exception) {
            validation_errors(['purchase' => [$exception->getMessage()]]);
            with_old($_POST);
            $this->redirect('/purchases/create');
        }
    }

    public function edit(): void
    {
        $this->requirePermission('purchases');

        $id = (int) ($_GET['id'] ?? 0);
        $purchase = $this->findPurchase($id);

        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        if (!$this->isPurchaseEditable($purchase)) {
            $this->redirect('/purchases/show?id=' . $id, null, 'Only purchases that are still pending can be edited.');
        }

        $purchaseItems = $this->listPurchaseItems($id);
        $productIds = array_map(static fn (array $item): int => (int) $item['product_id'], $purchaseItems);

        $preferredCurrencyId = (int) old('currency_id', (int) ($purchase['currency_id'] ?? 0));

        $this->render('purchases/form', [
            'title' => 'Edit Purchase',
            'action' => '/purchases/update',
            'suppliers' => $this->listSuppliers((int) $purchase['supplier_id']),
            'products' => $this->listProducts($productIds),
            'currencies' => $this->listCurrencies((int) ($purchase['currency_id'] ?? 0)),
            'defaultCurrency' => $this->resolveCurrency($preferredCurrencyId),
            'purchase' => $purchase,
            'purchaseItems' => $purchaseItems,
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('purchases');
        $this->verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $purchase = $this->findPurchase($id);

        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        if (!$this->isPurchaseEditable($purchase)) {
            $this->redirect('/purchases/show?id=' . $id, null, 'This purchase is locked because warehouse receipt processing has already started.');
        }

        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $invoiceDate = trim((string) ($_POST['invoice_date'] ?? (string) ($purchase['invoice_date'] ?? date('Y-m-d'))));
        $discountAmount = (float) ($_POST['discount_amount'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        $currency = $this->resolveCurrency((int) ($_POST['currency_id'] ?? (int) ($purchase['currency_id'] ?? 0)));
        $rate = max(0.00000001, (float) ($currency['rate_to_aed'] ?? 1));

        try {
            $items = FormHelper::normalizeItems(
                (array) ($_POST['product_id'] ?? []),
                (array) ($_POST['qty'] ?? []),
                (array) ($_POST['unit_price'] ?? []),
                (array) ($_POST['pricing_unit'] ?? []),
                (array) ($_POST['units_per_box'] ?? [])
            );

            if ($supplierId <= 0) {
                throw new RuntimeException('Please choose a supplier.');
            }

            $items = FormHelper::applyCurrencyRate($items, $rate);
            $totals = FormHelper::computeTotalsWithRate($items, $discountAmount, $rate);

            InventoryService::updatePendingPurchase($id, [
                'supplier_id' => $supplierId,
                'invoice_date' => $invoiceDate,
                'total_amount' => $totals['total_amount'],
                'discount_amount' => $totals['discount_amount'],
                'final_amount' => $totals['final_amount'],
                'total_amount_aed' => $totals['total_amount_aed'],
                'discount_amount_aed' => $totals['discount_amount_aed'],
                'final_amount_aed' => $totals['final_amount_aed'],
                'currency_id' => (int) ($currency['id'] ?? 0),
                'currency_code' => (string) ($currency['code'] ?? 'AED'),
                'currency_symbol' => (string) ($currency['symbol'] ?? 'د.إ'),
                'currency_rate_to_aed' => $rate,
                'note' => $note,
                'items' => $items,
            ]);

            $this->redirect('/purchases/show?id=' . $id, 'Pending purchase updated successfully.');
        } catch (\Throwable $exception) {
            validation_errors(['purchase' => [$exception->getMessage()]]);
            with_old($_POST);
            $this->redirect('/purchases/edit?id=' . $id);
        }
    }

    public function show(): void
    {
        $this->requirePermission('purchases');

        $id = (int) ($_GET['id'] ?? 0);
        $purchase = $this->findPurchase($id);

        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $pdo = Database::connection();
        $items = $pdo->prepare('
            SELECT pi.*, pr.name AS product_name, pr.code AS product_code, pr.unit,
                   (pi.qty - COALESCE(pi.received_qty, 0)) AS pending_qty
            FROM purchase_items pi
            INNER JOIN products pr ON pr.id = pi.product_id
            WHERE pi.purchase_id = :id
            ORDER BY pi.id ASC
        ');
        $items->execute(['id' => $id]);

        $receipts = $pdo->prepare('
            SELECT ir.*, w.name AS warehouse_name, u.name AS created_by_name
            FROM inventory_receipts ir
            INNER JOIN warehouses w ON w.id = ir.warehouse_id
            LEFT JOIN users u ON u.id = ir.created_by
            WHERE ir.purchase_id = :id
            ORDER BY ir.id DESC
        ');
        $receipts->execute(['id' => $id]);

        $returns = $pdo->prepare('
            SELECT prt.*, w.name AS warehouse_name, u.name AS created_by_name
            FROM purchase_returns prt
            INNER JOIN warehouses w ON w.id = prt.warehouse_id
            LEFT JOIN users u ON u.id = prt.created_by
            WHERE prt.purchase_id = :id
            ORDER BY prt.id DESC
        ');
        $returns->execute(['id' => $id]);

        $returnableWarehouses = $this->listReturnableWarehouses($id);
        $canCreateReturn = $returnableWarehouses !== [];
        $payments = $this->listPurchasePayments($id);
        $matchingBankAccounts = $this->listPayableBankAccounts((string) ($purchase['currency_code'] ?? 'AED'));

        $this->render('purchases/show', [
            'title' => 'Purchase Invoice',
            'purchase' => $purchase,
            'items' => $items->fetchAll(PDO::FETCH_ASSOC),
            'receipts' => $receipts->fetchAll(PDO::FETCH_ASSOC),
            'returns' => $returns->fetchAll(PDO::FETCH_ASSOC),
            'payments' => $payments,
            'matchingBankAccounts' => $matchingBankAccounts,
            'canCreateReturn' => $canCreateReturn,
            'returnableWarehouses' => $returnableWarehouses,
        ]);
    }


    public function createPayment(): void
    {
        $this->requirePermission('purchases');

        $purchaseId = (int) ($_GET['purchase_id'] ?? 0);
        $purchase = $this->findPurchase($purchaseId);
        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $this->render('purchases/payment_form', [
            'title' => 'Purchase / Supplier Payment',
            'purchase' => $purchase,
            'payments' => $this->listPurchasePayments($purchaseId),
            'bankAccounts' => $this->listPayableBankAccounts((string) ($purchase['currency_code'] ?? 'AED')),
            'action' => '/purchases/payments/store',
            'entry' => [
                'purchase_id' => $purchaseId,
                'payment_date' => old('payment_date', date('Y-m-d')),
                'amount_currency' => old('amount_currency', number_format((float) ($purchase['due_amount'] ?? 0), 2, '.', '')),
                'bank_account_id' => (int) old('bank_account_id', 0),
                'reference_no' => old('reference_no', 'PAY-' . date('ymdHis')),
                'note' => old('note', ''),
                'payment_action' => old('payment_action', 'credit'),
            ],
        ]);
    }

    public function storePayment(): void
    {
        $this->requirePermission('purchases');
        $this->verifyCsrf();

        $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
        $purchase = $this->findPurchase($purchaseId);
        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $action = trim((string) ($_POST['payment_action'] ?? 'credit'));
        $paymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
        $amountCurrency = round((float) ($_POST['amount_currency'] ?? 0), 2);
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $referenceNo = trim((string) ($_POST['reference_no'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        with_old($_POST);

        if ($action === 'credit') {
            clear_old();
            $this->redirect('/purchases/show?id=' . $purchaseId, 'Purchase kept as payable. You can settle this supplier invoice later from the purchase screen.');
        }

        $errors = [];
        if ($purchase['due_amount'] <= 0.009) {
            $errors['payment'][] = 'This purchase is already fully settled.';
        }
        if ($amountCurrency <= 0) {
            $errors['amount_currency'][] = 'Enter a payment amount greater than zero.';
        }
        if ($amountCurrency - (float) ($purchase['due_amount'] ?? 0) > 0.009) {
            $errors['amount_currency'][] = 'Payment amount cannot exceed the outstanding amount for this purchase.';
        }
        if ($bankAccountId <= 0) {
            $errors['bank_account_id'][] = 'Choose a banking account with the same currency as the purchase invoice.';
        }

        $bankAccount = $this->findPayableBankAccount($bankAccountId, (string) ($purchase['currency_code'] ?? 'AED'));
        if (!$bankAccount) {
            $errors['bank_account_id'][] = 'Choose an active banking account that matches the invoice currency exactly.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/purchases/payments/create?purchase_id=' . $purchaseId);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $paymentNo = \App\Core\InvoiceNumber::generate('purchase_payments', 'payment_no', 'PPY');
            $rate = max(0.00000001, (float) ($purchase['currency_rate_to_aed'] ?? 1));
            $amountAed = round($amountCurrency * $rate, 2);
            $newBalanceCurrency = round((float) ($bankAccount['current_balance_currency'] ?? 0) - $amountCurrency, 2);
            $newBalanceAed = round((float) ($bankAccount['current_balance_aed'] ?? 0) - $amountAed, 2);

            $paymentInsert = $pdo->prepare('
                INSERT INTO purchase_payments (
                    purchase_id, supplier_id, bank_account_id, payment_no, payment_date,
                    amount_currency, currency_code, exchange_rate_to_aed, amount_aed, reference_no, note, created_by, created_at, updated_at
                ) VALUES (
                    :purchase_id, :supplier_id, :bank_account_id, :payment_no, :payment_date,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed, :reference_no, :note, :created_by, NOW(), NOW()
                )
            ');
            $paymentInsert->execute([
                'purchase_id' => $purchaseId,
                'supplier_id' => (int) ($purchase['supplier_id'] ?? 0),
                'bank_account_id' => $bankAccountId,
                'payment_no' => $paymentNo,
                'payment_date' => $paymentDate,
                'amount_currency' => $amountCurrency,
                'currency_code' => (string) ($purchase['currency_code'] ?? 'AED'),
                'exchange_rate_to_aed' => $rate,
                'amount_aed' => $amountAed,
                'reference_no' => $referenceNo,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);

            $bankingInsert = $pdo->prepare('
                INSERT INTO banking_transactions (
                    bank_account_id, related_bank_account_id, txn_date, type, reference_no, counterparty,
                    amount_currency, currency_code, exchange_rate_to_aed, amount_aed,
                    balance_after_currency, balance_after_aed, note, transfer_group, created_by, created_at, updated_at
                ) VALUES (
                    :bank_account_id, NULL, :txn_date, "purchase_payment", :reference_no, :counterparty,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed,
                    :balance_after_currency, :balance_after_aed, :note, NULL, :created_by, NOW(), NOW()
                )
            ');
            $bankingInsert->execute([
                'bank_account_id' => $bankAccountId,
                'txn_date' => $paymentDate,
                'reference_no' => $paymentNo,
                'counterparty' => (string) ($purchase['supplier_name'] ?? 'Supplier'),
                'amount_currency' => $amountCurrency,
                'currency_code' => (string) ($purchase['currency_code'] ?? 'AED'),
                'exchange_rate_to_aed' => $rate,
                'amount_aed' => $amountAed,
                'balance_after_currency' => $newBalanceCurrency,
                'balance_after_aed' => $newBalanceAed,
                'note' => trim('Purchase ' . (string) ($purchase['invoice_no'] ?? '') . ($note !== '' ? ' • ' . $note : '')),
                'created_by' => Auth::id(),
            ]);

            $bankUpdate = $pdo->prepare('UPDATE bank_accounts SET current_balance_currency = :current_balance_currency, current_balance_aed = :current_balance_aed, updated_at = NOW() WHERE id = :id');
            $bankUpdate->execute([
                'current_balance_currency' => $newBalanceCurrency,
                'current_balance_aed' => $newBalanceAed,
                'id' => $bankAccountId,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            validation_errors(['payment' => [$exception->getMessage()]]);
            $this->redirect('/purchases/payments/create?purchase_id=' . $purchaseId);
        }

        clear_old();
        $purchase = $this->findPurchase($purchaseId);
        $message = ($purchase['due_amount'] ?? 0) <= 0.009
            ? 'Supplier payment posted successfully. This purchase is now fully settled.'
            : 'Supplier payment posted successfully. The remaining balance stays payable.';
        $this->redirect('/purchases/show?id=' . $purchaseId, $message);
    }

    public function createReturn(): void
    {
        $this->requirePermission('purchases');

        $purchaseId = (int) ($_GET['purchase_id'] ?? 0);
        $purchase = $this->findPurchase($purchaseId);
        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $warehouses = $this->listReturnableWarehouses($purchaseId);
        if ($warehouses === []) {
            $this->redirect('/purchases/show?id=' . $purchaseId, null, 'No returnable stock is currently available for this purchase. Purchase returns are allowed only against received quantities that are still on hand in the selected warehouse.');
        }

        $warehouseId = (int) ($_GET['warehouse_id'] ?? (int) old('warehouse_id', 0));
        $warehouseIds = array_map(static fn (array $warehouse): int => (int) $warehouse['id'], $warehouses);
        if ($warehouseId <= 0 || !in_array($warehouseId, $warehouseIds, true)) {
            $warehouseId = (int) $warehouses[0]['id'];
        }

        $items = $this->listReturnableItems($purchaseId, $warehouseId);
        if ($items === []) {
            $this->redirect('/purchases/show?id=' . $purchaseId, null, 'The selected warehouse does not have any stock available to return for this purchase.');
        }

        $warehouse = null;
        foreach ($warehouses as $entry) {
            if ((int) $entry['id'] === $warehouseId) {
                $warehouse = $entry;
                break;
            }
        }

        $this->render('purchases/return_form', [
            'title' => 'Purchase Return',
            'purchase' => $purchase,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $warehouse,
            'items' => $items,
            'action' => '/purchases/returns/store',
        ]);
    }

    public function storeReturn(): void
    {
        $this->requirePermission('purchases');
        $this->verifyCsrf();

        $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
        $purchase = $this->findPurchase($purchaseId);
        if (!$purchase) {
            $this->redirect('/purchases', null, 'Purchase invoice not found.');
        }

        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
        $returnDate = trim((string) ($_POST['return_date'] ?? date('Y-m-d')));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        $itemIds = array_map('intval', (array) ($_POST['purchase_item_id'] ?? []));
        $quantities = (array) ($_POST['return_qty'] ?? []);
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
            validation_errors(['warehouse_id' => ['Please choose a warehouse for this purchase return.']]);
            with_old($_POST);
            $this->redirect('/purchases/returns/create?purchase_id=' . $purchaseId);
        }

        if ($reason === '') {
            validation_errors(['reason' => ['Please enter the reason for this purchase return.']]);
            with_old($_POST);
            $this->redirect('/purchases/returns/create?purchase_id=' . $purchaseId . '&warehouse_id=' . $warehouseId);
        }

        if ($lines === []) {
            validation_errors(['return_qty' => ['Enter at least one quantity to return.']]);
            with_old($_POST);
            $this->redirect('/purchases/returns/create?purchase_id=' . $purchaseId . '&warehouse_id=' . $warehouseId);
        }

        try {
            InventoryService::returnPurchase([
                'purchase_id' => $purchaseId,
                'warehouse_id' => $warehouseId,
                'return_date' => $returnDate,
                'reason' => $reason,
                'note' => $note,
                'created_by' => Auth::id(),
                'lines' => $lines,
            ]);
        } catch (\Throwable $exception) {
            validation_errors(['purchase_return' => [$exception->getMessage()]]);
            with_old($_POST);
            $this->redirect('/purchases/returns/create?purchase_id=' . $purchaseId . '&warehouse_id=' . $warehouseId);
        }

        clear_old();
        $this->redirect('/purchases/show?id=' . $purchaseId, 'Purchase return posted successfully. Warehouse stock and company stock were reduced based on the quantities returned.');
    }

    private function findPurchase(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                p.*, s.name AS supplier_name, s.mobile AS supplier_mobile, s.address AS supplier_address,
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

        return $purchase ? $this->augmentPurchaseFinancials($purchase) : null;
    }

    private function listPurchaseItems(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT pi.*
            FROM purchase_items pi
            WHERE pi.purchase_id = :purchase_id
            ORDER BY pi.id ASC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isPurchaseEditable(array $purchase): bool
    {
        if ((string) ($purchase['receipt_status'] ?? 'pending') !== 'pending') {
            return false;
        }

        $purchaseId = (int) ($purchase['id'] ?? 0);
        if ($purchaseId <= 0) {
            return false;
        }

        $pdo = Database::connection();

        $receiptCount = (int) $pdo->query('SELECT COUNT(*) FROM inventory_receipts WHERE purchase_id = ' . $purchaseId)->fetchColumn();
        if ($receiptCount > 0) {
            return false;
        }

        $receivedQty = (float) $pdo->query('SELECT COALESCE(SUM(CASE WHEN COALESCE(pr.item_type, "inventory") = "inventory" THEN COALESCE(pi.received_qty, 0) ELSE 0 END), 0) FROM purchase_items pi INNER JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = ' . $purchaseId)->fetchColumn();
        if ($receivedQty > 0.0001) {
            return false;
        }

        $paymentCount = (int) $pdo->query('SELECT COUNT(*) FROM purchase_payments WHERE purchase_id = ' . $purchaseId)->fetchColumn();

        return $paymentCount === 0;
    }

    private function listSuppliers(int $includeId = 0): array
    {
        $statement = Database::connection()->prepare('
            SELECT id, name
            FROM suppliers
            WHERE status = "active" OR id = :include_id
            ORDER BY CASE WHEN id = :include_id THEN 0 ELSE 1 END, name ASC
        ');
        $statement->execute(['include_id' => $includeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listProducts(array $includeIds = []): array
    {
        $includeIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int) $value, $includeIds), static fn (int $value): bool => $value > 0)));

        $sql = 'SELECT id, name, code, unit, purchase_price, current_stock, COALESCE(item_type, "inventory") AS item_type, CASE WHEN COALESCE(item_type, "inventory") = "inventory" THEN COALESCE(units_per_box, 1) ELSE 1 END AS units_per_box FROM products WHERE status = "active"';
        $params = [];

        if ($includeIds !== []) {
            $placeholders = [];
            foreach ($includeIds as $index => $id) {
                $key = 'include_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $sql .= ' OR id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY name ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listCurrencies(int $includeId = 0): array
    {
        $statement = Database::connection()->prepare('
            SELECT *
            FROM currencies
            WHERE status = "active" OR id = :include_id
            ORDER BY CASE WHEN code = "AED" THEN 0 ELSE 1 END, is_default DESC, name ASC
        ');
        $statement->execute(['include_id' => $includeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolveCurrency(int $requestedId = 0): array
    {
        if ($requestedId > 0) {
            $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $requestedId]);
            $currency = $statement->fetch(PDO::FETCH_ASSOC);
            if ($currency) {
                return $currency;
            }
        }

        $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE code = :code AND status = "active" ORDER BY is_default DESC, id ASC LIMIT 1');
        $statement->execute(['code' => 'AED']);
        $currency = $statement->fetch(PDO::FETCH_ASSOC);
        if ($currency) {
            return $currency;
        }

        $currency = Database::connection()->query('SELECT * FROM currencies WHERE status = "active" ORDER BY is_default DESC, id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($currency) {
            return $currency;
        }

        throw new RuntimeException('No active invoice currency is configured. Please add AED in Products / Currency first.');
    }

    private function listReturnableWarehouses(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                w.id,
                w.name,
                w.code,
                COALESCE(SUM(iri.qty), 0) AS received_qty,
                COALESCE((
                    SELECT SUM(pri.qty)
                    FROM purchase_return_items pri
                    INNER JOIN purchase_returns prt ON prt.id = pri.purchase_return_id
                    WHERE prt.purchase_id = ir.purchase_id
                      AND prt.warehouse_id = ir.warehouse_id
                ), 0) AS returned_qty
            FROM inventory_receipts ir
            INNER JOIN inventory_receipt_items iri ON iri.receipt_id = ir.id
            INNER JOIN warehouses w ON w.id = ir.warehouse_id
            WHERE ir.purchase_id = :purchase_id
            GROUP BY w.id, w.name, w.code, ir.purchase_id, ir.warehouse_id
            ORDER BY w.name ASC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $warehouses = [];
        foreach ($rows as $row) {
            $receivedQty = round((float) ($row['received_qty'] ?? 0), 2);
            $returnedQty = round((float) ($row['returned_qty'] ?? 0), 2);
            $returnableQty = max(0.0, round($receivedQty - $returnedQty, 2));
            if ($returnableQty <= 0) {
                continue;
            }

            $row['received_qty'] = $receivedQty;
            $row['returned_qty'] = $returnedQty;
            $row['returnable_qty'] = $returnableQty;
            $warehouses[] = $row;
        }

        return $warehouses;
    }

    private function listReturnableItems(int $purchaseId, int $warehouseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                pi.id AS purchase_item_id,
                pi.purchase_id,
                pi.product_id,
                pi.qty,
                pi.received_qty,
                pi.returned_qty,
                pi.unit_price,
                pi.unit_price_aed,
                pi.total_price,
                pi.total_price_aed,
                pr.name AS product_name,
                pr.code AS product_code,
                pr.unit,
                pr.current_stock AS company_stock_qty,
                COALESCE(ws.qty, 0) AS warehouse_stock_qty,
                COALESCE(SUM(iri.qty), 0) AS received_qty_in_warehouse,
                COALESCE((
                    SELECT SUM(pri.qty)
                    FROM purchase_return_items pri
                    INNER JOIN purchase_returns prt ON prt.id = pri.purchase_return_id
                    WHERE prt.purchase_id = pi.purchase_id
                      AND prt.warehouse_id = :warehouse_id
                      AND pri.purchase_item_id = pi.id
                ), 0) AS returned_qty_in_warehouse
            FROM purchase_items pi
            INNER JOIN products pr ON pr.id = pi.product_id
            INNER JOIN inventory_receipt_items iri ON iri.purchase_item_id = pi.id
            INNER JOIN inventory_receipts ir ON ir.id = iri.receipt_id AND ir.warehouse_id = :warehouse_id
            LEFT JOIN warehouse_stocks ws ON ws.warehouse_id = :warehouse_id AND ws.product_id = pi.product_id
            WHERE pi.purchase_id = :purchase_id
            GROUP BY
                pi.id,
                pi.purchase_id,
                pi.product_id,
                pi.qty,
                pi.received_qty,
                pi.returned_qty,
                pi.unit_price,
                pi.unit_price_aed,
                pi.total_price,
                pi.total_price_aed,
                pr.name,
                pr.code,
                pr.unit,
                pr.current_stock,
                ws.qty
            ORDER BY pr.name ASC
        ');
        $statement->execute([
            'purchase_id' => $purchaseId,
            'warehouse_id' => $warehouseId,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $receivedQty = round((float) ($row['received_qty_in_warehouse'] ?? 0), 2);
            $returnedQty = round((float) ($row['returned_qty_in_warehouse'] ?? 0), 2);
            $historyReturnable = max(0.0, round($receivedQty - $returnedQty, 2));
            $warehouseQty = round((float) ($row['warehouse_stock_qty'] ?? 0), 2);
            $companyQty = round((float) ($row['company_stock_qty'] ?? 0), 2);
            $availableReturnQty = min($historyReturnable, $warehouseQty, $companyQty);
            $availableReturnQty = max(0.0, round($availableReturnQty, 2));

            if ($availableReturnQty <= 0) {
                continue;
            }

            $row['received_qty_in_warehouse'] = $receivedQty;
            $row['returned_qty_in_warehouse'] = $returnedQty;
            $row['history_returnable_qty'] = $historyReturnable;
            $row['available_return_qty'] = $availableReturnQty;
            $items[] = $row;
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function listPurchasePayments(int $purchaseId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                pp.*,
                ba.bank_name,
                ba.account_name,
                ba.code AS bank_account_code,
                ba.currency_code AS bank_currency_code,
                u.name AS created_by_name
            FROM purchase_payments pp
            INNER JOIN bank_accounts ba ON ba.id = pp.bank_account_id
            LEFT JOIN users u ON u.id = pp.created_by
            WHERE pp.purchase_id = :purchase_id
            ORDER BY pp.payment_date DESC, pp.id DESC
        ');
        $statement->execute(['purchase_id' => $purchaseId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function listPayableBankAccounts(string $currencyCode): array
    {
        $statement = Database::connection()->prepare('
            SELECT ba.*, COALESCE(c.symbol, ba.currency_code) AS currency_symbol
            FROM bank_accounts ba
            LEFT JOIN currencies c ON c.id = ba.currency_id
            WHERE ba.status = "active" AND ba.currency_code = :currency_code
            ORDER BY ba.bank_name ASC, ba.account_name ASC
        ');
        $statement->execute(['currency_code' => $currencyCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findPayableBankAccount(int $accountId, string $currencyCode): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT ba.*
            FROM bank_accounts ba
            WHERE ba.id = :id AND ba.status = "active" AND ba.currency_code = :currency_code
            LIMIT 1
        ');
        $statement->execute(['id' => $accountId, 'currency_code' => $currencyCode]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    /** @param array<string, mixed> $purchase */
    private function augmentPurchaseFinancials(array $purchase): array
    {
        $returnedAmount = round((float) ($purchase['returned_amount'] ?? 0), 2);
        $returnedAmountAed = round((float) ($purchase['returned_amount_aed'] ?? 0), 2);
        $paidAmount = round((float) ($purchase['paid_amount'] ?? 0), 2);
        $paidAmountAed = round((float) ($purchase['paid_amount_aed'] ?? 0), 2);

        $netAmount = max(0.0, round((float) ($purchase['final_amount'] ?? 0) - $returnedAmount, 2));
        $netAmountAed = max(0.0, round((float) ($purchase['final_amount_aed'] ?? 0) - $returnedAmountAed, 2));
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
        $purchase['payment_count'] = (int) ($purchase['payment_count'] ?? 0);
        $purchase['can_pay_supplier'] = $dueAmount > 0.009;

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

        $purchase['inventory_item_count'] = $inventoryLineCount;
        $purchase['inventory_received_qty'] = $inventoryReceivedQty;
        $purchase['inventory_pending_qty'] = $inventoryPendingQty;
        $purchase['has_inventory_items'] = $inventoryLineCount > 0;
        $purchase['can_receive_inventory'] = $inventoryLineCount > 0 && $inventoryPendingQty > 0.009;
        $purchase['receipt_status_display'] = $derivedReceiptStatus;

        return $purchase;
    }

}
