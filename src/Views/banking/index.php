<section class="page-head">
    <div>
        <h1>Banking</h1>
        <small>Create banking accounts, track balances by currency, and keep a clean transaction ledger without overcomplicating the workflow.</small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/banking/accounts/create')) ?>" class="btn secondary">New Account</a>
        <a href="<?= e(base_url('/banking/transactions/create')) ?>" class="btn secondary">New Entry</a>
        <a href="<?= e(base_url('/banking/transfers/create')) ?>" class="btn">Transfer</a>
    </div>
</section>

<section class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <form method="get" action="<?= e(base_url('/banking')) ?>" class="report-filter banking-filter-grid">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="field">
                <label>Account</label>
                <select name="account_id">
                    <option value="0">All accounts</option>
                    <?php foreach (($accounts ?? []) as $account): ?>
                        <option value="<?= e((string) $account['id']) ?>" <?= selected((string) $account['id'], (string) ($accountId ?? 0)) ?>><?= e($account['account_name'] . ' • ' . $account['currency_code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field">
                <label>Search</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Account, reference, counterparty">
            </div>
            <div class="field" style="align-self:end;">
                <button type="submit" class="btn secondary">Apply</button>
            </div>
        </form>
    </div>
</section>

<section class="stats-grid banking-stats-grid">
    <div class="stat-card"><div><small>Total Accounts</small><strong><?= e((string) ($summary['total_accounts'] ?? 0)) ?></strong></div></div>
    <div class="stat-card"><div><small>Active Accounts</small><strong><?= e((string) ($summary['active_accounts'] ?? 0)) ?></strong></div></div>
    <div class="stat-card"><div><small>Foreign Currency Accounts</small><strong><?= e((string) ($summary['foreign_accounts'] ?? 0)) ?></strong></div></div>
    <div class="stat-card"><div><small>Total Balance AED</small><strong><?= e(money($summary['total_balance_aed'] ?? 0)) ?></strong></div></div>
    <div class="stat-card"><div><small>Period Inflow AED</small><strong><?= e(money($summary['period_inflow_aed'] ?? 0)) ?></strong></div></div>
    <div class="stat-card"><div><small>Period Outflow AED</small><strong><?= e(money($summary['period_outflow_aed'] ?? 0)) ?></strong></div></div>
</section>

<section class="card" style="margin-top:12px;">
    <div class="card-b">
        <div class="tabs settings-tabs">
            <a href="<?= e(base_url('/banking?tab=overview&account_id=' . (int) $accountId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'overview' ? 'active' : '') ?>">Overview</a>
            <a href="<?= e(base_url('/banking?tab=accounts&account_id=' . (int) $accountId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'accounts' ? 'active' : '') ?>">Accounts</a>
            <a href="<?= e(base_url('/banking?tab=ledger&account_id=' . (int) $accountId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="tab <?= e($tab === 'ledger' ? 'active' : '') ?>">Ledger</a>
        </div>
    </div>
</section>

<?php if ($tab === 'accounts'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h">
            <h2>Banking Accounts</h2>
            <a href="<?= e(base_url('/banking/accounts/create')) ?>" class="btn secondary btn-sm">Create Account</a>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Bank</th>
                        <th>Type</th>
                        <th>Currency</th>
                        <th>Opening</th>
                        <th>Current</th>
                        <th>AED Eq.</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($accounts ?? []) === []): ?>
                        <tr><td colspan="10">No banking accounts have been created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach (($accounts ?? []) as $account): ?>
                            <tr>
                                <td><?= e($account['code']) ?></td>
                                <td>
                                    <strong><?= e($account['account_name']) ?></strong><br>
                                    <small><?= e($account['account_number'] ?: 'No account number') ?></small>
                                </td>
                                <td><?= e($account['bank_name']) ?></td>
                                <td><?= e(ucfirst((string) $account['account_type'])) ?><br><small><?= e($account['exposure']) ?></small></td>
                                <td><?= e($account['currency_code']) ?></td>
                                <td class="dt"><?= e(money($account['opening_balance_currency'])) ?> <?= e($account['currency_code']) ?></td>
                                <td class="dt"><?= e(money($account['current_balance_currency'])) ?> <?= e($account['currency_code']) ?></td>
                                <td class="dt"><?= e(money($account['current_balance_aed'])) ?> AED</td>
                                <td><span class="badge <?= e(($account['status'] ?? 'active') === 'active' ? 'green' : 'orange') ?>"><?= e(ucfirst((string) ($account['status'] ?? 'active'))) ?></span></td>
                                <td>
                                    <div class="table-actions-inline">
                                        <a class="tab active" href="<?= e(base_url('/banking/accounts/edit?id=' . $account['id'])) ?>">Edit</a>
                                        <a class="tab" href="<?= e(base_url('/banking/transactions/create?account_id=' . $account['id'])) ?>">Entry</a>
                                        <a class="tab" href="<?= e(base_url('/documents/banking/statement?account_id=' . $account['id'] . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" target="_blank" rel="noopener">Statement</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php elseif ($tab === 'ledger'): ?>
    <section class="card" style="margin-top:12px;">
        <div class="card-h">
            <h2>Banking Ledger</h2>
            <div class="row" style="gap:8px;">
                <?php if ((int) ($accountId ?? 0) > 0): ?>
                    <a href="<?= e(base_url('/documents/banking/statement?account_id=' . (int) $accountId . '&from=' . urlencode((string) $from) . '&to=' . urlencode((string) $to) . '&q=' . urlencode((string) $search))) ?>" class="btn btn-sm" target="_blank" rel="noopener">Print / PDF</a>
                <?php endif; ?>
                <a href="<?= e(base_url('/banking/transactions/create')) ?>" class="btn secondary btn-sm">New Entry</a>
            </div>
        </div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Counterparty</th>
                        <th>Amount</th>
                        <th>Rate</th>
                        <th>AED</th>
                        <th>Balance</th>
                        <th>Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (($ledger ?? []) === []): ?>
                        <tr><td colspan="10">No banking entries found for the selected filter.</td></tr>
                    <?php else: ?>
                        <?php foreach (($ledger ?? []) as $entry): ?>
                            <tr>
                                <td><?= e(date_display($entry['txn_date'])) ?></td>
                                <td>
                                    <strong><?= e($entry['account_name']) ?></strong><br>
                                    <small><?= e($entry['account_code']) ?> • <?= e($entry['account_currency_code']) ?></small>
                                </td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $entry['type']))) ?></td>
                                <td>
                                    <?= e($entry['reference_no'] ?: '—') ?>
                                    <?php if (!empty($entry['related_account_name'])): ?>
                                        <br><small><?= e('↔ ' . $entry['related_account_name'] . ' (' . $entry['related_account_code'] . ')') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($entry['counterparty'] ?: '—') ?></td>
                                <td class="dt"><?= e(money($entry['amount_currency'])) ?> <?= e($entry['currency_code']) ?></td>
                                <td class="dt"><?= e(number_format((float) $entry['exchange_rate_to_aed'], 6)) ?></td>
                                <td class="dt"><?= e(money($entry['amount_aed'])) ?></td>
                                <td class="dt"><?= e(money($entry['balance_after_currency'])) ?> <?= e($entry['currency_code']) ?></td>
                                <td><?= e($entry['note'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="inventory-two-col banking-two-col">
        <section class="card">
            <div class="card-h"><h2>Account Balance Snapshot</h2></div>
            <div class="card-b">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Account</th>
                            <th>Currency</th>
                            <th>Current Balance</th>
                            <th>AED Eq.</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (($accounts ?? []) === []): ?>
                            <tr><td colspan="5">No accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach (($accounts ?? []) as $account): ?>
                                <tr>
                                    <td><?= e($account['bank_name'] . ' / ' . $account['account_name']) ?></td>
                                    <td><?= e($account['currency_code']) ?></td>
                                    <td class="dt"><?= e(money($account['current_balance_currency'])) ?> <?= e($account['currency_code']) ?></td>
                                    <td class="dt"><?= e(money($account['current_balance_aed'])) ?> AED</td>
                                    <td><span class="badge <?= e(($account['status'] ?? 'active') === 'active' ? 'green' : 'orange') ?>"><?= e(ucfirst((string) ($account['status'] ?? 'active'))) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-h"><h2>Balance by Currency</h2></div>
            <div class="card-b">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Accounts</th>
                            <th>Balance</th>
                            <th>AED Eq.</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (($currencySummary ?? []) === []): ?>
                            <tr><td colspan="4">No balances available.</td></tr>
                        <?php else: ?>
                            <?php foreach (($currencySummary ?? []) as $row): ?>
                                <tr>
                                    <td><?= e($row['currency_code']) ?></td>
                                    <td class="dt"><?= e((string) ($row['account_count'] ?? 0)) ?></td>
                                    <td class="dt"><?= e(money($row['balance_currency'])) ?> <?= e($row['currency_code']) ?></td>
                                    <td class="dt"><?= e(money($row['balance_aed'])) ?> AED</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>

    <section class="card" style="margin-top:12px;">
        <div class="card-h"><h2>Recent Banking Activity</h2></div>
        <div class="card-b">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>AED</th>
                        <th>Reference</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $recent = array_slice((array) ($ledger ?? []), 0, 10); ?>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="6">No banking activity found yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent as $entry): ?>
                            <tr>
                                <td><?= e(date_display($entry['txn_date'])) ?></td>
                                <td><?= e($entry['account_name']) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $entry['type']))) ?></td>
                                <td class="dt"><?= e(money($entry['amount_currency'])) ?> <?= e($entry['currency_code']) ?></td>
                                <td class="dt"><?= e(money($entry['amount_aed'])) ?></td>
                                <td><?= e($entry['reference_no'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
