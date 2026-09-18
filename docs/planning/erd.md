# Entity Relationship Diagram — Inventory & Order Management System

Skema ini menerjemahkan model data §1.3 Project Brief (yang sebelumnya sudah
divalidasi lewat prototype JSON+localStorage) menjadi tabel relasional MySQL 8.
Implementasi SQL-nya ada di [`database/schema-and-seed.sql`](../../database/schema-and-seed.sql).

```mermaid
erDiagram
    CATEGORIES ||--o{ PRODUCTS : "mengelompokkan"
    PRODUCTS ||--o{ PRODUCT_STOCK : "punya stok per"
    WAREHOUSES ||--o{ PRODUCT_STOCK : "menyimpan"
    WAREHOUSES ||--o{ PURCHASE_ORDERS : "tujuan penerimaan"
    WAREHOUSES ||--o{ SALES_ORDERS : "asal pengeluaran"
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "memasok"
    CUSTOMERS ||--o{ SALES_ORDERS : "memesan"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "berisi"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "dipesan sebagai"
    SALES_ORDERS ||--o{ SALES_ORDER_ITEMS : "berisi"
    PRODUCTS ||--o{ SALES_ORDER_ITEMS : "dipesan sebagai"
    USERS ||--o{ SALES_ORDERS : "membuat (created_by)"
    USERS ||--o{ SALES_ORDERS : "menyetujui (approved_by)"
    PRODUCTS ||--o{ STOCK_LEDGER : "pergerakan"
    WAREHOUSES ||--o{ STOCK_LEDGER : "lokasi pergerakan"
    USERS ||--o{ STOCK_LEDGER : "melakukan"

    USERS {
        int id PK
        varchar name
        varchar email UK
        varchar password "bcrypt hash"
        enum role "Admin/Sales/Warehouse Staff"
        boolean active
    }
    WAREHOUSES {
        int id PK
        varchar name
        varchar location
        boolean active
    }
    CATEGORIES {
        int id PK
        varchar name
        varchar description
    }
    SUPPLIERS {
        int id PK
        varchar name
        varchar contact
        varchar address
        boolean active
    }
    CUSTOMERS {
        int id PK
        varchar name
        varchar contact
        varchar address
        boolean active
    }
    PRODUCTS {
        varchar sku PK
        varchar name
        int category_id FK
        varchar unit
        decimal buy_price
        decimal sell_price
        int reorder_point
        varchar image_url
        boolean active
    }
    PRODUCT_STOCK {
        int id PK
        varchar product_sku FK
        int warehouse_id FK
        int quantity "CHECK >= 0"
        timestamp updated_at
    }
    PURCHASE_ORDERS {
        int id PK
        varchar order_no UK
        int supplier_id FK
        int warehouse_id FK
        enum status "Draft/Ordered/PartiallyReceived/Received/Cancelled"
        date order_date
    }
    PURCHASE_ORDER_ITEMS {
        int id PK
        int purchase_order_id FK
        varchar product_sku FK
        int qty "CHECK > 0"
        int received_qty "CHECK 0..qty"
        decimal buy_price
    }
    SALES_ORDERS {
        int id PK
        varchar order_no UK
        int customer_id FK
        int warehouse_id FK
        int created_by FK
        int approved_by FK "nullable"
        enum status "Draft/PendingApproval/Approved/Fulfilled/Cancelled"
        date order_date
    }
    SALES_ORDER_ITEMS {
        int id PK
        int sales_order_id FK
        varchar product_sku FK
        int qty "CHECK > 0"
        decimal price
    }
    STOCK_LEDGER {
        int id PK
        varchar product_sku FK
        int warehouse_id FK
        enum movement_type "Receipt/Issue/Adjustment"
        int quantity "positif=masuk, negatif=keluar"
        enum ref_type "PO/SO/ADJ"
        varchar ref_id "order_no terkait"
        int performed_by FK
        timestamp created_at
    }
```

## Keputusan desain

- **`products.sku` sebagai primary key** (bukan surrogate `id`) — konsisten dengan
  seluruh prototype JS/JSON yang sudah dibangun, di mana SKU sudah dipakai sebagai
  business key alami di setiap referensi produk (form, tabel, ledger).
- **`product_stock` adalah tabel state saat ini**, bukan riwayat. Sumber kebenaran
  riwayat pergerakan ada di `stock_ledger` — service layer (nanti di PHP) wajib
  selalu menulis baris `stock_ledger` **lalu** meng-update `product_stock` dalam
  satu transaksi (lihat catatan transaksi di `schema-and-seed.sql`), bukan
  mengubah `product_stock` langsung dari controller.
- **`approved_by` nullable** pada `sales_orders` — order yang masih Draft/
  PendingApproval belum punya approver; constraint "Sales tidak boleh approve
  order sendiri" tetap harus ditegakkan di service layer (PHP), bukan lewat SQL,
  karena aturan itu bergantung pada role user saat request dibuat, bukan invarian
  data yang bisa dicek murni dari tabel.
- **`purchase_order_items.received_qty <= qty`** dijaga lewat `CHECK` constraint
  (MySQL 8.0.16+) — mencegah data cacat di level database, bukan cuma di aplikasi.
- **Tidak ada hard delete** di manapun — kolom `active` dipakai untuk
  menonaktifkan (produk, gudang, supplier, customer, user), sesuai §1.3.
