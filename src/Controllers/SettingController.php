<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BackupManager;
use App\Core\Controller;
use App\Core\MediaManager;
use App\Core\ThemeManager;
use App\Core\UpdateManager;

final class SettingController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('settings.overview');

        $modules = [];

        if (Auth::can('settings.backup')) {
            $modules[] = [
                'title' => 'Backup',
                'description' => 'Create a full backup, download archives, and restore the application when required.',
                'url' => '/settings/backup',
                'status' => 'Active',
            ];
        }

        if (Auth::can('settings.update')) {
            $modules[] = [
                'title' => 'Update',
                'description' => 'Upload a ZIP package to apply core updates directly from the admin panel.',
                'url' => '/settings/update',
                'status' => 'Active',
            ];
        }

        if (Auth::can('reports')) {
            $modules[] = [
                'title' => 'Reports',
                'description' => 'Open operational reports for sales, purchases, stock, customers, suppliers, and profitability.',
                'url' => '/settings/reports',
                'status' => 'Active',
            ];
        }

        if (Auth::can('settings.users')) {
            $modules[] = [
                'title' => 'Users',
                'description' => 'Create user accounts, modify access levels, delete users, and protect the primary account.',
                'url' => '/settings/users',
                'status' => 'Active',
            ];
        }


        if (Auth::can('settings.media')) {
            $modules[] = [
                'title' => 'Media',
                'description' => 'Review all uploaded media files, see where each file is linked, edit metadata, and detach or delete files safely.',
                'url' => '/settings/media',
                'status' => 'Active',
            ];
        }

        if (Auth::can('settings.forms')) {
            $modules[] = [
                'title' => 'Forms',
                'description' => 'Manage a unified print and PDF design system for sales invoices, purchase invoices, statements, and warehouse slips.',
                'url' => '/settings/forms',
                'status' => 'Active',
            ];
        }

        if (Auth::can('settings.theme')) {
            $modules[] = [
                'title' => 'Theme',
                'description' => 'Control the default dark or light mode, tune theme tokens, apply custom CSS, and reset the interface to the original design.',
                'url' => '/settings/theme',
                'status' => 'Active',
            ];
        }

        $this->render('settings/index', [
            'title' => 'Setting',
            'modules' => $modules,
        ]);
    }

    public function backup(): void
    {
        $this->requirePermission('settings.backup');

        $manager = new BackupManager();
        $this->render('settings/backup', [
            'title' => 'Backup',
            'backups' => $manager->listBackups(),
            'zipAvailable' => class_exists(\ZipArchive::class),
        ]);
    }

    public function createBackup(): void
    {
        $this->requirePermission('settings.backup');
        $this->verifyCsrf();

        $label = trim((string) ($_POST['label'] ?? 'manual-backup')) ?: 'manual-backup';
        try {
            $manager = new BackupManager();
            $backup = $manager->createFullBackup($label, [
                'created_by' => Auth::user()['email'] ?? 'admin',
                'mode' => 'manual',
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/backup', null, $exception->getMessage());
        }

        $this->redirect('/settings/backup', 'Backup created successfully: ' . ($backup['file_name'] ?? $backup['id'] ?? 'archive') . '.');
    }

    public function downloadBackup(): void
    {
        $this->requirePermission('settings.backup');

        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->redirect('/settings/backup', null, 'Please select a backup archive first.');
        }

        try {
            $path = (new BackupManager())->downloadPath($id);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/backup', null, $exception->getMessage());
        }

        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }

    public function restoreBackup(): void
    {
        $this->requirePermission('settings.backup');
        $this->verifyCsrf();

        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->redirect('/settings/backup', null, 'Please select a backup archive to restore.');
        }

        try {
            $result = (new BackupManager())->restoreById($id, [
                'created_by' => Auth::user()['email'] ?? 'admin',
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/backup', null, $exception->getMessage());
        }

        $message = 'Backup restored successfully.';
        if (!empty($result['safety_backup_id'])) {
            $message .= ' Safety backup created: ' . $result['safety_backup_id'] . '.';
        }

        $this->redirect('/settings/backup', $message);
    }

    public function restoreBackupUpload(): void
    {
        $this->requirePermission('settings.backup');
        $this->verifyCsrf();

        $file = $_FILES['backup_zip'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->redirect('/settings/backup', null, 'Please upload a valid backup ZIP file.');
        }

        try {
            $result = (new BackupManager())->restoreArchive((string) $file['tmp_name'], [
                'created_by' => Auth::user()['email'] ?? 'admin',
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/backup', null, $exception->getMessage());
        }

        $message = 'Uploaded backup restored successfully.';
        if (!empty($result['safety_backup_id'])) {
            $message .= ' Safety backup created: ' . $result['safety_backup_id'] . '.';
        }

        $this->redirect('/settings/backup', $message);
    }

    public function update(): void
    {
        $this->requirePermission('settings.update');

        $manager = new UpdateManager();
        $this->render('settings/update', [
            'title' => 'Update',
            'history' => $manager->listHistory(),
            'zipAvailable' => class_exists(\ZipArchive::class),
        ]);
    }

    public function uploadUpdate(): void
    {
        $this->requirePermission('settings.update');
        $this->verifyCsrf();

        $file = $_FILES['update_zip'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->redirect('/settings/update', null, 'Please upload a valid ZIP package first.');
        }

        try {
            $update = (new UpdateManager())->applyPackage(
                (string) $file['tmp_name'],
                (string) ($file['name'] ?? 'update.zip'),
                ['created_by' => Auth::user()['email'] ?? 'admin']
            );
        } catch (\Throwable $exception) {
            $this->redirect('/settings/update', null, $exception->getMessage());
        }

        $message = 'Core update applied successfully from ' . ($update['package_name'] ?? 'package') . '.';
        if (!empty($update['safety_backup_id'])) {
            $message .= ' Safety backup created: ' . $update['safety_backup_id'] . '.';
        }

        $this->redirect('/settings/update', $message);
    }


    public function theme(): void
    {
        $this->requirePermission('settings.theme');

        $this->render('settings/theme', [
            'title' => 'Setting / Theme',
            'theme' => ThemeManager::payload(),
            'defaults' => ThemeManager::defaults(),
        ]);
    }

    public function saveTheme(): void
    {
        $this->requirePermission('settings.theme');
        $this->verifyCsrf();

        ThemeManager::save($_POST);

        $this->redirect('/settings/theme', 'Theme settings saved successfully.');
    }

    public function resetTheme(): void
    {
        $this->requirePermission('settings.theme');
        $this->verifyCsrf();

        ThemeManager::reset();

        $this->redirect('/settings/theme', 'Theme settings were restored to the original defaults.');
    }


    public function media(): void
    {
        $this->requirePermission('settings.media');

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'link' => (string) ($_GET['link'] ?? 'all'),
            'type' => (string) ($_GET['type'] ?? 'all'),
        ];

        $manager = new MediaManager();
        $items = $manager->listMedia($filters);

        $this->render('settings/media/index', [
            'title' => 'Setting / Media',
            'items' => $items,
            'filters' => $filters,
            'summary' => $manager->summary($items),
        ]);
    }

    public function editMedia(): void
    {
        $this->requirePermission('settings.media');

        $path = trim((string) ($_GET['path'] ?? ''));
        $manager = new MediaManager();
        $media = $manager->find($path);

        if ($media === null) {
            $this->redirect('/settings/media', null, 'The selected media file could not be found.');
        }

        $this->render('settings/media/form', [
            'title' => 'Setting / Media / Edit',
            'media' => $media,
            'action' => '/settings/media/update?path=' . rawurlencode((string) $media['path']),
        ]);
    }

    public function updateMedia(): void
    {
        $this->requirePermission('settings.media');
        $this->verifyCsrf();

        $path = trim((string) ($_GET['path'] ?? ''));

        try {
            (new MediaManager())->saveMetadata($path, $_POST);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/media', null, $exception->getMessage());
        }

        $this->redirect('/settings/media', 'Media details updated successfully.');
    }

    public function deleteMedia(): void
    {
        $this->requirePermission('settings.media');
        $this->verifyCsrf();

        $path = trim((string) ($_GET['path'] ?? ''));

        try {
            $result = (new MediaManager())->delete($path);
        } catch (\Throwable $exception) {
            $this->redirect('/settings/media', null, $exception->getMessage());
        }

        $message = 'Media file deleted successfully.';
        if (!empty($result['links_detached'])) {
            $message .= ' Detached links: ' . (int) $result['links_detached'] . '.';
        }

        $this->redirect('/settings/media', $message);
    }

}
