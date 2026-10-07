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
