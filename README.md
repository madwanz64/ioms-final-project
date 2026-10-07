# Inventory & Order Management System (IOMS)

Final Project Intermediate Programmer — PT Neuronworks Indonesia.
Aplikasi web PHP 8.2+ native (tanpa framework) + MySQL 8 untuk mengelola produk,
stok multi-gudang, Purchase Order, dan Sales Order dengan tiga peran
(Admin, Sales, Warehouse Staff).

> **Status: dalam pengerjaan.** Sudah jalan: login/logout, guard role, produk,
> master data (kategori, gudang, supplier, customer), dan manajemen user. PO, SO, ledger transaksional,
> dashboard lengkap, laporan CSV, API JSON, script terjadwal, dan Docker
> belum dibuat (lihat [Known limitations](#known-limitations)).

## Fitur yang sudah tersedia

| Requirement | Status |
|---|---|
| AUTH-01 Login & session | ✅ Password `password_verify`, pesan gagal generik, akun nonaktif ditolak, ID session diganti setelah login |
| AUTH-02 Logout | ✅ Logout via POST + CSRF, session dihapus, halaman terlindungi kembali ke `/login` |
| PRD-01 Produk | ✅ Tambah/edit/nonaktif (tanpa hapus), SKU unik & immutable, angka bulat ≥ 0, upload JPG/PNG divalidasi dari isi file, nama file acak |
| WH-01 Stok multi-gudang | ✅ Admin mengelola gudang; detail produk menampilkan total & rincian per gudang; produk baru dan gudang baru otomatis melengkapi baris stok 0 |
| USR-01 Manajemen user | ✅ Admin menambah/mengubah/menonaktifkan user, email unik, role hanya 3 nilai, password di-hash; Sales & Warehouse mendapat 403; Admin tidak bisa menonaktifkan akunnya sendiri |
| Master data | ✅ Kategori (nama unik), supplier & customer (nonaktif, tanpa hapus) — khusus Admin |
| FIND-01 Produk | ✅ Cari nama/SKU, filter kategori & status stok, sort, pagination 10/halaman, filter tetap aktif saat pindah halaman |
| DASH-01 | ⏳ Sebagian: Admin & Warehouse melihat jumlah produk aktif dan produk di bawah reorder point (dari query) |
| ERR-01 | ✅ Tanpa login → redirect, tanpa hak akses → 403, tidak ditemukan → 404, error server → 500 tanpa stack trace |
| VAL-01 | ✅ untuk form yang sudah ada: HTML5 di frontend, semua aturan diulang di backend, input lama dipertahankan (kecuali password) |
| PO-01, SO-01, ARCH-02, REPORT-01, API-01, JOB-01 | ⏳ Belum |

## Struktur

```
app/Controller   HTTP: baca request, panggil service, render view/redirect
app/Service      Aturan bisnis & validasi (tidak menyentuh PDO/session/superglobal)
app/Repository   Interface + implementasi MySQL (PDO prepared statement)
app/Entity       Objek data immutable
app/Core         Router, Request/Response, Session, CSRF, View, Database, Env
config/          Loader environment (.env)
public/          Front controller index.php + CSS/JS
views/           Template PHP
database/        schema-and-seed.sql (digenerate dari scripts/generate-sql-seed.js)
tests/Unit       Unit test dengan fake repository (tanpa database)
tests/Integration Integration test ke MySQL nyata (database ioms_test)
prototype/       Prototype HTML/JS statis dari fase sebelumnya (arsip, bukan aplikasi)
docs/            planning, architecture, quality, testing
```

## Menjalankan secara lokal (sementara, sebelum Docker)

Kebutuhan: PHP 8.2+ (ekstensi pdo_mysql, fileinfo, mbstring), Composer 2, MySQL 8.

```bash
composer install
cp .env.example .env              # lalu isi DB_PASS sesuai MySQL lokal
mysql -u root -p < database/schema-and-seed.sql
composer serve                    # http://localhost:8000
```

## Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | admin@ioms.test | admin123 |
| Sales | sinta@ioms.test, doni@ioms.test | sales123 |
| Warehouse Staff | rudi@ioms.test, wulan@ioms.test | gudang123 |
| Warehouse Staff (nonaktif, untuk uji login ditolak) | agus@ioms.test | gudang123 |

Password di atas hanya untuk data demo seed; yang tersimpan di database adalah hash bcrypt.

## Test & static analysis

```bash
composer test               # unit + integration
composer test:unit          # unit saja (tanpa database)
composer test:integration   # butuh MySQL; membangun ulang database DB_TEST_NAME (default ioms_test)
composer analyse            # PHPStan level 6
```

Integration test **tidak pernah** menyentuh database aplikasi: test menolak berjalan
bila `DB_TEST_NAME` sama dengan `DB_NAME` atau tidak berakhiran `_test`.
Hasil terakhir: [docs/testing/hasil-test.md](docs/testing/hasil-test.md) ·
[docs/quality/static-analysis.md](docs/quality/static-analysis.md).

## Known limitations

- Docker Compose belum tersedia; aplikasi & test baru diuji pada PHP 8.3 + MySQL 8.0 lokal.
- Modul PO, SO, goods receipt/issue (ARCH-02), laporan CSV, API JSON, dan script
  low-stock belum dibangun.
- Daftar lengkap jalan pintas: [docs/quality/tech-debt.md](docs/quality/tech-debt.md).

## Penggunaan AI

Dicatat di [ai-usage-log.md](ai-usage-log.md) sesuai brief §6.2.
