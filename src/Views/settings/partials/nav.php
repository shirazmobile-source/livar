<?php use App\Core\Auth; ?>
<div class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <div class="tabs settings-tabs">
            <?php if (Auth::can('settings.overview')): ?><a href="<?= e(base_url('/settings')) ?>" class="tab <?= e(active_menu('/settings') === 'active' && request_path() === '/settings' ? 'active' : '') ?>">Overview</a><?php endif; ?>
            <?php if (Auth::can('settings.backup')): ?><a href="<?= e(base_url('/settings/backup')) ?>" class="tab <?= e(active_menu('/settings/backup')) ?>">Backup</a><?php endif; ?>
            <?php if (Auth::can('settings.update')): ?><a href="<?= e(base_url('/settings/update')) ?>" class="tab <?= e(active_menu('/settings/update')) ?>">Update</a><?php endif; ?>
            <?php if (Auth::can('reports')): ?><a href="<?= e(base_url('/settings/reports')) ?>" class="tab <?= e(active_menu(['/settings/reports', '/reports'])) ?>">Reports</a><?php endif; ?>
            <?php if (Auth::can('settings.users')): ?><a href="<?= e(base_url('/settings/users')) ?>" class="tab <?= e(active_menu('/settings/users')) ?>">Users</a><?php endif; ?>
            <?php if (Auth::can('settings.media')): ?><a href="<?= e(base_url('/settings/media')) ?>" class="tab <?= e(active_menu('/settings/media')) ?>">Media</a><?php endif; ?>
            <?php if (Auth::can('settings.forms')): ?><a href="<?= e(base_url('/settings/forms')) ?>" class="tab <?= e(active_menu('/settings/forms')) ?>">Forms</a><?php endif; ?>
            <?php if (Auth::can('settings.theme')): ?><a href="<?= e(base_url('/settings/theme')) ?>" class="tab <?= e(active_menu('/settings/theme')) ?>">Theme</a><?php endif; ?>
        </div>
    </div>
</div>
