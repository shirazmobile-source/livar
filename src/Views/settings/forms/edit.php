<section class="page-head">
    <div>
        <h1><?= e((string) ($definition['label'] ?? 'Form Template')) ?></h1>
        <small><?= e((string) ($definition['description'] ?? '')) ?></small>
    </div>
    <div class="page-head-actions">
        <a href="<?= e(base_url('/settings/forms')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<?php require __DIR__ . '/../partials/nav.php'; ?>

<section class="card" style="margin-bottom:12px;">
    <div class="card-b">
        <div class="tabs settings-tabs">
            <?php foreach (($types ?? []) as $entryType => $entry): ?>
                <a href="<?= e(base_url('/settings/forms/edit?type=' . $entryType)) ?>" class="tab <?= e($entryType === ($type ?? '') ? 'active' : '') ?>"><?= e($entry['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-h"><h2>Template Settings</h2><span class="badge green">Revision <?= e((string) ($revision ?? 'core-default')) ?></span></div>
    <div class="card-b">
        <form method="post" action="<?= e(base_url((string) $action)) ?>" class="grid-2">
            <?= App\Core\Csrf::field() ?>
            <div class="field"><label>Company Name</label><input type="text" name="global[company_name]" value="<?= e((string) ($global['company_name'] ?? '')) ?>"></div>
            <div class="field"><label>Company Tagline</label><input type="text" name="global[company_tagline]" value="<?= e((string) ($global['company_tagline'] ?? '')) ?>"></div>
            <div class="field"><label>Company Address</label><textarea name="global[company_address]" rows="3"><?= e((string) ($global['company_address'] ?? '')) ?></textarea></div>
            <div class="field"><label>Company Phone</label><input type="text" name="global[company_phone]" value="<?= e((string) ($global['company_phone'] ?? '')) ?>"></div>
            <div class="field"><label>Company Email</label><input type="text" name="global[company_email]" value="<?= e((string) ($global['company_email'] ?? '')) ?>"></div>
            <div class="field"><label>Company TRN</label><input type="text" name="global[company_trn]" value="<?= e((string) ($global['company_trn'] ?? '')) ?>"></div>
            <div class="field"><label>Company Logo URL / Path</label><input type="text" name="global[company_logo_url]" value="<?= e((string) ($global['company_logo_url'] ?? '')) ?>"></div>
            <div class="field"><label>Header Note</label><input type="text" name="global[header_note]" value="<?= e((string) ($global['header_note'] ?? '')) ?>"></div>

            <div class="field"><label>Document Title</label><input type="text" name="template[title]" value="<?= e((string) ($template['title'] ?? '')) ?>"></div>
            <div class="field"><label>Accent Color</label><input type="color" name="template[accent_color]" value="<?= e((string) ($template['accent_color'] ?? '#111827')) ?>"></div>
            <div class="field"><label>Paper Size</label><select name="template[paper_size]" data-paper-size><?php foreach (['A4','A5','LETTER'] as $paper): ?><option value="<?= e($paper) ?>" <?= selected($paper, (string) ($template['paper_size'] ?? 'A4')) ?>><?= e($paper) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Orientation</label><select name="template[orientation]" data-paper-orientation><?php foreach (['portrait' => 'Portrait', 'landscape' => 'Landscape'] as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($value, (string) ($template['orientation'] ?? 'portrait')) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>Font Family</label><select name="template[font_family]"><?php foreach ([
                'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif' => 'Inter / System Sans',
                'Arial, Helvetica, sans-serif' => 'Arial',
                'Tahoma, Arial, sans-serif' => 'Tahoma',
                'Verdana, Geneva, sans-serif' => 'Verdana',
                'Trebuchet MS, Arial, sans-serif' => 'Trebuchet MS',
                'Georgia, &quot;Times New Roman&quot;, serif' => 'Georgia',
                '&quot;Times New Roman&quot;, Times, serif' => 'Times New Roman',
            ] as $value => $label): ?><option value="<?= $value ?>" <?= selected(htmlspecialchars_decode($value, ENT_QUOTES), (string) ($template['font_family'] ?? 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif')) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Base Font Size (px)</label><input type="number" min="10" max="18" name="template[base_font_size]" value="<?= e((string) ($template['base_font_size'] ?? 13)) ?>"></div>
            <div class="field"><label>Title Font Size (px)</label><input type="number" min="16" max="36" name="template[title_font_size]" value="<?= e((string) ($template['title_font_size'] ?? 22)) ?>"></div>
            <div class="field"><label>Section Title Size (px)</label><input type="number" min="12" max="26" name="template[section_title_font_size]" value="<?= e((string) ($template['section_title_font_size'] ?? 16)) ?>"></div>
            <div class="field"><label>Table Font Size (px)</label><input type="number" min="9" max="18" name="template[table_font_size]" value="<?= e((string) ($template['table_font_size'] ?? 13)) ?>"></div>

            <?php if (in_array((string) ($type ?? ''), ['sales_invoice', 'purchase_invoice'], true)): ?>
                <div class="field"><label>Invoice Layout</label><select name="template[layout_variant]"><?php foreach (($invoiceLayoutVariants ?? []) as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($value, (string) ($template['layout_variant'] ?? 'classic')) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>QR Position</label><select name="template[qr_position]"><?php foreach (($invoiceQrPositions ?? []) as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($value, (string) ($template['qr_position'] ?? 'header_right')) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="field" style="grid-column:1 / -1;">
                    <label>Print Engine</label>
                    <div class="alert info" style="margin:0;">
                        Sales Invoice and Purchase Invoice now use a locked, print-safe document engine. Drag-and-drop positioning is disabled for these two financial documents so PDF and printed output remain stable and consistent.
                    </div>
                </div>
            <?php endif; ?>

            <div class="field" style="grid-column:1 / -1;">
                <label>Display Options</label>
                <?php
                $displayOptions = ['show_logo' => 'Show Logo', 'show_company_info' => 'Show Company Info', 'show_summary' => 'Show Summary', 'show_currency_rate' => 'Show Currency Rate', 'show_notes' => 'Show Notes', 'show_terms' => 'Show Terms', 'show_signatures' => 'Show Signatures', 'show_qr' => 'Show QR', 'show_footer' => 'Show Footer', 'auto_print' => 'Auto Print'];
                $isSalesInvoiceTemplate = (string) ($type ?? '') === 'sales_invoice';
                if ($isSalesInvoiceTemplate) {
                    unset($displayOptions['show_summary'], $displayOptions['show_currency_rate']);
                }
                ?>
                <?php if ($isSalesInvoiceTemplate): ?>
                    <input type="hidden" name="template[show_summary]" value="0">
                    <input type="hidden" name="template[show_currency_rate]" value="0">
                    <p class="muted" style="margin:6px 0 12px;">For Sales Invoice, the Summary block and Rate Snapshot are fixed hidden in print output.</p>
                <?php endif; ?>
                <div class="row" style="gap:16px; flex-wrap:wrap;">
                    <?php foreach ($displayOptions as $key => $label): ?>
                        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="template[<?= e($key) ?>]" value="1" <?= !empty($template[$key]) ? 'checked' : '' ?>><?= e($label) ?></label>
                    <?php endforeach; ?>
                    <?php if (!empty($visualDesigner)): ?>
                        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="template[layout_enabled]" value="1" <?= !empty($template['layout_enabled']) ? 'checked' : '' ?>>Visual Designer Active</label>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($visualDesigner)): ?>
                <?php
                $layoutJson = json_encode($template['layout'] ?? ['unit' => 'mm', 'widgets' => []], JSON_UNESCAPED_SLASHES);
                $defaultLayoutJson = json_encode($defaultLayout ?? ['unit' => 'mm', 'widgets' => []], JSON_UNESCAPED_SLASHES);
                $widgetCatalogJson = json_encode($widgetCatalog ?? [], JSON_UNESCAPED_SLASHES);
                $paperCatalogJson = json_encode($designerPaperCatalog ?? [], JSON_UNESCAPED_SLASHES);
                $paperMetricsCurrent = $paperMetrics ?? App\Core\FormTemplateManager::paperMetrics((string) ($template['paper_size'] ?? 'A4'), (string) ($template['orientation'] ?? 'portrait'));
                ?>
                <div class="field" style="grid-column:1 / -1;">
                    <label>Visual Layout Designer</label>
                    <p class="muted" style="margin:6px 0 12px;">This designer now uses a print-safe hybrid model. Header blocks such as Company, Meta, and QR can move freely with the mouse. Body blocks such as Bill To, Summary, Items, Notes, and Totals are rendered in flow-managed zones so the final PDF stays stable and does not overlap.</p>
                    <div class="designer-shell" data-form-designer data-initial-layout='<?= e((string) $layoutJson) ?>' data-default-layout='<?= e((string) $defaultLayoutJson) ?>' data-widget-catalog='<?= e((string) $widgetCatalogJson) ?>' data-paper-catalog='<?= e((string) $paperCatalogJson) ?>'>
                        <input type="hidden" name="template[layout_json]" value='<?= e((string) $layoutJson) ?>' data-designer-input>
                        <div class="designer-sidebar card" style="margin-bottom:0;">
                            <div class="card-h"><h2>Layout</h2><span class="badge">mm grid</span></div>
                            <div class="card-b">
                                <div class="designer-paper-summary" data-paper-summary>
                                    <div><strong data-summary-paper><?= e((string) ($paperMetricsCurrent['paper'] ?? 'A4')) ?></strong><small data-summary-page><?= e((string) (($paperMetricsCurrent['page_width_mm'] ?? 210) . ' × ' . ($paperMetricsCurrent['page_height_mm'] ?? 297) . ' mm')) ?></small></div>
                                    <div><strong>Safe Area</strong><small data-summary-safe><?= e((string) (($paperMetricsCurrent['content_width_mm'] ?? 190) . ' × ' . ($paperMetricsCurrent['content_height_mm'] ?? 277) . ' mm')) ?></small></div>
                                </div>
                                <div class="designer-widget-list" data-widget-list></div>
                                <div class="designer-inspector">
                                    <h3>Selected Block</h3>
                                    <div class="grid-2">
                                        <div class="field"><label>X (mm)</label><input type="number" step="0.5" min="0" data-prop="x"></div>
                                        <div class="field"><label>Y (mm)</label><input type="number" step="0.5" min="0" data-prop="y"></div>
                                        <div class="field"><label>Width (mm)</label><input type="number" step="0.5" min="20" data-prop="w"></div>
                                        <div class="field"><label>Min Height (mm)</label><input type="number" step="0.5" min="8" data-prop="h"></div>
                                    </div>
                                    <div class="field"><label>Design Advice</label><div class="designer-hint" data-widget-hint>Select a block to see guidance.</div></div>
                                    <div class="row" style="gap:10px; flex-wrap:wrap;">
                                        <button type="button" class="btn secondary" data-fit-layout>Fit to page</button>
                                        <button type="button" class="btn secondary" data-apply-preset>Apply standard preset</button>
                                        <button type="button" class="btn ghost" data-reset-layout>Reset visual layout</button>
                                    </div>
                                    <div class="designer-tips">
                                        <strong>PDF Tips</strong>
                                        <ul>
                                            <li>Keep browser print scale at 100%.</li>
                                            <li>Use margins = none or default browser zero margins.</li>
                                            <li>Start from the standard preset; do not place blocks on the page edge.</li>
                                            <li>Leave extra height for the items table when invoices can have many rows.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="designer-stage card" style="margin-bottom:0;">
                            <div class="card-h"><h2>Canvas</h2><span class="badge green">A4 / A5 / Letter</span></div>
                            <div class="card-b">
                                <div class="designer-canvas-wrap" data-designer-wrap>
                                    <div class="designer-paper" data-designer-paper>
                                        <div class="designer-ruler designer-ruler-x" data-ruler-x></div>
                                        <div class="designer-ruler designer-ruler-y" data-ruler-y></div>
                                        <div class="designer-safe-area" data-designer-safe>
                                            <div class="designer-grid-label">Safe printable area</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="field"><label>Watermark Text</label><input type="text" name="template[watermark_text]" value="<?= e((string) ($template['watermark_text'] ?? '')) ?>"></div>
            <div class="field"><label>Footer Text</label><input type="text" name="template[footer_text]" value="<?= e((string) ($template['footer_text'] ?? '')) ?>"></div>
            <div class="field" style="grid-column:1 / -1;"><label>Terms Text</label><textarea name="template[terms_text]" rows="5"><?= e((string) ($template['terms_text'] ?? '')) ?></textarea></div>
            <div class="field" style="grid-column:1 / -1;"><label>Custom CSS</label><textarea name="template[custom_css]" rows="10" placeholder=".doc-table thead th{font-size:11px;}"><?= e((string) ($template['custom_css'] ?? '')) ?></textarea></div>

            <div class="row" style="gap:10px; grid-column:1 / -1;">
                <button type="submit" class="btn">Save Template</button>
                <a class="btn secondary" href="<?= e(base_url('/settings/forms')) ?>">Cancel</a>
            </div>
        </form>

        <form method="post" action="<?= e(base_url((string) $resetAction)) ?>" style="margin-top:16px;">
            <?= App\Core\Csrf::field() ?>
            <button type="submit" class="btn ghost">Reset This Template to Default</button>
        </form>
    </div>
</section>

<?php if (!empty($visualDesigner)): ?>
<style>
.designer-shell{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start}
.designer-paper-summary{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.designer-paper-summary>div{border:1px solid var(--line,rgba(255,255,255,.08));border-radius:14px;padding:10px 12px;background:var(--panel-2,rgba(255,255,255,.03))}
.designer-paper-summary strong{display:block;font-size:13px;margin-bottom:2px}
.designer-paper-summary small{display:block;opacity:.8;line-height:1.4}
.designer-widget-list{display:grid;gap:8px;margin-bottom:18px}
.designer-widget-card{border:1px solid var(--line,rgba(255,255,255,.08));border-radius:14px;padding:10px 12px;background:var(--panel-2,rgba(255,255,255,.03));cursor:pointer;text-align:left}
.designer-widget-card.is-active{border-color:var(--accent,#f97316);box-shadow:0 0 0 1px rgba(249,115,22,.2) inset}
.designer-widget-card strong{display:block;margin-bottom:4px}
.designer-widget-card small{display:block;opacity:.8;line-height:1.4}
.designer-inspector h3{margin:0 0 12px;font-size:15px}
.designer-hint,.designer-tips{border:1px solid var(--line,rgba(255,255,255,.08));border-radius:14px;padding:10px 12px;background:var(--panel-2,rgba(255,255,255,.03));font-size:13px;line-height:1.55}
.designer-tips{margin-top:12px}
.designer-tips ul{margin:8px 0 0 18px;padding:0}
.designer-canvas-wrap{overflow:auto;padding:8px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.02));border-radius:18px}
.designer-paper{position:relative;margin:0 auto;border:1px solid rgba(148,163,184,.35);border-radius:18px;background:#fff;box-shadow:0 18px 40px rgba(15,23,42,.15);overflow:hidden}
.designer-safe-area{position:absolute;border:2px dashed rgba(249,115,22,.45);box-sizing:border-box;background-image:linear-gradient(to right,rgba(148,163,184,.10) 1px,transparent 1px),linear-gradient(to bottom,rgba(148,163,184,.10) 1px,transparent 1px);background-size:20px 20px}
.designer-grid-label{position:absolute;top:6px;right:10px;font-size:11px;color:#64748b;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:999px}
.designer-widget{position:absolute;border:1px solid rgba(17,24,39,.16);border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 8px 22px rgba(15,23,42,.08);overflow:hidden;user-select:none}
.designer-widget.is-selected{border-color:#f97316;box-shadow:0 0 0 2px rgba(249,115,22,.18),0 10px 24px rgba(15,23,42,.12)}
.designer-widget.is-flow{border-style:dashed;background:rgba(255,255,255,.98)}
.designer-widget.is-flow .designer-widget-bar{background:#1f2937}
.designer-widget-bar{padding:8px 12px;background:#111827;color:#fff;font-size:12px;font-weight:700;cursor:move;display:flex;justify-content:space-between;gap:8px}
.designer-widget-body{padding:10px 12px;font-size:12px;line-height:1.45;color:#475467}
.designer-widget-body .smart{display:inline-block;margin-top:6px;padding:3px 8px;border-radius:999px;background:rgba(249,115,22,.12);color:#c2410c;font-weight:700;font-size:11px}
.designer-widget-handle{position:absolute;right:8px;bottom:8px;width:14px;height:14px;border-radius:4px;background:#f97316;cursor:nwse-resize;box-shadow:0 0 0 2px rgba(255,255,255,.95)}
.designer-ruler{position:absolute;pointer-events:none;color:#64748b;font-size:10px}
.designer-ruler-x{left:0;right:0;top:0;height:20px}
.designer-ruler-y{top:0;bottom:0;left:0;width:20px}
.designer-ruler .tick{position:absolute}
.designer-ruler-x .tick{top:0;height:20px;border-left:1px solid rgba(148,163,184,.35)}
.designer-ruler-y .tick{left:0;width:20px;border-top:1px solid rgba(148,163,184,.35)}
.designer-ruler .tick span{position:absolute;white-space:nowrap}
.designer-ruler-x .tick span{top:2px;left:4px}
.designer-ruler-y .tick span{top:2px;left:2px;transform:rotate(-90deg);transform-origin:top left}
@media (max-width: 1200px){.designer-shell{grid-template-columns:1fr}}
</style>
<script>
(function(){
    const root = document.querySelector('[data-form-designer]');
    if (!root) return;
    const paperSizeField = document.querySelector('[data-paper-size]');
    const orientationField = document.querySelector('[data-paper-orientation]');
    const paper = root.querySelector('[data-designer-paper]');
    const safe = root.querySelector('[data-designer-safe]');
    const input = root.querySelector('[data-designer-input]');
    const list = root.querySelector('[data-widget-list]');
    const resetButton = root.querySelector('[data-reset-layout]');
    const fitButton = root.querySelector('[data-fit-layout]');
    const applyPresetButton = root.querySelector('[data-apply-preset]');
    const hintBox = root.querySelector('[data-widget-hint]');
    const summaryPaper = root.querySelector('[data-summary-paper]');
    const summaryPage = root.querySelector('[data-summary-page]');
    const summarySafe = root.querySelector('[data-summary-safe]');
    const rulerX = root.querySelector('[data-ruler-x]');
    const rulerY = root.querySelector('[data-ruler-y]');
    const catalog = JSON.parse(root.dataset.widgetCatalog || '{}');
    const defaults = JSON.parse(root.dataset.defaultLayout || '{"unit":"mm","widgets":{}}');
    const paperCatalog = JSON.parse(root.dataset.paperCatalog || '{}');
    const FIXED = ['header_brand', 'meta_block', 'qr_block'];
    const BODY_FLOW = ['party_block', 'summary_block', 'items_block'];
    const FOOTER_ROW = ['notes_block', 'totals_block'];
    const FOOTER_STACK = ['signatures_block', 'footer_block'];
    const GAP_MM = 6;
    const snap = 1;
    let state = JSON.parse(root.dataset.initialLayout || root.dataset.defaultLayout || '{"unit":"mm","widgets":{}}');
    let selectedKey = Object.keys(catalog)[0] || null;
    const fields = {
        x: root.querySelector('[data-prop="x"]'),
        y: root.querySelector('[data-prop="y"]'),
        w: root.querySelector('[data-prop="w"]'),
        h: root.querySelector('[data-prop="h"]')
    };

    function clone(obj){
        return JSON.parse(JSON.stringify(obj));
    }

    function paperKey(){
        return (paperSizeField ? paperSizeField.value : 'A4') + '_' + (orientationField ? orientationField.value : 'portrait');
    }

    function metrics(){
        return paperCatalog[paperKey()] || {paper:'A4',orientation:'portrait',page_width_mm:210,page_height_mm:297,margin_top_mm:10,margin_right_mm:10,margin_bottom_mm:10,margin_left_mm:10,content_width_mm:190,content_height_mm:277,grid_mm:1};
    }

    function suggestedMinHeight(key){
        const metric = metrics();
        const portrait = metric.orientation !== 'landscape';
        const map = {
            header_brand: portrait ? 36 : 26,
            meta_block: portrait ? 28 : 24,
            qr_block: Math.max(metric.recommended_qr_mm ? metric.recommended_qr_mm + 4 : 28, portrait ? 24 : 22),
            party_block: portrait ? 28 : 22,
            summary_block: 18,
            items_block: portrait ? 82 : 58,
            notes_block: 22,
            totals_block: 22,
            signatures_block: 12,
            footer_block: 8
        };
        return map[key] || 12;
    }

    function standardLayout(){
        const metric = metrics();
        const widgetDefaults = defaults.widgets || {};
        const current = clone(defaults);
        current.unit = 'mm';
        current.paper = metric.paper;
        current.orientation = metric.orientation;
        current.metrics = metric;
        current.widgets = clone(widgetDefaults);
        return current;
    }

    function clamp(value, min, max){
        return Math.min(max, Math.max(min, value));
    }

    function round(value){
        return Math.round(value * 10) / 10;
    }

    function isFixed(key){
        return FIXED.indexOf(key) !== -1;
    }

    function isBodyFlow(key){
        return BODY_FLOW.indexOf(key) !== -1;
    }

    function isFooterRow(key){
        return FOOTER_ROW.indexOf(key) !== -1;
    }

    function isFlowManaged(key){
        return !isFixed(key);
    }

    function widgetHint(key){
        const hints = {
            header_brand: 'Free block. Good for logo, title, and company information. It stays in the header area exactly where you place it.',
            meta_block: 'Free block. Best placed in the top-right area. Keep it tall enough for invoice number, date, warehouse, status, currency, and rate.',
            qr_block: 'Free block. Keep QR in the header zone. Recommended width is 28–36 mm.',
            party_block: 'Flow-managed block. In the final PDF it is stacked after the header so address lines can grow safely without overlap.',
            summary_block: 'Flow-managed block. It stays above the items table and uses its height setting as reserved vertical space.',
            items_block: 'Flow-managed smart block. Give it generous height so long invoices do not collide with notes, totals, or signatures.',
            notes_block: 'Flow-managed row block. It prints beside totals when both are visible.',
            totals_block: 'Flow-managed row block. It prints beside notes when both are visible.',
            signatures_block: 'Flow-managed block. It is anchored near the footer after notes and totals.',
            footer_block: 'Flow-managed block. It stays last and inside the printable safe area.'
        };
        return hints[key] || 'Move and size this block inside the safe printable area.';
    }

    function normalize(){
        const metric = metrics();
        state.unit = 'mm';
        state.paper = metric.paper;
        state.orientation = metric.orientation;
        state.metrics = metric;
        if (!state.widgets) state.widgets = {};
        Object.keys(catalog).forEach((key) => {
            const base = ((defaults.widgets || {})[key]) || {x:0,y:0,w:metric.content_width_mm,h:12,z:10};
            const item = Object.assign({}, base, state.widgets[key] || {});
            const minHeight = suggestedMinHeight(key);
            item.w = clamp(Number(item.w || base.w), 20, metric.content_width_mm);
            item.h = clamp(Number(item.h || base.h || minHeight), minHeight, metric.content_height_mm);
            item.x = clamp(Number(item.x || base.x), 0, Math.max(0, metric.content_width_mm - item.w));
            item.y = clamp(Number(item.y || base.y), 0, Math.max(0, metric.content_height_mm - item.h));
            item.z = clamp(parseInt(item.z || base.z || 10, 10), 1, 99);
            state.widgets[key] = {x:round(item.x), y:round(item.y), w:round(item.w), h:round(item.h), z:item.z};
        });
    }

    function saveState(){
        normalize();
        input.value = JSON.stringify(state);
    }

    function renderRulers(scale){
        const metric = metrics();
        rulerX.innerHTML = '';
        rulerY.innerHTML = '';
        for (let x = 0; x <= metric.page_width_mm; x += 10) {
            const tick = document.createElement('div');
            tick.className = 'tick';
            tick.style.left = (x * scale) + 'px';
            tick.style.height = (x % 50 === 0 ? 20 : 12) + 'px';
            tick.innerHTML = '<span>' + x + '</span>';
            rulerX.appendChild(tick);
        }
        for (let y = 0; y <= metric.page_height_mm; y += 10) {
            const tick = document.createElement('div');
            tick.className = 'tick';
            tick.style.top = (y * scale) + 'px';
            tick.style.width = (y % 50 === 0 ? 20 : 12) + 'px';
            tick.innerHTML = '<span>' + y + '</span>';
            rulerY.appendChild(tick);
        }
    }

    function updateSummary(){
        const metric = metrics();
        summaryPaper.textContent = metric.paper + ' / ' + metric.orientation;
        summaryPage.textContent = metric.page_width_mm + ' × ' + metric.page_height_mm + ' mm';
        summarySafe.textContent = metric.content_width_mm + ' × ' + metric.content_height_mm + ' mm';
    }

    function updateInspector(){
        if (!selectedKey || !state.widgets[selectedKey]) return;
        const item = state.widgets[selectedKey];
        fields.x.value = item.x;
        fields.y.value = item.y;
        fields.w.value = item.w;
        fields.h.value = item.h;
        hintBox.textContent = widgetHint(selectedKey);
    }

    function updateList(){
        list.innerHTML = '';
        Object.entries(catalog).forEach(([key, meta]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'designer-widget-card' + (key === selectedKey ? ' is-active' : '');
            const badge = isFlowManaged(key) ? '<small style="display:inline-block;margin-top:6px;color:#c2410c;font-weight:700;">Flow managed in print</small>' : '<small style="display:inline-block;margin-top:6px;color:#047857;font-weight:700;">Free in header</small>';
            button.innerHTML = '<strong>' + meta.label + '</strong><small>' + meta.description + '</small>' + badge;
            button.addEventListener('click', () => {
                selectedKey = key;
                render();
            });
            list.appendChild(button);
        });
    }

    function scaleForPaper(metric){
        const wrap = root.querySelector('[data-designer-wrap]');
        const maxWidth = Math.min(980, ((wrap ? wrap.clientWidth : 980) - 16) || 980);
        return Math.max(2.4, Math.min(4.2, maxWidth / metric.page_width_mm));
    }

    function mmDelta(dx, dy, scale){
        return {x: dx / scale, y: dy / scale};
    }

    function flowPreviewPlan(){
        const metric = metrics();
        const widgets = state.widgets || {};
        const plan = {};
        let headerBottom = 0;
        FIXED.forEach((key) => {
            const item = widgets[key];
            if (!item) return;
            plan[key] = {x:item.x, y:item.y, w:item.w, h:item.h, z:item.z};
            headerBottom = Math.max(headerBottom, item.y + item.h);
        });
        let currentY = Math.max(headerBottom + GAP_MM, 0);
        BODY_FLOW.slice().sort((a,b) => (widgets[a]?.y || 0) - (widgets[b]?.y || 0)).forEach((key) => {
            const item = widgets[key];
            if (!item) return;
            plan[key] = {x:0, y:currentY, w:metric.content_width_mm, h:item.h, z:item.z};
            currentY += item.h + GAP_MM;
        });
        const rowKeys = FOOTER_ROW.filter((key) => widgets[key]).sort((a,b) => (widgets[a]?.x || 0) - (widgets[b]?.x || 0));
        if (rowKeys.length === 2) {
            const leftKey = rowKeys[0];
            const rightKey = rowKeys[1];
            const leftSource = widgets[leftKey];
            const rightSource = widgets[rightKey];
            const totalWidth = Math.max(1, leftSource.w + rightSource.w);
            const proposedLeft = metric.content_width_mm * (leftSource.w / totalWidth);
            const leftWidth = clamp(round(proposedLeft), 40, metric.content_width_mm - 40 - GAP_MM);
            const rightWidth = round(metric.content_width_mm - GAP_MM - leftWidth);
            plan[leftKey] = {x:0, y:currentY, w:leftWidth, h:leftSource.h, z:leftSource.z};
            plan[rightKey] = {x:leftWidth + GAP_MM, y:currentY, w:rightWidth, h:rightSource.h, z:rightSource.z};
            currentY += Math.max(leftSource.h, rightSource.h) + GAP_MM;
        } else if (rowKeys.length === 1) {
            const key = rowKeys[0];
            const item = widgets[key];
            plan[key] = {x:0, y:currentY, w:metric.content_width_mm, h:item.h, z:item.z};
            currentY += item.h + GAP_MM;
        }
        FOOTER_STACK.slice().sort((a,b) => (widgets[a]?.y || 0) - (widgets[b]?.y || 0)).forEach((key) => {
            const item = widgets[key];
            if (!item) return;
            plan[key] = {x:0, y:currentY, w:metric.content_width_mm, h:item.h, z:item.z};
            currentY += item.h + GAP_MM;
        });
        return plan;
    }

    function startDrag(event, key, mode){
        event.preventDefault();
        event.stopPropagation();
        selectedKey = key;
        render();
        const metric = metrics();
        const scale = scaleForPaper(metric);
        const start = {x:event.clientX, y:event.clientY};
        const origin = Object.assign({}, state.widgets[key]);
        const onMove = (moveEvent) => {
            const delta = mmDelta(moveEvent.clientX - start.x, moveEvent.clientY - start.y, scale);
            if (mode === 'move') {
                state.widgets[key].x = clamp(round(Math.round((origin.x + delta.x) / snap) * snap), 0, Math.max(0, metric.content_width_mm - state.widgets[key].w));
                state.widgets[key].y = clamp(round(Math.round((origin.y + delta.y) / snap) * snap), 0, Math.max(0, metric.content_height_mm - state.widgets[key].h));
            } else {
                state.widgets[key].w = clamp(round(Math.round((origin.w + delta.x) / snap) * snap), 20, metric.content_width_mm - origin.x);
                state.widgets[key].h = clamp(round(Math.round((origin.h + delta.y) / snap) * snap), suggestedMinHeight(key), metric.content_height_mm - origin.y);
            }
            render(false);
        };
        const onUp = () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
            saveState();
            render(false);
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    }

    function fitToPage(){
        const metric = metrics();
        const xs = [], ys = [], rights = [], bottoms = [];
        Object.values(state.widgets || {}).forEach((item) => {
            xs.push(Number(item.x || 0));
            ys.push(Number(item.y || 0));
            rights.push(Number(item.x || 0) + Number(item.w || 0));
            bottoms.push(Number(item.y || 0) + Number(item.h || 0));
        });
        if (!xs.length) return;
        const minX = Math.min.apply(null, xs);
        const minY = Math.min.apply(null, ys);
        const maxX = Math.max.apply(null, rights);
        const maxY = Math.max.apply(null, bottoms);
        const usedW = Math.max(1, maxX - minX);
        const usedH = Math.max(1, maxY - minY);
        const scale = Math.min(metric.content_width_mm / usedW, metric.content_height_mm / usedH, 1);
        Object.keys(state.widgets).forEach((key) => {
            const item = state.widgets[key];
            item.x = round((item.x - minX) * scale);
            item.y = round((item.y - minY) * scale);
            item.w = round(item.w * scale);
            item.h = round(item.h * scale);
        });
        render();
    }

    function applyPreset(){
        state = standardLayout();
        selectedKey = Object.keys(catalog)[0] || null;
        render();
    }

    function createWidgetNode(key, meta, box, scale){
        const widget = document.createElement('div');
        const flow = isFlowManaged(key);
        widget.className = 'designer-widget' + (key === selectedKey ? ' is-selected' : '') + (flow ? ' is-flow' : ' is-fixed');
        widget.style.left = (box.x * scale) + 'px';
        widget.style.top = (box.y * scale) + 'px';
        widget.style.width = (box.w * scale) + 'px';
        widget.style.height = (box.h * scale) + 'px';
        const badge = flow ? '<div class="smart">Flow managed</div>' : '';
        widget.innerHTML = '<div class="designer-widget-bar"><span>' + meta.label + '</span><span>' + box.x.toFixed(1) + ',' + box.y.toFixed(1) + ' mm</span></div><div class="designer-widget-body">' + meta.description + badge + '</div><span class="designer-widget-handle"></span>';
        widget.addEventListener('mousedown', () => { selectedKey = key; render(); });
        widget.querySelector('.designer-widget-bar').addEventListener('mousedown', (e) => startDrag(e, key, 'move'));
        widget.querySelector('.designer-widget-handle').addEventListener('mousedown', (e) => startDrag(e, key, 'resize'));
        return widget;
    }

    function render(refreshList = true){
        normalize();
        const metric = metrics();
        const scale = scaleForPaper(metric);
        const previewPlan = flowPreviewPlan();
        paper.style.width = (metric.page_width_mm * scale) + 'px';
        paper.style.height = (metric.page_height_mm * scale) + 'px';
        safe.style.left = (metric.margin_left_mm * scale) + 'px';
        safe.style.top = (metric.margin_top_mm * scale) + 'px';
        safe.style.width = (metric.content_width_mm * scale) + 'px';
        safe.style.height = (metric.content_height_mm * scale) + 'px';
        safe.innerHTML = '<div class="designer-grid-label">Safe printable area</div>';
        Object.entries(catalog).forEach(([key, meta]) => {
            const box = previewPlan[key] || state.widgets[key];
            if (!box) return;
            safe.appendChild(createWidgetNode(key, meta, box, scale));
        });
        renderRulers(scale);
        updateSummary();
        if (refreshList) updateList();
        updateInspector();
        saveState();
    }

    Object.entries(fields).forEach(([prop, field]) => {
        field.addEventListener('input', () => {
            if (!selectedKey) return;
            state.widgets[selectedKey][prop] = parseFloat(field.value || state.widgets[selectedKey][prop] || 0);
            render(false);
        });
    });

    if (paperSizeField) paperSizeField.addEventListener('change', () => { fitToPage(); render(); });
    if (orientationField) orientationField.addEventListener('change', () => { fitToPage(); render(); });
    resetButton.addEventListener('click', () => { state = clone(defaults); selectedKey = Object.keys(catalog)[0] || null; render(); });
    fitButton.addEventListener('click', fitToPage);
    applyPresetButton.addEventListener('click', applyPreset);

    render();
})();
</script>
<?php endif; ?>
