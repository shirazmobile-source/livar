INSERT INTO categories (name, slug, status, created_at, updated_at)
VALUES
('Consumables', 'consumables', 'active', NOW(), NOW()),
('Devices', 'devices', 'active', NOW(), NOW());

INSERT INTO customers (code, name, mobile, phone, email, address, status, created_at, updated_at)
VALUES
('CUS-1001', 'Blue Horizon Trading', '+971500000111', '+97142300001', 'contact@bluehorizon.test', 'Dubai, UAE', 'active', NOW(), NOW()),
('CUS-1002', 'North Point Retail', '+971500000222', '+97142300002', 'sales@northpoint.test', 'Sharjah, UAE', 'active', NOW(), NOW());

INSERT INTO suppliers (code, name, mobile, phone, email, address, status, created_at, updated_at)
VALUES
('SUP-1001', 'Prime Supply House', '+971500000333', '+97142300003', 'hello@primesupply.test', 'Dubai Industrial Area', 'active', NOW(), NOW()),
('SUP-1002', 'Delta Wholesale', '+971500000444', '+97142300004', 'orders@delta.test', 'Abu Dhabi, UAE', 'active', NOW(), NOW());

INSERT INTO products (
    code, name, item_type, category_id, category, unit_id, unit, currency_id, price_currency_code,
    purchase_price_display, sale_price_display, purchase_price, sale_price,
    carton_length_cm, carton_width_cm, carton_height_cm, gross_weight_kg, cbm_per_carton, units_per_box,
    min_stock, current_stock, status, created_at, updated_at
)
VALUES
(
    'PRD-1001',
    'Thermal Paper Roll',
    'inventory',
    (SELECT id FROM categories WHERE slug = 'consumables' LIMIT 1),
    'Consumables',
    (SELECT id FROM units WHERE code = 'PCS' LIMIT 1),
    'pcs',
    (SELECT id FROM currencies WHERE code = 'AED' LIMIT 1),
    'AED',
    6.50, 10.00, 6.50, 10.00,
    0.00, 0.00, 0.00, 0.000, 0.000000, 1.00,
    20.00, 100.00, 'active', NOW(), NOW()
),
(
    'PRD-1002',
    'Barcode Scanner',
    'inventory',
    (SELECT id FROM categories WHERE slug = 'devices' LIMIT 1),
    'Devices',
    (SELECT id FROM units WHERE code = 'PCS' LIMIT 1),
    'pcs',
    (SELECT id FROM currencies WHERE code = 'AED' LIMIT 1),
    'AED',
    90.00, 130.00, 90.00, 130.00,
    0.00, 0.00, 0.00, 0.000, 0.000000, 1.00,
    5.00, 15.00, 'active', NOW(), NOW()
),
(
    'PRD-1003',
    'Receipt Printer',
    'inventory',
    (SELECT id FROM categories WHERE slug = 'devices' LIMIT 1),
    'Devices',
    (SELECT id FROM units WHERE code = 'PCS' LIMIT 1),
    'pcs',
    (SELECT id FROM currencies WHERE code = 'AED' LIMIT 1),
    'AED',
    180.00, 250.00, 180.00, 250.00,
    0.00, 0.00, 0.00, 0.000, 0.000000, 1.00,
    3.00, 8.00, 'active', NOW(), NOW()
);

INSERT INTO stock_movements (product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_at, updated_at)
SELECT p.id, 'adjustment', 'product', p.id, p.current_stock, 0.00, p.current_stock, 'Opening stock', NOW(), NOW()
FROM products p
WHERE p.code IN ('PRD-1001', 'PRD-1002', 'PRD-1003');

INSERT INTO warehouse_stocks (warehouse_id, product_id, qty, created_at, updated_at)
SELECT w.id, p.id, p.current_stock, NOW(), NOW()
FROM products p
CROSS JOIN (
    SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
) w
WHERE p.code IN ('PRD-1001', 'PRD-1002', 'PRD-1003');

INSERT INTO warehouse_movements (
    warehouse_id, product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_by, created_at, updated_at
)
SELECT w.id, p.id, 'adjustment', 'product', p.id, p.current_stock, 0.00, p.current_stock, 'Installer opening warehouse stock', NULL, NOW(), NOW()
FROM products p
CROSS JOIN (
    SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
) w
WHERE p.code IN ('PRD-1001', 'PRD-1002', 'PRD-1003');
