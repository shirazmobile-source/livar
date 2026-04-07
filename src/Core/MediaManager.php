<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class MediaManager
{
    /** @var array<string, array{table:string,column:string,label:string,url:string,mode:string,record_label?:string}> */
    private array $linkSources = [
        'customer_profile' => [
            'table' => 'customers',
            'column' => 'profile_image_path',
            'label' => 'Customer Profile',
            'url' => '/customers/edit?id=%d',
            'mode' => 'field',
            'record_label' => 'COALESCE(NULLIF(name, ""), NULLIF(company_name, ""), NULLIF(person_name, ""), code)',
        ],
        'customer_document' => [
            'table' => 'customer_documents',
            'column' => 'file_path',
            'label' => 'Customer Document',
            'url' => '/customers/edit?id=%d',
            'mode' => 'document',
        ],
        'product_image' => [
            'table' => 'products',
            'column' => 'image_path',
            'label' => 'Product Item',
            'url' => '/products/edit?id=%d',
            'mode' => 'field',
            'record_label' => 'COALESCE(NULLIF(name, ""), code)',
        ],
        'category_image' => [
            'table' => 'categories',
            'column' => 'image_path',
            'label' => 'Product Category',
            'url' => '/products/categories/edit?id=%d',
            'mode' => 'field',
            'record_label' => 'name',
        ],
    ];

    public function __construct()
    {
        $this->ensureMetadataTable();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMedia(array $filters = []): array
    {
        $files = $this->scanFiles();
        $metadata = $this->metadataMap();
        $linkMap = $this->linkMap();

        $items = [];
        foreach ($files as $path => $info) {
            $meta = $metadata[$path] ?? [
                'title' => '',
                'alt_text' => '',
                'notes' => '',
                'created_at' => null,
                'updated_at' => null,
            ];
            $links = $linkMap[$path] ?? [];
            $record = array_merge($info, [
                'path' => $path,
                'public_url' => public_upload_url($path),
                'title' => (string) ($meta['title'] ?? ''),
                'alt_text' => (string) ($meta['alt_text'] ?? ''),
                'notes' => (string) ($meta['notes'] ?? ''),
                'meta_created_at' => $meta['created_at'] ?? null,
                'meta_updated_at' => $meta['updated_at'] ?? null,
                'links' => $links,
                'linked_count' => count($links),
                'is_linked' => $links !== [],
            ]);

            if (!$this->matchesFilters($record, $filters)) {
                continue;
            }

            $items[] = $record;
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['modified_ts'] ?? 0) <=> ($a['modified_ts'] ?? 0);
        });

        return $items;
    }

    /**
     * @return array<string, int>
     */
    public function summary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'linked' => 0,
            'unlinked' => 0,
            'images' => 0,
            'documents' => 0,
        ];

        foreach ($items as $item) {
            if (!empty($item['is_linked'])) {
                $summary['linked']++;
            } else {
                $summary['unlinked']++;
            }

            if (($item['group'] ?? '') === 'image') {
                $summary['images']++;
            } elseif (($item['group'] ?? '') === 'document') {
                $summary['documents']++;
            }
        }

        return $summary;
    }

    public function find(string $path): ?array
    {
        $path = $this->normalizeRelativePath($path);
        if ($path === '') {
            return null;
        }

        foreach ($this->listMedia() as $item) {
            if (($item['path'] ?? '') === $path) {
                return $item;
            }
        }

        return null;
    }

    public function saveMetadata(string $path, array $input): void
    {
        $path = $this->normalizeRelativePath($path);
        $absolutePath = $this->absolutePath($path);

        if (!is_file($absolutePath)) {
            throw new RuntimeException('The selected media file was not found.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        $altText = trim((string) ($input['alt_text'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        $statement = Database::connection()->prepare('
            INSERT INTO media_library (path, title, alt_text, notes, created_at, updated_at)
            VALUES (:path, :title, :alt_text, :notes, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                alt_text = VALUES(alt_text),
                notes = VALUES(notes),
                updated_at = NOW()
        ');
        $statement->execute([
            'path' => $path,
            'title' => $title,
            'alt_text' => $altText,
            'notes' => $notes,
        ]);
    }

    public function delete(string $path): array
    {
        $path = $this->normalizeRelativePath($path);
        $absolutePath = $this->absolutePath($path);

        if (!is_file($absolutePath)) {
            throw new RuntimeException('The selected media file was not found on disk.');
        }

        $links = $this->linkMap()[$path] ?? [];
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            foreach ($links as $link) {
                $this->detachLink($pdo, $link, $path);
            }

            $deleteMeta = $pdo->prepare('DELETE FROM media_library WHERE path = :path');
            $deleteMeta->execute(['path' => $path]);

            if (is_file($absolutePath) && !@unlink($absolutePath)) {
                throw new RuntimeException('The media file could not be deleted. Please check folder permissions.');
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'path' => $path,
            'links_detached' => count($links),
        ];
    }

    private function detachLink(PDO $pdo, array $link, string $path): void
    {
        $type = (string) ($link['source_type'] ?? '');

        switch ($type) {
            case 'customer_profile':
                $statement = $pdo->prepare('UPDATE customers SET profile_image_path = NULL, updated_at = NOW() WHERE id = :id AND profile_image_path = :path');
                $statement->execute(['id' => (int) ($link['record_id'] ?? 0), 'path' => $path]);
                return;

            case 'product_image':
                $statement = $pdo->prepare('UPDATE products SET image_path = NULL, updated_at = NOW() WHERE id = :id AND image_path = :path');
                $statement->execute(['id' => (int) ($link['record_id'] ?? 0), 'path' => $path]);
                return;

            case 'category_image':
                $statement = $pdo->prepare('UPDATE categories SET image_path = NULL, updated_at = NOW() WHERE id = :id AND image_path = :path');
                $statement->execute(['id' => (int) ($link['record_id'] ?? 0), 'path' => $path]);
                return;

            case 'customer_document':
                $statement = $pdo->prepare('DELETE FROM customer_documents WHERE id = :id AND file_path = :path');
                $statement->execute(['id' => (int) ($link['document_id'] ?? 0), 'path' => $path]);
                return;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function scanFiles(): array
    {
        $uploadsRoot = public_upload_path();
        ensure_directory($uploadsRoot);

        $items = [];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $file->getPathname());
            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen(str_replace('\\', '/', public_path())) + 1));

            if (!str_starts_with($relativePath, 'uploads/')) {
                $relativePath = 'uploads/' . ltrim($relativePath, '/');
            }

            $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) {
                continue;
            }

            $group = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) ? 'image' : 'document';
            $mime = $group === 'image' ? 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension) : 'application/pdf';
            $dimensions = null;

            if ($group === 'image' && function_exists('getimagesize')) {
                $size = @getimagesize($absolutePath);
                if (is_array($size) && isset($size[0], $size[1])) {
                    $dimensions = $size[0] . ' × ' . $size[1];
                    if (!empty($size['mime'])) {
                        $mime = (string) $size['mime'];
                    }
                }
            }

            $items[$relativePath] = [
                'file_name' => basename($relativePath),
                'directory' => trim((string) dirname($relativePath), '.'),
                'extension' => $extension,
                'group' => $group,
                'mime_type' => $mime,
                'size_bytes' => (int) $file->getSize(),
                'size_label' => format_bytes((int) $file->getSize()),
                'modified_at' => date('Y-m-d H:i:s', (int) $file->getMTime()),
                'modified_ts' => (int) $file->getMTime(),
                'dimensions' => $dimensions,
                'is_image' => $group === 'image',
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function metadataMap(): array
    {
        if (!$this->tableExists('media_library')) {
            return [];
        }

        $rows = Database::connection()->query('SELECT path, title, alt_text, notes, created_at, updated_at FROM media_library')->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row['path']] = $row;
        }

        return $map;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function linkMap(): array
    {
        $map = [];
        $pdo = Database::connection();

        if ($this->tableExists('customers')) {
            $statement = $pdo->query('
                SELECT
                    profile_image_path AS path,
                    id AS record_id,
                    COALESCE(NULLIF(name, ""), NULLIF(company_name, ""), NULLIF(person_name, ""), code) AS record_label
                FROM customers
                WHERE profile_image_path IS NOT NULL AND profile_image_path <> ""
            ');

            foreach ($statement->fetchAll() as $row) {
                $path = (string) ($row['path'] ?? '');
                $map[$path][] = [
                    'source_type' => 'customer_profile',
                    'source_label' => 'Customer Profile',
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'record_label' => (string) ($row['record_label'] ?? 'Customer'),
                    'record_url' => base_url('/customers/edit?id=' . (int) ($row['record_id'] ?? 0)),
                ];
            }
        }

        if ($this->tableExists('customer_documents') && $this->tableExists('customers')) {
            $statement = $pdo->query('
                SELECT
                    d.id AS document_id,
                    d.file_path AS path,
                    d.original_name,
                    d.file_ext,
                    d.customer_id AS record_id,
                    COALESCE(NULLIF(c.name, ""), NULLIF(c.company_name, ""), NULLIF(c.person_name, ""), c.code) AS record_label
                FROM customer_documents d
                INNER JOIN customers c ON c.id = d.customer_id
                WHERE d.file_path IS NOT NULL AND d.file_path <> ""
            ');

            foreach ($statement->fetchAll() as $row) {
                $path = (string) ($row['path'] ?? '');
                $map[$path][] = [
                    'source_type' => 'customer_document',
                    'source_label' => 'Customer Document',
                    'document_id' => (int) ($row['document_id'] ?? 0),
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'record_label' => (string) ($row['record_label'] ?? 'Customer'),
                    'document_name' => (string) ($row['original_name'] ?? basename($path)),
                    'record_url' => base_url('/customers/edit?id=' . (int) ($row['record_id'] ?? 0)),
                ];
            }
        }

        if ($this->tableExists('products')) {
            $statement = $pdo->query('
                SELECT
                    image_path AS path,
                    id AS record_id,
                    COALESCE(NULLIF(name, ""), code) AS record_label
                FROM products
                WHERE image_path IS NOT NULL AND image_path <> ""
            ');

            foreach ($statement->fetchAll() as $row) {
                $path = (string) ($row['path'] ?? '');
                $map[$path][] = [
                    'source_type' => 'product_image',
                    'source_label' => 'Product Item',
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'record_label' => (string) ($row['record_label'] ?? 'Product'),
                    'record_url' => base_url('/products/edit?id=' . (int) ($row['record_id'] ?? 0)),
                ];
            }
        }

        if ($this->tableExists('categories')) {
            $statement = $pdo->query('
                SELECT
                    image_path AS path,
                    id AS record_id,
                    name AS record_label
                FROM categories
                WHERE image_path IS NOT NULL AND image_path <> ""
            ');

            foreach ($statement->fetchAll() as $row) {
                $path = (string) ($row['path'] ?? '');
                $map[$path][] = [
                    'source_type' => 'category_image',
                    'source_label' => 'Product Category',
                    'record_id' => (int) ($row['record_id'] ?? 0),
                    'record_label' => (string) ($row['record_label'] ?? 'Category'),
                    'record_url' => base_url('/products/categories/edit?id=' . (int) ($row['record_id'] ?? 0)),
                ];
            }
        }

        return $map;
    }

    private function matchesFilters(array $item, array $filters): bool
    {
        $search = strtolower(trim((string) ($filters['q'] ?? '')));
        $linkFilter = (string) ($filters['link'] ?? 'all');
        $typeFilter = (string) ($filters['type'] ?? 'all');

        if ($search !== '') {
            $haystack = [
                strtolower((string) ($item['file_name'] ?? '')),
                strtolower((string) ($item['path'] ?? '')),
                strtolower((string) ($item['title'] ?? '')),
                strtolower((string) ($item['notes'] ?? '')),
                strtolower((string) ($item['mime_type'] ?? '')),
            ];

            foreach ((array) ($item['links'] ?? []) as $link) {
                $haystack[] = strtolower((string) ($link['source_label'] ?? ''));
                $haystack[] = strtolower((string) ($link['record_label'] ?? ''));
                $haystack[] = strtolower((string) ($link['document_name'] ?? ''));
            }

            if (!str_contains(implode(' | ', $haystack), $search)) {
                return false;
            }
        }

        if ($linkFilter === 'linked' && empty($item['is_linked'])) {
            return false;
        }

        if ($linkFilter === 'unlinked' && !empty($item['is_linked'])) {
            return false;
        }

        if ($typeFilter !== 'all' && ($item['group'] ?? 'other') !== $typeFilter) {
            return false;
        }

        return true;
    }

    private function absolutePath(string $relativePath): string
    {
        return public_path($relativePath);
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        if (!str_starts_with($path, 'uploads/')) {
            throw new RuntimeException('Only public upload files can be managed from the media library.');
        }

        if (str_contains($path, '../')) {
            throw new RuntimeException('Invalid media path.');
        }

        return $path;
    }

    private function ensureMetadataTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS media_library (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                path VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(190) NULL,
                alt_text VARCHAR(255) NULL,
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_media_library_path (path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $statement = Database::connection()->prepare('SHOW TABLES LIKE :table');
        $statement->execute(['table' => $table]);

        return $cache[$table] = (bool) $statement->fetchColumn();
    }
}
