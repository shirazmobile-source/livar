<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use PDO;
use Throwable;

final class BankingController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('banking');

        $tab = trim((string) ($_GET['tab'] ?? 'overview'));
        if (!in_array($tab, ['overview', 'accounts', 'ledger'], true)) {
            $tab = 'overview';
        }

        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $accountId = (int) ($_GET['account_id'] ?? 0);
        $search = trim((string) ($_GET['q'] ?? ''));

        $pdo = Database::connection();
        $accounts = $this->fetchAccounts($pdo);

        $ledgerWhere = ['DATE(bt.txn_date) BETWEEN :from AND :to'];
        $ledgerParams = ['from' => $from, 'to' => $to];

        if ($accountId > 0) {
            $ledgerWhere[] = 'bt.bank_account_id = :account_id';
            $ledgerParams['account_id'] = $accountId;
        }

        if ($search !== '') {
            $ledgerWhere[] = '(
                ba.account_name LIKE :search OR
                ba.bank_name LIKE :search OR
                ba.code LIKE :search OR
                COALESCE(bt.reference_no, "") LIKE :search OR
                COALESCE(bt.counterparty, "") LIKE :search OR
                COALESCE(bt.note, "") LIKE :search
            )';
            $ledgerParams['search'] = '%' . $search . '%';
        }

        $ledgerSql = '
            SELECT
                bt.*,
                ba.code AS account_code,
                ba.account_name,
                ba.bank_name,
                ba.account_type,
                ba.currency_code AS account_currency_code,
                rba.account_name AS related_account_name,
                rba.code AS related_account_code
            FROM banking_transactions bt
            INNER JOIN bank_accounts ba ON ba.id = bt.bank_account_id
            LEFT JOIN bank_accounts rba ON rba.id = bt.related_bank_account_id
            WHERE ' . implode(' AND ', $ledgerWhere) . '
            ORDER BY bt.txn_date DESC, bt.id DESC
            LIMIT 300
        ';

        $ledgerStatement = $pdo->prepare($ledgerSql);
        $ledgerStatement->execute($ledgerParams);
        $ledger = $ledgerStatement->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'total_accounts' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM bank_accounts', []),
            'active_accounts' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM bank_accounts WHERE status = "active"', []),
            'foreign_accounts' => (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM bank_accounts WHERE currency_code <> "AED"', []),
            'total_balance_aed' => (float) $this->scalar($pdo, 'SELECT COALESCE(SUM(current_balance_aed), 0) FROM bank_accounts WHERE status = "active"', []),
            'period_inflow_aed' => (float) $this->scalar(
                $pdo,
                'SELECT COALESCE(SUM(amount_aed), 0) FROM banking_transactions WHERE DATE(txn_date) BETWEEN :from AND :to AND type IN ("deposit", "adjustment_in", "transfer_in", "sale_receipt")' . ($accountId > 0 ? ' AND bank_account_id = :account_id' : ''),
                $accountId > 0 ? ['from' => $from, 'to' => $to, 'account_id' => $accountId] : ['from' => $from, 'to' => $to]
            ),
            'period_outflow_aed' => (float) $this->scalar(
                $pdo,
                'SELECT COALESCE(SUM(amount_aed), 0) FROM banking_transactions WHERE DATE(txn_date) BETWEEN :from AND :to AND type IN ("withdrawal", "adjustment_out", "transfer_out", "purchase_payment")' . ($accountId > 0 ? ' AND bank_account_id = :account_id' : ''),
                $accountId > 0 ? ['from' => $from, 'to' => $to, 'account_id' => $accountId] : ['from' => $from, 'to' => $to]
            ),
        ];

        $currencySummary = $pdo->query('
            SELECT currency_code, COUNT(*) AS account_count,
                   COALESCE(SUM(current_balance_currency), 0) AS balance_currency,
                   COALESCE(SUM(current_balance_aed), 0) AS balance_aed
            FROM bank_accounts
            GROUP BY currency_code
            ORDER BY currency_code ASC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $this->render('banking/index', [
            'title' => 'Banking',
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'accountId' => $accountId,
            'search' => $search,
            'accounts' => $accounts,
            'ledger' => $ledger,
            'summary' => $summary,
            'currencySummary' => $currencySummary,
        ]);
    }

    public function createAccount(): void
    {
        $this->requirePermission('banking');

        $this->render('banking/accounts/form', [
            'title' => 'Banking / New Account',
            'action' => '/banking/accounts/store',
            'account' => [
                'code' => 'BNK-' . date('ymdHis'),
                'bank_name' => '',
                'account_name' => '',
                'account_type' => 'bank',
                'account_number' => '',
                'iban' => '',
                'swift_code' => '',
                'currency_id' => $this->defaultCurrencyId(),
                'currency_code' => 'AED',
                'opening_balance_currency' => '0.00',
                'status' => 'active',
                'notes' => '',
                'transaction_count' => 0,
            ],
            'currencies' => $this->currencies(),
            'lockedFinancialFields' => false,
        ]);
    }

    public function storeAccount(): void
    {
        $this->requirePermission('banking');
        $this->verifyCsrf();

        $input = $this->normalizeAccountInput($_POST);
        with_old($input);
        $errors = $this->validateAccount($input);

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/banking/accounts/create');
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('
            INSERT INTO bank_accounts (
                code, bank_name, account_name, account_type, account_number, iban, swift_code,
                currency_id, currency_code, opening_rate_to_aed, opening_balance_currency, opening_balance_aed,
                current_balance_currency, current_balance_aed, status, notes, created_by, created_at, updated_at
            ) VALUES (
                :code, :bank_name, :account_name, :account_type, :account_number, :iban, :swift_code,
                :currency_id, :currency_code, :opening_rate_to_aed, :opening_balance_currency, :opening_balance_aed,
                :current_balance_currency, :current_balance_aed, :status, :notes, :created_by, NOW(), NOW()
            )
        ');

        try {
            $statement->execute($input + ['created_by' => Auth::id()]);
        } catch (Throwable) {
            validation_errors(['code' => ['This banking account code already exists.']]);
            $this->redirect('/banking/accounts/create');
        }

        clear_old();
        $this->redirect('/banking?tab=accounts', 'Banking account created successfully.');
    }

    public function editAccount(): void
    {
        $this->requirePermission('banking');

        $account = $this->findAccount((int) ($_GET['id'] ?? 0));
        if (!$account) {
            $this->redirect('/banking?tab=accounts', null, 'Banking account not found.');
        }

        $lockedFinancialFields = (int) ($account['transaction_count'] ?? 0) > 0;

        $this->render('banking/accounts/form', [
            'title' => 'Banking / Edit Account',
            'action' => '/banking/accounts/update?id=' . (int) $account['id'],
            'account' => $account,
            'currencies' => $this->currencies(),
            'lockedFinancialFields' => $lockedFinancialFields,
        ]);
    }

    public function updateAccount(): void
    {
        $this->requirePermission('banking');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findAccount($id);
        if (!$existing) {
            $this->redirect('/banking?tab=accounts', null, 'Banking account not found.');
        }

        $lockedFinancialFields = (int) ($existing['transaction_count'] ?? 0) > 0;
        $input = $this->normalizeAccountInput($_POST, $existing, $lockedFinancialFields);
        with_old($input);
        $errors = $this->validateAccount($input, $id);

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/banking/accounts/edit?id=' . $id);
        }

        $statement = Database::connection()->prepare('
            UPDATE bank_accounts
            SET code = :code,
                bank_name = :bank_name,
                account_name = :account_name,
                account_type = :account_type,
                account_number = :account_number,
                iban = :iban,
                swift_code = :swift_code,
                currency_id = :currency_id,
                currency_code = :currency_code,
                opening_rate_to_aed = :opening_rate_to_aed,
                opening_balance_currency = :opening_balance_currency,
                opening_balance_aed = :opening_balance_aed,
                current_balance_currency = :current_balance_currency,
                current_balance_aed = :current_balance_aed,
                status = :status,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ');

        try {
            $statement->execute($input + ['id' => $id]);
        } catch (Throwable) {
            validation_errors(['code' => ['This banking account code already exists.']]);
            $this->redirect('/banking/accounts/edit?id=' . $id);
        }

        clear_old();
        $message = $lockedFinancialFields
            ? 'Banking account updated. Currency and opening balance were preserved because the ledger already contains transactions.'
            : 'Banking account updated successfully.';
        $this->redirect('/banking?tab=accounts', $message);
    }

    public function createTransaction(): void
    {
        $this->requirePermission('banking');

        $this->render('banking/transactions/form', [
            'title' => 'Banking / New Entry',
            'action' => '/banking/transactions/store',
            'accounts' => $this->activeAccounts(),
            'entry' => [
                'bank_account_id' => (int) ($_GET['account_id'] ?? 0),
                'txn_date' => date('Y-m-d'),
                'type' => 'deposit',
                'amount_currency' => '',
                'reference_no' => 'BNK-' . date('ymdHis'),
                'counterparty' => '',
                'note' => '',
            ],
        ]);
    }

    public function storeTransaction(): void
    {
        $this->requirePermission('banking');
        $this->verifyCsrf();

        $input = $this->normalizeTransactionInput($_POST);
        with_old($input);
        $errors = $this->validateTransaction($input);

        $account = $this->findAccount((int) $input['bank_account_id']);
        if (!$account || ($account['status'] ?? 'inactive') !== 'active') {
            $errors['bank_account_id'][] = 'Please choose an active banking account.';
        }

        $direction = $this->transactionDirection($input['type']);
        if ($account && $direction < 0 && (float) $account['current_balance_currency'] < (float) $input['amount_currency']) {
            $errors['amount_currency'][] = 'This entry would make the account balance negative.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/banking/transactions/create');
        }

        $rate = $this->currencyRateForAccount($account);
        $amountCurrency = round((float) $input['amount_currency'], 2);
        $amountAed = round($amountCurrency * $rate, 2);
        $newBalanceCurrency = round((float) $account['current_balance_currency'] + ($direction * $amountCurrency), 2);
        $newBalanceAed = round((float) $account['current_balance_aed'] + ($direction * $amountAed), 2);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare('
                INSERT INTO banking_transactions (
                    bank_account_id, related_bank_account_id, txn_date, type, reference_no, counterparty,
                    amount_currency, currency_code, exchange_rate_to_aed, amount_aed,
                    balance_after_currency, balance_after_aed, note, transfer_group, created_by, created_at, updated_at
                ) VALUES (
                    :bank_account_id, NULL, :txn_date, :type, :reference_no, :counterparty,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed,
                    :balance_after_currency, :balance_after_aed, :note, NULL, :created_by, NOW(), NOW()
                )
            ');
            $insert->execute([
                'bank_account_id' => (int) $account['id'],
                'txn_date' => $input['txn_date'],
                'type' => $input['type'],
                'reference_no' => $input['reference_no'],
                'counterparty' => $input['counterparty'],
                'amount_currency' => $amountCurrency,
                'currency_code' => $account['currency_code'],
                'exchange_rate_to_aed' => $rate,
                'amount_aed' => $amountAed,
                'balance_after_currency' => $newBalanceCurrency,
                'balance_after_aed' => $newBalanceAed,
                'note' => $input['note'],
                'created_by' => Auth::id(),
            ]);

            $update = $pdo->prepare('UPDATE bank_accounts SET current_balance_currency = :current_balance_currency, current_balance_aed = :current_balance_aed, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'current_balance_currency' => $newBalanceCurrency,
                'current_balance_aed' => $newBalanceAed,
                'id' => (int) $account['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        clear_old();
        $this->redirect('/banking?tab=ledger&account_id=' . (int) $account['id'], 'Banking entry posted successfully.');
    }

    public function createTransfer(): void
    {
        $this->requirePermission('banking');

        $this->render('banking/transfers/form', [
            'title' => 'Banking / New Transfer',
            'action' => '/banking/transfers/store',
            'accounts' => $this->activeAccounts(),
            'transfer' => [
                'source_account_id' => 0,
                'destination_account_id' => 0,
                'txn_date' => date('Y-m-d'),
                'amount_currency' => '',
                'reference_no' => 'TRF-' . date('ymdHis'),
                'note' => '',
            ],
        ]);
    }

    public function storeTransfer(): void
    {
        $this->requirePermission('banking');
        $this->verifyCsrf();

        $input = [
            'source_account_id' => (int) ($_POST['source_account_id'] ?? 0),
            'destination_account_id' => (int) ($_POST['destination_account_id'] ?? 0),
            'txn_date' => trim((string) ($_POST['txn_date'] ?? date('Y-m-d'))),
            'amount_currency' => trim((string) ($_POST['amount_currency'] ?? '0')),
            'reference_no' => trim((string) ($_POST['reference_no'] ?? 'TRF-' . date('ymdHis'))),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];

        with_old($input);
        $errors = Validator::make($input, [
            'txn_date' => ['required', 'max:20'],
            'reference_no' => ['max:80'],
            'note' => ['max:500'],
        ]);

        $amount = round((float) $input['amount_currency'], 2);
        if ($input['source_account_id'] <= 0) {
            $errors['source_account_id'][] = 'Choose the source account.';
        }
        if ($input['destination_account_id'] <= 0) {
            $errors['destination_account_id'][] = 'Choose the destination account.';
        }
        if ($input['source_account_id'] > 0 && $input['source_account_id'] === $input['destination_account_id']) {
            $errors['destination_account_id'][] = 'Source and destination accounts must be different.';
        }
        if ($amount <= 0) {
            $errors['amount_currency'][] = 'Enter a valid transfer amount.';
        }

        $source = $this->findAccount($input['source_account_id']);
        $destination = $this->findAccount($input['destination_account_id']);

        if (!$source || ($source['status'] ?? 'inactive') !== 'active') {
            $errors['source_account_id'][] = 'Choose an active source account.';
        }
        if (!$destination || ($destination['status'] ?? 'inactive') !== 'active') {
            $errors['destination_account_id'][] = 'Choose an active destination account.';
        }
        if ($source && $amount > (float) $source['current_balance_currency']) {
            $errors['amount_currency'][] = 'This transfer would make the source account balance negative.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/banking/transfers/create');
        }

        $sourceRate = $this->currencyRateForAccount($source);
        $destinationRate = $this->currencyRateForAccount($destination);
        $amountAed = round($amount * $sourceRate, 2);
        $destinationAmount = round($amountAed / max($destinationRate, 0.00000001), 2);

        $newSourceBalanceCurrency = round((float) $source['current_balance_currency'] - $amount, 2);
        $newSourceBalanceAed = round((float) $source['current_balance_aed'] - $amountAed, 2);
        $newDestinationBalanceCurrency = round((float) $destination['current_balance_currency'] + $destinationAmount, 2);
        $newDestinationBalanceAed = round((float) $destination['current_balance_aed'] + $amountAed, 2);

        $group = 'TRF-' . date('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 8);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare('
                INSERT INTO banking_transactions (
                    bank_account_id, related_bank_account_id, txn_date, type, reference_no, counterparty,
                    amount_currency, currency_code, exchange_rate_to_aed, amount_aed,
                    balance_after_currency, balance_after_aed, note, transfer_group, created_by, created_at, updated_at
                ) VALUES (
                    :bank_account_id, :related_bank_account_id, :txn_date, :type, :reference_no, :counterparty,
                    :amount_currency, :currency_code, :exchange_rate_to_aed, :amount_aed,
                    :balance_after_currency, :balance_after_aed, :note, :transfer_group, :created_by, NOW(), NOW()
                )
            ');

            $insert->execute([
                'bank_account_id' => (int) $source['id'],
                'related_bank_account_id' => (int) $destination['id'],
                'txn_date' => $input['txn_date'],
                'type' => 'transfer_out',
                'reference_no' => $input['reference_no'],
                'counterparty' => $destination['account_name'],
                'amount_currency' => $amount,
                'currency_code' => $source['currency_code'],
                'exchange_rate_to_aed' => $sourceRate,
                'amount_aed' => $amountAed,
                'balance_after_currency' => $newSourceBalanceCurrency,
                'balance_after_aed' => $newSourceBalanceAed,
                'note' => $input['note'],
                'transfer_group' => $group,
                'created_by' => Auth::id(),
            ]);

            $insert->execute([
                'bank_account_id' => (int) $destination['id'],
                'related_bank_account_id' => (int) $source['id'],
                'txn_date' => $input['txn_date'],
                'type' => 'transfer_in',
                'reference_no' => $input['reference_no'],
                'counterparty' => $source['account_name'],
                'amount_currency' => $destinationAmount,
                'currency_code' => $destination['currency_code'],
                'exchange_rate_to_aed' => $destinationRate,
                'amount_aed' => $amountAed,
                'balance_after_currency' => $newDestinationBalanceCurrency,
                'balance_after_aed' => $newDestinationBalanceAed,
                'note' => $input['note'],
                'transfer_group' => $group,
                'created_by' => Auth::id(),
            ]);

            $update = $pdo->prepare('UPDATE bank_accounts SET current_balance_currency = :current_balance_currency, current_balance_aed = :current_balance_aed, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'current_balance_currency' => $newSourceBalanceCurrency,
                'current_balance_aed' => $newSourceBalanceAed,
                'id' => (int) $source['id'],
            ]);
            $update->execute([
                'current_balance_currency' => $newDestinationBalanceCurrency,
                'current_balance_aed' => $newDestinationBalanceAed,
                'id' => (int) $destination['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        clear_old();
        $this->redirect('/banking?tab=ledger', 'Transfer posted successfully. Destination amount: ' . money($destinationAmount) . ' ' . $destination['currency_code'] . '.');
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAccounts(PDO $pdo): array
    {
        return $pdo->query('
            SELECT
                ba.*,
                COALESCE(c.name, ba.currency_code) AS currency_name,
                COALESCE(c.symbol, ba.currency_code) AS currency_symbol,
                COALESCE(c.rate_to_aed, ba.opening_rate_to_aed, 1) AS current_rate_to_aed,
                CASE WHEN ba.currency_code = "AED" THEN "Local" ELSE "Foreign" END AS exposure,
                (SELECT COUNT(*) FROM banking_transactions bt WHERE bt.bank_account_id = ba.id) AS transaction_count
            FROM bank_accounts ba
            LEFT JOIN currencies c ON c.id = ba.currency_id
            ORDER BY ba.status = "active" DESC, ba.bank_name ASC, ba.account_name ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function activeAccounts(): array
    {
        $statement = Database::connection()->query('
            SELECT ba.*, COALESCE(c.rate_to_aed, ba.opening_rate_to_aed, 1) AS current_rate_to_aed
            FROM bank_accounts ba
            LEFT JOIN currencies c ON c.id = ba.currency_id
            WHERE ba.status = "active"
            ORDER BY ba.bank_name ASC, ba.account_name ASC
        ');

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function currencies(): array
    {
        $currencies = Database::connection()->query('
            SELECT * FROM currencies WHERE status = "active" ORDER BY is_default DESC, code ASC
        ')->fetchAll(PDO::FETCH_ASSOC);

        if ($currencies === []) {
            $currencies[] = [
                'id' => 0,
                'name' => 'UAE Dirham',
                'code' => 'AED',
                'symbol' => 'AED',
                'rate_to_aed' => 1,
            ];
        }

        return $currencies;
    }

    private function defaultCurrencyId(): int
    {
        $statement = Database::connection()->query('SELECT id FROM currencies WHERE code = "AED" ORDER BY is_default DESC, id ASC LIMIT 1');
        $id = $statement->fetchColumn();
        return $id ? (int) $id : 0;
    }

    private function findAccount(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('
            SELECT
                ba.*,
                COALESCE(c.name, ba.currency_code) AS currency_name,
                COALESCE(c.symbol, ba.currency_code) AS currency_symbol,
                COALESCE(c.rate_to_aed, ba.opening_rate_to_aed, 1) AS current_rate_to_aed,
                (SELECT COUNT(*) FROM banking_transactions bt WHERE bt.bank_account_id = ba.id) AS transaction_count
            FROM bank_accounts ba
            LEFT JOIN currencies c ON c.id = ba.currency_id
            WHERE ba.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    /** @return array<string, mixed> */
    private function normalizeAccountInput(array $source, array $existing = [], bool $lockedFinancialFields = false): array
    {
        $currencyId = (int) ($source['currency_id'] ?? ($existing['currency_id'] ?? $this->defaultCurrencyId()));
        $currency = $this->currencyById($currencyId);

        if ($lockedFinancialFields) {
            $currencyId = (int) ($existing['currency_id'] ?? $currencyId);
            $currency = $this->currencyById($currencyId) ?? [
                'id' => $existing['currency_id'] ?? 0,
                'code' => $existing['currency_code'] ?? 'AED',
                'rate_to_aed' => $existing['opening_rate_to_aed'] ?? 1,
            ];
        }

        $currencyCode = (string) ($currency['code'] ?? ($existing['currency_code'] ?? 'AED'));
        $rate = (float) ($currency['rate_to_aed'] ?? ($existing['opening_rate_to_aed'] ?? 1));

        $openingBalanceCurrency = $lockedFinancialFields
            ? round((float) ($existing['opening_balance_currency'] ?? 0), 2)
            : round((float) ($source['opening_balance_currency'] ?? 0), 2);
        $openingBalanceAed = round($openingBalanceCurrency * $rate, 2);

        return [
            'code' => trim((string) ($source['code'] ?? ($existing['code'] ?? ''))),
            'bank_name' => trim((string) ($source['bank_name'] ?? ($existing['bank_name'] ?? ''))),
            'account_name' => trim((string) ($source['account_name'] ?? ($existing['account_name'] ?? ''))),
            'account_type' => trim((string) ($source['account_type'] ?? ($existing['account_type'] ?? 'bank'))),
            'account_number' => trim((string) ($source['account_number'] ?? ($existing['account_number'] ?? ''))),
            'iban' => trim((string) ($source['iban'] ?? ($existing['iban'] ?? ''))),
            'swift_code' => trim((string) ($source['swift_code'] ?? ($existing['swift_code'] ?? ''))),
            'currency_id' => (int) ($currency['id'] ?? $currencyId) ?: null,
            'currency_code' => $currencyCode,
            'opening_rate_to_aed' => $lockedFinancialFields ? (float) ($existing['opening_rate_to_aed'] ?? $rate) : $rate,
            'opening_balance_currency' => $openingBalanceCurrency,
            'opening_balance_aed' => $lockedFinancialFields ? round((float) ($existing['opening_balance_aed'] ?? $openingBalanceAed), 2) : $openingBalanceAed,
            'current_balance_currency' => $lockedFinancialFields ? round((float) ($existing['current_balance_currency'] ?? $openingBalanceCurrency), 2) : $openingBalanceCurrency,
            'current_balance_aed' => $lockedFinancialFields ? round((float) ($existing['current_balance_aed'] ?? $openingBalanceAed), 2) : $openingBalanceAed,
            'status' => trim((string) ($source['status'] ?? ($existing['status'] ?? 'active'))),
            'notes' => trim((string) ($source['notes'] ?? ($existing['notes'] ?? ''))),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function validateAccount(array $input, int $ignoreId = 0): array
    {
        $errors = Validator::make($input, [
            'code' => ['required', 'max:60'],
            'bank_name' => ['required', 'max:160'],
            'account_name' => ['required', 'max:160'],
            'account_type' => ['required', 'max:20'],
            'account_number' => ['max:80'],
            'iban' => ['max:80'],
            'swift_code' => ['max:40'],
            'currency_code' => ['required', 'max:10'],
            'notes' => ['max:500'],
            'status' => ['required', 'max:20'],
        ]);

        if (!in_array($input['account_type'], ['bank', 'cash', 'wallet'], true)) {
            $errors['account_type'][] = 'Choose a valid account type.';
        }

        if (!in_array($input['status'], ['active', 'inactive'], true)) {
            $errors['status'][] = 'Choose a valid account status.';
        }

        $statement = Database::connection()->prepare('SELECT id FROM bank_accounts WHERE code = :code AND id <> :id LIMIT 1');
        $statement->execute(['code' => $input['code'], 'id' => $ignoreId]);
        if ($statement->fetchColumn()) {
            $errors['code'][] = 'This banking account code is already in use.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function normalizeTransactionInput(array $source): array
    {
        return [
            'bank_account_id' => (int) ($source['bank_account_id'] ?? 0),
            'txn_date' => trim((string) ($source['txn_date'] ?? date('Y-m-d'))),
            'type' => trim((string) ($source['type'] ?? 'deposit')),
            'amount_currency' => trim((string) ($source['amount_currency'] ?? '0')),
            'reference_no' => trim((string) ($source['reference_no'] ?? '')),
            'counterparty' => trim((string) ($source['counterparty'] ?? '')),
            'note' => trim((string) ($source['note'] ?? '')),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function validateTransaction(array $input): array
    {
        $errors = Validator::make($input, [
            'txn_date' => ['required', 'max:20'],
            'type' => ['required', 'max:30'],
            'reference_no' => ['max:80'],
            'counterparty' => ['max:160'],
            'note' => ['max:500'],
        ]);

        if ($input['bank_account_id'] <= 0) {
            $errors['bank_account_id'][] = 'Please choose a banking account.';
        }

        if (!in_array($input['type'], ['deposit', 'withdrawal', 'adjustment_in', 'adjustment_out'], true)) {
            $errors['type'][] = 'Please choose a valid entry type.';
        }

        if ((float) $input['amount_currency'] <= 0) {
            $errors['amount_currency'][] = 'Enter a valid amount greater than zero.';
        }

        return $errors;
    }

    private function currencyById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT id, code, rate_to_aed FROM currencies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $currency = $statement->fetch(PDO::FETCH_ASSOC);

        return $currency ?: null;
    }

    private function transactionDirection(string $type): int
    {
        return in_array($type, ['deposit', 'adjustment_in', 'transfer_in', 'sale_receipt'], true) ? 1 : -1;
    }

    private function currencyRateForAccount(array $account): float
    {
        $rate = (float) ($account['current_rate_to_aed'] ?? $account['opening_rate_to_aed'] ?? 1);
        return $rate > 0 ? $rate : 1.0;
    }

    private function scalar(PDO $pdo, string $sql, array $params): string|int|float|null
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }
}
