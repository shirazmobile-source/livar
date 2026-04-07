<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\BankingController;
use App\Controllers\ProductController;
use App\Controllers\InventoryController;
use App\Controllers\PurchaseController;
use App\Controllers\ReportController;
use App\Controllers\SaleController;
use App\Controllers\SettingController;
use App\Controllers\SupplierController;
use App\Controllers\UserController;
use App\Router;

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (!installation_complete()) {
    header('Location: ' . install_url());
    exit;
}

try {
    $router = new Router();

    $authController = new AuthController();
    $dashboardController = new DashboardController();
    $documentController = new DocumentController();
    $customerController = new CustomerController();
    $supplierController = new SupplierController();
    $productController = new ProductController();
    $bankingController = new BankingController();
    $inventoryController = new InventoryController();
    $purchaseController = new PurchaseController();
    $saleController = new SaleController();
    $reportController = new ReportController();
    $settingController = new SettingController();
    $userController = new UserController();

    $router->get('/', [$dashboardController, 'index']);

    $router->get('/login', [$authController, 'showLogin']);
    $router->post('/login', [$authController, 'login']);
    $router->post('/logout', [$authController, 'logout']);

    $router->get('/customers', [$customerController, 'index']);
    $router->get('/customers/create', [$customerController, 'create']);
    $router->post('/customers/store', [$customerController, 'store']);
    $router->get('/customers/edit', [$customerController, 'edit']);
    $router->post('/customers/update', [$customerController, 'update']);
    $router->get('/customers/show', [$customerController, 'show']);

    $router->get('/suppliers', [$supplierController, 'index']);
    $router->get('/suppliers/create', [$supplierController, 'create']);
    $router->post('/suppliers/store', [$supplierController, 'store']);
    $router->get('/suppliers/edit', [$supplierController, 'edit']);
    $router->post('/suppliers/update', [$supplierController, 'update']);
    $router->get('/suppliers/show', [$supplierController, 'show']);

    $router->get('/products', [$productController, 'index']);
    $router->get('/products/create', [$productController, 'create']);
    $router->post('/products/store', [$productController, 'store']);
    $router->get('/products/edit', [$productController, 'edit']);
    $router->post('/products/update', [$productController, 'update']);
    $router->post('/products/delete', [$productController, 'delete']);
    $router->get('/products/qr', [$productController, 'qr']);

    $router->get('/products/categories', [$productController, 'categories']);
    $router->get('/products/categories/create', [$productController, 'createCategory']);
    $router->post('/products/categories/store', [$productController, 'storeCategory']);
    $router->get('/products/categories/edit', [$productController, 'editCategory']);
    $router->post('/products/categories/update', [$productController, 'updateCategory']);
    $router->post('/products/categories/delete', [$productController, 'deleteCategory']);

    $router->get('/products/units', [$productController, 'units']);
    $router->get('/products/units/create', [$productController, 'createUnit']);
    $router->post('/products/units/store', [$productController, 'storeUnit']);
    $router->get('/products/units/edit', [$productController, 'editUnit']);
    $router->post('/products/units/update', [$productController, 'updateUnit']);
    $router->post('/products/units/delete', [$productController, 'deleteUnit']);

    $router->get('/products/currencies', [$productController, 'currencies']);
    $router->get('/products/currencies/create', [$productController, 'createCurrency']);
    $router->post('/products/currencies/store', [$productController, 'storeCurrency']);
    $router->get('/products/currencies/edit', [$productController, 'editCurrency']);
    $router->post('/products/currencies/update', [$productController, 'updateCurrency']);
    $router->post('/products/currencies/delete', [$productController, 'deleteCurrency']);

    $router->get('/banking', [$bankingController, 'index']);
    $router->get('/banking/accounts/create', [$bankingController, 'createAccount']);
    $router->post('/banking/accounts/store', [$bankingController, 'storeAccount']);
    $router->get('/banking/accounts/edit', [$bankingController, 'editAccount']);
    $router->post('/banking/accounts/update', [$bankingController, 'updateAccount']);
    $router->get('/banking/transactions/create', [$bankingController, 'createTransaction']);
    $router->post('/banking/transactions/store', [$bankingController, 'storeTransaction']);
    $router->get('/banking/transfers/create', [$bankingController, 'createTransfer']);
    $router->post('/banking/transfers/store', [$bankingController, 'storeTransfer']);

    $router->get('/inventory', [$inventoryController, 'index']);
    $router->get('/inventory/warehouses/create', [$inventoryController, 'createWarehouse']);
    $router->post('/inventory/warehouses/store', [$inventoryController, 'storeWarehouse']);
    $router->get('/inventory/warehouses/edit', [$inventoryController, 'editWarehouse']);
    $router->post('/inventory/warehouses/update', [$inventoryController, 'updateWarehouse']);
    $router->get('/inventory/receipts/create', [$inventoryController, 'createReceipt']);
    $router->post('/inventory/receipts/store', [$inventoryController, 'storeReceipt']);

    $router->get('/purchases', [$purchaseController, 'index']);
    $router->get('/purchases/create', [$purchaseController, 'create']);
    $router->post('/purchases/store', [$purchaseController, 'store']);
    $router->get('/purchases/edit', [$purchaseController, 'edit']);
    $router->post('/purchases/update', [$purchaseController, 'update']);
    $router->get('/purchases/show', [$purchaseController, 'show']);
    $router->get('/purchases/payments/create', [$purchaseController, 'createPayment']);
    $router->post('/purchases/payments/store', [$purchaseController, 'storePayment']);
    $router->get('/purchases/returns/create', [$purchaseController, 'createReturn']);
    $router->post('/purchases/returns/store', [$purchaseController, 'storeReturn']);

    $router->get('/sales', [$saleController, 'index']);
    $router->get('/sales/create', [$saleController, 'create']);
    $router->post('/sales/store', [$saleController, 'store']);
    $router->get('/sales/show', [$saleController, 'show']);
    $router->get('/sales/receipts/create', [$saleController, 'createReceipt']);
    $router->post('/sales/receipts/store', [$saleController, 'storeReceipt']);

    $router->get('/reports', static function (): never {
        redirect('/settings/reports');
    });
    $router->get('/settings', [$settingController, 'index']);
    $router->get('/settings/reports', [$reportController, 'index']);
    $router->get('/settings/backup', [$settingController, 'backup']);
    $router->post('/settings/backup/create', [$settingController, 'createBackup']);
    $router->get('/settings/backup/download', [$settingController, 'downloadBackup']);
    $router->post('/settings/backup/restore', [$settingController, 'restoreBackup']);
    $router->post('/settings/backup/restore-upload', [$settingController, 'restoreBackupUpload']);
    $router->get('/settings/update', [$settingController, 'update']);
    $router->post('/settings/update/upload', [$settingController, 'uploadUpdate']);
    $router->get('/settings/users', [$userController, 'index']);
    $router->get('/settings/users/create', [$userController, 'create']);
    $router->post('/settings/users/store', [$userController, 'store']);
    $router->get('/settings/users/edit', [$userController, 'edit']);
    $router->post('/settings/users/update', [$userController, 'update']);
    $router->post('/settings/users/delete', [$userController, 'destroy']);

    $router->get('/settings/media', [$settingController, 'media']);
    $router->get('/settings/forms', [$documentController, 'forms']);
    $router->get('/settings/forms/edit', [$documentController, 'editForm']);
    $router->post('/settings/forms/update', [$documentController, 'updateForm']);
    $router->post('/settings/forms/reset', [$documentController, 'resetForm']);

    $router->get('/documents/sales/invoice', [$documentController, 'salesInvoice']);
    $router->get('/documents/purchases/invoice', [$documentController, 'purchaseInvoice']);
    $router->get('/documents/customers/statement', [$documentController, 'customerStatement']);
    $router->get('/documents/suppliers/statement', [$documentController, 'supplierStatement']);
    $router->get('/documents/banking/statement', [$documentController, 'bankStatement']);
    $router->get('/documents/inventory/receipt', [$documentController, 'inventoryReceipt']);
    $router->get('/documents/inventory/issue', [$documentController, 'inventoryIssue']);
    $router->get('/settings/theme', [$settingController, 'theme']);
    $router->post('/settings/theme/save', [$settingController, 'saveTheme']);
    $router->post('/settings/theme/reset', [$settingController, 'resetTheme']);
    $router->get('/settings/media/edit', [$settingController, 'editMedia']);
    $router->post('/settings/media/update', [$settingController, 'updateMedia']);
    $router->post('/settings/media/delete', [$settingController, 'deleteMedia']);

    $router->dispatch(request_method(), request_path());
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);

    if (config('app.debug', false)) {
        echo '<pre style="white-space:pre-wrap;font-family:monospace;">' . e((string) $exception) . '</pre>';
        exit;
    }

    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>The application encountered an error.</p>';
}
