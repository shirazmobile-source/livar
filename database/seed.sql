INSERT INTO categories (name, slug, status, created_at, updated_at)
VALUES
('General', 'general', 'active', NOW(), NOW());

INSERT INTO units (name, code, status, created_at, updated_at)
VALUES
('pcs', 'PCS', 'active', NOW(), NOW()),
('box', 'BOX', 'active', NOW(), NOW()),
('kg', 'KG', 'active', NOW(), NOW());

INSERT INTO currencies (name, code, symbol, rate_to_aed, is_default, status, created_at, updated_at)
VALUES
('UAE Dirham', 'AED', 'د.إ', 1.00000000, 1, 'active', NOW(), NOW()),
('US Dollar', 'USD', '$', 3.65000000, 0, 'active', NOW(), NOW());

INSERT INTO warehouses (code, name, location, manager_name, phone, notes, is_default, status, created_at, updated_at)
VALUES
('WH-001', 'Main Warehouse', 'Dubai, UAE', NULL, NULL, 'Created by installer.', 1, 'active', NOW(), NOW());
