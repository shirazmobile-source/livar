<?php use App\Core\Auth; ?>
<section class="page-head">
    <div>
        <h1>Setting / Users</h1>
        <small>Create user accounts, change access levels, delete standard accounts, and protect the primary account.</small>
    </div>
    <div class="page-head-actions">
        <form method="get" action="<?= e(base_url('/settings/users')) ?>" class="inline-search">
            <input type="search" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Search users by name, email, or role">
            <button class="btn secondary" type="submit">Search</button>
        </form>
        <a href="<?= e(base_url('/settings/users/create')) ?>" class="btn">New User</a>
    </div>
</section>

<?php require __DIR__ . '/../partials/nav.php'; ?>

<section class="card">
    <div class="card-h settings-actions">
        <div>
            <h2>User Accounts</h2>
            <small>The primary account cannot be edited except for password changes.</small>
        </div>
        <span class="badge blue"><?= e((string) count($users ?? [])) ?> account(s)</span>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Access</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="width: 180px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($users ?? []) === []): ?>
                    <tr><td colspan="6">No user accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $account): ?>
                        <?php $isPrimary = (int) ($account['is_primary'] ?? 0) === 1; ?>
                        <tr>
                            <td>
                                <strong><?= e($account['name'] ?? '') ?></strong>
                                <?php if ($isPrimary): ?><span class="badge orange" style="margin-left:6px;">Primary</span><?php endif; ?>
                                <?php if ((int) ($account['id'] ?? 0) === Auth::id()): ?><span class="badge blue" style="margin-left:6px;">You</span><?php endif; ?>
                                <br>
                                <small><?= e($account['email'] ?? '') ?></small>
                            </td>
                            <td><?= e(ucfirst((string) ($account['role'] ?? 'staff'))) ?></td>
                            <td>
                                <?php if ($isPrimary): ?>
                                    <span class="badge green">Full access</span>
                                <?php else: ?>
                                    <small><?= e(function_exists('permission_summary') ? permission_summary($account['permissions'] ?? []) : 'Limited access') ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= e(($account['status'] ?? 'inactive') === 'active' ? 'green' : 'red') ?>"><?= e(ucfirst((string) ($account['status'] ?? 'inactive'))) ?></span></td>
                            <td>
                                <small><?= e(date_display($account['last_login_at'] ?? null, 'Y-m-d H:i')) ?></small>
                                <?php if (!empty($account['last_login_ip'])): ?><br><small><?= e((string) $account['last_login_ip']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions-tight">
                                    <a href="<?= e(base_url('/settings/users/edit?id=' . (int) ($account['id'] ?? 0))) ?>" class="btn secondary btn-sm"><?= $isPrimary ? 'Change Password' : 'Modify' ?></a>
                                    <?php if (!$isPrimary && (int) ($account['id'] ?? 0) !== Auth::id()): ?>
                                        <form method="post" action="<?= e(base_url('/settings/users/delete?id=' . (int) ($account['id'] ?? 0))) ?>" onsubmit="return confirm('Delete this user account now? This action cannot be undone.')">
                                            <?= App\Core\Csrf::field() ?>
                                            <button type="submit" class="btn btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
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
