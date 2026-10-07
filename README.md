# Inventory & Order Management System (IOMS)

Final Project Intermediate Programmer — PT Neuronworks Indonesia.
Aplikasi web PHP 8.2+ native (tanpa framework) + MySQL 8 untuk mengelola produk,
stok multi-gudang, Purchase Order, dan Sales Order dengan tiga peran
(Admin, Sales, Warehouse Staff).

> **Status: dalam pengerjaan.** Seluruh fitur aplikasi §2 sudah jalan (login, master data,
> PO, SO, stock ledger, dashboard, laporan CSV, API JSON, script terjadwal) dan dapat
> dijalankan dengan Docker Compose. Yang belum: laporan SonarQube,
> critique.md (menunggu cuplikan assessor), dan tag release.

## Fitur yang sudah tersedia

| Requirement | Status |
|---|---|
| AUTH-01 Login & session | ✅ Password `password_verify`, pesan gagal generik, akun nonaktif ditolak, ID session diganti setelah login |
| AUTH-02 Logout | ✅ Logout via POST + CSRF, session dihapus, halaman terlindungi kembali ke `/login` |
| PRD-01 Produk | ✅ Tambah/edit/nonaktif (tanpa hapus), SKU unik & immutable, angka bulat ≥ 0, upload JPG/PNG divalidasi dari isi file, nama file acak |
| WH-01 Stok multi-gudang | ✅ Admin mengelola gudang; detail produk menampilkan total & rincian per gudang; produk baru dan gudang baru otomatis melengkapi baris stok 0 |
| USR-01 Manajemen user | ✅ Admin menambah/mengubah/menonaktifkan user, email unik, role hanya 3 nilai, password di-hash; Sales & Warehouse mendapat 403; Admin tidak bisa menonaktifkan akunnya sendiri |
| Profil sendiri | ✅ Semua role mengubah nama & password (wajib password lama); email/role/status hanya oleh Admin |
| Master data | ✅ Kategori (nama unik), supplier & customer (nonaktif, tanpa hapus) — khusus Admin |
| FIND-01 Produk | ✅ Cari nama/SKU, filter kategori & status stok, sort, pagination 10/halaman, filter tetap aktif saat pindah halaman |
| DASH-01 Dashboard | ✅ Admin: nilai inventori, produk di bawah reorder point, order per status (PO & SO); Sales: order miliknya per status; Warehouse: antrean goods receipt/issue & low stock — semua dari query agregasi |
| ERR-01 | ✅ Tanpa login → redirect, tanpa hak akses → 403, tidak ditemukan → 404, error server → 500 tanpa stack trace |
| VAL-01 | ✅ untuk form yang sudah ada: HTML5 di frontend, semua aturan diulang di backend, input lama dipertahankan (kecuali password) |
| PO-01 Purchase Order | ✅ Draft → Ordered → PartiallyReceived/Received, batal sebelum ada penerimaan; goods receipt penuh/sebagian menambah stok + ledger Receipt dalam satu transaksi; cari/filter status/sort tanggal/pagination |
| ARCH-02 Anti-oversell | ✅ Mekanisme siap & teruji ([ADR-001](docs/architecture/adr-001-mekanisme-anti-oversell.md)): FOR UPDATE berurutan + guard SQL + CHECK constraint; dipakai goods receipt & goods issue |
| SO-01 Sales Order | ✅ Draft → PendingApproval → Approved → Fulfilled / Cancelled; approve hanya Admin yang bukan pembuat order (ditegakkan di server, [ADR-002](docs/architecture/adr-002-otorisasi-sales-order.md)); goods issue ditolak bila stok kurang; Sales hanya melihat order miliknya |
| REPORT-01 Laporan CSV | ✅ Detail stock ledger, rekap stok per produk+gudang, dan status order dalam rentang tanggal; query sama dengan dashboard; hak unduh per role; aman dari CSV injection |
| API-01 Endpoint JSON | ✅ `GET /api/products/{sku}/availability` — 200/401/404 JSON; dipakai form SO & detail produk lewat Fetch API |
| JOB-01 Script terjadwal | ✅ `php scripts/check-low-stock.php` (`composer low-stock`) — ringkasan produk di bawah reorder point |

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

## Menjalankan dengan Docker (cara utama)

Kebutuhan: Docker Desktop / Docker Engine dengan Compose v2.

```bash
cp .env.example .env                     # nilai contoh sudah bisa langsung dipakai
docker compose up --build -d             # build image + MySQL 8, tunggu sampai selesai
```

- Aplikasi: **http://localhost:8080** (ubah lewat `APP_PORT` di `.env`).
- Schema & seed (`database/schema-and-seed.sql`) diimpor **otomatis** oleh container MySQL
  saat volume database pertama kali dibuat. Service `app` baru start setelah database
  *healthy* (impor selesai).
- MySQL juga bisa diakses dari host di `127.0.0.1:3307` (`DB_HOST_PORT`), user `root`,
  password `DB_PASS` dari `.env`.

Perintah yang sering dipakai:

```bash
docker compose exec app composer test                     # unit + integration test (MySQL di container)
docker compose exec app composer analyse                  # PHPStan
docker compose exec app php scripts/check-low-stock.php   # JOB-01
docker compose logs -f app                                # log Apache/PHP (error 500 tercatat di sini)
docker compose down -v && docker compose up -d            # reset database ke kondisi seed awal
```

> `-v` menghapus volume data MySQL dan gambar upload, jadi seed diimpor ulang dari awal.

## Menjalankan tanpa Docker (opsional)

Kebutuhan: PHP 8.2+ (ekstensi pdo_mysql, fileinfo, mbstring), Composer 2, MySQL 8.

```bash
composer install
cp .env.example .env              # sesuaikan DB_HOST/DB_PORT/DB_USER/DB_PASS dengan MySQL lokal
mysql -u root -p < database/schema-and-seed.sql
composer serve                    # http://localhost:8000
```

## Endpoint JSON (API-01)

```
GET /api/products/{sku}/availability      (butuh session login, sama seperti halaman)

200 {"sku":"SKU-0001","name":"Kabel HDMI 2m","unit":"pcs","active":true,"reorderPoint":10,
     "totalStock":4,"lowStock":true,"warehouses":[{"id":1,"name":"Gudang Jakarta","quantity":3}, ...]}
401 {"error":"unauthenticated","message":"..."}   tanpa login
404 {"error":"not_found","message":"..."}         SKU tidak ada (atau produk nonaktif untuk Sales)
```

## Script terjadwal (JOB-01)

```bash
docker compose exec app php scripts/check-low-stock.php   # di Docker
php scripts/check-low-stock.php                           # tanpa Docker (atau: composer low-stock)
```

Mencetak produk aktif di bawah reorder point (kekurangan terbesar dulu, rincian per gudang).
Exit code 0 = berhasil, 1 = gagal (mis. database). Tidak bisa dipanggil lewat web server.

## Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | admin@ioms.test | admin123 |
| Sales | sinta@ioms.test, doni@ioms.test | sales123 |
| Warehouse Staff | rudi@ioms.test, wulan@ioms.test | gudang123 |
| Warehouse Staff (nonaktif, untuk uji login ditolak) | agus@ioms.test | gudang123 |

Password di atas hanya untuk data demo seed; yang tersimpan di database adalah hash bcrypt.

## Test & static analysis

Di Docker, awali dengan `docker compose exec app`:

```bash
composer test               # unit + integration (satu perintah)
composer test:unit          # unit saja (tanpa database)
composer test:integration   # butuh MySQL; membangun ulang database DB_TEST_NAME (default ioms_test)
composer analyse            # PHPStan level 6
```

Integration test **tidak pernah** menyentuh database aplikasi: test menolak berjalan
bila `DB_TEST_NAME` sama dengan `DB_NAME` atau tidak berakhiran `_test`.
Hasil terakhir: [docs/testing/hasil-test.md](docs/testing/hasil-test.md) ·
[docs/quality/static-analysis.md](docs/quality/static-analysis.md).

## Known limitations

- Image aplikasi memasang dev dependency (PHPUnit, PHPStan) agar test bisa dijalankan di
  container; untuk produksi sungguhan sebaiknya dibuat image terpisah dengan `--no-dev`.
- Interpretasi requirement yang ambigu (mis. arti "mengusulkan" PO): [docs/planning/catatan-keputusan.md](docs/planning/catatan-keputusan.md).
- Daftar lengkap jalan pintas: [docs/quality/tech-debt.md](docs/quality/tech-debt.md).

## Dokumen

| Folder | Isi |
|---|---|
| [docs/planning/](docs/planning/) | [user story](docs/planning/user-stories.md), [scope](docs/planning/scope.md), [backlog](docs/planning/backlog.md), [ERD](docs/planning/erd.md), [class diagram initial](docs/planning/class-diagram-initial.md), [catatan keputusan](docs/planning/catatan-keputusan.md), wireframe |
| [docs/architecture/](docs/architecture/) | [class diagram as-built](docs/architecture/class-diagram-as-built.md), [ADR-001 anti-oversell](docs/architecture/adr-001-mekanisme-anti-oversell.md), [ADR-002 otorisasi SO](docs/architecture/adr-002-otorisasi-sales-order.md) |
| [docs/quality/](docs/quality/) | [refactor log](docs/quality/refactor-log.md) (R-01…R-03), [audit SRP](docs/quality/srp-audit.md), [tech-debt](docs/quality/tech-debt.md), [static analysis](docs/quality/static-analysis.md) |
| [docs/testing/](docs/testing/) | [skenario & hasil test](docs/testing/hasil-test.md) per slice + known bugs, [output PHPUnit](docs/testing/phpunit-testdox.txt), [output PHPStan](docs/testing/phpstan-output.txt), [screenshot desktop & 360px](docs/testing/screenshots/) |

## Sumber pihak ketiga (§6.1)

Kode aplikasi (PHP di `app/`, view, CSS, JavaScript, ikon SVG inline, favicon) dibuat sendiri,
tanpa framework, library frontend, CDN, atau font eksternal. CSS memakai font bawaan sistem.

| Komponen | Versi | Lisensi | Dipakai untuk |
|---|---|---|---|
| [Composer](https://getcomposer.org) (image `composer:2`) | 2.x | MIT | Autoload PSR-4 & pemasangan dev dependency |
| [PHPUnit](https://phpunit.de) | 11.5.56 | BSD-3-Clause | Unit & integration test (dev dependency) |
| [PHPStan](https://phpstan.org) | 2.2.16 | MIT | Static analysis (dev dependency) |
| Image Docker resmi [`php:8.3-apache`](https://hub.docker.com/_/php) | PHP 8.3 | PHP License / Apache 2.0 | Runtime aplikasi |
| Image Docker resmi [`mysql:8.0`](https://hub.docker.com/_/mysql) | 8.0 | GPLv2 | Database |

Alat bantu pengembangan yang **tidak** menjadi bagian aplikasi atau dependency project:
[puppeteer-core](https://pptr.dev) + Chrome, untuk uji tampilan 360px/desktop dan pembuatan
screenshot ([docs/testing/tools/ui-check.mjs](docs/testing/tools/ui-check.mjs)), dan
[Mermaid](https://mermaid.js.org) untuk menggambar diagram di dokumentasi. Bantuan AI dicatat
terpisah di bawah.

## Penggunaan AI

Dicatat di [ai-usage-log.md](ai-usage-log.md) sesuai brief §6.2.
