<?php require __DIR__ . '/partials/nav.php'; ?>
<?php
$theme = $theme ?? [];
$defaults = $defaults ?? [];
$shared = (array) ($theme['shared'] ?? []);
$light = (array) ($theme['light'] ?? []);
$dark = (array) ($theme['dark'] ?? []);
$topbar = (array) ($theme['topbar'] ?? []);
?>
<section class="page-head">
    <div>
        <h1>Theme</h1>
        <small>Customize light and dark theme tokens, save advanced CSS, and restore the original interface at any time.</small>
    </div>
    <div class="page-head-actions">
        <form method="post" action="<?= e(base_url('/settings/theme/reset')) ?>" onsubmit="return confirm('Restore the original theme design now?');">
            <?= App\Core\Csrf::field() ?>
            <button type="submit" class="btn secondary">Reset to Original</button>
        </form>
    </div>
</section>

<section class="card stack-gap-sm">
    <div class="card-h">
        <div>
            <h2>Theme Configuration</h2>
            <small>These settings define the default mode, design tokens, and any custom CSS overrides.</small>
        </div>
    </div>
    <div class="card-b">
        <form method="post" action="<?= e(base_url('/settings/theme/save')) ?>" class="stack-gap">
            <?= App\Core\Csrf::field() ?>

            <div class="theme-settings-grid">
                <section class="theme-panel">
                    <h3>Mode</h3>
                    <div class="field">
                        <label for="default_mode">Default Theme</label>
                        <select id="default_mode" name="default_mode">
                            <option value="dark" <?= e(selected('dark', $theme['default_mode'] ?? 'dark')) ?>>Dark</option>
                            <option value="light" <?= e(selected('light', $theme['default_mode'] ?? 'dark')) ?>>Light</option>
                        </select>
                        <small>The top-right icon still lets each browser switch instantly between night and day mode.</small>
                    </div>

                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="shared_brand">Brand Color</label>
                            <input id="shared_brand" name="shared[brand]" value="<?= e($shared['brand'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_success">Success Color</label>
                            <input id="shared_success" name="shared[success]" value="<?= e($shared['success'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_danger">Danger Color</label>
                            <input id="shared_danger" name="shared[danger]" value="<?= e($shared['danger'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_info">Info Color</label>
                            <input id="shared_info" name="shared[info]" value="<?= e($shared['info'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_radius">Main Radius</label>
                            <input id="shared_radius" name="shared[radius]" value="<?= e($shared['radius'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_radius_sm">Small Radius</label>
                            <input id="shared_radius_sm" name="shared[radius_sm]" value="<?= e($shared['radius_sm'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_btn_h">Button Height</label>
                            <input id="shared_btn_h" name="shared[btn_h]" value="<?= e($shared['btn_h'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="shared_container">Container Width</label>
                            <input id="shared_container" name="shared[container]" value="<?= e($shared['container'] ?? '') ?>">
                        </div>
                    </div>
                </section>

                <section class="theme-panel">
                    <h3>Advanced CSS</h3>
                    <div class="field">
                        <label for="custom_css">Custom CSS</label>
                        <textarea id="custom_css" name="custom_css" class="theme-code"><?= e((string) ($theme['custom_css'] ?? '')) ?></textarea>
                        <small>Use this area for anything beyond the standard controls, such as page-specific spacing, table styles, or custom component tweaks.</small>
                    </div>
                    <div class="notice">
                        Example selectors: <code>.topbar</code>, <code>.card</code>, <code>.table th</code>, <code>.btn.secondary</code>, <code>.userpill</code>
                    </div>
                </section>
            </div>

            <div class="theme-settings-grid">
                <section class="theme-panel">
                    <h3>Light Palette</h3>
                    <div class="field-grid field-grid-2">
                        <div class="field"><label>Background</label><input name="light[bg]" value="<?= e($light['bg'] ?? '') ?>"></div>
                        <div class="field"><label>Text</label><input name="light[text]" value="<?= e($light['text'] ?? '') ?>"></div>
                        <div class="field"><label>Muted</label><input name="light[muted]" value="<?= e($light['muted'] ?? '') ?>"></div>
                        <div class="field"><label>Label</label><input name="light[label]" value="<?= e($light['label'] ?? '') ?>"></div>
                        <div class="field"><label>Surface</label><input name="light[surface]" value="<?= e($light['surface'] ?? '') ?>"></div>
                        <div class="field"><label>Surface 2</label><input name="light[surface_2]" value="<?= e($light['surface_2'] ?? '') ?>"></div>
                        <div class="field"><label>Surface 3</label><input name="light[surface_3]" value="<?= e($light['surface_3'] ?? '') ?>"></div>
                        <div class="field"><label>Border</label><input name="light[border]" value="<?= e($light['border'] ?? '') ?>"></div>
                        <div class="field field-full"><label>Topbar Background</label><input name="topbar[light]" value="<?= e($topbar['light'] ?? '') ?>"></div>
                    </div>
                </section>

                <section class="theme-panel">
                    <h3>Dark Palette</h3>
                    <div class="field-grid field-grid-2">
                        <div class="field"><label>Background</label><input name="dark[bg]" value="<?= e($dark['bg'] ?? '') ?>"></div>
                        <div class="field"><label>Text</label><input name="dark[text]" value="<?= e($dark['text'] ?? '') ?>"></div>
                        <div class="field"><label>Muted</label><input name="dark[muted]" value="<?= e($dark['muted'] ?? '') ?>"></div>
                        <div class="field"><label>Label</label><input name="dark[label]" value="<?= e($dark['label'] ?? '') ?>"></div>
                        <div class="field"><label>Surface</label><input name="dark[surface]" value="<?= e($dark['surface'] ?? '') ?>"></div>
                        <div class="field"><label>Surface 2</label><input name="dark[surface_2]" value="<?= e($dark['surface_2'] ?? '') ?>"></div>
                        <div class="field"><label>Surface 3</label><input name="dark[surface_3]" value="<?= e($dark['surface_3'] ?? '') ?>"></div>
                        <div class="field"><label>Border</label><input name="dark[border]" value="<?= e($dark['border'] ?? '') ?>"></div>
                        <div class="field field-full"><label>Topbar Background</label><input name="topbar[dark]" value="<?= e($topbar['dark'] ?? '') ?>"></div>
                    </div>
                </section>
            </div>

            <div class="theme-preview-grid">
                <section class="card theme-preview-shell">
                    <div class="card-h">
                        <div>
                            <h2>Live Preview</h2>
                            <small>The preview uses the currently stored theme values.</small>
                        </div>
                        <span class="badge orange">Preview</span>
                    </div>
                    <div class="card-b stack-gap">
                        <div class="kpi-grid theme-kpi-preview">
                            <div class="kpi-tile"><div class="kpi-copy"><div class="kpi-label">Brand</div><div class="kpi-value">AED</div></div></div>
                            <div class="kpi-tile"><div class="kpi-copy"><div class="kpi-label">Cards</div><div class="kpi-value">Theme</div></div></div>
                        </div>
                        <div class="row theme-preview-actions">
                            <button type="button" class="btn">Primary</button>
                            <button type="button" class="btn secondary">Secondary</button>
                        </div>
                        <div class="tabs settings-tabs">
                            <span class="tab active">Active Tab</span>
                            <span class="tab">Normal Tab</span>
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th>Element</th><th>State</th><th>Value</th></tr></thead>
                                <tbody>
                                <tr><td>Interface</td><td><span class="badge green">Ready</span></td><td><?= e(strtoupper((string) ($theme['default_mode'] ?? 'dark'))) ?></td></tr>
                                <tr><td>Brand</td><td><span class="badge blue">Accent</span></td><td><?= e($shared['brand'] ?? '') ?></td></tr>
                                <tr><td>Radius</td><td><span class="badge orange">Shape</span></td><td><?= e($shared['radius'] ?? '') ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="form-actions row-actions-tight">
                <button type="submit" class="btn">Save Theme</button>
                <span class="muted">A new theme revision is generated on every save so browsers adopt the latest default mode automatically.</span>
            </div>
        </form>
    </div>
</section>
