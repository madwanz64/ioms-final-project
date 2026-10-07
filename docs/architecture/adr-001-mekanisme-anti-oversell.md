# ADR-001 · Mekanisme anti-oversell untuk operasi stok (ARCH-02)

- **Status:** Diterima
- **Tanggal:** 2026-10-07
- **Terkait:** ARCH-02, PO-01, SO-01, DB-01, §8.2 (critical failure: oversell dapat direproduksi)

## Context

Goods issue (Sales Order) mengurangi `product_stock` dan goods receipt (Purchase Order)
menambahnya. Dua request bisa diproses hampir bersamaan untuk produk & gudang yang sama.
Pola naif "baca stok di PHP → cek cukup → tulis stok baru" rentan dua masalah:

1. **Oversell.** Request A dan B sama-sama membaca stok 5. Keduanya meminta 4, keduanya
   lolos cek, sehingga stok menjadi -3 (atau ditolak CHECK constraint dengan error 500).
2. **Lost update.** A menulis `quantity = 1` dan B menulis `quantity = 1`, padahal
   seharusnya -3. Satu pengurangan hilang, dan `product_stock` tidak lagi sama dengan
   `SUM(stock_ledger)`.

Brief menuntut hasil akhir stok selalu benar, ledger konsisten dengan stok, dan request
kedua **ditolak atau ditunda** saat stok sudah dihabiskan request pertama.

Opsi yang dipertimbangkan:

| Opsi | Cara kerja | Kelebihan | Kekurangan |
|---|---|---|---|
| A. Pessimistic lock | `SELECT quantity ... FOR UPDATE` di awal transaksi | Request kedua menunggu lalu membaca stok terbaru; mudah dinalar | Request kedua tertahan selama transaksi pertama berjalan |
| B. Conditional update | `UPDATE ... SET quantity = quantity - :q WHERE ... AND quantity >= :q`, lalu cek `rowCount()` | Atomik dalam satu statement | Tanpa lock eksplisit, cek "stok cukup" untuk pesan ke user tidak bisa dilakukan sebelum menulis |
| C. Optimistic lock | Kolom `version`, update gagal jika versi berubah, lalu retry | Tidak ada lock menunggu | Perlu perubahan skema + logika retry; paling rumit dijelaskan & diuji |

## Decision

Memakai **A sebagai mekanisme utama, B dan CHECK constraint sebagai lapis pengaman**
(*defense in depth*). Semuanya terjadi di dalam **satu transaksi eksplisit**
(`beginTransaction` / `commit` / `rollBack`, lewat `TransactionManagerInterface`):

1. **Kunci dulu, dengan urutan tetap.** `StockRepositoryInterface::lockQuantities()`
   menjalankan `SELECT ... FOR UPDATE` untuk semua pasangan (SKU, gudang) yang akan
   diubah, **diurutkan berdasarkan SKU**. Urutan yang sama di setiap transaksi mencegah
   deadlock (A mengunci X lalu menunggu Y, sementara B mengunci Y lalu menunggu X).
   Dokumen order (PO/SO) juga dikunci `FOR UPDATE` lebih dulu, sehingga dua goods
   receipt untuk PO yang sama tidak bisa berjalan bersamaan.
2. **Validasi bisnis di Service memakai angka yang sudah terkunci.** Karena baris sudah
   dikunci, angka yang dibaca tidak bisa berubah sampai commit. Service bisa menolak
   dengan pesan jelas ("stok tidak cukup: tersedia 3, diminta 4").
3. **Tulis ledger dan stok dalam satu method.** `StockRepositoryInterface::recordMovement()`
   adalah satu-satunya jalan untuk mengubah `product_stock`. Method ini selalu menulis
   baris `stock_ledger` **dan** memperbarui stok, sehingga keduanya tidak mungkin terpisah.
4. **Lapis kedua — conditional update.** Update stok berbentuk
   `SET quantity = quantity + :delta WHERE ... AND quantity + :delta >= 0`. Bila jumlah
   baris yang berubah bukan 1, repository melempar `InsufficientStockException` dan
   seluruh transaksi di-rollback. Lapis ini melindungi kalau suatu hari ada kode yang
   lupa memanggil `lockQuantities()`.
5. **Lapis ketiga — database.** `CHECK (quantity >= 0)` pada `product_stock` dan
   `CHECK (received_qty <= qty)` pada `purchase_order_items` menolak data tidak valid
   walaupun kedua lapis di atas terlewati.

## Consequences

**Positif**
- Skenario oversell dan lost update tidak mungkin terjadi. Request kedua **ditunda**
  sampai yang pertama commit, lalu **ditolak** bila stok sudah tidak cukup.
- `product_stock` selalu sama dengan `SUM(stock_ledger)`, karena keduanya hanya berubah
  bersama di `recordMovement()` dalam satu transaksi.
- Aturan bisnis (cukup/tidak, status order) tetap di Service dan bisa di-unit-test dengan
  fake repository. Repository hanya bertanggung jawab atas lock dan SQL.

**Negatif / risiko yang diterima**
- Request kedua tertahan selama transaksi pertama. Transaksi dijaga tetap pendek (tanpa
  operasi lambat seperti upload file atau HTTP di dalamnya). Bila menunggu lebih dari
  `innodb_lock_wait_timeout` (default 50 detik), MySQL membatalkan request kedua dan user
  diminta mencoba lagi. Data tetap benar.
- Ada sedikit redundansi (cek di Service + guard di SQL + CHECK). Ini disengaja, bukan
  duplikasi logic: masing-masing lapis menjaga kegagalan yang berbeda.

## Bukti

- Integration test `StockConcurrencyTest`:
  - Koneksi kedua yang mencoba mengunci baris stok yang sedang dikunci koneksi pertama
    ditolak seketika (`FOR UPDATE NOWAIT`). Ini membuktikan request kedua tidak bisa
    membaca/menulis stok di tengah transaksi pertama.
  - Setelah transaksi pertama menghabiskan stok dan commit, permintaan kedua ditolak
    `InsufficientStockException`. Stok akhir 0, bukan negatif, dan cocok dengan ledger.
  - Conditional update menolak pengurangan melebihi stok walaupun lock dilewati.
- Simulasi thread/paralel sungguhan tidak dipakai (brief §3.1 tidak mewajibkan). Skenario
  terkontrol dengan dua koneksi PDO menunjukkan perilaku yang sama secara deterministik.

## Catatan lanjutan (2026-10-07)

Transfer stok antar-gudang (K-08) dibangun **tanpa mengubah `StockService` maupun
`MySqlStockRepository`**: `StockTransferService` cukup membuat dua `StockChange` per item
(Issue di gudang asal, Receipt di gudang tujuan). Kedua baris stok ikut dikunci dalam urutan
yang sama (SKU lalu gudang), sehingga dua transfer yang berlawanan arah pada produk yang sama
tidak bisa saling deadlock, dan transfer yang bersamaan dengan goods issue tetap tidak bisa
membuat stok negatif.

Saat menulis integration test transfer, ditemukan bahwa `Database::transactional` yang
dipanggil di dalam transaksi lain hanya "ikut" transaksi luar, sehingga kegagalan blok dalam
tidak membatalkan tulisannya sendiri. Kini blok bersarang memakai **SAVEPOINT** dan
`ROLLBACK TO SAVEPOINT` (commit `2a42d5e`), sehingga jaminan "semua atau tidak sama sekali"
berlaku di setiap tingkat.
