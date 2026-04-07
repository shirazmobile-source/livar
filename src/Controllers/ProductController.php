<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use PDO;
use PDOException;
use RuntimeException;

final class ProductController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('products');

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'category_id' => (int) ($_GET['category_id'] ?? 0),
            'unit_id' => (int) ($_GET['unit_id'] ?? 0),
            'currency_id' => (int) ($_GET['currency_id'] ?? 0),
            'stock' => trim((string) ($_GET['stock'] ?? '')),
            'item_type' => trim((string) ($_GET['item_type'] ?? '')),
        ];

        $pdo = Database::connection();
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(p.name LIKE :search OR p.code LIKE :search OR COALESCE(c.name, p.category) LIKE :search OR COALESCE(u.name, p.unit) LIKE :search OR COALESCE(cur.code, p.price_currency_code) LIKE :search)';
            $params['search'] = '%' . $filters['q'] . '%';
        }

        if ($filters['status'] !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if ($filters['category_id'] > 0) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if ($filters['unit_id'] > 0) {
            $where[] = 'p.unit_id = :unit_id';
            $params['unit_id'] = $filters['unit_id'];
        }

        if ($filters['currency_id'] > 0) {
            $where[] = 'p.currency_id = :currency_id';
            $params['currency_id'] = $filters['currency_id'];
        }

        if ($filters['item_type'] !== '' && in_array($filters['item_type'], ['inventory', 'non_inventory', 'service'], true)) {
            $where[] = 'COALESCE(p.item_type, "inventory") = :item_type';
            $params['item_type'] = $filters['item_type'];
        }

        if ($filters['stock'] === 'low') {
            $where[] = 'COALESCE(p.item_type, "inventory") = "inventory"';
            $where[] = 'p.current_stock <= p.min_stock AND p.current_stock > 0';
        } elseif ($filters['stock'] === 'out') {
            $where[] = 'COALESCE(p.item_type, "inventory") = "inventory"';
            $where[] = 'p.current_stock <= 0';
        } elseif ($filters['stock'] === 'healthy') {
            $where[] = 'COALESCE(p.item_type, "inventory") = "inventory"';
            $where[] = 'p.current_stock > p.min_stock';
        }

        $sql = '
            SELECT
                p.*,
                COALESCE(c.name, p.category, "—") AS category_label,
                COALESCE(u.name, p.unit, "—") AS unit_label,
                COALESCE(cur.name, "UAE Dirham") AS currency_name,
                COALESCE(cur.code, p.price_currency_code, "AED") AS currency_code,
                COALESCE(cur.symbol, "د.إ") AS currency_symbol,
                COALESCE(cur.rate_to_aed, 1.00000000) AS rate_to_aed,
                COALESCE(p.item_type, "inventory") AS item_type
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units u ON u.id = p.unit_id
            LEFT JOIN currencies cur ON cur.id = p.currency_id
        ';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.id DESC';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $products = $statement->fetchAll(PDO::FETCH_ASSOC);

        $summary = $pdo->query('
            SELECT
                COUNT(*) AS total_items,
                SUM(CASE WHEN COALESCE(item_type, "inventory") = "inventory" AND current_stock <= min_stock THEN 1 ELSE 0 END) AS low_stock_items,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active_items,
                SUM(CASE WHEN COALESCE(item_type, "inventory") = "inventory" AND current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock_items
            FROM products
        ')->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->render('products/index', [
            'title' => 'Products / Items',
            'products' => $products,
            'filters' => $filters,
            'categories' => $this->listCategories(),
            'units' => $this->listUnits(),
            'currencies' => $this->listCurrencies(),
            'summary' => $summary,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('products');

        $currency = $this->aedCurrency() ?? $this->defaultCurrency();
        $generatedCode = $this->generateUniqueProductCode();

        $this->render('products/form', [
            'title' => 'Products / Items / New Item',
            'action' => '/products/store',
            'generatedCode' => $generatedCode,
            'product' => [
                'code' => $generatedCode,
                'name' => '',
                'category_id' => 0,
                'unit_id' => 0,
                'item_type' => 'inventory',
                'currency_id' => (int) ($currency['id'] ?? 0),
                'purchase_price_display' => '0.00',
                'sale_price_display' => '0.00',
                'purchase_price' => '0.00',
                'sale_price' => '0.00',
                'carton_length_cm' => '0',
                'carton_width_cm' => '0',
                'carton_height_cm' => '0',
                'gross_weight_kg' => '0',
                'cbm_per_carton' => '0.000000',
                'units_per_box' => '1',
                'min_stock' => '0',
                'current_stock' => '0',
                'status' => 'active',
                'image_path' => '',
            ],
            'categories' => $this->listCategories(),
            'units' => $this->listUnits(),
            'currencies' => $this->listCurrencies(),
            'currencyRates' => $this->currencyRates(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $input = $this->normalizeProductInput($_POST);
        $generatedSeed = trim((string) ($_POST['generated_code_seed'] ?? ''));
        $codeWasAutoGenerated = false;

        if ($input['code'] === '') {
            $input['code'] = $this->generateUniqueProductCode();
            $codeWasAutoGenerated = true;
        } elseif ($generatedSeed !== '' && $input['code'] === $generatedSeed) {
            $codeWasAutoGenerated = true;
            if ($this->existsByField('products', 'code', $input['code'])) {
                $input['code'] = $this->generateUniqueProductCode();
            }
        }

        with_old($input);

        $errors = $this->validateProduct($input);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/create');
        }

        $meta = $this->resolveProductMeta($input);

        try {
            $imagePath = $this->handleUpload($_FILES['image'] ?? null, 'products', 'prd');
        } catch (RuntimeException $exception) {
            validation_errors(['image' => [$exception->getMessage()]]);
            $this->redirect('/products/create');
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('
            INSERT INTO products (
                code, name, item_type, category_id, category, unit_id, unit, currency_id, price_currency_code,
                image_path, purchase_price_display, sale_price_display, purchase_price, sale_price,
                carton_length_cm, carton_width_cm, carton_height_cm, gross_weight_kg, cbm_per_carton, units_per_box,
                min_stock, current_stock, status, created_at, updated_at
            ) VALUES (
                :code, :name, :item_type, :category_id, :category, :unit_id, :unit, :currency_id, :price_currency_code,
                :image_path, :purchase_price_display, :sale_price_display, :purchase_price, :sale_price,
                :carton_length_cm, :carton_width_cm, :carton_height_cm, :gross_weight_kg, :cbm_per_carton, :units_per_box,
                :min_stock, :current_stock, :status, NOW(), NOW()
            )
        ');

        $inserted = false;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $statement->execute($meta + [
                    'code' => $input['code'],
                    'name' => $input['name'],
                    'item_type' => $input['item_type'],
                    'image_path' => $imagePath,
                    'purchase_price_display' => $input['purchase_price_display'],
                    'sale_price_display' => $input['sale_price_display'],
                    'purchase_price' => $input['purchase_price'],
                    'sale_price' => $input['sale_price'],
                    'carton_length_cm' => $input['carton_length_cm'],
                    'carton_width_cm' => $input['carton_width_cm'],
                    'carton_height_cm' => $input['carton_height_cm'],
                    'gross_weight_kg' => $input['gross_weight_kg'],
                    'cbm_per_carton' => $input['cbm_per_carton'],
                    'units_per_box' => $input['units_per_box'],
                    'min_stock' => $input['min_stock'],
                    'current_stock' => $input['current_stock'],
                    'status' => $input['status'],
                ]);
                $inserted = true;
                break;
            } catch (PDOException $exception) {
                if (!$codeWasAutoGenerated || $attempt === 4) {
                    remove_public_file($imagePath);
                    validation_errors(['code' => ['The product code already exists. Please enter another code and try again.']]);
                    $this->redirect('/products/create');
                }

                $input['code'] = $this->generateUniqueProductCode();
                with_old($input);
            }
        }

        if (!$inserted) {
            remove_public_file($imagePath);
            validation_errors(['code' => ['The product code could not be generated. Please try again.']]);
            $this->redirect('/products/create');
        }

        $productId = (int) $pdo->lastInsertId();

        if ($input['item_type'] === 'inventory' && (float) $input['current_stock'] > 0) {
            $movement = $pdo->prepare('
                INSERT INTO stock_movements (
                    product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at
                ) VALUES (
                    :product_id, "adjustment", "product", :ref_id, :qty_in, 0, :balance_after, :note, NOW(), NOW()
                )
            ');
            $movement->execute([
                'product_id' => $productId,
                'ref_id' => $productId,
                'qty_in' => (float) $input['current_stock'],
                'balance_after' => (float) $input['current_stock'],
                'note' => 'Opening stock',
            ]);
        }

        clear_old();
        $this->redirect('/products', 'Item created successfully.');
    }

    public function edit(): void
    {
        $this->requirePermission('products');

        $product = $this->findProduct((int) ($_GET['id'] ?? 0));
        if (!$product) {
            $this->redirect('/products', null, 'Item not found.');
        }

        $this->render('products/form', [
            'title' => 'Products / Items / Edit Item',
            'action' => '/products/update?id=' . (int) $product['id'],
            'product' => $product,
            'categories' => $this->listCategories(true),
            'units' => $this->listUnits(true),
            'currencies' => $this->listCurrencies(true),
            'currencyRates' => $this->currencyRates(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findProduct($id);
        if (!$existing) {
            $this->redirect('/products', null, 'Item not found.');
        }

        $input = $this->normalizeProductInput($_POST, $existing);
        with_old($input);

        $errors = $this->validateProduct($input, $id);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/edit?id=' . $id);
        }

        $meta = $this->resolveProductMeta($input);

        try {
            $imagePath = $this->handleUpload($_FILES['image'] ?? null, 'products', 'prd', (string) ($existing['image_path'] ?? ''));
        } catch (RuntimeException $exception) {
            validation_errors(['image' => [$exception->getMessage()]]);
            $this->redirect('/products/edit?id=' . $id);
        }

        if (isset($_POST['remove_image']) && (string) $_POST['remove_image'] === '1') {
            remove_public_file((string) ($existing['image_path'] ?? ''));
            $imagePath = '';
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('
            UPDATE products
            SET code = :code,
                name = :name,
                item_type = :item_type,
                category_id = :category_id,
                category = :category,
                unit_id = :unit_id,
                unit = :unit,
                currency_id = :currency_id,
                price_currency_code = :price_currency_code,
                image_path = :image_path,
                purchase_price_display = :purchase_price_display,
                sale_price_display = :sale_price_display,
                purchase_price = :purchase_price,
                sale_price = :sale_price,
                carton_length_cm = :carton_length_cm,
                carton_width_cm = :carton_width_cm,
                carton_height_cm = :carton_height_cm,
                gross_weight_kg = :gross_weight_kg,
                cbm_per_carton = :cbm_per_carton,
                units_per_box = :units_per_box,
                min_stock = :min_stock,
                current_stock = :current_stock,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ');

        try {
            $statement->execute($meta + [
                'id' => $id,
                'code' => $input['code'],
                'name' => $input['name'],
                'item_type' => $input['item_type'],
                'image_path' => $imagePath,
                'purchase_price_display' => $input['purchase_price_display'],
                'sale_price_display' => $input['sale_price_display'],
                'purchase_price' => $input['purchase_price'],
                'sale_price' => $input['sale_price'],
                'carton_length_cm' => $input['carton_length_cm'],
                'carton_width_cm' => $input['carton_width_cm'],
                'carton_height_cm' => $input['carton_height_cm'],
                'gross_weight_kg' => $input['gross_weight_kg'],
                'cbm_per_carton' => $input['cbm_per_carton'],
                'units_per_box' => $input['units_per_box'],
                'min_stock' => $input['min_stock'],
                'current_stock' => $input['current_stock'],
                'status' => $input['status'],
            ]);
        } catch (PDOException $exception) {
            validation_errors(['code' => ['This product code is already in use.']]);
            $this->redirect('/products/edit?id=' . $id);
        }

        $newStock = (float) $input['current_stock'];
        $oldStock = (float) ($existing['current_stock'] ?? 0);

        if ($input['item_type'] === 'inventory' && abs($newStock - $oldStock) > 0.0001) {
            $movement = $pdo->prepare('
                INSERT INTO stock_movements (
                    product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at
                ) VALUES (
                    :product_id, "adjustment", "product", :ref_id, :qty_in, :qty_out, :balance_after, :note, NOW(), NOW()
                )
            ');

            $movement->execute([
                'product_id' => $id,
                'ref_id' => $id,
                'qty_in' => $newStock > $oldStock ? $newStock - $oldStock : 0,
                'qty_out' => $oldStock > $newStock ? $oldStock - $newStock : 0,
                'balance_after' => $newStock,
                'note' => 'Manual stock adjustment',
            ]);
        }

        clear_old();
        $this->redirect('/products', 'Item updated successfully.');
    }


    public function delete(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $product = $this->findProduct((int) ($_GET['id'] ?? 0));
        if (!$product) {
            $this->redirect('/products', null, 'Item not found.');
        }

        $productId = (int) $product['id'];
        $purchaseRefs = $this->countReferences('SELECT COUNT(*) FROM purchase_items WHERE product_id = :id', $productId);
        $saleRefs = $this->countReferences('SELECT COUNT(*) FROM sale_items WHERE product_id = :id', $productId);
        $movementRefs = $this->countReferences('SELECT COUNT(*) FROM stock_movements WHERE product_id = :id', $productId);

        if (($purchaseRefs + $saleRefs + $movementRefs) > 0) {
            $this->redirect('/products/edit?id=' . $productId, null, 'This item is already used in purchases, sales, or stock history and cannot be deleted. Set it to inactive instead.');
        }

        Database::connection()->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
        remove_public_file((string) ($product['image_path'] ?? ''));

        $this->redirect('/products', 'Item deleted successfully.');
    }

    public function qr(): void
    {
        $this->requirePermission('products');

        $product = $this->findProduct((int) ($_GET['id'] ?? 0));
        if (!$product) {
            $this->redirect('/products', null, 'Item not found.');
        }

        $payload = implode(' | ', array_filter([
            $product['code'] ?? null,
            $product['name'] ?? null,
            $product['category_label'] ?? null,
        ]));

        $this->render('products/qr', [
            'title' => 'Products / Items / QR Label',
            'product' => $product,
            'payload' => $payload,
        ]);
    }

    public function categories(): void
    {
        $this->requirePermission('products');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();
        if ($search !== '') {
            $statement = $pdo->prepare('
                SELECT c.*, COUNT(p.id) AS items_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id
                WHERE c.name LIKE :search OR c.slug LIKE :search
                GROUP BY c.id
                ORDER BY c.name ASC
            ');
            $statement->execute(['search' => '%' . $search . '%']);
            $categories = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categories = $pdo->query('
                SELECT c.*, COUNT(p.id) AS items_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id
                GROUP BY c.id
                ORDER BY c.name ASC
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('products/categories/index', [
            'title' => 'Products / Category',
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function createCategory(): void
    {
        $this->requirePermission('products');

        $this->render('products/categories/form', [
            'title' => 'Products / Category / New Category',
            'action' => '/products/categories/store',
            'category' => [
                'name' => '',
                'slug' => '',
                'status' => 'active',
                'image_path' => '',
            ],
        ]);
    }

    public function storeCategory(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => slugify((string) ($_POST['slug'] ?? ($_POST['name'] ?? ''))),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:120'],
            'slug' => ['required', 'max:150'],
            'status' => ['required', 'max:20'],
        ]);

        if ($this->existsByField('categories', 'name', $input['name'])) {
            $errors['name'][] = 'This category name already exists.';
        }
        if ($this->existsByField('categories', 'slug', $input['slug'])) {
            $errors['slug'][] = 'This category slug already exists.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/categories/create');
        }

        try {
            $imagePath = $this->handleUpload($_FILES['image'] ?? null, 'categories', 'cat');
        } catch (RuntimeException $exception) {
            validation_errors(['image' => [$exception->getMessage()]]);
            $this->redirect('/products/categories/create');
        }

        Database::connection()->prepare('
            INSERT INTO categories (name, slug, image_path, status, created_at, updated_at)
            VALUES (:name, :slug, :image_path, :status, NOW(), NOW())
        ')->execute($input + ['image_path' => $imagePath]);

        clear_old();
        $this->redirect('/products/categories', 'Category created successfully.');
    }

    public function editCategory(): void
    {
        $this->requirePermission('products');

        $category = $this->findCategory((int) ($_GET['id'] ?? 0));
        if (!$category) {
            $this->redirect('/products/categories', null, 'Category not found.');
        }

        $this->render('products/categories/form', [
            'title' => 'Products / Category / Edit Category',
            'action' => '/products/categories/update?id=' . (int) $category['id'],
            'category' => $category,
        ]);
    }

    public function updateCategory(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findCategory($id);
        if (!$existing) {
            $this->redirect('/products/categories', null, 'Category not found.');
        }

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => slugify((string) ($_POST['slug'] ?? ($_POST['name'] ?? ''))),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:120'],
            'slug' => ['required', 'max:150'],
            'status' => ['required', 'max:20'],
        ]);

        if ($this->existsByField('categories', 'name', $input['name'], $id)) {
            $errors['name'][] = 'This category name already exists.';
        }
        if ($this->existsByField('categories', 'slug', $input['slug'], $id)) {
            $errors['slug'][] = 'This category slug already exists.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/categories/edit?id=' . $id);
        }

        try {
            $imagePath = $this->handleUpload($_FILES['image'] ?? null, 'categories', 'cat', (string) ($existing['image_path'] ?? ''));
        } catch (RuntimeException $exception) {
            validation_errors(['image' => [$exception->getMessage()]]);
            $this->redirect('/products/categories/edit?id=' . $id);
        }

        if (isset($_POST['remove_image']) && (string) $_POST['remove_image'] === '1') {
            remove_public_file((string) ($existing['image_path'] ?? ''));
            $imagePath = '';
        }

        $pdo = Database::connection();
        $pdo->prepare('
            UPDATE categories SET name = :name, slug = :slug, image_path = :image_path, status = :status, updated_at = NOW() WHERE id = :id
        ')->execute($input + ['image_path' => $imagePath, 'id' => $id]);

        if ($existing['name'] !== $input['name']) {
            $pdo->prepare('UPDATE products SET category = :category WHERE category_id = :id')->execute([
                'category' => $input['name'],
                'id' => $id,
            ]);
        }

        clear_old();
        $this->redirect('/products/categories', 'Category updated successfully.');
    }

    public function deleteCategory(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $category = $this->findCategory((int) ($_GET['id'] ?? 0));
        if (!$category) {
            $this->redirect('/products/categories', null, 'Category not found.');
        }

        if ($this->countReferences('SELECT COUNT(*) FROM products WHERE category_id = :id', (int) $category['id']) > 0) {
            $this->redirect('/products/categories', null, 'This category is assigned to one or more items. Reassign those items before deleting it.');
        }

        Database::connection()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => (int) $category['id']]);
        remove_public_file((string) ($category['image_path'] ?? ''));

        $this->redirect('/products/categories', 'Category deleted successfully.');
    }

    public function units(): void
    {
        $this->requirePermission('products');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();
        if ($search !== '') {
            $statement = $pdo->prepare('
                SELECT u.*, COUNT(p.id) AS items_count
                FROM units u
                LEFT JOIN products p ON p.unit_id = u.id
                WHERE u.name LIKE :search OR COALESCE(u.code, "") LIKE :search
                GROUP BY u.id
                ORDER BY u.name ASC
            ');
            $statement->execute(['search' => '%' . $search . '%']);
            $units = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $units = $pdo->query('
                SELECT u.*, COUNT(p.id) AS items_count
                FROM units u
                LEFT JOIN products p ON p.unit_id = u.id
                GROUP BY u.id
                ORDER BY u.name ASC
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('products/units/index', [
            'title' => 'Products / Unit',
            'units' => $units,
            'search' => $search,
        ]);
    }

    public function createUnit(): void
    {
        $this->requirePermission('products');

        $this->render('products/units/form', [
            'title' => 'Products / Unit / New Unit',
            'action' => '/products/units/store',
            'unit' => [
                'name' => '',
                'code' => '',
                'status' => 'active',
            ],
        ]);
    }

    public function storeUnit(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:80'],
            'code' => ['max:30'],
            'status' => ['required', 'max:20'],
        ]);

        if ($this->existsByField('units', 'name', $input['name'])) {
            $errors['name'][] = 'This unit name already exists.';
        }
        if ($input['code'] !== '' && $this->existsByField('units', 'code', $input['code'])) {
            $errors['code'][] = 'This unit code already exists.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/units/create');
        }

        Database::connection()->prepare('
            INSERT INTO units (name, code, status, created_at, updated_at)
            VALUES (:name, :code, :status, NOW(), NOW())
        ')->execute($input);

        clear_old();
        $this->redirect('/products/units', 'Unit created successfully.');
    }

    public function editUnit(): void
    {
        $this->requirePermission('products');

        $unit = $this->findUnit((int) ($_GET['id'] ?? 0));
        if (!$unit) {
            $this->redirect('/products/units', null, 'Unit not found.');
        }

        $this->render('products/units/form', [
            'title' => 'Products / Unit / Edit Unit',
            'action' => '/products/units/update?id=' . (int) $unit['id'],
            'unit' => $unit,
        ]);
    }

    public function updateUnit(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findUnit($id);
        if (!$existing) {
            $this->redirect('/products/units', null, 'Unit not found.');
        }

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        with_old($input);

        $errors = Validator::make($input, [
            'name' => ['required', 'max:80'],
            'code' => ['max:30'],
            'status' => ['required', 'max:20'],
        ]);

        if ($this->existsByField('units', 'name', $input['name'], $id)) {
            $errors['name'][] = 'This unit name already exists.';
        }
        if ($input['code'] !== '' && $this->existsByField('units', 'code', $input['code'], $id)) {
            $errors['code'][] = 'This unit code already exists.';
        }

        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/units/edit?id=' . $id);
        }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE units SET name = :name, code = :code, status = :status, updated_at = NOW() WHERE id = :id')->execute($input + ['id' => $id]);

        if ($existing['name'] !== $input['name']) {
            $pdo->prepare('UPDATE products SET unit = :unit WHERE unit_id = :id')->execute([
                'unit' => $input['name'],
                'id' => $id,
            ]);
        }

        clear_old();
        $this->redirect('/products/units', 'Unit updated successfully.');
    }

    public function deleteUnit(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $unit = $this->findUnit((int) ($_GET['id'] ?? 0));
        if (!$unit) {
            $this->redirect('/products/units', null, 'Unit not found.');
        }

        if ($this->countReferences('SELECT COUNT(*) FROM products WHERE unit_id = :id', (int) $unit['id']) > 0) {
            $this->redirect('/products/units', null, 'This unit is assigned to one or more items. Reassign those items before deleting it.');
        }

        Database::connection()->prepare('DELETE FROM units WHERE id = :id')->execute(['id' => (int) $unit['id']]);

        $this->redirect('/products/units', 'Unit deleted successfully.');
    }

    public function currencies(): void
    {
        $this->requirePermission('products');

        $search = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();
        if ($search !== '') {
            $statement = $pdo->prepare('
                SELECT c.*, COUNT(p.id) AS items_count
                FROM currencies c
                LEFT JOIN products p ON p.currency_id = c.id
                WHERE c.name LIKE :search OR c.code LIKE :search OR c.symbol LIKE :search
                GROUP BY c.id
                ORDER BY c.is_default DESC, c.name ASC
            ');
            $statement->execute(['search' => '%' . $search . '%']);
            $currencies = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $currencies = $pdo->query('
                SELECT c.*, COUNT(p.id) AS items_count
                FROM currencies c
                LEFT JOIN products p ON p.currency_id = c.id
                GROUP BY c.id
                ORDER BY c.is_default DESC, c.name ASC
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('products/currencies/index', [
            'title' => 'Products / Currency',
            'currencies' => $currencies,
            'search' => $search,
        ]);
    }

    public function createCurrency(): void
    {
        $this->requirePermission('products');

        $this->render('products/currencies/form', [
            'title' => 'Products / Currency / New Currency',
            'action' => '/products/currencies/store',
            'currency' => [
                'name' => '',
                'code' => '',
                'symbol' => '',
                'rate_to_aed' => '1.00000000',
                'is_default' => '0',
                'status' => 'active',
            ],
        ]);
    }

    public function storeCurrency(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $input = $this->normalizeCurrencyInput($_POST);
        with_old($input);

        $errors = $this->validateCurrency($input);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/currencies/create');
        }

        $pdo = Database::connection();
        if ((int) $input['is_default'] === 1) {
            $pdo->exec('UPDATE currencies SET is_default = 0');
        }

        $pdo->prepare('
            INSERT INTO currencies (name, code, symbol, rate_to_aed, is_default, status, created_at, updated_at)
            VALUES (:name, :code, :symbol, :rate_to_aed, :is_default, :status, NOW(), NOW())
        ')->execute($input);

        clear_old();
        $this->redirect('/products/currencies', 'Currency created successfully.');
    }

    public function editCurrency(): void
    {
        $this->requirePermission('products');

        $currency = $this->findCurrency((int) ($_GET['id'] ?? 0));
        if (!$currency) {
            $this->redirect('/products/currencies', null, 'Currency not found.');
        }

        $this->render('products/currencies/form', [
            'title' => 'Products / Currency / Edit Currency',
            'action' => '/products/currencies/update?id=' . (int) $currency['id'],
            'currency' => $currency,
        ]);
    }

    public function updateCurrency(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $id = (int) ($_GET['id'] ?? 0);
        $existing = $this->findCurrency($id);
        if (!$existing) {
            $this->redirect('/products/currencies', null, 'Currency not found.');
        }

        $input = $this->normalizeCurrencyInput($_POST, $existing);
        with_old($input);

        $errors = $this->validateCurrency($input, $id);
        if ($errors !== []) {
            validation_errors($errors);
            $this->redirect('/products/currencies/edit?id=' . $id);
        }

        $pdo = Database::connection();
        if ((int) $input['is_default'] === 1) {
            $pdo->exec('UPDATE currencies SET is_default = 0');
        } elseif ((int) ($existing['is_default'] ?? 0) === 1) {
            $input['is_default'] = '1';
        }

        $pdo->prepare('
            UPDATE currencies
            SET name = :name, code = :code, symbol = :symbol, rate_to_aed = :rate_to_aed, is_default = :is_default, status = :status, updated_at = NOW()
            WHERE id = :id
        ')->execute($input + ['id' => $id]);

        if (($existing['code'] ?? '') !== $input['code']) {
            $pdo->prepare('UPDATE products SET price_currency_code = :code WHERE currency_id = :id')->execute([
                'code' => $input['code'],
                'id' => $id,
            ]);
        }

        clear_old();
        $this->redirect('/products/currencies', 'Currency updated successfully.');
    }

    public function deleteCurrency(): void
    {
        $this->requirePermission('products');
        $this->verifyCsrf();

        $currency = $this->findCurrency((int) ($_GET['id'] ?? 0));
        if (!$currency) {
            $this->redirect('/products/currencies', null, 'Currency not found.');
        }

        if ((int) ($currency['is_default'] ?? 0) === 1) {
            $this->redirect('/products/currencies', null, 'The default currency cannot be deleted.');
        }

        if ($this->countReferences('SELECT COUNT(*) FROM products WHERE currency_id = :id', (int) $currency['id']) > 0) {
            $this->redirect('/products/currencies', null, 'This currency is assigned to one or more items. Reassign those items before deleting it.');
        }

        Database::connection()->prepare('DELETE FROM currencies WHERE id = :id')->execute(['id' => (int) $currency['id']]);

        $this->redirect('/products/currencies', 'Currency deleted successfully.');
    }

    private function generateUniqueProductCode(int $maxAttempts = 50): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $lastCode = 'Li-XXXX';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $suffix = '';
            for ($index = 0; $index < 4; $index++) {
                $suffix .= $characters[random_int(0, strlen($characters) - 1)];
            }

            $code = 'Li-' . $suffix;
            $lastCode = $code;

            if (!$this->existsByField('products', 'code', $code)) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique product code automatically. Last attempted code: ' . $lastCode);
    }

    private function validateProduct(array $input, ?int $ignoreId = null): array
    {
        $errors = Validator::make($input, [
            'code' => ['required', 'max:60'],
            'name' => ['required', 'max:190'],
            'purchase_price_display' => ['required', 'numeric', 'min:0'],
            'sale_price_display' => ['required', 'numeric', 'min:0'],
            'carton_length_cm' => ['numeric', 'min:0'],
            'carton_width_cm' => ['numeric', 'min:0'],
            'carton_height_cm' => ['numeric', 'min:0'],
            'gross_weight_kg' => ['numeric', 'min:0'],
            'units_per_box' => ['required', 'numeric', 'min:1'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'max:20'],
            'item_type' => ['required', 'max:30'],
        ]);

        if (!in_array($input['item_type'], ['inventory', 'non_inventory', 'service'], true)) {
            $errors['item_type'][] = 'Choose a valid item type.';
        }

        if ($input['category_id'] <= 0 || !$this->findCategory($input['category_id'])) {
            $errors['category_id'][] = 'Choose a valid category.';
        }
        if ($input['unit_id'] <= 0 || !$this->findUnit($input['unit_id'])) {
            $errors['unit_id'][] = 'Choose a valid unit.';
        }
        if ($input['currency_id'] <= 0 || !$this->findCurrency($input['currency_id'])) {
            $errors['currency_id'][] = 'The AED base currency is not configured. Add or restore the AED currency in Products / Currency.';
        }
        if ($this->existsByField('products', 'code', $input['code'], $ignoreId)) {
            $errors['code'][] = 'This code is already assigned to another item.';
        }

        if ($ignoreId !== null) {
            $existing = $this->findProduct($ignoreId);
            if ($existing && (string) ($existing['item_type'] ?? 'inventory') !== $input['item_type'] && !$this->canChangeProductType($ignoreId)) {
                $errors['item_type'][] = 'Item type can only be changed before the item is used in inventory or invoices.';
            }
        }

        return $errors;
    }

    private function normalizeProductInput(array $source, ?array $fallback = null): array
    {
        $currency = $this->aedCurrency() ?? $this->defaultCurrency();
        $currencyId = (int) ($currency['id'] ?? 0);

        $itemType = trim((string) ($source['item_type'] ?? ($fallback['item_type'] ?? 'inventory')));
        if (!in_array($itemType, ['inventory', 'non_inventory', 'service'], true)) {
            $itemType = 'inventory';
        }

        $purchaseDisplay = trim((string) ($source['purchase_price_display'] ?? ($fallback['purchase_price_display'] ?? ($fallback['purchase_price'] ?? '0'))));
        $saleDisplay = trim((string) ($source['sale_price_display'] ?? ($fallback['sale_price_display'] ?? ($fallback['sale_price'] ?? '0'))));

        $purchaseBase = number_format((float) $purchaseDisplay, 2, '.', '');
        $saleBase = number_format((float) $saleDisplay, 2, '.', '');

        $cartonLength = number_format((float) ($source['carton_length_cm'] ?? ($fallback['carton_length_cm'] ?? '0')), 2, '.', '');
        $cartonWidth = number_format((float) ($source['carton_width_cm'] ?? ($fallback['carton_width_cm'] ?? '0')), 2, '.', '');
        $cartonHeight = number_format((float) ($source['carton_height_cm'] ?? ($fallback['carton_height_cm'] ?? '0')), 2, '.', '');
        $grossWeight = number_format((float) ($source['gross_weight_kg'] ?? ($fallback['gross_weight_kg'] ?? '0')), 3, '.', '');
        $unitsPerBox = number_format(max(1, (float) ($source['units_per_box'] ?? ($fallback['units_per_box'] ?? '1'))), 2, '.', '');
        if ($itemType !== 'inventory') {
            $cartonLength = '0.00';
            $cartonWidth = '0.00';
            $cartonHeight = '0.00';
            $grossWeight = '0.000';
            $unitsPerBox = '1.00';
        }

        $cbm = ((float) $cartonLength > 0 && (float) $cartonWidth > 0 && (float) $cartonHeight > 0)
            ? (((float) $cartonLength * (float) $cartonWidth * (float) $cartonHeight) / 1000000)
            : 0.0;

        $minStock = trim((string) ($source['min_stock'] ?? ($fallback['min_stock'] ?? '0')));
        $currentStock = trim((string) ($source['current_stock'] ?? ($fallback['current_stock'] ?? '0')));
        if ($itemType !== 'inventory') {
            $minStock = '0';
            $currentStock = '0';
        }

        return [
            'code' => trim((string) ($source['code'] ?? ($fallback['code'] ?? ''))),
            'name' => trim((string) ($source['name'] ?? ($fallback['name'] ?? ''))),
            'item_type' => $itemType,
            'category_id' => (int) ($source['category_id'] ?? ($fallback['category_id'] ?? 0)),
            'unit_id' => (int) ($source['unit_id'] ?? ($fallback['unit_id'] ?? 0)),
            'currency_id' => $currencyId,
            'purchase_price_display' => $purchaseBase,
            'sale_price_display' => $saleBase,
            'purchase_price' => $purchaseBase,
            'sale_price' => $saleBase,
            'carton_length_cm' => $cartonLength,
            'carton_width_cm' => $cartonWidth,
            'carton_height_cm' => $cartonHeight,
            'gross_weight_kg' => $grossWeight,
            'cbm_per_carton' => number_format($cbm, 6, '.', ''),
            'units_per_box' => $unitsPerBox,
            'min_stock' => $minStock,
            'current_stock' => $currentStock,
            'status' => trim((string) ($source['status'] ?? ($fallback['status'] ?? 'active'))),
            'image_path' => (string) ($fallback['image_path'] ?? ''),
        ];
    }

    private function resolveProductMeta(array $input): array
    {
        $category = $this->findCategory((int) $input['category_id']);
        $unit = $this->findUnit((int) $input['unit_id']);
        $currency = $this->findCurrency((int) $input['currency_id']);

        return [
            'category_id' => (int) ($category['id'] ?? 0),
            'category' => (string) ($category['name'] ?? ''),
            'unit_id' => (int) ($unit['id'] ?? 0),
            'unit' => (string) ($unit['name'] ?? ''),
            'currency_id' => (int) ($currency['id'] ?? 0),
            'price_currency_code' => (string) ($currency['code'] ?? 'AED'),
        ];
    }

    private function normalizeCurrencyInput(array $source, ?array $fallback = null): array
    {
        return [
            'name' => trim((string) ($source['name'] ?? ($fallback['name'] ?? ''))),
            'code' => strtoupper(trim((string) ($source['code'] ?? ($fallback['code'] ?? '')))),
            'symbol' => trim((string) ($source['symbol'] ?? ($fallback['symbol'] ?? ''))),
            'rate_to_aed' => trim((string) ($source['rate_to_aed'] ?? ($fallback['rate_to_aed'] ?? '1.00000000'))),
            'is_default' => isset($source['is_default']) ? '1' : (string) ($fallback['is_default'] ?? '0'),
            'status' => trim((string) ($source['status'] ?? ($fallback['status'] ?? 'active'))),
        ];
    }

    private function validateCurrency(array $input, ?int $ignoreId = null): array
    {
        $errors = Validator::make($input, [
            'name' => ['required', 'max:80'],
            'code' => ['required', 'max:10'],
            'symbol' => ['required', 'max:16'],
            'rate_to_aed' => ['required', 'numeric', 'min:0.000001'],
            'status' => ['required', 'max:20'],
        ]);

        if ($this->existsByField('currencies', 'name', $input['name'], $ignoreId)) {
            $errors['name'][] = 'This currency name already exists.';
        }
        if ($this->existsByField('currencies', 'code', $input['code'], $ignoreId)) {
            $errors['code'][] = 'This currency code already exists.';
        }

        return $errors;
    }

    private function findProduct(int $id): ?array
    {
        $statement = Database::connection()->prepare('
            SELECT
                p.*,
                COALESCE(c.name, p.category, "—") AS category_label,
                COALESCE(u.name, p.unit, "—") AS unit_label,
                COALESCE(cur.name, "UAE Dirham") AS currency_name,
                COALESCE(cur.code, p.price_currency_code, "AED") AS currency_code,
                COALESCE(cur.symbol, "د.إ") AS currency_symbol,
                COALESCE(cur.rate_to_aed, 1.00000000) AS rate_to_aed,
                COALESCE(p.item_type, "inventory") AS item_type
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units u ON u.id = p.unit_id
            LEFT JOIN currencies cur ON cur.id = p.currency_id
            WHERE p.id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return null;
        }

        $product['item_type'] = (string) ($product['item_type'] ?? 'inventory');
        $product['purchase_price_display'] = number_format((float) ($product['purchase_price'] ?? 0), 2, '.', '');
        $product['sale_price_display'] = number_format((float) ($product['sale_price'] ?? 0), 2, '.', '');
        $product['carton_length_cm'] = number_format((float) ($product['carton_length_cm'] ?? 0), 2, '.', '');
        $product['carton_width_cm'] = number_format((float) ($product['carton_width_cm'] ?? 0), 2, '.', '');
        $product['carton_height_cm'] = number_format((float) ($product['carton_height_cm'] ?? 0), 2, '.', '');
        $product['gross_weight_kg'] = number_format((float) ($product['gross_weight_kg'] ?? 0), 3, '.', '');
        $product['cbm_per_carton'] = number_format((float) ($product['cbm_per_carton'] ?? 0), 6, '.', '');
        $product['units_per_box'] = number_format(max(1, (float) ($product['units_per_box'] ?? 1)), 2, '.', '');

        $aed = $this->aedCurrency();
        if ($aed) {
            $product['currency_id'] = (int) $aed['id'];
            $product['currency_name'] = (string) $aed['name'];
            $product['currency_code'] = (string) $aed['code'];
            $product['currency_symbol'] = (string) $aed['symbol'];
            $product['rate_to_aed'] = (float) $aed['rate_to_aed'];
        } else {
            $product['currency_code'] = 'AED';
            $product['currency_symbol'] = 'د.إ';
            $product['rate_to_aed'] = 1.0;
        }

        return $product;
    }

    private function canChangeProductType(int $productId): bool
    {
        $pdo = Database::connection();
        $checks = [
            'SELECT COUNT(*) FROM stock_movements WHERE product_id = :id',
            'SELECT COUNT(*) FROM warehouse_stocks WHERE product_id = :id',
            'SELECT COUNT(*) FROM purchase_items WHERE product_id = :id',
            'SELECT COUNT(*) FROM sale_items WHERE product_id = :id',
        ];

        foreach ($checks as $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute(['id' => $productId]);
            if ((int) $statement->fetchColumn() > 0) {
                return false;
            }
        }

        return true;
    }

    private function findCategory(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    private function findUnit(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    private function findCurrency(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    private function defaultCurrency(): ?array
    {
        $statement = Database::connection()->query('SELECT * FROM currencies WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
        $currency = $statement->fetch(PDO::FETCH_ASSOC);

        if ($currency) {
            return $currency;
        }

        $fallback = Database::connection()->query('SELECT * FROM currencies ORDER BY id ASC LIMIT 1');
        $currency = $fallback->fetch(PDO::FETCH_ASSOC);
        return $currency ?: null;
    }

    private function aedCurrency(): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM currencies WHERE code = :code ORDER BY is_default DESC, id ASC LIMIT 1');
        $statement->execute(['code' => 'AED']);
        $currency = $statement->fetch(PDO::FETCH_ASSOC);

        return $currency ?: null;
    }

    private function listCategories(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM categories';
        if (!$includeInactive) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY name ASC';

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listUnits(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM units';
        if (!$includeInactive) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY name ASC';

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listCurrencies(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM currencies';
        if (!$includeInactive) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY is_default DESC, name ASC';

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function currencyRates(): array
    {
        $rates = [];
        foreach ($this->listCurrencies(true) as $currency) {
            $rates[(int) $currency['id']] = [
                'code' => (string) $currency['code'],
                'symbol' => (string) $currency['symbol'],
                'rate' => (float) $currency['rate_to_aed'],
            ];
        }

        return $rates;
    }

    private function existsByField(string $table, string $field, string $value, ?int $ignoreId = null): bool
    {
        $sql = sprintf('SELECT id FROM %s WHERE %s = :value', $table, $field);
        $params = ['value' => $value];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return (bool) $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function countReferences(string $sql, int $id): int
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn();
    }

    private function handleUpload(array|null $file, string $folder, string $prefix, string $existingPath = ''): string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The image upload could not be completed.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('The uploaded image must be 5 MB or smaller.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Only JPG, PNG, WEBP, and GIF files are supported.');
        }

        ensure_directory(public_upload_path($folder));
        $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $relativePath = 'uploads/' . trim($folder, '/') . '/' . $filename;
        $absolutePath = public_path($relativePath);

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $absolutePath)) {
            throw new RuntimeException('The uploaded image could not be moved into place.');
        }

        if ($existingPath !== '' && $existingPath !== $relativePath) {
            remove_public_file($existingPath);
        }

        return $relativePath;
    }
}
