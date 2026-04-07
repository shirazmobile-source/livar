<?php
$errors = validation_errors();
$editing = isset($user['id']);
$primaryOnly = (bool) ($isPrimaryUser ?? false);
?>
<section class="page-head">
    <div>
        <h1><?= $editing ? 'Setting / Users / Modify User' : 'Setting / Users / New User' ?></h1>
        <small><?= $primaryOnly ? 'The primary account is protected. Only the password can be changed.' : 'Create and manage user access for login and module permissions.' ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/settings/users')) ?>" class="btn secondary">Back to Users</a>
    </div>
</section>

<?php require __DIR__ . '/../partials/nav.php'; ?>

<section class="card">
    <div class="card-h">
        <div>
            <h2><?= $editing ? ($primaryOnly ? 'Primary Account Password' : 'User Account Details') : 'Create User Account' ?></h2>
            <small><?= $primaryOnly ? 'Identity, role, status, and permissions are locked for the primary account.' : 'All standard user fields and access permissions can be configured here.' ?></small>
        </div>
    </div>
    <div class="card-b">
        <?php require __DIR__ . '/../../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url($action)) ?>" class="stack-gap">
            <?= App\Core\Csrf::field() ?>

            <?php if ($primaryOnly): ?>
                <div class="notice">
                    <strong>Protected primary account.</strong> Only the account password can be changed from this page.
                </div>
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label>Account Name</label>
                        <input type="text" value="<?= e((string) ($user['name'] ?? '')) ?>" disabled>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" value="<?= e((string) ($user['email'] ?? '')) ?>" disabled>
                    </div>
                </div>
            <?php else: ?>
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= e((string) old('name', (string) ($user['name'] ?? ''))) ?>" required>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= e((string) old('email', (string) ($user['email'] ?? ''))) ?>" required>
                    </div>
                </div>

                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label>Role</label>
                        <select name="role" required>
                            <?php foreach (($roles ?? []) as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= e(selected($value, old('role', (string) ($user['role'] ?? 'staff')))) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="active" <?= e(selected('active', old('status', (string) ($user['status'] ?? 'active')))) ?>>Active</option>
                            <option value="inactive" <?= e(selected('inactive', old('status', (string) ($user['status'] ?? 'active')))) ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <section class="permission-box">
                    <div class="permission-box-head">
                        <div>
                            <h3>Menu & Page Access</h3>
                            <small>Choose which sections this account can open after sign-in.</small>
                        </div>
                    </div>
                    <div class="permission-groups">
                        <?php foreach (($permissionGroups ?? []) as $groupLabel => $permissionKeys): ?>
                            <div class="permission-group-card">
                                <h4><?= e($groupLabel) ?></h4>
                                <div class="permission-list">
                                    <?php foreach ($permissionKeys as $permissionKey): ?>
                                        <?php $meta = permission_catalog()[$permissionKey] ?? ['label' => $permissionKey, 'description' => '']; ?>
                                        <label class="permission-item">
                                            <input type="checkbox" name="permissions[]" value="<?= e($permissionKey) ?>" <?= e(checked($permissionKey, in_array($permissionKey, (array) old('permissions', $user['permissions'] ?? []), true) ? $permissionKey : '')) ?>>
                                            <span>
                                                <strong><?= e($meta['label']) ?></strong>
                                                <small><?= e($meta['description']) ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="field-grid field-grid-2">
                <div class="field">
                    <label><?= $editing ? 'New Password (leave blank to keep current password)' : 'Password' ?></label>
                    <input type="password" name="password" <?= $editing && !$primaryOnly ? '' : 'required' ?> minlength="8">
                </div>
                <div class="field">
                    <label>Password Confirmation</label>
                    <input type="password" name="password_confirmation" <?= $editing && !$primaryOnly ? '' : 'required' ?> minlength="8">
                </div>
            </div>

            <?php if (($isEditingSelf ?? false) && !$primaryOnly): ?>
                <div class="notice">You are editing the account currently signed in. If you remove access to Users, you will be redirected to the first section still allowed for your account.</div>
            <?php endif; ?>

            <div class="row form-actions">
                <button type="submit" class="btn"><?= $editing ? ($primaryOnly ? 'Change Password' : 'Save Changes') : 'Create User' ?></button>
                <a href="<?= e(base_url('/settings/users')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
