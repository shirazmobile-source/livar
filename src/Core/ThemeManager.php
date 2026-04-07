<?php

declare(strict_types=1);

namespace App\Core;

final class ThemeManager
{
    private const FILE = 'app/theme-settings.json';

    public static function defaults(): array
    {
        return [
            'revision' => 'core-default',
            'default_mode' => 'dark',
            'shared' => [
                'brand' => '#FF5500',
                'success' => '#00c878',
                'danger' => '#ff5a5a',
                'info' => '#468cff',
                'radius' => '18px',
                'radius_sm' => '14px',
                'btn_h' => '44px',
                'container' => '1100px',
            ],
            'light' => [
                'bg' => '#ffffff',
                'text' => '#111111',
                'muted' => '#666666',
                'label' => '#222222',
                'surface' => '#ffffff',
                'surface_2' => '#fbfbfb',
                'surface_3' => '#f6f6f6',
                'border' => '#e9e9e9',
            ],
            'dark' => [
                'bg' => '#050506',
                'text' => 'rgba(255,255,255,.92)',
                'muted' => 'rgba(255,255,255,.66)',
                'label' => 'rgba(255,255,255,.84)',
                'surface' => 'rgba(255,255,255,.06)',
                'surface_2' => 'rgba(255,255,255,.04)',
                'surface_3' => 'rgba(255,255,255,.03)',
                'border' => 'rgba(255,255,255,.14)',
            ],
            'topbar' => [
                'light' => 'rgba(255,255,255,.86)',
                'dark' => 'rgba(0,0,0,.35)',
            ],
            'custom_css' => '',
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

        return [
            'revision' => self::sanitizeRevision($stored['revision'] ?? $defaults['revision']),
            'default_mode' => self::sanitizeMode($stored['default_mode'] ?? $defaults['default_mode']),
            'shared' => self::mergeSection($defaults['shared'], $stored['shared'] ?? []),
            'light' => self::mergeSection($defaults['light'], $stored['light'] ?? []),
            'dark' => self::mergeSection($defaults['dark'], $stored['dark'] ?? []),
            'topbar' => self::mergeSection($defaults['topbar'], $stored['topbar'] ?? []),
            'custom_css' => self::sanitizeCustomCss($stored['custom_css'] ?? $defaults['custom_css']),
        ];
    }

    public static function currentMode(): string
    {
        return self::payload()['default_mode'];
    }

    public static function revision(): string
    {
        return self::payload()['revision'];
    }

    public static function save(array $input): array
    {
        $defaults = self::defaults();

        $theme = [
            'revision' => date('YmdHis'),
            'default_mode' => self::sanitizeMode($input['default_mode'] ?? $defaults['default_mode']),
            'shared' => self::mergeSection($defaults['shared'], $input['shared'] ?? []),
            'light' => self::mergeSection($defaults['light'], $input['light'] ?? []),
            'dark' => self::mergeSection($defaults['dark'], $input['dark'] ?? []),
            'topbar' => self::mergeSection($defaults['topbar'], $input['topbar'] ?? []),
            'custom_css' => self::sanitizeCustomCss($input['custom_css'] ?? $defaults['custom_css']),
        ];

        self::write($theme);

        return $theme;
    }

    public static function reset(): array
    {
        $theme = self::defaults();
        $theme['revision'] = date('YmdHis');
        self::write($theme);

        return $theme;
    }

    public static function compiledCss(): string
    {
        $theme = self::payload();
        $css = [];

        $css[] = ':root{' . self::compileVariables(array_merge(self::sharedVariables($theme['shared']), self::paletteVariables($theme['light']))) . '}';
        $css[] = 'html[data-theme="dark"]{' . self::compileVariables(array_merge(self::sharedVariables($theme['shared']), self::paletteVariables($theme['dark']))) . '}';
        $css[] = ':root{color-scheme:light;}';
        $css[] = 'html[data-theme="dark"]{color-scheme:dark;}';
        $css[] = '.topbar{background:' . self::cssValue($theme['topbar']['light'] ?? self::defaults()['topbar']['light']) . ' !important;}';
        $css[] = 'html[data-theme="dark"] .topbar{background:' . self::cssValue($theme['topbar']['dark'] ?? self::defaults()['topbar']['dark']) . ' !important;}';
        $css[] = 'html[data-theme="dark"] select{background:' . self::cssValue($theme['dark']['bg'] ?? self::defaults()['dark']['bg']) . ';color:' . self::cssValue($theme['dark']['text'] ?? self::defaults()['dark']['text']) . ';border-color:' . self::cssValue($theme['dark']['border'] ?? self::defaults()['dark']['border']) . ';}';
        $css[] = 'html[data-theme="dark"] select option,html[data-theme="dark"] select optgroup{background:' . self::cssValue($theme['dark']['bg'] ?? self::defaults()['dark']['bg']) . ' !important;color:' . self::cssValue($theme['dark']['text'] ?? self::defaults()['dark']['text']) . ' !important;}';
        $css[] = 'html[data-theme="dark"] select option:checked{background:' . self::cssValue($theme['shared']['brand'] ?? self::defaults()['shared']['brand']) . ' !important;color:#000 !important;}';

        $customCss = trim((string) ($theme['custom_css'] ?? ''));
        if ($customCss !== '') {
            $css[] = $customCss;
        }

        return implode("
", $css);
    }

    private static function sharedVariables(array $section): array
    {
        return [
            '--brand' => $section['brand'] ?? self::defaults()['shared']['brand'],
            '--success' => $section['success'] ?? self::defaults()['shared']['success'],
            '--danger' => $section['danger'] ?? self::defaults()['shared']['danger'],
            '--info' => $section['info'] ?? self::defaults()['shared']['info'],
            '--radius' => $section['radius'] ?? self::defaults()['shared']['radius'],
            '--radius-sm' => $section['radius_sm'] ?? self::defaults()['shared']['radius_sm'],
            '--btn-h' => $section['btn_h'] ?? self::defaults()['shared']['btn_h'],
            '--container' => $section['container'] ?? self::defaults()['shared']['container'],
        ];
    }

    private static function paletteVariables(array $section): array
    {
        return [
            '--bg' => $section['bg'] ?? '',
            '--text' => $section['text'] ?? '',
            '--muted' => $section['muted'] ?? '',
            '--label' => $section['label'] ?? '',
            '--surface' => $section['surface'] ?? '',
            '--surface-2' => $section['surface_2'] ?? '',
            '--surface-3' => $section['surface_3'] ?? '',
            '--border' => $section['border'] ?? '',
        ];
    }

    private static function compileVariables(array $variables): string
    {
        $compiled = [];
        foreach ($variables as $name => $value) {
            $compiled[] = $name . ':' . self::cssValue((string) $value) . ';';
        }

        return implode('', $compiled);
    }

    private static function mergeSection(array $defaults, mixed $section): array
    {
        $merged = $defaults;
        if (!is_array($section)) {
            return $merged;
        }

        foreach ($defaults as $key => $fallback) {
            if (!array_key_exists($key, $section)) {
                continue;
            }

            $merged[$key] = self::sanitizeToken($section[$key], (string) $fallback);
        }

        return $merged;
    }

    private static function sanitizeMode(mixed $value): string
    {
        return in_array($value, ['light', 'dark'], true) ? (string) $value : self::defaults()['default_mode'];
    }

    private static function sanitizeRevision(mixed $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value) ?: '';
        return $value !== '' ? $value : 'core-default';
    }

    private static function sanitizeToken(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 120) {
            return $fallback;
        }

        if (!preg_match('/^[#(),.%\sa-zA-Z0-9:+\-\/]+$/', $value)) {
            return $fallback;
        }

        return $value;
    }

    private static function sanitizeCustomCss(mixed $value): string
    {
        $css = trim((string) $value);
        if ($css === '') {
            return '';
        }

        $css = str_ireplace(['</style', '<script', '</script'], '', $css);
        return substr($css, 0, 12000);
    }

    private static function cssValue(string $value): string
    {
        return trim($value);
    }

    private static function write(array $theme): void
    {
        \ensure_directory(\storage_path('app'));
        file_put_contents(self::path(), json_encode($theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function path(): string
    {
        return \storage_path(self::FILE);
    }
}
