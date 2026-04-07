SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS carton_length_cm DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER sale_price,
    ADD COLUMN IF NOT EXISTS carton_width_cm DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER carton_length_cm,
    ADD COLUMN IF NOT EXISTS carton_height_cm DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER carton_width_cm,
    ADD COLUMN IF NOT EXISTS gross_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER carton_height_cm,
    ADD COLUMN IF NOT EXISTS cbm_per_carton DECIMAL(14,6) NOT NULL DEFAULT 0.000000 AFTER gross_weight_kg,
    ADD COLUMN IF NOT EXISTS units_per_box DECIMAL(14,2) NOT NULL DEFAULT 1.00 AFTER cbm_per_carton;

ALTER TABLE purchases
    ADD COLUMN IF NOT EXISTS receipt_status ENUM('pending','partial','received') NOT NULL DEFAULT 'pending' AFTER final_amount_aed,
    ADD COLUMN IF NOT EXISTS return_status ENUM('none','partial','returned') NOT NULL DEFAULT 'none' AFTER receipt_status;

ALTER TABLE purchase_items
    ADD COLUMN IF NOT EXISTS display_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER product_id,
    ADD COLUMN IF NOT EXISTS pricing_unit VARCHAR(20) NOT NULL DEFAULT 'unit' AFTER display_qty,
    ADD COLUMN IF NOT EXISTS units_per_box DECIMAL(14,2) NOT NULL DEFAULT 1.00 AFTER pricing_unit,
    ADD COLUMN IF NOT EXISTS received_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER qty,
    ADD COLUMN IF NOT EXISTS returned_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER received_qty;

UPDATE purchase_items
SET display_qty = qty
WHERE COALESCE(display_qty, 0) = 0;

ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER customer_id;

ALTER TABLE sale_items
    ADD COLUMN IF NOT EXISTS display_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER product_id,
    ADD COLUMN IF NOT EXISTS pricing_unit VARCHAR(20) NOT NULL DEFAULT 'unit' AFTER display_qty,
    ADD COLUMN IF NOT EXISTS units_per_box DECIMAL(14,2) NOT NULL DEFAULT 1.00 AFTER pricing_unit;

UPDATE sale_items
SET display_qty = qty
WHERE COALESCE(display_qty, 0) = 0;

CREATE TABLE IF NOT EXISTS warehouses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  location VARCHAR(190) DEFAULT NULL,
  manager_name VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(60) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_warehouses_code (code),
  KEY idx_warehouses_status (status),
  KEY idx_warehouses_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_stocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_warehouse_product (warehouse_id, product_id),
  KEY idx_warehouse_stocks_product (product_id),
  CONSTRAINT fk_warehouse_stocks_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_warehouse_stocks_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  type ENUM('receipt','sale','adjustment','return') NOT NULL,
  ref_type VARCHAR(50) NOT NULL,
  ref_id BIGINT UNSIGNED DEFAULT NULL,
  qty_in DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  qty_out DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  balance_after DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  note VARCHAR(255) DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_warehouse_movements_warehouse (warehouse_id),
  KEY idx_warehouse_movements_product (product_id),
  KEY idx_warehouse_movements_created (created_at),
  KEY fk_warehouse_movements_user (created_by),
  CONSTRAINT fk_warehouse_movements_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_warehouse_movements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_warehouse_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  receipt_no VARCHAR(80) NOT NULL,
  receipt_date DATE NOT NULL,
  note TEXT DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_receipts_no (receipt_no),
  KEY idx_inventory_receipts_purchase (purchase_id),
  KEY idx_inventory_receipts_warehouse (warehouse_id),
  KEY fk_inventory_receipts_user (created_by),
  CONSTRAINT fk_inventory_receipts_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_receipts_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_receipts_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_receipt_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_id BIGINT UNSIGNED NOT NULL,
  purchase_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  unit_cost_aed DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_inventory_receipt_items_receipt (receipt_id),
  KEY idx_inventory_receipt_items_purchase_item (purchase_item_id),
  KEY idx_inventory_receipt_items_product (product_id),
  CONSTRAINT fk_inventory_receipt_items_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_inventory_receipt_items_purchase_item FOREIGN KEY (purchase_item_id) REFERENCES purchase_items (id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_receipt_items_receipt FOREIGN KEY (receipt_id) REFERENCES inventory_receipts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_returns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  return_no VARCHAR(80) NOT NULL,
  return_date DATE NOT NULL,
  currency_id BIGINT UNSIGNED DEFAULT NULL,
  currency_code VARCHAR(10) NOT NULL DEFAULT 'AED',
  currency_symbol VARCHAR(16) NOT NULL DEFAULT 'د.إ',
  currency_rate_to_aed DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
  total_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_amount_aed DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  reason VARCHAR(255) NOT NULL,
  note TEXT DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_purchase_returns_no (return_no),
  KEY idx_purchase_returns_purchase (purchase_id),
  KEY idx_purchase_returns_warehouse (warehouse_id),
  KEY idx_purchase_returns_date (return_date),
  KEY fk_purchase_returns_user (created_by),
  CONSTRAINT fk_purchase_returns_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
  CONSTRAINT fk_purchase_returns_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_purchase_returns_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_return_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_return_id BIGINT UNSIGNED NOT NULL,
  purchase_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  unit_price_aed DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_price_aed DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_purchase_return_items_return (purchase_return_id),
  KEY idx_purchase_return_items_purchase_item (purchase_item_id),
  KEY idx_purchase_return_items_product (product_id),
  CONSTRAINT fk_purchase_return_items_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_purchase_return_items_purchase_item FOREIGN KEY (purchase_item_id) REFERENCES purchase_items (id),
  CONSTRAINT fk_purchase_return_items_return FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  path VARCHAR(255) NOT NULL,
  title VARCHAR(190) DEFAULT NULL,
  alt_text VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY path (path),
  KEY idx_media_library_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO warehouses (code, name, location, manager_name, phone, notes, is_default, status, created_at, updated_at)
SELECT 'WH-001', 'Main Warehouse', 'Migrated warehouse', NULL, NULL, 'Auto-created during schema reconciliation.', 1, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM warehouses);

UPDATE warehouses
SET is_default = 0
WHERE id NOT IN (
    SELECT id FROM (
        SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
    ) AS selected_default
);

UPDATE warehouses
SET is_default = 1
WHERE id IN (
    SELECT id FROM (
        SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
    ) AS selected_default
);

INSERT INTO warehouse_stocks (warehouse_id, product_id, qty, created_at, updated_at)
SELECT default_warehouse.id, p.id, p.current_stock, NOW(), NOW()
FROM products p
CROSS JOIN (
    SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
) AS default_warehouse
LEFT JOIN warehouse_stocks ws
    ON ws.warehouse_id = default_warehouse.id
   AND ws.product_id = p.id
WHERE p.item_type = 'inventory'
  AND p.current_stock > 0
  AND ws.id IS NULL;

INSERT INTO warehouse_movements (
    warehouse_id, product_id, type, ref_type, ref_id, qty_in, qty_out, balance_after, note, created_by, created_at, updated_at
)
SELECT default_warehouse.id, p.id, 'adjustment', 'product', p.id, p.current_stock, 0.00, p.current_stock,
       'Auto-created from product current_stock during schema reconciliation.', NULL, NOW(), NOW()
FROM products p
CROSS JOIN (
    SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
) AS default_warehouse
LEFT JOIN warehouse_movements wm
    ON wm.warehouse_id = default_warehouse.id
   AND wm.product_id = p.id
   AND wm.ref_type = 'product'
   AND wm.ref_id = p.id
WHERE p.item_type = 'inventory'
  AND p.current_stock > 0
  AND wm.id IS NULL;

UPDATE sales
SET warehouse_id = (
    SELECT id FROM (
        SELECT id FROM warehouses ORDER BY is_default DESC, id ASC LIMIT 1
    ) AS selected_default
)
WHERE warehouse_id IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
