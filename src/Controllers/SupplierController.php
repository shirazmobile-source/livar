<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use PDO;

final class SupplierController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('suppliers');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();

        $sql = '
            SELECT
                s.*,
                COALESCE((SELECT COUNT(*) FROM purchases p WHERE p.supplier_id = s.id), 0) AS purchase_count,
                (SELECT MAX(p.invoice_date) FROM purchases p WHERE p.supplier_id = s.id) AS last_purchase_date,
                (SELECT MAX(pp.payment_date) FROM purchase_payments pp WHERE pp.supplier_id = s.id) AS last_payment_date
            FROM suppliers s
        ';

        if ($search !== '') {
            $statement = $pdo->prepare($sql . '
                WHERE s.name LIKE :search OR s.code LIKE :search OR s.mobile LIKE :search OR s.email LIKE :search
                ORDER BY s.id DESC
            ');
            $statement->execute(['search' => '%' . $search . '%']);
            $suppliers = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $suppliers = $pdo->query($sql . ' ORDER BY s.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        }

        $summary = [
            'total_suppliers' => count($suppliers),
            'active_suppliers' => count(array_filter($suppliers, static fn (array $supplier): bool => (string) ($supplier['status'] ?? 'active') === 'active')),
            'suppliers_with_purchases' => count(array_filter($suppliers, static fn (array $supplier): bool => (int) ($supplier['purchase_count'] ?? 0) > 0)),
            'suppliers_paid_recently' => count(array_filter($suppliers, static fn (array $supplier): bool => !empty($supplier['last_payment_date']))),
        ];

        $this->render('suppliers/index', [
            'title' => 'Suppliers',
            'suppliers' => $suppliers,
            'search' => $search,
            'summary' => $summary,
        ]);
    }

    public function show(): void
    {
        $this->requirePermission('suppliers');

        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $this->findSupplier($id);
        if (!$supplier) {
            $this->redirect('/suppliers', null, 'Supplier not found.');
        }

        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $currencyCode = strtoupper(trim((string) ($_GET['currency'] ?? 'ALL')));
        if ($currencyCode === '') {
            $currencyCode = 'ALL';
        }

        $statement = $this->buildStatement($id, $from, $to, $currencyCode);
        $summaryByCurrency = $this->buildSummaryByCurrency($id);
        $currencyOptions = $this->listSupplierCurrencies($id);

        $this->render('suppliers/show', [
            'title' => 'Supplier Statement',
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'currencyCode' => $currencyCode,
            'currencyOptions' => $currencyOptions,
            'summaryByCurrency' => $summaryByCurrency,
            'statementSections' => $statement['sections'],
            'statementMeta' => $statement['meta'],
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('suppliers');

        $this->render('suppliers/form', [
            'title' => 'New Supplier',
            'action' => '/suppliers/store',
            'supplier' => [
                'code' => 'SUP-' . date('ymdHis'),
                'name' => '',
                'mobile' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'status' => 'active',
            ],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('suppliers');
        $this->verifyCsrf();

        $input = [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];

        with_old($input);

        $errors = Validator::make($input, [
            'code' => ['required', 'max:50'],
            'name' => ['required', 'max:120'],
            'email' => ['max:120'],
        ]);

        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/suppliers/create');
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('
            INSERT INTO suppliers (code, name, mobile, phone, email, address, status, created_at, updated_at)
            VALUES (:code, :name, :mobile, :phone, :email, :address, :status, NOW(), NOW())
        ');

        $statement->execute($input);

        clear_old();
        $this->redirect('/suppliers', 'Supplier created successfully.');
    }

    public function edit(): void
    {
        $this->requirePermission('suppliers');

        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $this->findSupplier($id);

        if (!$supplier) {
            $this->redirect('/suppliers', null, 'Supplier not found.');
        }

        $this->render('suppliers/form', [
            'title' => 'Edit Supplier',
            'action' => '/suppliers/update?id=' . $id,
            'supplier' => $supplier,
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('suppliers');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $input = [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];

        with_old($input);

        $errors = Validator::make($input, [
            'code' => ['required', 'max:50'],
            'name' => ['required', 'max:120'],
            'email' => ['max:120'],
        ]);

        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/suppliers/edit?id=' . $id);
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('
            UPDATE suppliers
            SET code = :code, name = :name, mobile = :mobile, phone = :phone, email = :email,
                address = :address, status = :status, updated_at = NOW()
            WHERE id = :id
        ');

        $statement->execute($input + ['id' => $id]);

        clear_old();
        $this->redirect('/suppliers', 'Supplier updated successfully.');
    }

    private function findSupplier(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $supplier = $statement->fetch(PDO::FETCH_ASSOC);

        return $supplier ?: null;
    }

    /** @return array<int, string> */
    private function listSupplierCurrencies(int $supplierId): array
    {
        $statement = Database::connection()->prepare('
            SELECT DISTINCT p.currency_code
            FROM purchases p
            WHERE p.supplier_id = :supplier_id
            ORDER BY p.currency_code ASC
        ');
        $statement->execute(['supplier_id' => $supplierId]);

        return array_values(array_filter(array_map(static fn ($value): string => strtoupper((string) $value), $statement->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildSummaryByCurrency(int $supplierId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                p.id,
                p.invoice_no,
                p.invoice_date,
                p.currency_code,
                p.final_amount,
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
                    'net_payable' => 0.0,
                    'outstanding' => 0.0,
                    'last_purchase_date' => null,
                ];
            }

            $purchaseAmount = round((float) ($row['final_amount'] ?? 0), 2);
            $returnedAmount = round((float) ($row['returned_amount'] ?? 0), 2);
            $paidAmount = round((float) ($row['paid_amount'] ?? 0), 2);
            $netPayable = max(0.0, round($purchaseAmount - $returnedAmount, 2));
            $outstanding = max(0.0, round($netPayable - $paidAmount, 2));

            $summary[$code]['invoice_count']++;
            if ($outstanding > 0.009) {
                $summary[$code]['open_invoice_count']++;
            }
            $summary[$code]['purchase_total'] += $purchaseAmount;
            $summary[$code]['return_total'] += $returnedAmount;
            $summary[$code]['paid_total'] += $paidAmount;
            $summary[$code]['net_payable'] += $netPayable;
            $summary[$code]['outstanding'] += $outstanding;

            if (!empty($row['invoice_date'])) {
                $lastDate = (string) $row['invoice_date'];
                if ($summary[$code]['last_purchase_date'] === null || strcmp($lastDate, (string) $summary[$code]['last_purchase_date']) > 0) {
                    $summary[$code]['last_purchase_date'] = $lastDate;
                }
            }
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function buildStatement(int $supplierId, string $from, string $to, string $currencyCode): array
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
                SELECT
                    p.currency_code,
                    p.invoice_date AS txn_date,
                    COALESCE(p.created_at, CONCAT(p.invoice_date, " 00:00:00")) AS created_at,
                    "purchase" AS txn_type,
                    1 AS sort_order,
                    p.invoice_no AS reference_no,
                    CONCAT("Purchase invoice • ", p.invoice_no) AS description,
                    p.final_amount AS debit_amount,
                    0.00 AS credit_amount,
                    p.id AS purchase_id,
                    p.id AS entity_id
                FROM purchases p
                WHERE p.supplier_id = :supplier_id

                UNION ALL

                SELECT
                    prt.currency_code,
                    prt.return_date AS txn_date,
                    COALESCE(prt.created_at, CONCAT(prt.return_date, " 00:00:00")) AS created_at,
                    "purchase_return" AS txn_type,
                    2 AS sort_order,
                    prt.return_no AS reference_no,
                    CONCAT("Purchase return • ", prt.return_no, " / ", p.invoice_no) AS description,
                    0.00 AS debit_amount,
                    prt.total_amount AS credit_amount,
                    prt.purchase_id AS purchase_id,
                    prt.id AS entity_id
                FROM purchase_returns prt
                INNER JOIN purchases p ON p.id = prt.purchase_id
                WHERE p.supplier_id = :supplier_id

                UNION ALL

                SELECT
                    pp.currency_code,
                    pp.payment_date AS txn_date,
                    COALESCE(pp.created_at, CONCAT(pp.payment_date, " 00:00:00")) AS created_at,
                    "payment" AS txn_type,
                    3 AS sort_order,
                    pp.payment_no AS reference_no,
                    CONCAT("Supplier payment • ", pp.payment_no, " / ", p.invoice_no) AS description,
                    0.00 AS debit_amount,
                    pp.amount_currency AS credit_amount,
                    pp.purchase_id AS purchase_id,
                    pp.id AS entity_id
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
            if (!isset($runningByCurrency[$code])) {
                $runningByCurrency[$code] = 0.0;
            }

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
            $row['debit_amount'] = $debit;
            $row['credit_amount'] = $credit;
            $row['running_balance'] = $runningByCurrency[$code];
            $row['link'] = '/purchases/show?id=' . (int) ($row['purchase_id'] ?? 0);
            $groupedRows[$code][] = $row;
        }

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
}
