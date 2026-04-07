<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\FormHelper;
use App\Core\InventoryService;
use App\Core\InvoiceNumber;
use PDO;
use RuntimeException;

final class SaleController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('sales');

        $pdo = Database::connection();
        $sales = $pdo->query('
            SELECT
                s.*,
                c.name AS customer_name,
                w.name AS warehouse_name,
                COALESCE((SELECT SUM(sr.amount_currency) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS received_amount,
                COALESCE((SELECT SUM(sr.amount_aed) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS received_amount_aed,
                COALESCE((SELECT COUNT(*) FROM sale_receipts sr WHERE sr.sale_id = s.id), 0) AS receipt_count
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN warehouses w ON w.id = s.warehouse_id
            ORDER BY s.id DESC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $sales = array_map(fn (array $sale): array => $this->augmentSaleFinancials($sale), $sales);

        $this->render('sales/index', [
            'title' => 'Sales',
            'sales' => $sales,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('sales');

        $pdo = Database::connection();
        $customers = $pdo->query('SELECT id, name FROM customers WHERE status = "active" ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query('SELECT id, name, code, unit, sale_price, current_stock, COALESCE(item_type, "inventory") AS item_type, CASE WHEN COALESCE(item_type, "inventory") = "inventory" THEN COALESCE(units_per_box, 1) ELSE 1 END AS units_per_box FROM products WHERE status = "active" ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $currencies = $this->listCurrencies();
        $defaultCurrency = $this->resolveCurrency((int) old('currency_id', 0));
        $warehouses = $pdo->query('SELECT * FROM warehouses WHERE status = "active" ORDER BY is_default DESC, name ASC')->fetchAll(PDO::FETCH_ASSOC);

        if ($warehouses === []) {
            $this->redirect('/inventory?tab=warehouses', null, 'Create at least one active warehouse before issuing a sale invoice.');
        }

        $warehouseStocks = $this->warehouseStockMap();

        $this->render('sales/form', [
            'title' => 'New Sale',
            'action' => '/sales/store',
            'customers' => $customers,
            'products' => $products,
            'currencies' => $currencies,
            'defaultCurrency' => $defaultCurrency,
            'warehouses' => $warehouses,
            'warehouseStocks' => $warehouseStocks,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('sales');
        $this->verifyCsrf();

        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
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

            if ($customerId <= 0) {
                throw new RuntimeException('Please choose a customer.');
            }

            if ($warehouseId <= 0) {
                throw new RuntimeException('Please choose a warehouse.');
            }

            $items = FormHelper::applyCurrencyRate($items, $rate);
            $totals = FormHelper::computeTotalsWithRate($items, $discountAmount, $rate);

            $saleId = InventoryService::sale([
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
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
                'payment_status' => 'pending',
                'note' => $note,
                'created_by' => Auth::id(),
                'items' => $items,
            ]);

            $this->redirect('/sales/receipts/create?sale_id=' . $saleId, 'Sale recorded successfully. Now choose whether to keep it receivable or post a same-currency customer receipt into banking.');
        } catch (\Throwable $exception) {
            validation_errors(['sale' => [$exception->getMessage()]]);
            with_old($_POST);
            $this->redirect('/sales/create');
        }
    }

    public function show(): void
    {
        $this->requirePermission('sales');

        $id = (int) ($_GET['id'] ?? 0);
        $sale = $this->findSale($id);

        if (!$sale) {
            $this->redirect('/sales', null, 'Sales invoice not found.');
        }

        $pdo = Database::connection();
        $items = $pdo->prepare('
            SELECT si.*, pr.name AS product_name, pr.code AS product_code, pr.unit
            FROM sale_items si
            INNER JOIN products pr ON pr.id = si.product_id
            WHERE si.sale_id = :id
            ORDER BY si.id ASC
        ');
        $items->execute(['id' => $id]);

        $receipts = $this->listSaleReceipts($id);
        $matchingBankAccounts = $this->listReceivableBankAccounts((string) ($sale['currency_code'] ?? 'AED'));

        $this->render('sales/show', [
            'title' => 'Sales Invoice',
            'sale' => $sale,
            'items' => $items->fetchAll(PDO::FETCH_ASSOC),
            'receipts' => $receipts,
            'matchingBankAccounts' => $matchingBankAccounts,
        ]);
    }

    public function createReceipt(): void
    {
        $this->requirePermission('sales');

        $saleId = (int) ($_GET['sale_id'] ?? 0);
        $sale = $this->findSale($saleId);
        if (!$sale) {
            $this->redirect('/sales', null, 'Sales invoice not found.');
        }

        $this->render('sales/receipt_form', [
            'title' => 'Sale / Customer Receipt',
            'sale' => $sale,
            'receipts' => $this->listSaleReceipts($saleId),
            'bankAccounts' => $this->listReceivableBankAccounts((string) ($sale['currency_code'] ?? 'AED')),
            'action' => '/sales/receipts/store',
            'entry' => [
                'sale_id' => $saleId,
                'receipt_date' => old('receipt_date', date('Y-m-d')),
                'amount_currency' => old('amount_currency', number_format((float) ($sale['due_amount'] ?? 0), 2, '.', '')),
                'bank_account_id' => (int) old('bank_account_id', 0),
                'reference_no' => old('reference_no', 'RCV-' . date('ymdHis')),
                'note' => old('note', ''),
                'receipt_action' => old('receipt_action', 'credit'),
            ],
        ]);
    }

    public function storeReceipt(): void
    {
        $this->requirePermission('sales');
        $this->verifyCsrf();

        $saleId = (int) ($_POST['sale_id'] ?? 0);
        $sale = $this->findSale($saleId);
        if (!$sale) {
            $this->redirect('/sales', null, 'Sales invoice not found.');
        }

        $action = trim((string) ($_POST['receipt_action'] ?? 'credit'));
        $receiptDate = trim((string) ($_POST['receipt_date'] ?? date('Y-m-d')));
        $amountCurrency = round((float) ($_POST['amount_currency'] ?? 0), 2);
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $referenceNo = trim((string) ($_POST['reference_no'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        with_old($_POST);

        if ($action === 'credit') {
            clear_old();
            $this->redirect('/sales/show?id=' . $saleId, 'Sale kept as receivable. You can collect customer payment later from the sales screen.');
        }

        $errors = [];
        if ((float) ($sale['due_amount'] ?? 0) <= 0.009) {
            $errors['receipt'][] = 'This sale is already fully settled.';
        }
        if ($amountCurrency <= 0) {
            $errors['amount_currency'][] = 'Enter a receipt amount greater than zero.';
        }
        if ($amountCurrency - (float) ($sale['due_amount'] ?? 0) > 0.009) {
            $errors['amount_currency'][] = 'Receipt amount cannot exceed the outstanding amount for this sale.';
        }
        if ($bankAccountId <= 0) {
            $errors['bank_account_id'][] = 'Choose a banking account with the same currency as the sales invoice.';
        }

        $bankAccount = $this->findReceivableBankAccount($bankAccountId, (string) ($sale['currency_code'] ?? 'AED'));
        if (!$bankAccount) {
            $errors['bank_account_id'][] = 'Choose an active banking account that matches the invoice currency exactly.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/sales/receipts/create?sale_id=' . $saleId);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $receiptNo = InvoiceNumber::generate('sale_receipts', 'receipt_no', 'SRC');
            $rate = max(0.00000001, (float) ($sale['currency_rate_to_aed'] ?? 1));
            $amountAed = round($amountCurrency * $rate, 2);
            $newBalanceCurrency = round((float) ($bankAccount['current_balance_currency'] ?? 0) + $amountCurrency, 2);
            $newBalanceAed = round((float) ($bankAccount['current_balance_aed'] ?? 0) + $amountAed, 2);

            $receiptInsert = $pdo->prepare('
                INSERT INTO sale_receipts (
                    sale_id, customer_id, bank_account_id, receipt_no, receipt_date,
                    amount_currency, currency_code, exchange_rate_to_aed, amount_aed, reference_no, note, created_by, created_at, updated_at
                ) VALUES (
                    :sale_id, :customer_id, :bank_account_id, :receipt_no, :receipt_date,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed, :reference_no, :note, :created_by, NOW(), NOW()
                )
            ');
            $receiptInsert->execute([
                'sale_id' => $saleId,
                'customer_id' => (int) ($sale['customer_id'] ?? 0),
                'bank_account_id' => $bankAccountId,
                'receipt_no' => $receiptNo,
                'receipt_date' => $receiptDate,
                'amount_currency' => $amountCurrency,
                'currency_code' => (string) ($sale['currency_code'] ?? 'AED'),
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
                    :bank_account_id, NULL, :txn_date, "sale_receipt", :reference_no, :counterparty,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed,
                    :balance_after_currency, :balance_after_aed, :note, NULL, :created_by, NOW(), NOW()
                )
            ');
            $bankingInsert->execute([
                'bank_account_id' => $bankAccountId,
                'txn_date' => $receiptDate,
                'reference_no' => $receiptNo,
                'counterparty' => (string) ($sale['customer_name'] ?? 'Customer'),
                'amount_currency' => $amountCurrency,
                'currency_code' => (string) ($sale['currency_code'] ?? 'AED'),
                'exchange_rate_to_aed' => $rate,
                'amount_aed' => $amountAed,
                'balance_after_currency' => $newBalanceCurrency,
                'balance_after_aed' => $newBalanceAed,
                'note' => trim('Sale ' . (string) ($sale['invoice_no'] ?? '') . ($note !== '' ? ' • ' . $note : '')),
                'created_by' => Auth::id(),
            ]);

            $bankUpdate = $pdo->prepare('UPDATE bank_accounts SET current_balance_currency = :current_balance_currency, current_balance_aed = :current_balance_aed, updated_at = NOW() WHERE id = :id');
            $bankUpdate->execute([
                'current_balance_currency' => $newBalanceCurrency,
                'current_balance_aed' => $newBalanceAed,
                'id' => $bankAccountId,
            ]);

            $updatedPaid = round((float) ($sale['received_amount'] ?? 0) + $amountCurrency, 2);
            $updatedDue = max(0.0, round((float) ($sale['final_amount'] ?? 0) - $updatedPaid, 2));
            $saleStatus = $updatedDue <= 0.009 ? 'paid' : 'pending';
            $saleUpdate = $pdo->prepare('UPDATE sales SET payment_status = :payment_status, updated_at = NOW() WHERE id = :id');
            $saleUpdate->execute([
                'payment_status' => $saleStatus,
                'id' => $saleId,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            validation_errors(['receipt' => [$exception->getMessage()]]);
            $this->redirect('/sales/receipts/create?sale_id=' . $saleId);
        }

        clear_old();
        $sale = $this->findSale($saleId);
        $message = ((float) ($sale['due_amount'] ?? 0) <= 0.009)
            ? 'Customer receipt posted successfully. This sale is now fully settled.'
            : 'Customer receipt posted successfully. The remaining balance stays receivable.';
        $this->redirect('/sales/show?id=' . $saleId, $message);
    }

    private function listCurrencies(): array
    {
        return Database::connection()->query('
            SELECT *
            FROM currencies
            WHERE status = "active"
            ORDER BY CASE WHEN code = "AED" THEN 0 ELSE 1 END, is_default DESC, name ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolveCurrency(int $requestedId = 0): array
    {
        if ($requestedId > 0) {
            $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE id = :id AND status = "active" LIMIT 1');
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

    private function warehouseStockMap(): array
    {
        $rows = Database::connection()->query('
            SELECT warehouse_id, product_id, qty
            FROM warehouse_stocks
        ')->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['product_id']][(int) $row['warehouse_id']] = (float) $row['qty'];
        }

        return $map;
    }

    private function findSale(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                s.*,
                c.name AS customer_name,
                c.mobile AS customer_mobile,
                c.address AS customer_address,
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

        return $this->augmentSaleFinancials($sale);
    }

    /** @return array<int, array<string, mixed>> */
    private function listSaleReceipts(int $saleId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                sr.*,
                ba.bank_name,
                ba.account_name,
                ba.code AS bank_account_code,
                ba.currency_code AS bank_currency_code,
                u.name AS created_by_name
            FROM sale_receipts sr
            INNER JOIN bank_accounts ba ON ba.id = sr.bank_account_id
            LEFT JOIN users u ON u.id = sr.created_by
            WHERE sr.sale_id = :sale_id
            ORDER BY sr.receipt_date DESC, sr.id DESC
        ');
        $statement->execute(['sale_id' => $saleId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function listReceivableBankAccounts(string $currencyCode): array
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

    private function findReceivableBankAccount(int $accountId, string $currencyCode): ?array
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

    /** @param array<string, mixed> $sale */
    private function augmentSaleFinancials(array $sale): array
    {
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
        $sale['receipt_count'] = $receiptCount;
        $sale['can_receive_payment'] = $dueAmount > 0.009;

        return $sale;
    }
}
