<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use PDO;
use PDOException;

final class UserController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('settings.users');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();

        if ($search !== '') {
            $statement = $pdo->prepare('
                SELECT id, name, email, role, status, is_primary, permissions, last_login_at, last_login_ip, created_at
                FROM users
                WHERE name LIKE :search OR email LIKE :search OR role LIKE :search
                ORDER BY is_primary DESC, id ASC
            ');
            $statement->execute(['search' => '%' . $search . '%']);
            $users = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $users = $pdo->query('
                SELECT id, name, email, role, status, is_primary, permissions, last_login_at, last_login_ip, created_at
                FROM users
                ORDER BY is_primary DESC, id ASC
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('settings/users/index', [
            'title' => 'Setting / Users',
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('settings.users');

        $this->render('settings/users/form', [
            'title' => 'Setting / Users / New User',
            'action' => '/settings/users/store',
            'user' => $this->defaultUser(),
            'isPrimaryUser' => false,
            'isEditingSelf' => false,
            'roles' => $this->roles(),
            'permissionGroups' => permission_groups(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('settings.users');
        $this->verifyCsrf();

        $input = $this->normalizeInput($_POST);
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'max:50'],
            'status' => ['required', 'max:20'],
        ]);

        if (!array_key_exists($input['role'], $this->roles())) {
            $errors['role'][] = 'Please choose a valid user role.';
        }

        if (!in_array($input['status'], ['active', 'inactive'], true)) {
            $errors['status'][] = 'Please choose a valid user status.';
        }

        if (count($input['permissions']) === 0) {
            $errors['permissions'][] = 'Select at least one access permission for the account.';
        }

        if (strlen($password) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/settings/users/create');
        }

        $statement = Database::connection()->prepare('
            INSERT INTO users (name, email, password, role, status, permissions, is_primary, created_at, updated_at)
            VALUES (:name, :email, :password, :role, :status, :permissions, 0, NOW(), NOW())
        ');

        try {
            $statement->execute([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $input['role'],
                'status' => $input['status'],
                'permissions' => encode_permissions($input['permissions']),
            ]);
        } catch (PDOException $exception) {
            validation_errors(['email' => ['This email address is already in use.']]);
            $this->redirect('/settings/users/create');
        }

        clear_old();
        $this->redirect('/settings/users', 'User account created successfully.');
    }

    public function edit(): void
    {
        $this->requirePermission('settings.users');

        $user = $this->findUser((int) ($_GET['id'] ?? 0));
        if (!$user) {
            $this->redirect('/settings/users', null, 'User account not found.');
        }

        $this->render('settings/users/form', [
            'title' => 'Setting / Users / Edit User',
            'action' => '/settings/users/update?id=' . (int) $user['id'],
            'user' => $user,
            'isPrimaryUser' => (int) ($user['is_primary'] ?? 0) === 1,
            'isEditingSelf' => (int) $user['id'] === Auth::id(),
            'roles' => $this->roles(),
            'permissionGroups' => permission_groups(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('settings.users');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findUser($id);
        if (!$existing) {
            $this->redirect('/settings/users', null, 'User account not found.');
        }

        $isPrimary = (int) ($existing['is_primary'] ?? 0) === 1;
        $isSelf = $id === Auth::id();
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($isPrimary) {
            if ($password === '') {
                validation_errors(['password' => ['Enter a new password for the primary account.']]);
                $this->redirect('/settings/users/edit?id=' . $id);
            }

            if (strlen($password) < 8) {
                validation_errors(['password' => ['Password must be at least 8 characters.']]);
                $this->redirect('/settings/users/edit?id=' . $id);
            }

            if ($password !== $passwordConfirmation) {
                validation_errors(['password_confirmation' => ['Password confirmation does not match.']]);
                $this->redirect('/settings/users/edit?id=' . $id);
            }

            $statement = Database::connection()->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
            $statement->execute([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $id,
            ]);

            if ($isSelf) {
                Auth::refresh();
            }

            $target = Auth::can('settings.users') ? '/settings/users' : Auth::homePath();
            $this->redirect($target, 'Primary account password updated successfully.');
        }

        $input = $this->normalizeInput($_POST, $existing);
        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'max:50'],
            'status' => ['required', 'max:20'],
        ]);

        if (!array_key_exists($input['role'], $this->roles())) {
            $errors['role'][] = 'Please choose a valid user role.';
        }

        if (!in_array($input['status'], ['active', 'inactive'], true)) {
            $errors['status'][] = 'Please choose a valid user status.';
        }

        if (count($input['permissions']) === 0) {
            $errors['permissions'][] = 'Select at least one access permission for the account.';
        }

        if ($isSelf && $input['status'] !== 'active') {
            $errors['status'][] = 'You cannot deactivate the account you are currently using.';
        }

        if ($password !== '' && strlen($password) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/settings/users/edit?id=' . $id);
        }

        $fields = [
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'status' => $input['status'],
            'permissions' => encode_permissions($input['permissions']),
            'id' => $id,
        ];

        $sql = 'UPDATE users SET name = :name, email = :email, role = :role, status = :status, permissions = :permissions, updated_at = NOW()';
        if ($password !== '') {
            $sql .= ', password = :password';
            $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';

        try {
            $statement = Database::connection()->prepare($sql);
            $statement->execute($fields);
        } catch (PDOException $exception) {
            validation_errors(['email' => ['This email address is already in use.']]);
            $this->redirect('/settings/users/edit?id=' . $id);
        }

        if ($isSelf) {
            Auth::refresh();
        }

        clear_old();
        $target = Auth::can('settings.users') ? '/settings/users' : Auth::homePath();
        $this->redirect($target, 'User account updated successfully.');
    }

    public function destroy(): void
    {
        $this->requirePermission('settings.users');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $user = $this->findUser($id);
        if (!$user) {
            $this->redirect('/settings/users', null, 'User account not found.');
        }

        if ((int) ($user['is_primary'] ?? 0) === 1) {
            $this->redirect('/settings/users', null, 'The primary account cannot be deleted. Only its password can be changed.');
        }

        if ($id === Auth::id()) {
            $this->redirect('/settings/users', null, 'You cannot delete the account you are currently using.');
        }

        $pdo = Database::connection();
        $purchaseCount = $this->countReferences($pdo, 'SELECT COUNT(*) FROM purchases WHERE created_by = :id', $id);
        $saleCount = $this->countReferences($pdo, 'SELECT COUNT(*) FROM sales WHERE created_by = :id', $id);
        if ($purchaseCount > 0 || $saleCount > 0) {
            $this->redirect('/settings/users', null, 'This user is linked to existing purchase or sales records. Change the account to inactive instead of deleting it.');
        }

        $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);

        $this->redirect('/settings/users', 'User account deleted successfully.');
    }

    private function findUser(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['permissions'] = normalize_permissions($user['permissions'] ?? '[]');
        return $user;
    }

    private function normalizeInput(array $source, ?array $fallback = null): array
    {
        return [
            'name' => trim((string) ($source['name'] ?? ($fallback['name'] ?? ''))),
            'email' => trim((string) ($source['email'] ?? ($fallback['email'] ?? ''))),
            'role' => trim((string) ($source['role'] ?? ($fallback['role'] ?? 'staff'))),
            'status' => trim((string) ($source['status'] ?? ($fallback['status'] ?? 'active'))),
            'permissions' => normalize_permissions($source['permissions'] ?? ($fallback['permissions'] ?? [])),
        ];
    }

    private function defaultUser(): array
    {
        return [
            'name' => '',
            'email' => '',
            'role' => 'staff',
            'status' => 'active',
            'permissions' => ['dashboard'],
        ];
    }

    private function roles(): array
    {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
        ];
    }

    private function countReferences(PDO $pdo, string $sql, int $id): int
    {
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return (int) ($statement->fetchColumn() ?: 0);
    }
}
