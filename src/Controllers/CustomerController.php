<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class CustomerController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('customers');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();

        $sql = '
            SELECT
                c.*,
                COALESCE(NULLIF(c.name, ""), NULLIF(c.company_name, ""), NULLIF(c.person_name, ""), c.code) AS display_name,
                COALESCE(cur.code, c.currency_code, "AED") AS currency_code_label,
                COALESCE(cur.symbol, "د.إ") AS currency_symbol_label,
                COALESCE(cur.name, "UAE Dirham") AS currency_name_label,
                COALESCE((SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id), 0) AS sale_count,
                (SELECT MAX(s.invoice_date) FROM sales s WHERE s.customer_id = c.id) AS last_sale_date,
                (SELECT MAX(sr.receipt_date)
                    FROM sale_receipts sr
                    INNER JOIN sales s2 ON s2.id = sr.sale_id
                    WHERE s2.customer_id = c.id
                ) AS last_receipt_date
            FROM customers c
            LEFT JOIN currencies cur ON cur.id = c.currency_id
        ';

        $params = [];
        if ($search !== '') {
            $sql .= '
                WHERE c.code LIKE :search
                   OR c.name LIKE :search
                   OR COALESCE(c.company_name, "") LIKE :search
                   OR COALESCE(c.person_name, "") LIKE :search
                   OR COALESCE(c.mobile, "") LIKE :search
                   OR COALESCE(c.trn_number, "") LIKE :search
                   OR COALESCE(c.email, "") LIKE :search
                   OR COALESCE(c.country_name, "") LIKE :search
            ';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY c.id DESC';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $customers = $statement->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'total_customers' => count($customers),
            'active_customers' => count(array_filter($customers, static fn (array $customer): bool => (string) ($customer['status'] ?? 'active') === 'active')),
            'customers_with_sales' => count(array_filter($customers, static fn (array $customer): bool => (int) ($customer['sale_count'] ?? 0) > 0)),
            'customers_paid_recently' => count(array_filter($customers, static fn (array $customer): bool => !empty($customer['last_receipt_date']))),
        ];

        $this->render('customers/index', [
            'title' => 'Customers',
            'customers' => $customers,
            'search' => $search,
            'summary' => $summary,
        ]);
    }

    public function show(): void
    {
        $this->requirePermission('customers');

        $id = (int) ($_GET['id'] ?? 0);
        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->redirect('/customers', null, 'Customer not found.');
        }

        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $currencyCode = strtoupper(trim((string) ($_GET['currency'] ?? 'ALL')));
        if ($currencyCode === '') {
            $currencyCode = 'ALL';
        }

        $statement = $this->buildStatement($id, $from, $to, $currencyCode);
        $summaryByCurrency = $this->buildSummaryByCurrency($id);
        $currencyOptions = $this->listCustomerCurrencies($id);

        $this->render('customers/show', [
            'title' => 'Customer Statement',
            'customer' => $customer,
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
        $this->requirePermission('customers');

        $currency = $this->aedCurrency() ?? $this->defaultCurrency();
        $country = $this->inferCountryFromMobile('+971');

        $this->render('customers/form', [
            'title' => 'New Customer',
            'action' => '/customers/store',
            'customer' => [
                'code' => 'CUS-' . date('ymdHis'),
                'customer_type' => 'individual',
                'company_name' => '',
                'person_name' => '',
                'name' => '',
                'mobile' => '+971 ',
                'email' => '',
                'trn_number' => '',
                'address' => '',
                'status' => 'active',
                'profile_image_path' => '',
                'currency_id' => (int) ($currency['id'] ?? 0),
                'currency_code' => (string) ($currency['code'] ?? 'AED'),
                'country_code' => (string) ($country['code'] ?? 'AE'),
                'country_name' => (string) ($country['name'] ?? 'United Arab Emirates'),
                'documents' => [],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('customers');
        $this->verifyCsrf();

        $input = $this->normalizeCustomerInput($_POST);
        with_old($input);

        $errors = $this->validateCustomer($input);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/customers/create');
        }

        try {
            $profileImagePath = $this->handleImageUpload($_FILES['profile_image'] ?? null, 'customers', 'cus');
        } catch (RuntimeException $exception) {
            validation_errors(['profile_image' => [$exception->getMessage()]]);
            $this->redirect('/customers/create');
        }

        $currency = $this->resolveCurrencyMeta((int) $input['currency_id']);

        $pdo = Database::connection();
        $documentPaths = [];

        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare('
                INSERT INTO customers (
                    code, customer_type, company_name, person_name, name, mobile, email, trn_number,
                    address, status, profile_image_path, currency_id, currency_code, country_code,
                    country_name, created_at, updated_at
                ) VALUES (
                    :code, :customer_type, :company_name, :person_name, :name, :mobile, :email, :trn_number,
                    :address, :status, :profile_image_path, :currency_id, :currency_code, :country_code,
                    :country_name, NOW(), NOW()
                )
            ');

            $statement->execute([
                'code' => $input['code'],
                'customer_type' => $input['customer_type'],
                'company_name' => $input['company_name'],
                'person_name' => $input['person_name'],
                'name' => $input['name'],
                'mobile' => $input['mobile'],
                'email' => $input['email'],
                'trn_number' => $input['trn_number'],
                'address' => $input['address'],
                'status' => $input['status'],
                'profile_image_path' => $profileImagePath,
                'currency_id' => $currency['id'],
                'currency_code' => $currency['code'],
                'country_code' => $input['country_code'],
                'country_name' => $input['country_name'],
            ]);

            $customerId = (int) $pdo->lastInsertId();
            $documentPaths = $this->storeDocuments($_FILES['documents'] ?? null, $customerId);

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            remove_public_file($profileImagePath);
            $this->cleanupFiles($documentPaths);
            validation_errors(['code' => ['The customer code already exists. Please try again.']]);
            $this->redirect('/customers/create');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            remove_public_file($profileImagePath);
            $this->cleanupFiles($documentPaths);
            validation_errors(['documents' => [$exception->getMessage()]]);
            $this->redirect('/customers/create');
        }

        clear_old();
        $this->redirect('/customers', 'Customer created successfully.');
    }

    public function edit(): void
    {
        $this->requirePermission('customers');

        $customer = $this->findCustomer((int) ($_GET['id'] ?? 0));
        if (!$customer) {
            $this->redirect('/customers', null, 'Customer not found.');
        }

        $this->render('customers/form', [
            'title' => 'Edit Customer',
            'action' => '/customers/update?id=' . (int) $customer['id'],
            'customer' => $customer,
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('customers');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findCustomer($id);
        if (!$existing) {
            $this->redirect('/customers', null, 'Customer not found.');
        }

        $input = $this->normalizeCustomerInput($_POST, $existing);
        with_old($input);

        $errors = $this->validateCustomer($input, $id);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/customers/edit?id=' . $id);
        }

        try {
            $profileImagePath = $this->handleImageUpload($_FILES['profile_image'] ?? null, 'customers', 'cus', (string) ($existing['profile_image_path'] ?? ''));
        } catch (RuntimeException $exception) {
            validation_errors(['profile_image' => [$exception->getMessage()]]);
            $this->redirect('/customers/edit?id=' . $id);
        }

        if ((string) ($_POST['remove_profile_image'] ?? '') === '1') {
            remove_public_file((string) ($existing['profile_image_path'] ?? ''));
            $profileImagePath = '';
        }

        $currency = $this->resolveCurrencyMeta((int) $input['currency_id']);
        $removeDocumentIds = array_map('intval', (array) ($_POST['remove_document_ids'] ?? []));

        $pdo = Database::connection();
        $documentPaths = [];

        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare('
                UPDATE customers
                SET code = :code,
                    customer_type = :customer_type,
                    company_name = :company_name,
                    person_name = :person_name,
                    name = :name,
                    mobile = :mobile,
                    email = :email,
                    trn_number = :trn_number,
                    address = :address,
                    status = :status,
                    profile_image_path = :profile_image_path,
                    currency_id = :currency_id,
                    currency_code = :currency_code,
                    country_code = :country_code,
                    country_name = :country_name,
                    updated_at = NOW()
                WHERE id = :id
            ');

            $statement->execute([
                'id' => $id,
                'code' => $input['code'],
                'customer_type' => $input['customer_type'],
                'company_name' => $input['company_name'],
                'person_name' => $input['person_name'],
                'name' => $input['name'],
                'mobile' => $input['mobile'],
                'email' => $input['email'],
                'trn_number' => $input['trn_number'],
                'address' => $input['address'],
                'status' => $input['status'],
                'profile_image_path' => $profileImagePath,
                'currency_id' => $currency['id'],
                'currency_code' => $currency['code'],
                'country_code' => $input['country_code'],
                'country_name' => $input['country_name'],
            ]);

            $this->removeDocuments($id, $removeDocumentIds);
            $documentPaths = $this->storeDocuments($_FILES['documents'] ?? null, $id);

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->cleanupFiles($documentPaths);
            validation_errors(['code' => ['The customer code already exists. Please try again.']]);
            $this->redirect('/customers/edit?id=' . $id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->cleanupFiles($documentPaths);
            validation_errors(['documents' => [$exception->getMessage()]]);
            $this->redirect('/customers/edit?id=' . $id);
        }

        clear_old();
        $this->redirect('/customers', 'Customer updated successfully.');
    }


    /** @return array<int, string> */
    private function listCustomerCurrencies(int $customerId): array
    {
        $statement = Database::connection()->prepare('
            SELECT DISTINCT s.currency_code
            FROM sales s
            WHERE s.customer_id = :customer_id
            ORDER BY s.currency_code ASC
        ');
        $statement->execute(['customer_id' => $customerId]);

        return array_values(array_filter(array_map(static fn ($value): string => strtoupper((string) $value), $statement->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildSummaryByCurrency(int $customerId): array
    {
        $statement = Database::connection()->prepare('
            SELECT
                s.id,
                s.invoice_no,
                s.invoice_date,
                s.currency_code,
                s.final_amount,
                s.payment_status,
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
                    'last_sale_date' => null,
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

            if (!empty($row['invoice_date'])) {
                $lastDate = (string) $row['invoice_date'];
                if ($summary[$code]['last_sale_date'] === null || strcmp($lastDate, (string) $summary[$code]['last_sale_date']) > 0) {
                    $summary[$code]['last_sale_date'] = $lastDate;
                }
            }
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function buildStatement(int $customerId, string $from, string $to, string $currencyCode): array
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
                SELECT
                    s.currency_code,
                    s.invoice_date AS txn_date,
                    COALESCE(s.created_at, CONCAT(s.invoice_date, " 00:00:00")) AS created_at,
                    "sale" AS txn_type,
                    1 AS sort_order,
                    s.invoice_no AS reference_no,
                    CONCAT("Sales invoice • ", s.invoice_no) AS description,
                    s.final_amount AS debit_amount,
                    0.00 AS credit_amount,
                    s.id AS sale_id,
                    s.id AS entity_id
                FROM sales s
                WHERE s.customer_id = :customer_id

                UNION ALL

                SELECT
                    sr.currency_code,
                    sr.receipt_date AS txn_date,
                    COALESCE(sr.created_at, CONCAT(sr.receipt_date, " 00:00:00")) AS created_at,
                    "receipt" AS txn_type,
                    2 AS sort_order,
                    sr.receipt_no AS reference_no,
                    CONCAT("Customer receipt • ", sr.receipt_no, " / ", s.invoice_no) AS description,
                    0.00 AS debit_amount,
                    sr.amount_currency AS credit_amount,
                    sr.sale_id AS sale_id,
                    sr.id AS entity_id
                FROM sale_receipts sr
                INNER JOIN sales s ON s.id = sr.sale_id
                WHERE s.customer_id = :customer_id

                UNION ALL

                SELECT
                    s.currency_code,
                    s.invoice_date AS txn_date,
                    COALESCE(s.created_at, CONCAT(s.invoice_date, " 00:00:00")) AS created_at,
                    "legacy_receipt" AS txn_type,
                    3 AS sort_order,
                    CONCAT("LEGACY-", s.invoice_no) AS reference_no,
                    CONCAT("Legacy settlement • ", s.invoice_no) AS description,
                    0.00 AS debit_amount,
                    s.final_amount AS credit_amount,
                    s.id AS sale_id,
                    s.id AS entity_id
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
            $row['link'] = '/sales/show?id=' . (int) ($row['sale_id'] ?? 0);
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

    private function normalizeCustomerInput(array $source, ?array $existing = null): array
    {
        $type = strtolower(trim((string) ($source['customer_type'] ?? ($existing['customer_type'] ?? 'individual'))));
        if (!in_array($type, ['business', 'individual'], true)) {
            $type = 'individual';
        }

        $companyName = trim((string) ($source['company_name'] ?? ($existing['company_name'] ?? '')));
        $personName = trim((string) ($source['person_name'] ?? ($existing['person_name'] ?? '')));
        $mobile = trim((string) ($source['mobile'] ?? ($existing['mobile'] ?? '')));
        $country = $this->inferCountryFromMobile($mobile);

        $countryCode = trim((string) ($source['country_code'] ?? ($existing['country_code'] ?? ($country['code'] ?? ''))));
        $countryName = trim((string) ($source['country_name'] ?? ($existing['country_name'] ?? ($country['name'] ?? ''))));

        if (($country['code'] ?? '') !== '') {
            $countryCode = (string) $country['code'];
        }
        if (($country['name'] ?? '') !== '') {
            $countryName = (string) $country['name'];
        }

        $name = $type === 'business' ? $companyName : $personName;

        return [
            'code' => trim((string) ($source['code'] ?? ($existing['code'] ?? ''))),
            'customer_type' => $type,
            'company_name' => $companyName,
            'person_name' => $personName,
            'name' => $name,
            'mobile' => $mobile,
            'email' => trim((string) ($source['email'] ?? ($existing['email'] ?? ''))),
            'trn_number' => trim((string) ($source['trn_number'] ?? ($existing['trn_number'] ?? ''))),
            'address' => trim((string) ($source['address'] ?? ($existing['address'] ?? ''))),
            'status' => trim((string) ($source['status'] ?? ($existing['status'] ?? 'active'))),
            'currency_id' => 0,
            'country_code' => $countryCode,
            'country_name' => $countryName,
        ];
    }

    private function validateCustomer(array $input, ?int $ignoreId = null): array
    {
        $errors = Validator::make($input, [
            'code' => ['required', 'max:60'],
            'company_name' => ['max:160'],
            'person_name' => ['max:160'],
            'email' => ['max:190'],
            'trn_number' => ['max:120'],
            'mobile' => ['max:60'],
        ]);

        if ($input['customer_type'] === 'business' && $input['company_name'] === '') {
            $errors['company_name'][] = 'Company name is required for business customers.';
        }

        if ($input['customer_type'] === 'individual' && $input['person_name'] === '') {
            $errors['person_name'][] = 'Person name is required for individual customers.';
        }

        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if ($input['mobile'] !== '' && !str_starts_with($input['mobile'], '+')) {
            $errors['mobile'][] = 'Mobile must start with an international dialing code, for example +971.';
        }

        if (!in_array($input['status'], ['active', 'inactive'], true)) {
            $errors['status'][] = 'Please select a valid status.';
        }


        if ($this->existsByField('customers', 'code', $input['code'], $ignoreId)) {
            $errors['code'][] = 'This customer code already exists.';
        }

        return $errors;
    }

    private function findCustomer(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                c.*,
                COALESCE(cur.code, c.currency_code, "AED") AS currency_code_label,
                COALESCE(cur.symbol, "د.إ") AS currency_symbol_label,
                COALESCE(cur.name, "UAE Dirham") AS currency_name_label
            FROM customers c
            LEFT JOIN currencies cur ON cur.id = c.currency_id
            WHERE c.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $customer = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return null;
        }

        $docs = Database::connection()->prepare('SELECT * FROM customer_documents WHERE customer_id = :customer_id ORDER BY id DESC');
        $docs->execute(['customer_id' => $id]);
        $customer['documents'] = $docs->fetchAll(PDO::FETCH_ASSOC);

        return $customer;
    }

    private function listCurrencies(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM currencies';
        if (!$includeInactive) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY is_default DESC, name ASC';

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function defaultCurrency(): ?array
    {
        $statement = Database::connection()->query('SELECT * FROM currencies WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
        $currency = $statement->fetch(PDO::FETCH_ASSOC);
        if ($currency) {
            return $currency;
        }

        $fallback = Database::connection()->query('SELECT * FROM currencies ORDER BY id ASC LIMIT 1');
        $currency = $fallback->fetch(PDO::FETCH_ASSOC);
        return $currency ?: null;
    }

    private function resolveCurrencyMeta(int $currencyId): array
    {
        $aed = $this->aedCurrency();
        if ($aed) {
            return [
                'id' => (int) $aed['id'],
                'code' => (string) $aed['code'],
            ];
        }

        if ($currencyId > 0) {
            $statement = Database::connection()->prepare('SELECT id, code FROM currencies WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $currencyId]);
            $currency = $statement->fetch(PDO::FETCH_ASSOC);
            if ($currency) {
                return [
                    'id' => (int) $currency['id'],
                    'code' => (string) $currency['code'],
                ];
            }
        }

        $default = $this->defaultCurrency();
        if ($default) {
            return [
                'id' => (int) $default['id'],
                'code' => (string) $default['code'],
            ];
        }

        return ['id' => null, 'code' => 'AED'];
    }

    private function aedCurrency(): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE code = :code ORDER BY is_default DESC, id ASC LIMIT 1');
        $statement->execute(['code' => 'AED']);
        $currency = $statement->fetch(PDO::FETCH_ASSOC);

        return $currency ?: null;
    }

    private function existsByField(string $table, string $field, string $value, ?int $ignoreId = null): bool
    {
        $sql = sprintf('SELECT id FROM %s WHERE %s = :value', $table, $field);
        $params = ['value' => $value];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (bool) $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function handleImageUpload(array|null $file, string $folder, string $prefix, string $existingPath = ''): string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The profile image upload could not be completed.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('The profile image must be 5 MB or smaller.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Only JPG, PNG, and WEBP profile images are supported.');
        }

        ensure_directory(public_upload_path($folder));
        $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $relativePath = 'uploads/' . trim($folder, '/') . '/' . $filename;
        $absolutePath = public_path($relativePath);

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $absolutePath)) {
            throw new RuntimeException('The uploaded profile image could not be moved into place.');
        }

        if ($existingPath !== '' && $existingPath !== $relativePath) {
            remove_public_file($existingPath);
        }

        return $relativePath;
    }

    /** @return array<int, string> */
    private function storeDocuments(array|null $files, int $customerId): array
    {
        $storedPaths = [];
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return $storedPaths;
        }

        ensure_directory(public_upload_path('customer-documents'));
        $pdo = Database::connection();
        $insert = $pdo->prepare('
            INSERT INTO customer_documents (
                customer_id, original_name, file_path, file_ext, mime_type, file_size, created_at
            ) VALUES (
                :customer_id, :original_name, :file_path, :file_ext, :mime_type, :file_size, NOW()
            )
        ');

        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $count = count($files['name']);

        for ($index = 0; $index < $count; $index++) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the customer documents could not be uploaded.');
            }

            $originalName = (string) ($files['name'][$index] ?? 'document');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) {
                throw new RuntimeException('Customer documents support PDF, JPG, JPEG, and PNG files only.');
            }

            $size = (int) ($files['size'][$index] ?? 0);
            if ($size > 8 * 1024 * 1024) {
                throw new RuntimeException('Each customer document must be 8 MB or smaller.');
            }

            $filename = 'doc-' . $customerId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
            $relativePath = 'uploads/customer-documents/' . $filename;
            $absolutePath = public_path($relativePath);

            if (!move_uploaded_file((string) ($files['tmp_name'][$index] ?? ''), $absolutePath)) {
                throw new RuntimeException('A customer document could not be moved into place.');
            }

            $storedPaths[] = $relativePath;

            $insert->execute([
                'customer_id' => $customerId,
                'original_name' => $originalName,
                'file_path' => $relativePath,
                'file_ext' => strtoupper($extension),
                'mime_type' => (string) ($files['type'][$index] ?? ''),
                'file_size' => $size,
            ]);
        }

        return $storedPaths;
    }

    private function removeDocuments(int $customerId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$customerId], $ids);
        $statement = Database::connection()->prepare(
            'SELECT id, file_path FROM customer_documents WHERE customer_id = ? AND id IN (' . $placeholders . ')'
        );
        $statement->execute($params);
        $documents = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($documents === []) {
            return;
        }

        $delete = Database::connection()->prepare('DELETE FROM customer_documents WHERE customer_id = :customer_id AND id = :id');
        foreach ($documents as $document) {
            $delete->execute([
                'customer_id' => $customerId,
                'id' => (int) $document['id'],
            ]);
            remove_public_file((string) ($document['file_path'] ?? ''));
        }
    }

    private function cleanupFiles(array $relativePaths): void
    {
        foreach ($relativePaths as $path) {
            remove_public_file((string) $path);
        }
    }

    private function inferCountryFromMobile(string $mobile): array
    {
        $normalized = preg_replace('/[^0-9+]/', '', $mobile) ?: '';
        $map = $this->countryDialCodeMap();
        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($map as $dialCode => $country) {
            if ($normalized !== '' && str_starts_with($normalized, $dialCode)) {
                return [
                    'dial' => $dialCode,
                    'code' => $country['code'],
                    'name' => $country['name'],
                ];
            }
        }

        return [
            'dial' => '',
            'code' => '',
            'name' => '',
        ];
    }

    private function countryDialCodeMap(): array
    {
        return [
            '+971' => ['code' => 'AE', 'name' => 'United Arab Emirates'],
            '+966' => ['code' => 'SA', 'name' => 'Saudi Arabia'],
            '+973' => ['code' => 'BH', 'name' => 'Bahrain'],
            '+974' => ['code' => 'QA', 'name' => 'Qatar'],
            '+968' => ['code' => 'OM', 'name' => 'Oman'],
            '+965' => ['code' => 'KW', 'name' => 'Kuwait'],
            '+964' => ['code' => 'IQ', 'name' => 'Iraq'],
            '+962' => ['code' => 'JO', 'name' => 'Jordan'],
            '+961' => ['code' => 'LB', 'name' => 'Lebanon'],
            '+90' => ['code' => 'TR', 'name' => 'Turkey'],
            '+98' => ['code' => 'IR', 'name' => 'Iran'],
            '+44' => ['code' => 'GB', 'name' => 'United Kingdom'],
            '+1' => ['code' => 'US', 'name' => 'United States / Canada'],
            '+91' => ['code' => 'IN', 'name' => 'India'],
            '+92' => ['code' => 'PK', 'name' => 'Pakistan'],
            '+20' => ['code' => 'EG', 'name' => 'Egypt'],
            '+61' => ['code' => 'AU', 'name' => 'Australia'],
            '+49' => ['code' => 'DE', 'name' => 'Germany'],
            '+33' => ['code' => 'FR', 'name' => 'France'],
            '+39' => ['code' => 'IT', 'name' => 'Italy'],
            '+34' => ['code' => 'ES', 'name' => 'Spain'],
            '+7' => ['code' => 'RU', 'name' => 'Russia / Kazakhstan'],
            '+60' => ['code' => 'MY', 'name' => 'Malaysia'],
            '+65' => ['code' => 'SG', 'name' => 'Singapore'],
        ];
    }
}
