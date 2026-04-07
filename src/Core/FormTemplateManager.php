<?php

declare(strict_types=1);

namespace App\Core;

final class FormTemplateManager
{
    private const FILE = 'app/document-forms.json';

    public static function types(): array
    {
        return [
            'sales_invoice' => [
                'label' => 'Sales Invoice',
                'description' => 'Customer-facing sales invoice with warehouse and payment summary.',
                'route' => '/documents/sales/invoice?id={id}',
            ],
            'purchase_invoice' => [
                'label' => 'Purchase Invoice',
                'description' => 'Supplier-facing purchase invoice with receipt and payment detail.',
                'route' => '/documents/purchases/invoice?id={id}',
            ],
            'customer_statement' => [
                'label' => 'Customer Statement',
                'description' => 'Running balance statement showing sales invoices and customer receipts.',
                'route' => '/documents/customers/statement?id={id}',
            ],
            'supplier_statement' => [
                'label' => 'Supplier Statement',
                'description' => 'Running balance statement for purchases, returns, and supplier payments.',
                'route' => '/documents/suppliers/statement?id={id}',
            ],
            'bank_statement' => [
                'label' => 'Bank Statement',
                'description' => 'Account statement with opening balance, inflow, outflow, and closing balance.',
                'route' => '/documents/banking/statement?account_id={id}',
            ],
            'inventory_receipt' => [
                'label' => 'Warehouse Receipt',
                'description' => 'Inbound warehouse receipt note generated after receiving a purchase.',
                'route' => '/documents/inventory/receipt?id={id}',
            ],
            'inventory_issue' => [
                'label' => 'Warehouse Issue',
                'description' => 'Outbound warehouse issue note generated from a sales invoice.',
                'route' => '/documents/inventory/issue?sale_id={id}',
            ],
        ];
    }


    public static function invoiceLayoutVariants(): array
    {
        return [
            'classic' => 'Classic',
            'compact' => 'Compact',
            'modern' => 'Modern',
        ];
    }

    public static function invoiceQrPositions(): array
    {
        return [
            'header_right' => 'Header Right',
            'meta_bottom' => 'Below Meta',
        ];
    }

    public static function defaults(): array
    {
        return [
            'revision' => 'core-default',
            'global' => [
                'company_name' => (string) config('app.name', 'LCA'),
                'company_tagline' => 'Centralized Accounting',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_trn' => '',
                'company_logo_url' => '',
                'header_note' => '',
            ],
            'templates' => [
                'sales_invoice' => array_merge(
                    self::templateDefaults('sales_invoice', 'Sales Invoice', true, false, true),
                    ['show_currency_rate' => false]
                ),
                'purchase_invoice' => self::templateDefaults('purchase_invoice', 'Purchase Invoice', true, true, true),
                'customer_statement' => self::templateDefaults('customer_statement', 'Customer Statement', false, true, false),
                'supplier_statement' => self::templateDefaults('supplier_statement', 'Supplier Statement', false, true, false),
                'bank_statement' => self::templateDefaults('bank_statement', 'Bank Statement', false, true, false),
                'inventory_receipt' => self::templateDefaults('inventory_receipt', 'Warehouse Receipt', false, false, true, 'A5', 'landscape'),
                'inventory_issue' => self::templateDefaults('inventory_issue', 'Warehouse Issue', false, false, true, 'A5', 'landscape'),
            ],
        ];
    }

    public static function payload(): array
    {
        $defaults = self::defaults();
        $stored = [];

        if (is_file(self::path())) {
            $decoded = json_decode((string) file_get_contents(self::path()), true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        }

        $templates = [];
        foreach (self::types() as $type => $meta) {
            $templates[$type] = self::mergeTemplate(
                $type,
                $defaults['templates'][$type] ?? self::templateDefaults($type, $meta['label']),
                $stored['templates'][$type] ?? []
            );
        }

        return [
            'revision' => self::sanitizeRevision($stored['revision'] ?? $defaults['revision']),
            'global' => self::mergeGlobal($defaults['global'], $stored['global'] ?? []),
            'templates' => $templates,
        ];
    }

    public static function global(): array
    {
        return self::payload()['global'];
    }

    public static function template(string $type): array
    {
        $payload = self::payload();

        if (!isset($payload['templates'][$type])) {
            throw new \RuntimeException('Unknown form template type: ' . $type);
        }

        return $payload['templates'][$type];
    }

    public static function save(string $type, array $input): array
    {
        $types = self::types();
        if (!isset($types[$type])) {
            throw new \RuntimeException('Unknown form template type: ' . $type);
        }

        $defaults = self::defaults();
        $payload = self::payload();

        $payload['revision'] = date('YmdHis');
        $payload['global'] = self::mergeGlobal($defaults['global'], $input['global'] ?? $payload['global']);
        $payload['templates'][$type] = self::mergeTemplate($type, $defaults['templates'][$type], $input['template'] ?? $payload['templates'][$type]);

        self::write($payload);

        return $payload;
    }

    public static function reset(string $type): array
    {
        $defaults = self::defaults();
        $payload = self::payload();
        $payload['revision'] = date('YmdHis');
        $template = $payload['templates'][$type] ?? [];
        $paper = (string) ($template['paper_size'] ?? ($defaults['templates'][$type]['paper_size'] ?? 'A4'));
        $orientation = (string) ($template['orientation'] ?? ($defaults['templates'][$type]['orientation'] ?? 'portrait'));
        $payload['templates'][$type] = self::templateDefaults($type, self::types()[$type]['label'] ?? 'Document', (bool) ($defaults['templates'][$type]['show_qr'] ?? false), (bool) ($defaults['templates'][$type]['show_summary'] ?? true), (bool) ($defaults['templates'][$type]['show_terms'] ?? true), $paper, $orientation);
        if ($type === 'sales_invoice') {
            $payload['templates'][$type]['show_summary'] = false;
            $payload['templates'][$type]['show_currency_rate'] = false;
        }
        self::write($payload);

        return $payload;
    }

    public static function supportsVisualDesigner(string $type): bool
    {
        return false;
    }

    public static function widgetCatalog(string $type): array
    {
        if (!self::supportsVisualDesigner($type)) {
            return [];
        }

        return [
            'header_brand' => ['label' => 'Company / Title', 'description' => 'Logo, document title, and company information block.'],
            'meta_block' => ['label' => 'Invoice Meta', 'description' => 'Invoice no, date, warehouse, status, currency, and rate.'],
            'qr_block' => ['label' => 'QR Block', 'description' => 'QR code block for invoice scan and quick verification.'],
            'party_block' => ['label' => $type === 'purchase_invoice' ? 'Supplier Block' : 'Bill To Block', 'description' => 'Customer or supplier profile block.'],
            'summary_block' => ['label' => 'Summary Cards', 'description' => 'Headline KPI values such as total, paid, due, or returned.'],
            'items_block' => ['label' => 'Items Table', 'description' => 'Dynamic line-item grid. Keep extra space for multi-line invoices.', 'smart' => true],
            'notes_block' => ['label' => 'Notes / Terms', 'description' => 'Notes and terms area.'],
            'totals_block' => ['label' => 'Totals Panel', 'description' => 'Sub-total, discounts, AED view, and net balance.', 'smart' => true],
            'signatures_block' => ['label' => 'Signature Row', 'description' => 'Prepared by, approved by, and customer or supplier signature blocks.'],
            'footer_block' => ['label' => 'Footer', 'description' => 'Footer text and generated timestamp.'],
        ];
    }

    public static function paperMetrics(string $paper = 'A4', string $orientation = 'portrait'): array
    {
        $paper = self::sanitizePaper($paper);
        $orientation = self::sanitizeOrientation($orientation);

        $sizes = [
            'A4' => ['w' => 210.0, 'h' => 297.0, 'm' => 10.0],
            'A5' => ['w' => 148.0, 'h' => 210.0, 'm' => 8.0],
            'LETTER' => ['w' => 216.0, 'h' => 279.0, 'm' => 10.0],
        ];

        $base = $sizes[$paper] ?? $sizes['A4'];
        $pageWidth = (float) $base['w'];
        $pageHeight = (float) $base['h'];
        if ($orientation === 'landscape') {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }

        $margin = (float) $base['m'];
        $safeWidth = round($pageWidth - ($margin * 2), 2);
        $safeHeight = round($pageHeight - ($margin * 2), 2);

        return [
            'paper' => $paper,
            'orientation' => $orientation,
            'page_width_mm' => $pageWidth,
            'page_height_mm' => $pageHeight,
            'margin_top_mm' => $margin,
            'margin_right_mm' => $margin,
            'margin_bottom_mm' => $margin,
            'margin_left_mm' => $margin,
            'content_width_mm' => $safeWidth,
            'content_height_mm' => $safeHeight,
            'grid_mm' => 1.0,
            'recommended_qr_mm' => $paper === 'A5' ? 24.0 : 30.0,
        ];
    }

    public static function designerPaperCatalog(): array
    {
        $catalog = [];
        foreach (['A4', 'A5', 'LETTER'] as $paper) {
            foreach (['portrait', 'landscape'] as $orientation) {
                $catalog[$paper . '_' . $orientation] = self::paperMetrics($paper, $orientation);
            }
        }
        return $catalog;
    }

    public static function defaultDesignerLayout(string $type, string $paper = 'A4', string $orientation = 'portrait'): array
    {
        if (!self::supportsVisualDesigner($type)) {
            return ['unit' => 'mm', 'widgets' => []];
        }

        $metrics = self::paperMetrics($paper, $orientation);
        $w = $metrics['content_width_mm'];
        $h = $metrics['content_height_mm'];

        if ($metrics['orientation'] === 'landscape') {
            $layout = [
                'header_brand' => ['x' => 0.0, 'y' => 0.0, 'w' => round($w * 0.46, 2), 'h' => 24.0, 'z' => 10],
                'meta_block' => ['x' => round($w * 0.50, 2), 'y' => 0.0, 'w' => round($w * 0.50, 2), 'h' => 24.0, 'z' => 10],
                'qr_block' => ['x' => round($w - max(32.0, $metrics['recommended_qr_mm'] + 6.0), 2), 'y' => 26.0, 'w' => max(32.0, $metrics['recommended_qr_mm'] + 6.0), 'h' => max(26.0, $metrics['recommended_qr_mm'] + 4.0), 'z' => 10],
                'party_block' => ['x' => 0.0, 'y' => 26.0, 'w' => round($w * 0.60, 2), 'h' => 26.0, 'z' => 10],
                'summary_block' => ['x' => round($w * 0.64, 2), 'y' => 26.0, 'w' => round($w * 0.36, 2), 'h' => 20.0, 'z' => 10],
                'items_block' => ['x' => 0.0, 'y' => 58.0, 'w' => $w, 'h' => max(58.0, round($h * 0.36, 2)), 'z' => 10],
                'notes_block' => ['x' => 0.0, 'y' => round($h - 50.0, 2), 'w' => round($w * 0.55, 2), 'h' => 20.0, 'z' => 10],
                'totals_block' => ['x' => round($w * 0.60, 2), 'y' => round($h - 50.0, 2), 'w' => round($w * 0.40, 2), 'h' => 20.0, 'z' => 10],
                'signatures_block' => ['x' => 0.0, 'y' => round($h - 24.0, 2), 'w' => $w, 'h' => 10.0, 'z' => 10],
                'footer_block' => ['x' => 0.0, 'y' => round($h - 10.0, 2), 'w' => $w, 'h' => 6.0, 'z' => 10],
            ];
        } else {
            $layout = [
                'header_brand' => ['x' => 0.0, 'y' => 0.0, 'w' => round($w * 0.52, 2), 'h' => 30.0, 'z' => 10],
                'meta_block' => ['x' => round($w * 0.56, 2), 'y' => 0.0, 'w' => round($w * 0.44, 2), 'h' => 28.0, 'z' => 10],
                'qr_block' => ['x' => round($w * 0.56, 2), 'y' => 31.0, 'w' => round($w * 0.44, 2), 'h' => 24.0, 'z' => 10],
                'party_block' => ['x' => 0.0, 'y' => 60.0, 'w' => $w, 'h' => 35.0, 'z' => 10],
                'summary_block' => ['x' => 0.0, 'y' => 100.0, 'w' => $w, 'h' => 20.0, 'z' => 10],
                'items_block' => ['x' => 0.0, 'y' => 125.0, 'w' => $w, 'h' => max(82.0, round($h * 0.31, 2)), 'z' => 10],
                'notes_block' => ['x' => 0.0, 'y' => round($h - 61.0, 2), 'w' => round($w * 0.54, 2), 'h' => 24.0, 'z' => 10],
                'totals_block' => ['x' => round($w * 0.58, 2), 'y' => round($h - 61.0, 2), 'w' => round($w * 0.42, 2), 'h' => 24.0, 'z' => 10],
                'signatures_block' => ['x' => 0.0, 'y' => round($h - 29.0, 2), 'w' => $w, 'h' => 12.0, 'z' => 10],
                'footer_block' => ['x' => 0.0, 'y' => round($h - 11.0, 2), 'w' => $w, 'h' => 7.0, 'z' => 10],
            ];
        }

        return [
            'unit' => 'mm',
            'paper' => $metrics['paper'],
            'orientation' => $metrics['orientation'],
            'metrics' => $metrics,
            'widgets' => $layout,
        ];
    }

    public static function compiledCss(string $type): string
    {
        $template = self::template($type);
        $accent = self::sanitizeColor((string) ($template['accent_color'] ?? '#111827'), '#111827');
        $paper = (string) ($template['paper_size'] ?? 'A4');
        $orientation = (string) ($template['orientation'] ?? 'portrait');
        $metrics = self::paperMetrics($paper, $orientation);
        $css = [];
        $fontFamily = self::sanitizeFontFamily($template['font_family'] ?? 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif');
        $baseFontSize = self::sanitizeNumber($template['base_font_size'] ?? 13, 13, 10, 18);
        $titleFontSize = self::sanitizeNumber($template['title_font_size'] ?? 22, 22, 16, 36);
        $sectionTitleFontSize = self::sanitizeNumber($template['section_title_font_size'] ?? 16, 16, 12, 26);
        $tableFontSize = self::sanitizeNumber($template['table_font_size'] ?? 13, 13, 9, 18);
        $tableHeadSize = max(9, $tableFontSize - 1);
        $smallTextSize = max(10, $baseFontSize - 1);
        $css[] = ':root{--doc-accent:' . $accent . ';--doc-font-family:' . $fontFamily . ';--doc-base-font-size:' . $baseFontSize . 'px;--doc-title-size:' . $titleFontSize . 'px;--doc-section-title-size:' . $sectionTitleFontSize . 'px;--doc-table-font-size:' . $tableFontSize . 'px;--doc-table-head-font-size:' . $tableHeadSize . 'px;--doc-small-size:' . $smallTextSize . 'px;}';
        $css[] = '.doc-badge,.doc-kpi strong,.doc-section-title::after{background:var(--doc-accent);}';
        $css[] = '.doc-accent-text,.doc-meta strong,.doc-summary strong{color:var(--doc-accent);}';
        $baseSelector = '.document-sheet[data-doc-type="' . $type . '"]';
        $css[] = $baseSelector . '{width:' . $metrics['page_width_mm'] . 'mm;min-height:' . $metrics['page_height_mm'] . 'mm;}';
        $css[] = $baseSelector . ' .doc-inner{padding:' . $metrics['margin_top_mm'] . 'mm ' . $metrics['margin_right_mm'] . 'mm ' . $metrics['margin_bottom_mm'] . 'mm ' . $metrics['margin_left_mm'] . 'mm;min-height:' . $metrics['content_height_mm'] . 'mm;}';

        if (!empty($template['layout_enabled']) && self::supportsVisualDesigner($type)) {
            $css[] = $baseSelector . '[data-doc-layout="free"] .doc-layout-free{position:relative;min-height:' . $metrics['content_height_mm'] . 'mm;}';
            $css[] = $baseSelector . '[data-doc-layout="free"] .doc-widget{position:absolute;box-sizing:border-box;}';
            $css[] = $baseSelector . '[data-doc-layout="free"] .doc-widget .doc-section{margin-top:0;}';
            $css[] = $baseSelector . '[data-doc-layout="free"] .doc-widget .doc-section-title{margin-top:0;}';
            $css[] = $baseSelector . '[data-doc-layout="free"] [data-doc-widget="footer_block"] .doc-footer{margin-top:0;}';
            $css[] = $baseSelector . '[data-doc-layout="free"] [data-doc-widget="signatures_block"] .doc-signatures{height:100%;align-items:end;}';
            foreach (($template['layout']['widgets'] ?? []) as $widget => $settings) {
                $x = self::sanitizeMillimeters($settings['x'] ?? 0, 0.0, 0.0, $metrics['content_width_mm']);
                $y = self::sanitizeMillimeters($settings['y'] ?? 0, 0.0, 0.0, $metrics['content_height_mm']);
                $w = self::sanitizeMillimeters($settings['w'] ?? $metrics['content_width_mm'], $metrics['content_width_mm'], 20.0, $metrics['content_width_mm']);
                $h = self::sanitizeMillimeters($settings['h'] ?? 12, 12.0, 8.0, $metrics['content_height_mm']);
                $z = (int) ($settings['z'] ?? 10);
                $selector = $baseSelector . '[data-doc-layout="free"] [data-doc-widget="' . $widget . '"]';
                $css[] = $selector . '{left:' . $x . 'mm;top:' . $y . 'mm;width:' . $w . 'mm;min-height:' . $h . 'mm;z-index:' . max(1, min(99, $z)) . ';}';
            }
        }

        $customCss = trim((string) ($template['custom_css'] ?? ''));
        if ($customCss !== '') {
            $css[] = $customCss;
        }

        return implode("\n", $css);
    }

    public static function documentMeta(string $type): array
    {
        $types = self::types();
        $payload = self::payload();
        $template = $payload['templates'][$type] ?? self::templateDefaults($type, $types[$type]['label'] ?? $type);
        $paper = (string) ($template['paper_size'] ?? 'A4');
        $orientation = (string) ($template['orientation'] ?? 'portrait');

        return [
            'type' => $type,
            'definition' => $types[$type] ?? ['label' => $type, 'description' => '', 'route' => ''],
            'global' => $payload['global'],
            'template' => $template,
            'revision' => $payload['revision'],
            'visual_designer' => self::supportsVisualDesigner($type),
            'widget_catalog' => self::widgetCatalog($type),
            'default_layout' => self::defaultDesignerLayout($type, $paper, $orientation),
            'paper_metrics' => self::paperMetrics($paper, $orientation),
            'designer_paper_catalog' => self::designerPaperCatalog(),
            'invoice_layout_variants' => self::invoiceLayoutVariants(),
            'invoice_qr_positions' => self::invoiceQrPositions(),
        ];
    }

    private static function templateDefaults(string $type, string $title, bool $showQr = false, bool $showSummary = true, bool $showTerms = true, string $paperSize = 'A4', string $orientation = 'portrait'): array
    {
        $base = [
            'title' => $title,
            'paper_size' => $paperSize,
            'orientation' => $orientation,
            'accent_color' => '#111827',
            'show_logo' => true,
            'show_company_info' => true,
            'show_summary' => $showSummary,
            'show_currency_rate' => true,
            'show_notes' => true,
            'show_terms' => $showTerms,
            'show_signatures' => true,
            'show_qr' => $showQr,
            'show_footer' => true,
            'auto_print' => false,
            'watermark_text' => '',
            'footer_text' => 'Generated from LiVAR Centralized Accounting.',
            'terms_text' => '',
            'custom_css' => '',
            'font_family' => 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'base_font_size' => 13,
            'title_font_size' => 22,
            'section_title_font_size' => 16,
            'table_font_size' => 13,
            'layout_variant' => in_array($type, ['sales_invoice', 'purchase_invoice'], true) ? 'classic' : 'standard',
            'qr_position' => 'header_right',
            'layout_enabled' => false,
            'layout' => self::defaultDesignerLayout($type, $paperSize, $orientation),
        ];

        return $base;
    }

    private static function mergeGlobal(array $defaults, mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $merged = [];
        foreach ($defaults as $key => $fallback) {
            $merged[$key] = self::sanitizeText($input[$key] ?? $fallback, (string) $fallback, 500);
        }

        return $merged;
    }

    private static function mergeTemplate(string $type, array $defaults, mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $paper = self::sanitizePaper($input['paper_size'] ?? $defaults['paper_size']);
        $orientation = self::sanitizeOrientation($input['orientation'] ?? $defaults['orientation']);

        return [
            'title' => self::sanitizeText($input['title'] ?? $defaults['title'], $defaults['title'], 120),
            'paper_size' => $paper,
            'orientation' => $orientation,
            'accent_color' => self::sanitizeColor((string) ($input['accent_color'] ?? $defaults['accent_color']), $defaults['accent_color']),
            'show_logo' => self::sanitizeBool($input['show_logo'] ?? $defaults['show_logo']),
            'show_company_info' => self::sanitizeBool($input['show_company_info'] ?? $defaults['show_company_info']),
            'show_summary' => self::sanitizeBool($input['show_summary'] ?? $defaults['show_summary']),
            'show_currency_rate' => self::sanitizeBool($input['show_currency_rate'] ?? $defaults['show_currency_rate']),
            'show_notes' => self::sanitizeBool($input['show_notes'] ?? $defaults['show_notes']),
            'show_terms' => self::sanitizeBool($input['show_terms'] ?? $defaults['show_terms']),
            'show_signatures' => self::sanitizeBool($input['show_signatures'] ?? $defaults['show_signatures']),
            'show_qr' => self::sanitizeBool($input['show_qr'] ?? $defaults['show_qr']),
            'show_footer' => self::sanitizeBool($input['show_footer'] ?? $defaults['show_footer']),
            'auto_print' => self::sanitizeBool($input['auto_print'] ?? $defaults['auto_print']),
            'watermark_text' => self::sanitizeText($input['watermark_text'] ?? $defaults['watermark_text'], $defaults['watermark_text'], 120),
            'footer_text' => self::sanitizeText($input['footer_text'] ?? $defaults['footer_text'], $defaults['footer_text'], 600),
            'terms_text' => self::sanitizeText($input['terms_text'] ?? $defaults['terms_text'], $defaults['terms_text'], 3000),
            'custom_css' => self::sanitizeCustomCss($input['custom_css'] ?? $defaults['custom_css']),
            'font_family' => self::sanitizeFontFamily($input['font_family'] ?? $defaults['font_family'] ?? 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'),
            'base_font_size' => self::sanitizeNumber($input['base_font_size'] ?? $defaults['base_font_size'] ?? 13, 13, 10, 18),
            'title_font_size' => self::sanitizeNumber($input['title_font_size'] ?? $defaults['title_font_size'] ?? 22, 22, 16, 36),
            'section_title_font_size' => self::sanitizeNumber($input['section_title_font_size'] ?? $defaults['section_title_font_size'] ?? 16, 16, 12, 26),
            'table_font_size' => self::sanitizeNumber($input['table_font_size'] ?? $defaults['table_font_size'] ?? 13, 13, 9, 18),
            'layout_variant' => in_array($type, ['sales_invoice', 'purchase_invoice'], true)
                ? self::sanitizeEnum($input['layout_variant'] ?? $defaults['layout_variant'] ?? 'classic', array_keys(self::invoiceLayoutVariants()), 'classic')
                : 'standard',
            'qr_position' => in_array($type, ['sales_invoice', 'purchase_invoice'], true)
                ? self::sanitizeEnum($input['qr_position'] ?? $defaults['qr_position'] ?? 'header_right', array_keys(self::invoiceQrPositions()), 'header_right')
                : 'header_right',
            'layout_enabled' => false,
            'layout' => self::sanitizeLayout($type, $paper, $orientation, $input['layout_json'] ?? ($input['layout'] ?? []), $defaults['layout'] ?? self::defaultDesignerLayout($type, $paper, $orientation)),
        ];
    }

    private static function sanitizeLayout(string $type, string $paper, string $orientation, mixed $value, array $fallback): array
    {
        $layout = [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $layout = $decoded;
            }
        } elseif (is_array($value)) {
            $layout = $value;
        }

        $metrics = self::paperMetrics($paper, $orientation);
        $fallback = is_array($fallback) ? $fallback : self::defaultDesignerLayout($type, $paper, $orientation);
        $fallbackMetrics = is_array($fallback['metrics'] ?? null) ? $fallback['metrics'] : $metrics;
        $catalog = self::widgetCatalog($type);
        $legacyPercent = strtolower((string) ($layout['unit'] ?? '')) !== 'mm';
        $widgets = [];

        foreach ($catalog as $key => $_meta) {
            $base = $fallback['widgets'][$key] ?? ['x' => 0.0, 'y' => 0.0, 'w' => $metrics['content_width_mm'], 'h' => 12.0, 'z' => 10];
            $incoming = is_array($layout['widgets'][$key] ?? null) ? $layout['widgets'][$key] : [];
            if ($legacyPercent) {
                $widgets[$key] = [
                    'x' => self::sanitizeMillimeters(($fallbackMetrics['content_width_mm'] * self::sanitizePercent($incoming['x'] ?? $base['x'], (float) $base['x'])) / 100, (float) ($base['x'] ?? 0.0), 0.0, $metrics['content_width_mm']),
                    'y' => self::sanitizeMillimeters(($fallbackMetrics['content_height_mm'] * self::sanitizePercent($incoming['y'] ?? $base['y'], (float) $base['y'])) / 100, (float) ($base['y'] ?? 0.0), 0.0, $metrics['content_height_mm']),
                    'w' => self::sanitizeMillimeters(($fallbackMetrics['content_width_mm'] * self::sanitizePercent($incoming['w'] ?? $base['w'], (float) $base['w'], 8.0, 100.0)) / 100, (float) ($base['w'] ?? $metrics['content_width_mm']), 20.0, $metrics['content_width_mm']),
                    'h' => self::sanitizeMillimeters(($fallbackMetrics['content_height_mm'] * self::sanitizePercent($incoming['h'] ?? $base['h'], (float) $base['h'], 3.0, 60.0)) / 100, (float) ($base['h'] ?? 12.0), 8.0, $metrics['content_height_mm']),
                    'z' => max(1, min(99, (int) ($incoming['z'] ?? $base['z'] ?? 10))),
                ];
                continue;
            }

            $x = self::sanitizeMillimeters($incoming['x'] ?? $base['x'], (float) ($base['x'] ?? 0.0), 0.0, $metrics['content_width_mm']);
            $y = self::sanitizeMillimeters($incoming['y'] ?? $base['y'], (float) ($base['y'] ?? 0.0), 0.0, $metrics['content_height_mm']);
            $w = self::sanitizeMillimeters($incoming['w'] ?? $base['w'], (float) ($base['w'] ?? $metrics['content_width_mm']), 20.0, $metrics['content_width_mm']);
            $h = self::sanitizeMillimeters($incoming['h'] ?? $base['h'], (float) ($base['h'] ?? 12.0), 8.0, $metrics['content_height_mm']);
            if ($x + $w > $metrics['content_width_mm']) {
                $x = max(0.0, round($metrics['content_width_mm'] - $w, 2));
            }
            if ($y + $h > $metrics['content_height_mm']) {
                $y = max(0.0, round($metrics['content_height_mm'] - $h, 2));
            }

            $widgets[$key] = [
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'z' => max(1, min(99, (int) ($incoming['z'] ?? $base['z'] ?? 10))),
            ];
        }

        return [
            'unit' => 'mm',
            'paper' => $metrics['paper'],
            'orientation' => $metrics['orientation'],
            'metrics' => $metrics,
            'widgets' => $widgets,
        ];
    }


    private static function sanitizeEnum(mixed $value, array $allowed, string $fallback): string
    {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function sanitizePaper(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['A4', 'A5', 'LETTER'], true) ? $value : 'A4';
    }

    private static function sanitizeOrientation(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['portrait', 'landscape'], true) ? $value : 'portrait';
    }

    private static function sanitizeBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    private static function sanitizeText(mixed $value, string $fallback, int $maxLength): string
    {
        $value = trim((string) $value);
        $value = strip_tags($value);

        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private static function sanitizeColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    private static function sanitizeCustomCss(mixed $value): string
    {
        $css = trim((string) $value);
        if ($css === '') {
            return '';
        }

        $css = str_ireplace(['</style', '<script', '</script'], '', $css);
        return substr($css, 0, 20000);
    }

    private static function sanitizeFontFamily(mixed $value): string
    {
        $allowed = [
            'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'Arial, Helvetica, sans-serif',
            'Tahoma, Arial, sans-serif',
            'Verdana, Geneva, sans-serif',
            'Trebuchet MS, Arial, sans-serif',
            'Georgia, "Times New Roman", serif',
            '"Times New Roman", Times, serif',
        ];

        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private static function sanitizeNumber(mixed $value, int $fallback, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $number = (int) round((float) $value);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    private static function sanitizePercent(mixed $value, float $fallback, float $min = 0.0, float $max = 100.0): float
    {
        if (!is_numeric($value)) {
            return $fallback;
        }
        $number = round((float) $value, 2);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }
        return $number;
    }

    private static function sanitizeMillimeters(mixed $value, float $fallback, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return round($fallback, 2);
        }
        $number = round((float) $value, 2);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }
        return $number;
    }

    private static function sanitizeRevision(mixed $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value) ?: '';
        return $value !== '' ? $value : 'core-default';
    }

    private static function write(array $payload): void
    {
        ensure_directory(storage_path('app'));
        file_put_contents(self::path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function path(): string
    {
        return storage_path(self::FILE);
    }
}
