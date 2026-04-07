<section class="page-head">
    <div>
        <h1>Setting / Update</h1>
        <small>Upload ZIP packages here to update the site core without using DirectAdmin or File Manager for each release.</small>
    </div>
</section>

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="settings-page-grid">
    <section class="card">
        <div class="card-h">
            <div>
                <h2>Core Update</h2>
                <small>Upload the update ZIP package that will be provided for each release.</small>
            </div>
            <span class="badge <?= e(($zipAvailable ?? false) ? 'green' : 'red') ?>"><?= ($zipAvailable ?? false) ? 'ZIP Ready' : 'ZIP Missing' ?></span>
        </div>
        <div class="card-b stack-gap">
            <div class="notice">
                The updater keeps your current <code>.env</code>, logs, sessions, and backup archives. A safety backup is generated automatically before every core update.
            </div>
            <form method="post" action="<?= e(base_url('/settings/update/upload')) ?>" enctype="multipart/form-data" class="stack-gap-sm">
                <?= App\Core\Csrf::field() ?>
                <div class="field">
                    <label>Update ZIP package</label>
                    <input type="file" name="update_zip" accept=".zip" required>
                </div>
                <button type="submit" class="btn" onclick="return confirm('Apply this core update now? The site files will be replaced with the package contents.')" <?= ($zipAvailable ?? false) ? '' : 'disabled' ?>>Upload & Update</button>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-h">
            <div>
                <h2>Package Rules</h2>
                <small>How future update ZIP files should be prepared.</small>
            </div>
        </div>
        <div class="card-b">
            <ul class="install-list">
                <li>The ZIP should contain the full project structure or one top-level folder containing the full project.</li>
                <li><code>.env</code> is preserved automatically and is not overwritten during updates.</li>
                <li>If the package contains <code>update.sql</code> or <code>database/update.sql</code>, it will be executed automatically after the files are copied.</li>
                <li>Each update is logged below so you can track what was installed and when.</li>
            </ul>
        </div>
    </section>
</div>

<section class="card" style="margin-top:12px;">
    <div class="card-h">
        <div>
            <h2>Update History</h2>
            <small>Every successful update package applied from this panel is listed here.</small>
        </div>
    </div>
    <div class="card-b">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Installed At</th>
                    <th>Package</th>
                    <th>Files Applied</th>
                    <th>SQL Scripts</th>
                    <th>Safety Backup</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (($history ?? []) === []): ?>
                    <tr><td colspan="6">No updates have been applied from the admin panel yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="dt"><?= e(date_display($entry['installed_at'] ?? null, 'Y-m-d H:i')) ?></td>
                            <td>
                                <strong><?= e($entry['package_name'] ?? 'package.zip') ?></strong><br>
                                <small><?= e($entry['installed_by'] ?? 'system') ?></small>
                            </td>
                            <td><?= e((string) ($entry['files_applied'] ?? 0)) ?></td>
                            <td><?= e((string) ($entry['sql_scripts_run'] ?? 0)) ?></td>
                            <td><?= e((string) ($entry['safety_backup_id'] ?? '—')) ?></td>
                            <td><span class="badge green"><?= e(ucfirst((string) ($entry['status'] ?? 'success'))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
