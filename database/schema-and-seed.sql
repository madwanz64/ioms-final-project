-- =============================================================================
-- Inventory & Order Management System — Schema & Seed (MySQL 8)
-- Digenerate otomatis oleh scripts/generate-sql-seed.js — JANGAN diedit manual,
-- edit generator-nya lalu jalankan ulang `node scripts/generate-sql-seed.js`.
--
-- Bisa dijalankan berulang kali dengan aman dari kondisi database kosong
-- maupun yang sudah terisi (DROP TABLE IF EXISTS di awal setiap tabel).
-- =============================================================================

CREATE DATABASE IF NOT EXISTS ioms CHARACTER SET utf8mb4;
USE ioms;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS stock_ledger;
DROP TABLE IF EXISTS sales_order_items;
DROP TABLE IF EXISTS sales_orders;
DROP TABLE IF EXISTS purchase_order_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS product_stock;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS warehouses;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- users — AUTH-01, USR-01
-- -----------------------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'bcrypt hash via password_hash(), BUKAN plaintext',
  role ENUM('Admin', 'Sales', 'Warehouse Staff') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- warehouses — WH-01
-- -----------------------------------------------------------------------------
CREATE TABLE warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- categories — PRD-01
-- -----------------------------------------------------------------------------
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- suppliers — PO-01
-- -----------------------------------------------------------------------------
CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- customers — SO-01
-- -----------------------------------------------------------------------------
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- products — PRD-01. SKU dipakai sebagai primary key (business key alami,
-- konsisten dengan seluruh prototype JS yang sudah mereferensikan produk
-- lewat SKU, bukan id surrogate).
-- -----------------------------------------------------------------------------
CREATE TABLE products (
  sku VARCHAR(20) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  unit VARCHAR(20) NOT NULL,
  buy_price DECIMAL(14, 2) NOT NULL,
  sell_price DECIMAL(14, 2) NOT NULL,
  reorder_point INT UNSIGNED NOT NULL DEFAULT 0,
  image_url VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_products_buy_price CHECK (buy_price >= 0),
  CONSTRAINT chk_products_sell_price CHECK (sell_price >= 0),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
  INDEX idx_products_category (category_id),
  INDEX idx_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- product_stock — WH-01. State stok SAAT INI per gudang. Riwayat perubahan
-- selalu ada di stock_ledger; tabel ini hanya boleh diperbarui bersamaan
-- dengan menulis baris stock_ledger, dalam satu transaksi (lihat ARCH-02).
-- -----------------------------------------------------------------------------
CREATE TABLE product_stock (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_sku VARCHAR(20) NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_stock_quantity_nonnegative CHECK (quantity >= 0),
  CONSTRAINT fk_stock_product FOREIGN KEY (product_sku) REFERENCES products (sku),
  CONSTRAINT fk_stock_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  UNIQUE KEY uq_stock_product_warehouse (product_sku, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- purchase_orders + purchase_order_items — PO-01. Menerima BOLEH sebagian
-- (received_qty <= qty), makanya ada status PartiallyReceived.
-- -----------------------------------------------------------------------------
CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  status ENUM('Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled') NOT NULL DEFAULT 'Draft',
  order_date DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL COMMENT 'pembuat/pengusul PO (K-03)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_po_order_no (order_no),
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users (id),
  INDEX idx_po_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_sku VARCHAR(20) NOT NULL,
  qty INT NOT NULL,
  received_qty INT NOT NULL DEFAULT 0,
  buy_price DECIMAL(14, 2) NOT NULL,
  CONSTRAINT chk_poi_qty_positive CHECK (qty > 0),
  CONSTRAINT chk_poi_received_range CHECK (received_qty >= 0 AND received_qty <= qty),
  CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_sku) REFERENCES products (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- sales_orders + sales_order_items — SO-01. approved_by nullable karena order
-- Draft/PendingApproval belum punya approver. Aturan "Sales tidak boleh
-- approve order sendiri" ditegakkan di service layer PHP (bergantung role
-- user saat request, bukan invarian data murni), bukan lewat constraint SQL.
-- -----------------------------------------------------------------------------
CREATE TABLE sales_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  status ENUM('Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled') NOT NULL DEFAULT 'Draft',
  order_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_so_order_no (order_no),
  CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT fk_so_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_so_created_by FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT fk_so_approved_by FOREIGN KEY (approved_by) REFERENCES users (id),
  INDEX idx_so_status (status),
  INDEX idx_so_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_order_id INT UNSIGNED NOT NULL,
  product_sku VARCHAR(20) NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(14, 2) NOT NULL,
  CONSTRAINT chk_soi_qty_positive CHECK (qty > 0),
  CONSTRAINT fk_soi_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_soi_product FOREIGN KEY (product_sku) REFERENCES products (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- stock_ledger — satu-satunya sumber kebenaran riwayat pergerakan stok.
-- idx_ledger_created_at dipakai untuk filter rentang tanggal pada REPORT-01
-- (WHERE created_at BETWEEN :start AND :end).
-- -----------------------------------------------------------------------------
CREATE TABLE stock_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_sku VARCHAR(20) NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  movement_type ENUM('Receipt', 'Issue', 'Adjustment') NOT NULL,
  quantity INT NOT NULL COMMENT 'positif = stok masuk, negatif = stok keluar',
  ref_type ENUM('PO', 'SO', 'ADJ', 'TRF') NOT NULL,
  ref_id VARCHAR(30) NOT NULL COMMENT 'nomor PO/SO/transfer terkait',
  performed_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_product FOREIGN KEY (product_sku) REFERENCES products (sku),
  CONSTRAINT fk_ledger_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_ledger_user FOREIGN KEY (performed_by) REFERENCES users (id),
  INDEX idx_ledger_product_warehouse (product_sku, warehouse_id),
  INDEX idx_ledger_created_at (created_at),
  INDEX idx_ledger_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- stock_transfers + stock_transfer_items — perpindahan stok antar-gudang (K-08).
-- Dokumen transfer hanya mencatat "apa dipindah ke mana oleh siapa"; perubahan
-- stoknya selalu lewat stock_ledger: Issue di gudang asal + Receipt di gudang
-- tujuan (ref_type 'TRF'), dalam satu transaksi bersama dokumen ini.
-- -----------------------------------------------------------------------------
CREATE TABLE stock_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_no VARCHAR(30) NOT NULL,
  from_warehouse_id INT UNSIGNED NOT NULL,
  to_warehouse_id INT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transfer_no (transfer_no),
  CONSTRAINT chk_transfer_different_warehouse CHECK (from_warehouse_id <> to_warehouse_id),
  CONSTRAINT fk_transfer_from FOREIGN KEY (from_warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_transfer_to FOREIGN KEY (to_warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_transfer_created_by FOREIGN KEY (created_by) REFERENCES users (id),
  INDEX idx_transfer_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_transfer_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stock_transfer_id INT UNSIGNED NOT NULL,
  product_sku VARCHAR(20) NOT NULL,
  qty INT NOT NULL,
  CONSTRAINT chk_sti_qty_positive CHECK (qty > 0),
  CONSTRAINT fk_sti_transfer FOREIGN KEY (stock_transfer_id) REFERENCES stock_transfers (id) ON DELETE CASCADE,
  CONSTRAINT fk_sti_product FOREIGN KEY (product_sku) REFERENCES products (sku),
  UNIQUE KEY uq_sti_transfer_product (stock_transfer_id, product_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- CONTOH TRANSAKSI MULTI-TABEL (ARCH-02) — bukti untuk DB-01
-- Ini pola yang akan dipakai StockService::processGoodsIssue() di PHP nanti:
-- goods issue HARUS mengubah product_stock dan menulis stock_ledger dalam
-- satu transaksi atomik, dengan validasi stok dilakukan ULANG tepat sebelum
-- commit (bukan hanya saat halaman dimuat) untuk mencegah oversell saat dua
-- request diproses hampir bersamaan.
--
-- START TRANSACTION;
--
--   -- 1. Kunci & baca ulang baris stok yang relevan (FOR UPDATE mencegah
--   --    request lain membaca/mengubah baris yang sama sebelum transaksi ini selesai)
--   SELECT quantity FROM product_stock
--     WHERE product_sku = 'SKU-0002' AND warehouse_id = 1
--     FOR UPDATE;
--
--   -- 2. Validasi di PHP: jika quantity < qty yang diminta -> ROLLBACK, tolak.
--
--   -- 3. Kurangi stok
--   UPDATE product_stock SET quantity = quantity - 5
--     WHERE product_sku = 'SKU-0002' AND warehouse_id = 1;
--
--   -- 4. Catat pergerakan di ledger (jejak audit, sumber kebenaran riwayat)
--   INSERT INTO stock_ledger (product_sku, warehouse_id, movement_type, quantity, ref_type, ref_id, performed_by)
--     VALUES ('SKU-0002', 1, 'Issue', -5, 'SO', 'SO-2026-0087', 4);
--
--   -- 5. Update status order
--   UPDATE sales_orders SET status = 'Fulfilled' WHERE id = 87;
--
-- COMMIT;
--
-- Kalau salah satu langkah gagal (mis. constraint quantity >= 0 dilanggar karena
-- ada request lain yang lebih dulu menghabiskan stok), seluruh transaksi
-- di-ROLLBACK otomatis oleh MySQL — tidak ada perubahan sebagian (partial write)
-- yang bisa membuat product_stock dan stock_ledger jadi tidak sinkron.
-- =============================================================================

-- =============================================================================
-- SEED DATA (dikonversi dari prototype/data/*.json — lihat scripts/generate-sql-seed.js)
-- =============================================================================

INSERT INTO categories (id, name, description) VALUES
  (1, 'Elektronik', 'Perangkat dan aksesoris elektronik'),
  (2, 'ATK', 'Alat tulis kantor'),
  (3, 'Aksesoris', 'Aksesoris pendukung perangkat');

INSERT INTO warehouses (id, name, location, active) VALUES
  (1, 'Gudang Jakarta', 'Jl. Industri No. 12, Jakarta Utara', 1),
  (2, 'Gudang Surabaya', 'Jl. Rungkut Industri No. 5, Surabaya', 1);

INSERT INTO users (id, name, email, password, role, active) VALUES
  (1, 'Budi Santoso', 'admin@ioms.test', '$2b$10$TDJXe.p.tKr1upaiXGJwjueCPjawK/o1kdIiD34URgoYD.iepD1IS', 'Admin', 1),
  (2, 'Sinta Wulandari', 'sinta@ioms.test', '$2b$10$F.WypOyZdNHFJXZsihFkMOfZGXFSFWws4qtjMDcHHn9d.mm/gps3u', 'Sales', 1),
  (3, 'Doni Pratama', 'doni@ioms.test', '$2b$10$F.WypOyZdNHFJXZsihFkMOfZGXFSFWws4qtjMDcHHn9d.mm/gps3u', 'Sales', 1),
  (4, 'Rudi Hartono', 'rudi@ioms.test', '$2b$10$.25Ky2jMAaZBxmj3tnhfleEw.ayn17hXV3CwlkN7wjFtoCkvQronu', 'Warehouse Staff', 1),
  (5, 'Agus Setiawan', 'agus@ioms.test', '$2b$10$.25Ky2jMAaZBxmj3tnhfleEw.ayn17hXV3CwlkN7wjFtoCkvQronu', 'Warehouse Staff', 0),
  (6, 'Wulan Sari', 'wulan@ioms.test', '$2b$10$.25Ky2jMAaZBxmj3tnhfleEw.ayn17hXV3CwlkN7wjFtoCkvQronu', 'Warehouse Staff', 1);

INSERT INTO suppliers (id, name, contact, address, active) VALUES
  (1, 'CV Elektronik Jaya', '021-5551234', 'Jakarta Barat', 1),
  (2, 'PT Kertas Nusantara', '021-5559876', 'Tangerang', 1),
  (3, 'UD Sumber Aksesoris', '0813-9988-2211', 'Bekasi', 1),
  (4, 'PT Sumber Plastik', '024-8765432', 'Semarang', 1),
  (5, 'Toko Aksesoris Komputer', '0857-2233-4455', 'Surabaya', 0);

INSERT INTO customers (id, name, contact, address, active) VALUES
  (1, 'PT Sumber Makmur', '021-7778899', 'Jakarta Selatan', 1),
  (2, 'Toko Jaya Abadi', '0812-1122-3344', 'Surabaya', 1),
  (3, 'CV Berkah', '0819-5566-7788', 'Bandung', 1),
  (4, 'UD Makmur Sentosa', '0857-1234-5678', 'Semarang', 1),
  (5, 'Toko Elektronik Cahaya', '0813-9900-1122', 'Jakarta Barat', 1);

INSERT INTO products (sku, name, category_id, unit, buy_price, sell_price, reorder_point, image_url, active) VALUES
  ('SKU-0001', 'Kabel HDMI 2m', 3, 'pcs', 30000, 45000, 10, NULL, 1),
  ('SKU-0002', 'Mouse Wireless', 1, 'pcs', 85000, 120000, 15, NULL, 1),
  ('SKU-0003', 'Keyboard Mechanical', 1, 'pcs', 480000, 650000, 8, NULL, 1),
  ('SKU-0004', 'Kertas A4 80gr', 2, 'rim', 42000, 52000, 20, NULL, 1),
  ('SKU-0005', 'Map Plastik', 2, 'pcs', 3000, 5000, 30, NULL, 1),
  ('SKU-0006', 'Monitor LED 24"', 1, 'pcs', 1450000, 1850000, 5, NULL, 1),
  ('SKU-0007', 'Headset USB', 3, 'pcs', 95000, 135000, 12, NULL, 1),
  ('SKU-0008', 'Flashdisk 32GB', 1, 'pcs', 55000, 80000, 25, NULL, 1),
  ('SKU-0009', 'Printer Inkjet', 1, 'pcs', 890000, 1150000, 4, NULL, 1),
  ('SKU-0010', 'Tinta Printer Hitam', 2, 'pcs', 38000, 55000, 20, NULL, 1),
  ('SKU-0011', 'Pulpen Pilot', 2, 'pcs', 2500, 4000, 50, NULL, 1),
  ('SKU-0012', 'Stapler Kecil', 2, 'pcs', 12000, 18000, 15, NULL, 1),
  ('SKU-0013', 'Isi Staples No.10', 2, 'box', 4000, 7000, 30, NULL, 1),
  ('SKU-0014', 'Kabel LAN 5m', 3, 'pcs', 25000, 38000, 12, NULL, 1),
  ('SKU-0015', 'Adaptor Charger Laptop', 1, 'pcs', 210000, 290000, 6, NULL, 1),
  ('SKU-0016', 'Speaker Bluetooth Mini', 1, 'pcs', 140000, 195000, 10, NULL, 1),
  ('SKU-0017', 'Webcam HD', 1, 'pcs', 175000, 240000, 8, NULL, 1),
  ('SKU-0018', 'Tas Laptop', 3, 'pcs', 95000, 140000, 10, NULL, 1),
  ('SKU-0019', 'Whiteboard 60x90', 2, 'pcs', 110000, 155000, 5, NULL, 1),
  ('SKU-0020', 'Spidol Whiteboard', 2, 'pcs', 6000, 9000, 40, NULL, 1),
  ('SKU-0021', 'Router WiFi', 1, 'pcs', 220000, 310000, 6, NULL, 1),
  ('SKU-0022', 'Power Bank 10000mAh', 3, 'pcs', 105000, 150000, 10, NULL, 1),
  ('SKU-0023', 'Kalkulator Scientific', 2, 'pcs', 65000, 95000, 10, NULL, 1),
  ('SKU-0024', 'Ordner Arsip', 2, 'pcs', 15000, 22000, 20, NULL, 1),
  ('SKU-0025', 'Mousepad Gaming', 3, 'pcs', 25000, 40000, 15, NULL, 1),
  ('SKU-0026', 'Hardisk Eksternal 1TB', 1, 'pcs', 520000, 690000, 5, NULL, 1),
  ('SKU-0027', 'Proyektor Mini', 1, 'pcs', 650000, 850000, 4, NULL, 1),
  ('SKU-0028', 'Lampu Meja LED', 1, 'pcs', 85000, 125000, 8, NULL, 1),
  ('SKU-0029', 'Cutter Kertas', 2, 'pcs', 8000, 14000, 15, NULL, 1),
  ('SKU-0030', 'Rak Arsip Kecil', 2, 'pcs', 95000, 140000, 6, NULL, 1),
  ('SKU-0031', 'Charger Wireless', 3, 'pcs', 120000, 175000, 10, NULL, 1),
  ('SKU-0032', 'Baterai AA (4pcs)', 3, 'pack', 15000, 25000, 20, NULL, 1);

INSERT INTO product_stock (product_sku, warehouse_id, quantity) VALUES
  ('SKU-0001', 1, 3),
  ('SKU-0001', 2, 1),
  ('SKU-0002', 1, 1),
  ('SKU-0002', 2, 1),
  ('SKU-0003', 1, 34),
  ('SKU-0003', 2, 20),
  ('SKU-0004', 1, 5),
  ('SKU-0004', 2, 3),
  ('SKU-0005', 1, 48),
  ('SKU-0005', 2, 30),
  ('SKU-0006', 1, 2),
  ('SKU-0006', 2, 0),
  ('SKU-0007', 1, 62),
  ('SKU-0007', 2, 40),
  ('SKU-0008', 1, 69),
  ('SKU-0008', 2, 45),
  ('SKU-0009', 1, 1),
  ('SKU-0009', 2, 0),
  ('SKU-0010', 1, 23),
  ('SKU-0010', 2, 15),
  ('SKU-0011', 1, 30),
  ('SKU-0011', 2, 20),
  ('SKU-0012', 1, 37),
  ('SKU-0012', 2, 25),
  ('SKU-0013', 1, 44),
  ('SKU-0013', 2, 30),
  ('SKU-0014', 1, 51),
  ('SKU-0014', 2, 35),
  ('SKU-0015', 1, 58),
  ('SKU-0015', 2, 40),
  ('SKU-0016', 1, 65),
  ('SKU-0016', 2, 45),
  ('SKU-0017', 1, 72),
  ('SKU-0017', 2, 10),
  ('SKU-0018', 1, 79),
  ('SKU-0018', 2, 15),
  ('SKU-0019', 1, 26),
  ('SKU-0019', 2, 20),
  ('SKU-0020', 1, 33),
  ('SKU-0020', 2, 25),
  ('SKU-0021', 1, 40),
  ('SKU-0021', 2, 30),
  ('SKU-0022', 1, 47),
  ('SKU-0022', 2, 35),
  ('SKU-0023', 1, 54),
  ('SKU-0023', 2, 40),
  ('SKU-0024', 1, 61),
  ('SKU-0024', 2, 45),
  ('SKU-0025', 1, 68),
  ('SKU-0025', 2, 10),
  ('SKU-0026', 1, 75),
  ('SKU-0026', 2, 15),
  ('SKU-0027', 1, 22),
  ('SKU-0027', 2, 20),
  ('SKU-0028', 1, 29),
  ('SKU-0028', 2, 25),
  ('SKU-0029', 1, 36),
  ('SKU-0029', 2, 30),
  ('SKU-0030', 1, 43),
  ('SKU-0030', 2, 35),
  ('SKU-0031', 1, 50),
  ('SKU-0031', 2, 40),
  ('SKU-0032', 1, 57),
  ('SKU-0032', 2, 45);

INSERT INTO purchase_orders (id, order_no, supplier_id, warehouse_id, status, order_date, created_by) VALUES
  (1, 'PO-2026-0001', 1, 1, 'Received', '2026-08-15', 1),
  (2, 'PO-2026-0002', 2, 1, 'Received', '2026-08-17', 4),
  (3, 'PO-2026-0003', 3, 2, 'Received', '2026-08-19', 6),
  (4, 'PO-2026-0004', 1, 1, 'PartiallyReceived', '2026-08-25', 1),
  (5, 'PO-2026-0005', 2, 1, 'PartiallyReceived', '2026-08-27', 4),
  (6, 'PO-2026-0006', 1, 1, 'Ordered', '2026-08-30', 6),
  (7, 'PO-2026-0007', 4, 2, 'Ordered', '2026-08-31', 1),
  (8, 'PO-2026-0008', 3, 2, 'Draft', '2026-09-02', 4),
  (9, 'PO-2026-0009', 2, 1, 'Draft', '2026-09-03', 6),
  (10, 'PO-2026-0010', 1, 1, 'Cancelled', '2026-08-23', 1),
  (11, 'PO-2026-0011', 4, 1, 'Ordered', '2026-09-01', 4),
  (12, 'PO-2026-0012', 2, 2, 'Draft', '2026-09-03', 6);

INSERT INTO purchase_order_items (purchase_order_id, product_sku, qty, received_qty, buy_price) VALUES
  (1, 'SKU-0006', 5, 5, 1450000),
  (2, 'SKU-0004', 50, 50, 42000),
  (2, 'SKU-0005', 100, 100, 3000),
  (3, 'SKU-0001', 20, 20, 30000),
  (4, 'SKU-0009', 10, 4, 890000),
  (5, 'SKU-0004', 30, 10, 42000),
  (5, 'SKU-0010', 40, 40, 38000),
  (6, 'SKU-0002', 15, 0, 85000),
  (7, 'SKU-0005', 50, 0, 3000),
  (8, 'SKU-0007', 10, 0, 95000),
  (9, 'SKU-0011', 100, 0, 2500),
  (10, 'SKU-0003', 5, 0, 480000),
  (11, 'SKU-0028', 20, 0, 85000),
  (12, 'SKU-0029', 50, 0, 8000);

INSERT INTO sales_orders (id, order_no, customer_id, warehouse_id, created_by, approved_by, status, order_date) VALUES
  (1, 'SO-2026-0001', 1, 1, 2, 1, 'Fulfilled', '2026-08-21'),
  (2, 'SO-2026-0002', 2, 1, 2, 1, 'Fulfilled', '2026-08-22'),
  (3, 'SO-2026-0003', 3, 2, 3, 1, 'Fulfilled', '2026-08-23'),
  (4, 'SO-2026-0004', 1, 1, 2, 1, 'Cancelled', '2026-08-24'),
  (5, 'SO-2026-0005', 4, 2, 3, 1, 'Fulfilled', '2026-08-25'),
  (6, 'SO-2026-0006', 2, 1, 2, 1, 'Approved', '2026-08-29'),
  (7, 'SO-2026-0007', 5, 1, 3, 1, 'Approved', '2026-08-30'),
  (8, 'SO-2026-0008', 3, 2, 2, NULL, 'PendingApproval', '2026-08-31'),
  (9, 'SO-2026-0009', 1, 1, 3, NULL, 'PendingApproval', '2026-09-01'),
  (10, 'SO-2026-0010', 4, 1, 2, NULL, 'PendingApproval', '2026-09-02'),
  (11, 'SO-2026-0011', 2, 2, 3, NULL, 'Draft', '2026-09-03'),
  (12, 'SO-2026-0012', 5, 1, 2, NULL, 'Draft', '2026-09-03'),
  (13, 'SO-2026-0013', 3, 1, 3, 1, 'Fulfilled', '2026-08-26'),
  (14, 'SO-2026-0014', 1, 2, 2, 1, 'Fulfilled', '2026-08-27'),
  (15, 'SO-2026-0015', 3, 1, 3, 1, 'Fulfilled', '2026-08-28'),
  (16, 'SO-2026-0016', 4, 2, 2, NULL, 'PendingApproval', '2026-09-03');

INSERT INTO sales_order_items (sales_order_id, product_sku, qty, price) VALUES
  (1, 'SKU-0001', 2, 45000),
  (1, 'SKU-0002', 5, 120000),
  (2, 'SKU-0003', 10, 650000),
  (3, 'SKU-0004', 20, 52000),
  (4, 'SKU-0005', 3, 5000),
  (5, 'SKU-0006', 4, 1850000),
  (6, 'SKU-0001', 1, 45000),
  (7, 'SKU-0007', 6, 135000),
  (8, 'SKU-0002', 3, 120000),
  (9, 'SKU-0008', 2, 80000),
  (9, 'SKU-0009', 1, 1150000),
  (10, 'SKU-0010', 15, 55000),
  (11, 'SKU-0011', 5, 4000),
  (12, 'SKU-0012', 10, 18000),
  (13, 'SKU-0013', 6, 7000),
  (14, 'SKU-0014', 2, 38000),
  (15, 'SKU-0015', 2, 290000),
  (16, 'SKU-0027', 1, 850000);

INSERT INTO stock_ledger (product_sku, warehouse_id, movement_type, quantity, ref_type, ref_id, performed_by, created_at) VALUES
  ('SKU-0001', 1, 'Adjustment', 5, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0002', 1, 'Adjustment', 6, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0002', 2, 'Adjustment', 1, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0003', 1, 'Adjustment', 44, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0003', 2, 'Adjustment', 20, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0004', 2, 'Adjustment', 23, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0005', 2, 'Adjustment', 30, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0006', 2, 'Adjustment', 4, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0007', 1, 'Adjustment', 62, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0007', 2, 'Adjustment', 40, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0008', 1, 'Adjustment', 69, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0008', 2, 'Adjustment', 45, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0010', 2, 'Adjustment', 15, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0011', 1, 'Adjustment', 30, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0011', 2, 'Adjustment', 20, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0012', 1, 'Adjustment', 37, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0012', 2, 'Adjustment', 25, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0013', 1, 'Adjustment', 50, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0013', 2, 'Adjustment', 30, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0014', 1, 'Adjustment', 51, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0014', 2, 'Adjustment', 37, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0015', 1, 'Adjustment', 60, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0015', 2, 'Adjustment', 40, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0016', 1, 'Adjustment', 65, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0016', 2, 'Adjustment', 45, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0017', 1, 'Adjustment', 72, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0017', 2, 'Adjustment', 10, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0018', 1, 'Adjustment', 79, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0018', 2, 'Adjustment', 15, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0019', 1, 'Adjustment', 26, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0019', 2, 'Adjustment', 20, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0020', 1, 'Adjustment', 33, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0020', 2, 'Adjustment', 25, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0021', 1, 'Adjustment', 40, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0021', 2, 'Adjustment', 30, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0022', 1, 'Adjustment', 47, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0022', 2, 'Adjustment', 35, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0023', 1, 'Adjustment', 54, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0023', 2, 'Adjustment', 40, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0024', 1, 'Adjustment', 61, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0024', 2, 'Adjustment', 45, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0025', 1, 'Adjustment', 68, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0025', 2, 'Adjustment', 10, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0026', 1, 'Adjustment', 75, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0026', 2, 'Adjustment', 15, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0027', 1, 'Adjustment', 22, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0027', 2, 'Adjustment', 20, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0028', 1, 'Adjustment', 29, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0028', 2, 'Adjustment', 25, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0029', 1, 'Adjustment', 36, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0029', 2, 'Adjustment', 30, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0030', 1, 'Adjustment', 43, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0030', 2, 'Adjustment', 35, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0031', 1, 'Adjustment', 50, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0031', 2, 'Adjustment', 40, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0032', 1, 'Adjustment', 57, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0032', 2, 'Adjustment', 45, 'ADJ', 'OPENING', 1, '2026-08-01 08:00:00'),
  ('SKU-0006', 1, 'Receipt', 5, 'PO', 'PO-2026-0001', 4, '2026-08-15 10:00:00'),
  ('SKU-0004', 1, 'Receipt', 50, 'PO', 'PO-2026-0002', 4, '2026-08-17 10:00:00'),
  ('SKU-0005', 1, 'Receipt', 100, 'PO', 'PO-2026-0002', 4, '2026-08-17 10:00:00'),
  ('SKU-0001', 2, 'Receipt', 20, 'PO', 'PO-2026-0003', 4, '2026-08-19 10:00:00'),
  ('SKU-0001', 1, 'Issue', -2, 'SO', 'SO-2026-0001', 4, '2026-08-21 14:00:00'),
  ('SKU-0002', 1, 'Issue', -5, 'SO', 'SO-2026-0001', 4, '2026-08-21 14:00:00'),
  ('SKU-0003', 1, 'Issue', -10, 'SO', 'SO-2026-0002', 4, '2026-08-22 14:00:00'),
  ('SKU-0004', 2, 'Issue', -20, 'SO', 'SO-2026-0003', 4, '2026-08-23 14:00:00'),
  ('SKU-0009', 1, 'Receipt', 4, 'PO', 'PO-2026-0004', 4, '2026-08-25 10:00:00'),
  ('SKU-0006', 2, 'Issue', -4, 'SO', 'SO-2026-0005', 4, '2026-08-25 14:00:00'),
  ('SKU-0013', 1, 'Issue', -6, 'SO', 'SO-2026-0013', 4, '2026-08-26 14:00:00'),
  ('SKU-0004', 1, 'Receipt', 10, 'PO', 'PO-2026-0005', 4, '2026-08-27 10:00:00'),
  ('SKU-0010', 1, 'Receipt', 40, 'PO', 'PO-2026-0005', 4, '2026-08-27 10:00:00'),
  ('SKU-0014', 2, 'Issue', -2, 'SO', 'SO-2026-0014', 4, '2026-08-27 14:00:00'),
  ('SKU-0015', 1, 'Issue', -2, 'SO', 'SO-2026-0015', 4, '2026-08-28 14:00:00'),
  ('SKU-0001', 2, 'Adjustment', -19, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00'),
  ('SKU-0004', 1, 'Adjustment', -55, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00'),
  ('SKU-0005', 1, 'Adjustment', -52, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00'),
  ('SKU-0006', 1, 'Adjustment', -3, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00'),
  ('SKU-0009', 1, 'Adjustment', -3, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00'),
  ('SKU-0010', 1, 'Adjustment', -17, 'ADJ', 'OPNAME-2026-09', 1, '2026-09-01 09:00:00');

