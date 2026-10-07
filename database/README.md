# Database

`schema-and-seed.sql` di folder ini **digenerate otomatis**, jangan diedit manual.
Untuk mengubah struktur tabel atau data seed, edit `scripts/generate-sql-seed.js`
lalu jalankan ulang:

```
node scripts/generate-sql-seed.js
```

Seed data-nya sengaja dikonversi dari file yang sama dengan prototype JS
(`prototype/data/*.json`) supaya keduanya tetap konsisten — kecuali kolom
`password` pada `users`, yang di sini sudah berupa hash bcrypt asli (bukan
plaintext seperti di prototype JS), siap dipakai `password_verify()` PHP.

## Cara import (setelah MySQL 8 terpasang)

```
mysql -u root -p < database/schema-and-seed.sql
```

Skrip ini aman dijalankan berulang kali — otomatis membuat database `ioms`
kalau belum ada, dan **menimpa ulang** semua tabel (`DROP TABLE IF EXISTS`)
setiap kali dijalankan, jadi selalu mulai dari kondisi bersih.

## Akun demo (setelah seed masuk)

| Role | Email | Password |
|---|---|---|
| Admin | admin@ioms.test | admin123 |
| Sales | sinta@ioms.test / doni@ioms.test | sales123 |
| Warehouse Staff | rudi@ioms.test / wulan@ioms.test | gudang123 |
| Warehouse Staff (nonaktif, untuk uji AUTH-01) | agus@ioms.test | gudang123 |

## Status verifikasi

Sudah diuji terhadap MySQL 8.0.46 sungguhan (2026-09-18):

- Import bersih tanpa error (`mysql -u root -p < database/schema-and-seed.sql`).
- Jumlah baris tiap tabel cocok 100% dengan `prototype/data/*.json`.
- FK, `CHECK` constraint (`quantity >= 0`, `received_qty <= qty`), dan
  `UNIQUE` (email) terbukti **menolak** data tidak valid saat dicoba
  langsung (bukan cuma diterima sintaksnya).
- Pola transaksi multi-tabel di komentar (ARCH-02) diuji dengan
  `START TRANSACTION` + `SELECT ... FOR UPDATE` + `UPDATE` + `INSERT` +
  `ROLLBACK` — `product_stock` dan `stock_ledger` sama-sama batal berubah
  setelah rollback, sesuai perilaku atomik yang diharapkan.
- Konsistensi ledger (2026-10-02): `SUM(stock_ledger.quantity)` per produk+gudang
  sama persis dengan `product_stock.quantity` untuk seluruh 64 baris, dan saldo
  berjalan per produk+gudang tidak pernah negatif. Selisih yang tidak berasal dari
  order dicatat sebagai `Adjustment` (saldo awal `OPENING` dan koreksi
  `OPNAME-2026-09`), bukan diisi langsung ke `product_stock`.
- Index terbukti benar-benar dipakai query planner (`EXPLAIN` menunjukkan
  `key: idx_products_category`, bukan cuma ada tapi tidak terpakai).
- Query JOIN realistis (produk low-stock lintas 3 tabel, detail Sales
  Order lintas 4 tabel) menghasilkan data yang sesuai ekspektasi.

Docker (2026-10-07): file ini di-mount ke `/docker-entrypoint-initdb.d/` container
`mysql:8.0` (lihat `compose.yaml`) dan diimpor otomatis saat volume database pertama kali
dibuat. Terverifikasi dari clone bersih: 32 produk, 28 order, 6 user; 153 test lulus di container.
