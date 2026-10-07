// Generator database/schema-and-seed.sql dari:
//  - DDL yang ditulis manual di bawah (SCHEMA_SQL)
//  - data seed yang SAMA dengan prototype/data/*.json (dikonversi jadi INSERT),
//    supaya prototype JS dan seed MySQL tetap konsisten satu sama lain.
// Pakai: node scripts/generate-sql-seed.js
//
// CATATAN: password di prototype/data/users.json masih plaintext (khusus demo
// client-side, lihat komentar di prototype/js/auth.js). Untuk seed SQL ini,
// password digantikan hash bcrypt asli (dibuat sekali lewat bcryptjs) supaya
// baris user siap diverifikasi dengan password_verify() PHP yang sesungguhnya.
const fs = require('node:fs');
const path = require('node:path');

const DATA_DIR = path.join(__dirname, '..', 'prototype', 'data');
const OUT_FILE = path.join(__dirname, '..', 'database', 'schema-and-seed.sql');

function readJson(file) {
  return JSON.parse(fs.readFileSync(path.join(DATA_DIR, file), 'utf8'));
}

// Hash bcrypt asli untuk password demo (dibuat sekali via bcryptjs, cost 10).
// Kompatibel dengan password_verify() PHP (format $2b$ diterima sama seperti $2y$).
const PASSWORD_HASHES = {
  admin123: '$2b$10$TDJXe.p.tKr1upaiXGJwjueCPjawK/o1kdIiD34URgoYD.iepD1IS',
  sales123: '$2b$10$F.WypOyZdNHFJXZsihFkMOfZGXFSFWws4qtjMDcHHn9d.mm/gps3u',
  gudang123: '$2b$10$.25Ky2jMAaZBxmj3tnhfleEw.ayn17hXV3CwlkN7wjFtoCkvQronu',
};

function sqlStr(value) {
  if (value === null || value === undefined) return 'NULL';
  return "'" + String(value).replaceAll('\\', '\\\\').replaceAll("'", "''") + "'";
}
function sqlBool(value) {
  return value ? 1 : 0;
}
function sqlNum(value) {
  return value === null || value === undefined ? 'NULL' : Number(value);
}

function insertBlock(table, columns, rows) {
  if (rows.length === 0) return `-- (tidak ada data untuk ${table})\n`;
  const values = rows.map((r) => `  (${columns.map((c) => r[c]).join(', ')})`).join(',\n');
  return `INSERT INTO ${table} (${columns.map((c) => c.replace(/^v_/, '')).join(', ')}) VALUES\n${values};\n\n`;
}

// ===========================================================================
// DDL — struktur tabel (lihat juga docs/planning/erd.md untuk diagramnya)
// ===========================================================================
const SCHEMA_SQL = `-- =============================================================================
-- Inventory & Order Management System — Schema & Seed (MySQL 8)
-- Digenerate otomatis oleh scripts/generate-sql-seed.js — JANGAN diedit manual,
-- edit generator-nya lalu jalankan ulang \`node scripts/generate-sql-seed.js\`.
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

`;

// ===========================================================================
// Seed — dikonversi dari prototype/data/*.json
// ===========================================================================
function buildSeedSql() {
  let sql = '-- =============================================================================\n';
  sql += '-- SEED DATA (dikonversi dari prototype/data/*.json — lihat scripts/generate-sql-seed.js)\n';
  sql += '-- =============================================================================\n\n';

  // --- categories ---
  const categories = readJson('categories.json');
  sql += insertBlock(
    'categories',
    ['id', 'name', 'description'],
    categories.map((c) => ({ id: c.id, name: sqlStr(c.name), description: sqlStr(c.description) }))
  );

  // --- warehouses ---
  const warehouses = readJson('warehouses.json');
  sql += insertBlock(
    'warehouses',
    ['id', 'name', 'location', 'active'],
    warehouses.map((w) => ({ id: w.id, name: sqlStr(w.name), location: sqlStr(w.location), active: sqlBool(w.active) }))
  );

  // --- users (password plaintext -> hash bcrypt) ---
  const users = readJson('users.json');
  sql += insertBlock(
    'users',
    ['id', 'name', 'email', 'password', 'role', 'active'],
    users.map((u) => ({
      id: u.id,
      name: sqlStr(u.name),
      email: sqlStr(u.email),
      password: sqlStr(PASSWORD_HASHES[u.password] || u.password),
      role: sqlStr(u.role),
      active: sqlBool(u.active),
    }))
  );

  // --- suppliers ---
  const suppliers = readJson('suppliers.json');
  sql += insertBlock(
    'suppliers',
    ['id', 'name', 'contact', 'address', 'active'],
    suppliers.map((s) => ({ id: s.id, name: sqlStr(s.name), contact: sqlStr(s.contact), address: sqlStr(s.address), active: sqlBool(s.active) }))
  );

  // --- customers ---
  const customers = readJson('customers.json');
  sql += insertBlock(
    'customers',
    ['id', 'name', 'contact', 'address', 'active'],
    customers.map((c) => ({ id: c.id, name: sqlStr(c.name), contact: sqlStr(c.contact), address: sqlStr(c.address), active: sqlBool(c.active) }))
  );

  // --- products ---
  const products = readJson('products.json');
  sql += insertBlock(
    'products',
    ['sku', 'name', 'category_id', 'unit', 'buy_price', 'sell_price', 'reorder_point', 'image_url', 'active'],
    products.map((p) => ({
      sku: sqlStr(p.sku),
      name: sqlStr(p.name),
      category_id: p.categoryId,
      unit: sqlStr(p.unit),
      buy_price: sqlNum(p.buyPrice),
      sell_price: sqlNum(p.sellPrice),
      reorder_point: sqlNum(p.reorderPoint),
      image_url: sqlStr(p.imageUrl),
      active: sqlBool(p.active),
    }))
  );

  // --- product_stock ---
  const productStock = readJson('product-stock.json');
  sql += insertBlock(
    'product_stock',
    ['product_sku', 'warehouse_id', 'quantity'],
    productStock.map((s) => ({ product_sku: sqlStr(s.sku), warehouse_id: s.warehouseId, quantity: sqlNum(s.quantity) }))
  );

  // --- purchase_orders + items ---
  const purchaseOrders = readJson('purchase-orders.json');
  sql += insertBlock(
    'purchase_orders',
    ['id', 'order_no', 'supplier_id', 'warehouse_id', 'status', 'order_date', 'created_by'],
    purchaseOrders.map((o) => ({
      id: o.id,
      order_no: sqlStr(o.orderNo),
      supplier_id: o.supplierId,
      warehouse_id: o.warehouseId,
      status: sqlStr(o.status),
      order_date: sqlStr(o.createdAt),
      // K-03: data prototype tidak mencatat pembuat PO, jadi seed membagi secara
      // deterministik ke Admin (1), Rudi (4), dan Wulan (6) — Admin & Warehouse Staff.
      created_by: [1, 4, 6][(o.id - 1) % 3],
    }))
  );
  const poItems = [];
  purchaseOrders.forEach((o) => {
    o.items.forEach((item) => {
      poItems.push({
        purchase_order_id: o.id,
        product_sku: sqlStr(item.sku),
        qty: sqlNum(item.qty),
        received_qty: sqlNum(item.receivedQty),
        buy_price: sqlNum(item.buyPrice),
      });
    });
  });
  sql += insertBlock('purchase_order_items', ['purchase_order_id', 'product_sku', 'qty', 'received_qty', 'buy_price'], poItems);

  // --- sales_orders + items ---
  const salesOrders = readJson('sales-orders.json');
  sql += insertBlock(
    'sales_orders',
    ['id', 'order_no', 'customer_id', 'warehouse_id', 'created_by', 'approved_by', 'status', 'order_date'],
    salesOrders.map((o) => ({
      id: o.id,
      order_no: sqlStr(o.orderNo),
      customer_id: o.customerId,
      warehouse_id: o.warehouseId,
      created_by: o.createdBy,
      approved_by: sqlNum(o.approvedBy),
      status: sqlStr(o.status),
      order_date: sqlStr(o.createdAt),
    }))
  );
  const soItems = [];
  salesOrders.forEach((o) => {
    o.items.forEach((item) => {
      soItems.push({ sales_order_id: o.id, product_sku: sqlStr(item.sku), qty: sqlNum(item.qty), price: sqlNum(item.price) });
    });
  });
  sql += insertBlock('sales_order_items', ['sales_order_id', 'product_sku', 'qty', 'price'], soItems);

  // --- stock_ledger ---
  const stockLedger = readJson('stock-ledger.json');
  sql += insertBlock(
    'stock_ledger',
    ['product_sku', 'warehouse_id', 'movement_type', 'quantity', 'ref_type', 'ref_id', 'performed_by', 'created_at'],
    stockLedger.map((l) => ({
      product_sku: sqlStr(l.sku),
      warehouse_id: l.warehouseId,
      movement_type: sqlStr(l.type),
      quantity: sqlNum(l.quantity),
      ref_type: sqlStr(l.refType),
      ref_id: sqlStr(l.refId),
      performed_by: l.byUserId,
      created_at: sqlStr(l.timestamp.replace('T', ' ')),
    }))
  );

  return sql;
}

fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
fs.writeFileSync(OUT_FILE, SCHEMA_SQL + buildSeedSql(), 'utf8');
console.log('wrote', path.relative(process.cwd(), OUT_FILE));
