<div class="card stack-gap-sm">
    <div class="card-b">
        <div class="tabs settings-tabs">
            <a href="<?= e(base_url('/products')) ?>" class="tab <?= e((request_path() === '/products' || str_starts_with(request_path(), '/products/create') || str_starts_with(request_path(), '/products/edit') || str_starts_with(request_path(), '/products/qr')) ? 'active' : '') ?>">Items</a>
            <a href="<?= e(base_url('/products/categories')) ?>" class="tab <?= e(str_starts_with(request_path(), '/products/categories') ? 'active' : '') ?>">Category</a>
            <a href="<?= e(base_url('/products/units')) ?>" class="tab <?= e(str_starts_with(request_path(), '/products/units') ? 'active' : '') ?>">Unit</a>
            <a href="<?= e(base_url('/products/currencies')) ?>" class="tab <?= e(str_starts_with(request_path(), '/products/currencies') ? 'active' : '') ?>">Currency</a>
        </div>
    </div>
</div>
