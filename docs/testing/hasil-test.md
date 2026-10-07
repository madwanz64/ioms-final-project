# Hasil Test

## Slice 1 — Auth + Produk (2026-10-02)

Lingkungan: PHP 8.3.16 (CLI, Windows), MySQL 8.0.46 lokal, PHPUnit 11.5.56.
**Belum dijalankan di dalam Docker** (Docker Compose belum dibuat).

### Otomatis

| Suite | Perintah | Hasil |
|---|---|---|
| Unit | `composer test:unit` | 32 test, lulus — tanpa database/session/filesystem |
| Integration | `composer test:integration` | 10 test, lulus — MySQL nyata, database `ioms_test` |
| Semua | `composer test` | 42 test, 97 assertion, lulus 3x berturut-turut dengan urutan acak (`executionOrder="random"`) |

Area logic yang diuji unit (TEST-01): autentikasi (AuthService), validasi produk
(ProductService), perhitungan low stock & dashboard (ProductSummary, DashboardService),
dan whitelist parameter pencarian (ProductSearchCriteria).

Cek bahwa test benar-benar menguji logic: kondisi `isLowStock()` sengaja diubah dari
`<` menjadi `<=` → 2 kasus batas gagal → kode dikembalikan → lulus lagi.

### Skenario manual (smoke test `php -S` + curl)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Buka `/products` tanpa login | 302 ke `/login` | ✅ |
| 2 | Login password salah | 401, "Email atau password salah, atau akun tidak aktif." | ✅ |
| 3 | Login akun nonaktif (agus@) dengan password benar | 401, pesan yang sama persis dengan #2 | ✅ |
| 4 | POST login tanpa token CSRF | 403 | ✅ |
| 5 | Login Admin (email huruf besar) | 302 ke `/dashboard`, ID session berganti | ✅ |
| 6 | Dashboard Admin | 32 produk aktif, 5 di bawah reorder point | ✅ |
| 7 | `/products?stock=low` | 5 produk | ✅ |
| 8 | `/products?page=2&sort=name_desc` | data 11–20 dari 32, link halaman membawa `sort` | ✅ |
| 9 | Detail SKU-0001 | total 4 (Jakarta 3, Surabaya 1) | ✅ |
| 10 | SKU tidak ada | 404 | ✅ |
| 11 | Cari `%` | empty state "Tidak ada produk yang cocok" | ✅ |
| 12 | Cari `<script>` | ditampilkan ter-escape `&lt;script&gt;` | ✅ |
| 13 | Sales buka form tambah / POST simpan produk | 403 / 403 | ✅ |
| 14 | Warehouse Staff buka form edit | 403 | ✅ |
| 15 | Sales buka detail produk | harga beli tidak ditampilkan | ✅ |
| 16 | Simpan produk dengan SKU duplikat, nama kosong, kategori 99, harga -5, harga 1.5, reorder "abc" | 422, 6 pesan error sekaligus, isian lain dipertahankan | ✅ |
| 17 | Upload file teks bernama `.png` | 422 "Format gambar harus JPG atau PNG." | ✅ |
| 18 | Upload PNG asli | tersimpan sebagai `/uploads/products/<32 hex>.png`, stok 0 di 2 gudang | ✅ |
| 19 | Nonaktifkan produk | badge Nonaktif; Sales mendapat 404 untuk produk itu | ✅ |
| 20 | Logout lalu buka `/dashboard` | 302 ke `/login` | ✅ |
| 21 | Database tidak bisa diakses (password DB salah) | 500 dengan pesan umum, tanpa SQLSTATE/stack trace; detail masuk log server | ✅ |
| 22 | URL tidak dikenal / POST ke route GET | 404 / 405 | ✅ |

## Known bugs

Belum ada yang diketahui pada slice ini.

---

## Slice 2 — Master data & manajemen user (2026-10-07)

Lingkungan sama (PHP 8.3.16, MySQL 8.0.46 lokal, belum Docker).

### Otomatis

| Suite | Hasil |
|---|---|
| Unit | 47 test (+15), lulus |
| Integration | 14 test (+4), lulus |
| Semua (`composer test`) | 61 test, 149 assertion, lulus |

Area logic baru: manajemen user (UserService), nama unik master data
(CategoryService, WarehouseService). Mutation check: guard "Admin tidak bisa
menonaktifkan/mengubah role akun sendiri" dimatikan → 1 test gagal → dikembalikan.

Refactor R-01 (`InputValidator`): 42 test lama lulus sebelum & sesudah tanpa test diubah.

### Skenario manual (smoke test `php -S` + curl)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Admin membuka 5 daftar + form tambah/edit | 200 | ✅ |
| 2 | Sales & Warehouse membuka `/categories`, `/warehouses`, `/suppliers`, `/customers`, `/users` | 403 untuk ke-10 kombinasi | ✅ |
| 3 | Sales POST `/users` (mencoba membuat Admin) | 403 | ✅ |
| 4 | `/users/999/edit`, `/users/abc/edit` | 404, 404 | ✅ |
| 5 | Tambah kategori "elektronik" (sudah ada "Elektronik") | 422 "Nama kategori sudah dipakai." | ✅ |
| 6 | Tambah user: nama kosong, email `SINTA@ioms.test`, role "Boss", password "123" | 422 dengan 4 pesan; password tidak dikirim balik ke form | ✅ |
| 7 | Konfirmasi password berbeda | 422 "Konfirmasi password tidak sama." | ✅ |
| 8 | Admin mengubah dirinya jadi Sales + nonaktif | 422, dua pesan penolakan | ✅ |
| 9 | Tambah user `TARI@ioms.test` | tersimpan `tari@ioms.test`, hash `$2y$10$`; user bisa login | ✅ |
| 10 | Admin menonaktifkan Tari saat sesi Tari masih aktif | request Tari berikutnya → 302 `/login` | ✅ |
| 11 | Tambah "Gudang Medan" | 32 baris `product_stock` qty 0 (= jumlah produk) | ✅ |
| 12 | Tambah supplier aktif & customer nonaktif | tersimpan di tabel masing-masing, badge status benar | ✅ |
| 13 | Nama kategori `<img src=x onerror=alert(1)>` | tampil ter-escape di flash & tabel | ✅ |

---

## Slice 3 — Purchase Order, goods receipt & anti-oversell (2026-10-07)

### Otomatis

| Suite | Hasil |
|---|---|
| Unit | 71 test (+24), lulus |
| Integration | 18 test (+4 file baru: 7 test), lulus — termasuk dua koneksi MySQL bersamaan |
| Semua | 89 test, 228 assertion, lulus |

Area logic baru: validasi tanggal PO, transisi status PO, perhitungan goods receipt
(sisa qty, status hasil), dan aturan stok/anti-oversell (StockService).

**Bukti ARCH-02** (`tests/Integration/StockConcurrencyTest.php`, penjelasan di ADR-001):

| Skenario | Hasil |
|---|---|
| Transaksi A mengunci SKU-0001@gudang 1; koneksi B mencoba `FOR UPDATE NOWAIT` pada baris yang sama | B ditolak dengan error MySQL 3572 (tanpa NOWAIT, B menunggu A selesai); baris SKU-0002 tetap bisa dikunci B |
| Issue 3 unit (stok 3) lalu issue 1 unit | Issue kedua ditolak "tersedia 0, dibutuhkan 1"; stok 0 = SUM(ledger); ledger issue kedua tidak tertulis |
| `recordMovement()` langsung -4 tanpa lock | Ditolak guard SQL (`InsufficientStockException`), stok tetap 3 |
| `UPDATE` langsung -100 tanpa guard | Ditolak `CHECK (quantity >= 0)` |

**Mutation check:**
- `FOR UPDATE` dihapus → test lock gagal.
- Guard SQL dilonggarkan → test guard gagal, dan pesan error-nya menunjukkan CHECK constraint (lapis ketiga) yang menolak.
- Kode dikembalikan → 89 lulus.

### Skenario manual (smoke test `php -S` + curl, Admin + Warehouse + Sales)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Sales membuka `/purchase-orders` | 403 | ✅ |
| 2 | Daftar PO (12 seed), filter Ordered + sort terlama, cari "kertas" | 10 per halaman; PO-0006, 0007, 0011; 4 hasil | ✅ |
| 3 | Warehouse membuat PO: supplier nonaktif, tanggal 2099, qty 0, harga -1, SKU ganda | 422 dengan 5 pesan, tidak tersimpan | ✅ |
| 4 | Warehouse membuat PO valid (baris kosong diabaikan) | PO-2026-0013 Draft, 2 item | ✅ |
| 5 | Warehouse mencoba "Tandai Ordered" | 403 | ✅ |
| 6 | Terima barang saat Draft | ditolak dengan pesan status | ✅ |
| 7 | Admin menandai Ordered | status Ordered | ✅ |
| 8 | Terima 11 dari sisa 10 / qty "abc" / semua kosong | 422 per item / "minimal satu item" | ✅ |
| 9 | Terima 4 dari 10 | PartiallyReceived, stok SKU-0001 3 → 7, sisa 6 | ✅ |
| 10 | Admin membatalkan PO PartiallyReceived | ditolak | ✅ |
| 11 | Terima sisa (6 + 5) | Received; 3 baris ledger Receipt, `performed_by` = Rudi; stok = SUM(ledger) (13 dan 6) | ✅ |
| 12 | Terima lagi setelah Received | ditolak; form penerimaan tidak tampil | ✅ |

---

## Slice 4 — Sales Order, approval & goods issue (2026-10-07)

### Otomatis

| Suite | Hasil |
|---|---|
| Unit | 92 test (+21), lulus |
| Integration | 22 test (+3), lulus |
| Semua | 114 test, 298 assertion, lulus |

Area logic baru: ownership & authorization approve (`SalesOrderPolicy`), transisi status
SO, goods issue (`SalesOrderService`). Refactor R-02: 89 test lama lulus tanpa diubah.

Mutation check (setelah koreksi K-05): `canReview` dibuat mengizinkan Sales → 4 test
gagal (unit policy 2 kasus, unit service, integration) → dikembalikan.

### Skenario manual (smoke test 4 akun: Sinta & Doni = Sales, Admin, Rudi = Warehouse)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Daftar SO Sinta | hanya 9 order miliknya (= jumlah di DB) | ✅ |
| 2 | Daftar SO Warehouse | 14 order non-Draft, tidak ada Draft | ✅ |
| 3 | Sinta membuka SO milik Doni | 404 | ✅ |
| 4 | Sinta membuat SO SKU-0006 qty 5 (stok 2) dengan `items[0][price]=1` | 422 "Stok tersedia di Gudang Jakarta hanya 2." | ✅ |
| 5 | Sinta membuat SO SKU-0003 qty 4 dengan `price=1` disuntik | tersimpan Draft, harga 650.000 dari katalog | ✅ |
| 6 | Warehouse membuka Draft Sinta | 404 | ✅ |
| 7 | Sinta submit | PendingApproval | ✅ |
| 8 | **Sinta approve SO miliknya sendiri** (POST langsung) | **403** "Anda tidak dapat menyetujui Sales Order yang Anda buat sendiri." | ✅ |
| 9 | Doni (Sales lain) approve SO Sinta | 404, status tetap PendingApproval | ✅ (setelah perbaikan, lihat catatan) |
| 10 | Warehouse approve / Sinta goods issue | 403 / 403 | ✅ |
| 11 | Warehouse goods issue sebelum Approved | ditolak dengan pesan status | ✅ |
| 12 | Admin approve | Approved, `approved_by` = 1 | ✅ |
| 13 | Sinta membatalkan SO Approved | 403 | ✅ |
| 14 | Warehouse goods issue | Fulfilled; ledger Issue -4 oleh Rudi; stok 34 → 30 = SUM(ledger) | ✅ |
| 15 | **Dua SO masing-masing 2 unit SKU-0006 (stok 2), keduanya Approved; goods issue berurutan** | SO pertama Fulfilled; SO kedua ditolak "tersedia 0, dibutuhkan 2" dan tetap Approved; stok 0 = SUM(ledger); tidak ada ledger untuk SO kedua | ✅ |
| 16 | Admin membuat, mengajukan, lalu menyetujui SO miliknya | ~~403~~ → setelah koreksi K-05: Approved, `approved_by` = `created_by` = 1; tombol Setujui tampil | ✅ (diuji ulang) |
| 17 | Admin membatalkan SO Approved / SO Fulfilled | Cancelled / ditolak | ✅ |

**Catatan perbaikan:** pada percobaan pertama, skenario #9 mengembalikan 302 + flash
"tidak ditemukan" (data aman, status tidak berubah, tapi kode HTTP tidak konsisten dengan
halaman detail). Diperbaiki dengan `NotFoundException` → 404, lalu diuji ulang.

---

## Slice 5 — Dashboard tiga role & laporan CSV (2026-10-07)

### Otomatis

| Suite | Hasil |
|---|---|
| Unit | 115 test (+23), lulus |
| Integration | 30 test (+5 di file baru; total termasuk data provider), lulus |
| Semua | 145 test, 355 assertion, lulus |

Area logic baru: rentang tanggal laporan, hak unduh per role, rekap status (termasuk nol),
dan keamanan CSV (formula injection, sanitasi nama file).

Mutation check (dilakukan dengan benar, lihat catatan):
- Guard CSV dipersempit menjadi hanya `=` → 3 test gagal (`+`, `-`, `@`).
- Batas akhir rentang tanpa `+1 hari` → 2 test gagal (tanggal akhir tidak lagi inklusif).

**Catatan jujur:** percobaan mutasi pertama pada guard CSV memakai `sed` yang polanya tidak
cocok, sehingga kode tidak berubah dan hasil "lulus" tidak bermakna. Pengembalian mutasi itu
memakai `git checkout` yang ikut menghapus method `csv()`/`csvCell()` yang belum di-commit.
Kesalahan langsung terdeteksi oleh test berikutnya (7 error), kode dipasang kembali, lalu
mutasi diulang memakai edit yang diverifikasi.

### Skenario manual (smoke test 3 role; angka dibandingkan dengan query SQL independen)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Dashboard Admin | Nilai inventori Rp 248.575.000 (= SQL), 5 low stock, 4 SO menunggu persetujuan (= SQL), +229/−51 unit 90 hari | ✅ |
| 2 | Dashboard Sales (Sinta) | Draft 1, Pending 3, Approved 1, nilai Fulfilled Rp 7.266.000 (= SQL milik Sinta) | ✅ |
| 3 | Dashboard Warehouse | 5 PO menunggu penerimaan, 2 SO menunggu goods issue (= SQL), daftar antrean tampil | ✅ |
| 4 | Laporan Admin Agustus | 72 baris ledger (= SQL), pratinjau 20 terakhir | ✅ |
| 5 | CSV detail ledger | `text/csv`, BOM UTF-8, nama file `pergerakan-stok_2026-08-01_2026-08-31.csv`, 72 baris data | ✅ |
| 6 | CSV status order Admin Agustus | PO & SO semua status termasuk 0; jumlah SO cocok dengan SQL | ✅ |
| 7 | CSV status order Sinta | hanya SO miliknya, tanpa baris PO | ✅ |
| 8 | Sales unduh ledger / Warehouse unduh status order | 403 / 403 | ✅ |
| 9 | Jenis laporan tidak dikenal / tanpa login | 404 / 302 ke login | ✅ |
| 10 | Rentang: awal > akhir, 2026-02-30, > 366 hari, akhir di masa depan | 422 dengan pesan yang sesuai | ✅ |

---

## Slice 6 — API JSON & script terjadwal (2026-10-07)

### Otomatis

| Suite | Hasil |
|---|---|
| Semua (`composer test`) | 151 test, 371 assertion, lulus (+4 unit, +2 integration) |

Mutation check: `LowStockReportService` hanya membaca halaman pertama → test 130 produk gagal → dikembalikan.

### API-01 (curl, sesuai bukti yang diminta brief)

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | Tanpa session login | 401 `application/json` `{"error":"unauthenticated"}` (bukan redirect HTML) | ✅ |
| 2 | Dengan login (Sales), SKU-0001 | 200 JSON: total 4, Jakarta 3, Surabaya 1, `lowStock: true` | ✅ |
| 3 | SKU tidak ada | 404 JSON `not_found` | ✅ |
| 4 | SKU huruf kecil `sku-0006` | 200, dinormalisasi ke SKU-0006; karakter `"` pada nama ter-escape JSON | ✅ |
| 5 | POST ke endpoint GET | 405 JSON `method_not_allowed` | ✅ |
| 6 | URL `/api/...` tak dikenal | 404 JSON (bukan halaman HTML) | ✅ |
| 7 | Produk nonaktif: Sales / Admin | 404 / 200 dengan `"active": false` | ✅ |

### JOB-01

| # | Skenario | Hasil |
|---|---|---|
| 1 | `php scripts/check-low-stock.php` | 5 produk, urut kekurangan (SKU-0002 kurang 13 di atas), rincian per gudang, exit 0 ✅ |
| 2 | Dijalankan dari folder lain (`cd /c && php Projects/training/scripts/...`) | sama, exit 0 ✅ |
| 3 | Password DB salah | pesan ke STDERR, exit 1 ✅ |
| 4 | Dipanggil lewat web server | 404 (bukan di document root; ada juga penjaga `PHP_SAPI`) ✅ |

### Belum diuji

- Perilaku Fetch API di **browser sungguhan** (petunjuk stok di form SO dan tombol "Muat
  ulang stok") belum diuji dengan klik langsung karena sesi ini tidak memiliki browser.
  Yang sudah diverifikasi: endpoint yang dipanggil (curl), markup `data-*` yang dibaca script
  ada di halaman, dan `node --check public/js/app.js` lolos. **Perlu dicek manual di browser
  sebelum demo.**

### Perbaikan yang ditemukan di slice ini

- Zona waktu: output script mencetak 03:13 padahal 10:13 WIB. PHP berjalan di UTC, sehingga
  pukul 00:00–07:00 WIB tanggal hari ini akan ditolak sebagai "masa depan". Diperbaiki dengan
  `APP_TIMEZONE` (PHP & sesi MySQL); diverifikasi `date()` PHP = `NOW()` MySQL = 10:14 WIB.

---

## Slice 7 — Docker Compose (2026-10-07)

Lingkungan: Docker 29.8.2, Compose 5.5.1 (Docker Desktop, Windows); image `php:8.3-apache`
(PHP 8.3.35) dan `mysql:8.0`.

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | `docker compose up --build -d` | db *healthy* setelah seed diimpor, lalu app start | ✅ |
| 2 | Ekstensi PHP di container | pdo_mysql, fileinfo, mbstring | ✅ |
| 3 | Seed otomatis | 32 produk, 28 order (PO+SO), 6 user; waktu MySQL = WIB | ✅ |
| 4 | **`docker compose exec app composer test`** | 153 test (unit + integration ke MySQL di container) lulus | ✅ |
| 5 | `docker compose exec app composer analyse` | PHPStan 0 error | ✅ |
| 6 | `docker compose exec app php scripts/check-low-stock.php` | ringkasan 5 produk, exit 0 | ✅ |
| 7 | HTTP: `/login`, CSS/JS statis, API tanpa login | 200, 200, 401 JSON | ✅ |
| 8 | Akses `/config/config.php`, `/.env`, `/../app/Core/Env.php` | 404 (di luar document root) | ✅ |
| 9 | Login 3 role; akun nonaktif | 302 ke dashboard; 401 | ✅ |
| 10 | PO dibuat Warehouse → Ordered (Admin) → terima 8 + 12 | Received | ✅ |
| 11 | SO Sinta → submit → Sinta approve sendiri → Admin approve → Warehouse goods issue | 403 untuk Sinta; Fulfilled, `approved_by` = 1 | ✅ |
| 12 | Stok SKU-0002@Jakarta setelah +20 −15 | 6 = SUM(ledger); timestamp ledger WIB | ✅ |
| 13 | Upload PNG produk | tersimpan di volume, URL gambar 200 `image/png` | ✅ |
| 14 | CSV ledger hari ini & API availability | 3 baris (Receipt, Receipt, Issue); JSON stok terbaru | ✅ |
| 15 | Container db dihentikan | halaman 500 pesan umum tanpa SQLSTATE; `PDOException` tercatat di `docker compose logs app` | ✅ |

### Uji dari folder bersih (§5.1 "Uji sebelum submission")

`git clone` ke folder baru (tanpa `vendor/` dan `.env`) → `cp .env.example .env` →
`docker compose up --build -d` (project & port berbeda agar tidak bentrok):

- 153 test lulus di container clone;
- login Admin berhasil, dashboard menampilkan nilai inventori Rp 248.575.000 dari seed;
- JOB-01 berjalan;
- stack clone dihapus kembali (`down -v`).

### Bug yang ditemukan saat menyiapkan Docker

`Env::load()` hanya membaca env var sungguhan untuk key yang ada di file `.env`. Di container
tidak ada `.env`, sehingga `DB_HOST=db` dari Compose akan diabaikan. Diperbaiki sebelum build
pertama (commit `fix:` terpisah) dengan unit test yang gagal pada implementasi lama.

---

## Slice 8 — Profil sendiri (2026-10-07)

Otomatis: 160 test, 399 assertion, lulus (+7 unit di `UserServiceTest`). Mutation: verifikasi
password lama dimatikan → test "password saat ini salah" gagal → dikembalikan.

Smoke test di Docker (setelah `docker compose up --build -d app`):

| # | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| 1 | `/profile` tanpa login | 302 ke `/login` | ✅ |
| 2 | Sales / Admin / Warehouse membuka profil | 200, email & role tampil read-only milik masing-masing | ✅ |
| 3 | POST tanpa token CSRF | 403 | ✅ |
| 4 | Ubah nama sambil menyisipkan `role=Admin&email=evil@...&active=0` | nama berubah; email, role, status tetap | ✅ |
| 5 | Password lama salah | 422 "Password saat ini salah."; tidak ada password yang dikirim balik ke form | ✅ |
| 6 | Ganti password dengan benar | ID session berubah, hash berubah (`$2y$10$`), sesi tetap valid | ✅ |
| 7 | Login dengan password lama / baru | 401 / 302 ke dashboard | ✅ |

Catatan: satu pengecekan awal (profil Warehouse) sempat gagal karena skrip uji memakai ulang
cookie jar milik Admin; diulang dengan cookie jar terpisah dan lulus. Bukan bug aplikasi.
Database Docker di-reset ke seed setelah uji (`down -v`).

---

## Slice 9 — Uji tampilan di browser sungguhan (2026-10-07)

Alat: Chrome headless lewat `puppeteer-core`
([docs/testing/tools/ui-check.mjs](tools/ui-check.mjs)), terhadap aplikasi Docker
(`localhost:8080`, data seed). Layar **desktop 1366px** dan **mobile 360px**.
Hasil mentah: [ui-check-result.json](ui-check-result.json).
Screenshot: [screenshots/](screenshots/) (`desktop-*.jpg`, `mobile-*.jpg`; 43 file).

### Responsif (UI-01): halaman tidak boleh bergeser ke samping

Tabel lebar boleh digeser **di dalam** wadahnya (`.table-wrap`), tetapi halaman itu sendiri
tidak boleh lebih lebar dari layar. Dicek 21 halaman: login, 3 dashboard, daftar/detail/form
produk, PO, SO, laporan, user, profil, keadaan kosong, 403, 404.

| Percobaan | Hasil |
|---|---|
| Pertama | **3 dari 42 gagal di 360px:** detail PO (halaman 681px), detail SO (701px), form PO (501px) |
| Setelah perbaikan (commit `f1401dd`) | **42/42 lulus** |

Penyebab:
1. Tabel kunci–nilai di detail PO/SO mewarisi `min-width:640px` dari tabel daftar, padahal
   tidak berada di wadah scroll. Diperbaiki dengan kelas `.info-table`. (Perbaikan serupa sudah
   ada di prototype lewat `#info-table`, tetapi tidak terbawa ke view PHP.)
2. Label `.sr-only` (`position:absolute`) di dalam tabel item "lolos" dari wadah scroll karena
   `.table-wrap` tidak punya `position`. Diperbaiki dengan `.table-wrap{position:relative}`.

Perbaikan kosmetik sekaligus: input/select di tabel item mengikuti gaya form, angka kartu
statistik diperkecil di 360px, dan favicon ditambahkan (menghilangkan error 404
`favicon.ico` di console).

### Fetch API (API-01) di browser

| # | Skenario | Hasil |
|---|---|---|
| 1 | Form SO (Sales): Gudang Jakarta + SKU-0006 qty 5 | "Stok tersedia: 2 pcs", ditandai merah; harga katalog Rp 1.850.000 tampil ✅ |
| 2 | Gudang asal diganti ke Surabaya | petunjuk diperbarui "Stok tersedia: 0 pcs" ✅ |
| 3 | "+ Tambah item" | baris baru dengan nama field `items[3][...]` ✅ |
| 4 | Detail produk: "Muat ulang stok" | status "Diperbarui …" tanpa reload ✅ |
| 5 | Cookie session dihapus lalu klik "Muat ulang stok" | API menjawab 401 JSON → toast "Sesi berakhir, silakan login ulang." ✅ |

Console error JavaScript: hanya respons 403/404 dari dua halaman error yang memang sengaja
dibuka (skenario 20–21); tidak ada error script.

---

## Known bugs & keterbatasan terkini (2026-10-07)

Tidak ada bug fungsional yang diketahui pada alur wajib. Keterbatasan yang bisa terlihat saat
demo (rinciannya di [tech-debt.md](../quality/tech-debt.md)):

| # | Gejala | Dampak | Rujukan |
|---|---|---|---|
| 1 | File gambar > `post_max_size` PHP (8 MB di Docker) dijawab 403 "sesi formulir kedaluwarsa", bukan pesan ukuran | Data aman; pesan kurang tepat. Diuji di Docker: 2,5 MB & 5 MB → 422 "Ukuran gambar maksimal 2 MB."; 9 MB → 403; tidak ada produk tersimpan. Di browser, JS sudah menolak > 2 MB sebelum kirim. | tech-debt #2 |
| 2 | Dua Admin menyimpan email user yang sama di detik yang sama → kedua lolos validasi, yang kedua ditolak UNIQUE KEY → halaman 500 | Sangat jarang; data tetap benar | tech-debt #7 |
| 3 | Di layar 360px, kolom "Terima Sekarang" pada detail PO baru terlihat setelah tabel digeser ke samping | Bisa dipakai, tetapi kurang nyaman | baru dicatat |
| 4 | Konfirmasi batal/tolak/goods issue memakai dialog bawaan browser | Kosmetik | tech-debt #15 |
| 5 | Gambar produk lama tidak dihapus saat diganti | Disk bertambah | tech-debt #1 |
