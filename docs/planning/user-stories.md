# User Story

> Disusun ulang pada 2026-10-07 dari Project Brief §1–§2. Kriteria penerimaan diambil dari
> "Ketentuan minimum" setiap requirement. Kolom **Bukti** merujuk test otomatis atau skenario
> di [`docs/testing/hasil-test.md`](../testing/hasil-test.md).

Format: *Sebagai <peran>, saya ingin <tujuan>, agar <manfaat>.*

## Semua peran

| ID | Story | Kriteria penerimaan | Req | Bukti |
|---|---|---|---|---|
| US-01 | Sebagai pengguna, saya ingin login dengan email & password agar melihat menu sesuai peran saya. | Login valid → dashboard sesuai role; kredensial salah atau akun nonaktif → satu pesan generik; ID session diperbarui setelah login. | AUTH-01 | `AuthServiceTest`, `AuthIntegrationTest`, slice 1 #2–#6 |
| US-02 | Sebagai pengguna, saya ingin logout agar orang lain tidak bisa memakai sesi saya. | Session dihapus; URL terlindungi kembali ke login. | AUTH-02 | slice 1 #20 |
| US-03 | Sebagai pengguna, saya ingin mengganti nama dan password saya sendiri. | Ganti password wajib password lama; email/role/status tidak bisa diubah sendiri. | §1.2 | `UserServiceTest`, slice 8 |

## Admin

| ID | Story | Kriteria penerimaan | Req | Bukti |
|---|---|---|---|---|
| US-10 | Sebagai Admin, saya ingin membuat akun Sales dan Warehouse Staff, karena tidak ada registrasi publik. | Email unik, role hanya 3 nilai, password di-hash, bisa dinonaktifkan; Sales/Warehouse mendapat 403. | USR-01 | `UserServiceTest`, slice 2 |
| US-11 | Sebagai Admin, saya ingin mengelola produk beserta reorder point dan gambarnya. | SKU unik, angka ≥ 0, produk hanya dinonaktifkan; gambar divalidasi tipe & ukuran, nama acak. | PRD-01 | `ProductServiceTest`, slice 1 #16–#19 |
| US-12 | Sebagai Admin, saya ingin mengelola kategori, gudang, supplier, dan customer. | Gudang baru otomatis punya baris stok untuk setiap produk; supplier/customer dinonaktifkan, tidak dihapus. | WH-01, §1.3 | `MasterDataServiceTest`, `MasterDataRepositoryTest` |
| US-13 | Sebagai Admin, saya ingin menyetujui atau menolak Sales Order. | Hanya order PendingApproval; Sales tidak bisa menyetujui walau endpoint dipanggil langsung (server). | SO-01, §1.2 | `SalesOrderPolicyTest`, slice 4 #8 |
| US-14 | Sebagai Admin, saya ingin melihat nilai inventori, produk di bawah reorder point, dan order per status. | Semua angka dari query agregasi. | DASH-01 | `ReportRepositoryTest`, slice 5 #1 |
| US-15 | Sebagai Admin, saya ingin mengunduh CSV pergerakan stok dan status order per rentang tanggal. | Query sama dengan dashboard; rentang tervalidasi. | REPORT-01 | `ReportServiceTest`, slice 5 #4–#7 |

## Sales

| ID | Story | Kriteria penerimaan | Req | Bukti |
|---|---|---|---|---|
| US-20 | Sebagai Sales, saya ingin membuat Sales Order dari katalog dan stok yang tersedia. | Status Draft; harga jual per item dari form, default harga katalog (K-07); qty dicek terhadap stok gudang asal (petunjuk stok via API). | SO-01, API-01 | `SalesOrderServiceTest`, slice 4 #4–#5, slice 10 |
| US-21 | Sebagai Sales, saya ingin mengajukan order agar diproses Admin. | Draft → PendingApproval; tidak bisa menyetujui order sendiri. | SO-01 | slice 4 #7–#8 |
| US-22 | Sebagai Sales, saya ingin melihat ringkasan dan laporan order **milik saya** saja. | Daftar, dashboard, dan CSV dibatasi `created_by` di query; order orang lain 404. | DASH-01, REPORT-01 | `SalesOrderFulfillmentTest`, slice 4 #1, #3 |

## Warehouse Staff

| ID | Story | Kriteria penerimaan | Req | Bukti |
|---|---|---|---|---|
| US-30 | Sebagai Warehouse Staff, saya ingin membuat dan memesan Purchase Order saat stok rendah. | Warehouse boleh membuat PO dan menandainya Ordered (K-01); pembuat PO tercatat (K-03); pembatalan oleh Admin. | PO-01 | `PurchaseOrderServiceTest`, `PurchaseOrderReceiptTest`, slice 10 |
| US-31 | Sebagai Warehouse Staff, saya ingin mencatat penerimaan barang, termasuk sebagian. | Stok bertambah + ledger Receipt dalam satu transaksi; sisa qty tercatat; status Partially/Received. | PO-01, ARCH-02 | `PurchaseOrderReceiptTest`, slice 3 |
| US-32 | Sebagai Warehouse Staff, saya ingin memproses goods issue SO yang Approved tanpa risiko oversell. | Ditolak bila stok kurang; aman bila dua proses berjalan bersamaan. | SO-01, ARCH-02 | `StockConcurrencyTest`, slice 4 #15 |
| US-33 | Sebagai Warehouse Staff, saya ingin melihat antrean penerimaan/pengiriman dan produk low-stock. | Antrean PO Ordered/PartiallyReceived dan SO Approved. | DASH-01 | slice 5 #3 |
| US-34 | Sebagai Warehouse Staff, saya ingin ringkasan harian produk di bawah reorder point dari script terjadwal. | `php scripts/check-low-stock.php` berjalan di luar web, bisa via `docker compose exec`. | JOB-01 | `LowStockReportTest`, slice 6–7 |
| US-35 | Sebagai Warehouse Staff, saya ingin memindahkan stok dari satu gudang ke gudang lain. | Gudang asal ≠ tujuan & aktif; Issue asal + Receipt tujuan dalam satu transaksi; ditolak seluruhnya bila stok asal kurang; total stok semua gudang tetap. | K-08, ARCH-02 | `StockTransferServiceTest`, `StockTransferTest`, slice 10 |

## Lintas peran (non-fungsional)

| ID | Story | Req |
|---|---|---|
| US-40 | Sebagai pengguna, saya ingin mencari, memfilter, mengurutkan, dan berpindah halaman di daftar produk/PO/SO tanpa kehilangan filter. | FIND-01 |
| US-41 | Sebagai pengguna, saya ingin pesan error yang jelas dan aman (403/404/500 tanpa detail teknis). | ERR-01, VAL-01 |
| US-42 | Sebagai pengguna seluler, saya ingin aplikasi bisa dipakai di layar 360px. | UI-01 |
