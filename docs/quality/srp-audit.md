# Audit SRP (DESIGN-03)

> *Single Responsibility Principle*: sebuah kelas hanya punya **satu alasan untuk berubah**.

## Kelas yang diaudit: `StockService` pada draf awal

Sumber: [class diagram initial](../planning/class-diagram-initial.md) (commit `e82e59a`, dibuat
sebelum kode PHP pertama ditulis).

```
class StockService {
  +receive(poId, items, user)     // goods receipt PO
  +issue(soId, user)              // goods issue SO
}
StockService ..> StockRepositoryInterface
PurchaseOrderController --> StockService
SalesOrderController --> StockService
```

### Pelanggaran yang ditemukan

Kalau dibangun sesuai draf, `receive()` dan `issue()` harus mengerjakan semua hal berikut:

| # | Tanggung jawab | Alasan untuk berubah | Pemilik bisnisnya |
|---|---|---|---|
| 1 | Aturan dokumen PO: hanya status Ordered/PartiallyReceived yang boleh menerima, qty tidak melebihi sisa, status akhir Partially/Received | Alur PO berubah (mis. aturan pembatalan K-02) | Pembelian |
| 2 | Aturan dokumen SO: hanya Approved yang boleh dikirim, status menjadi Fulfilled | Alur SO berubah | Penjualan |
| 3 | Otorisasi: siapa yang boleh memproses goods issue | Aturan peran berubah (mis. koreksi K-05) | Keamanan / SoD |
| 4 | Mekanisme konkurensi: lock berurutan, cek stok, tulis ledger + stok | Mekanisme anti-oversell berubah (ADR-001) | Teknis / integritas data |
| 5 | Batas transaksi: apa saja yang harus commit bersama | Data yang ikut dalam satu operasi berubah | Teknis |

Ada lima alasan untuk berubah, dan dua di antaranya (PO dan SO) bahkan milik modul bisnis yang
berbeda. Gejalanya juga sudah terlihat di diagram: `StockService` harus mengenal
`PurchaseOrderRepositoryInterface` **dan** `SalesOrderRepositoryInterface`, sehingga perubahan
apa pun di PO atau SO menyentuh kelas yang juga memegang logika paling kritis (anti-oversell).

### Cara dipecah (as-built)

Pemecahan dilakukan saat merancang modul PO, **sebelum** kode stok ditulis (bersamaan dengan
ADR-001), jadi tidak ada versi "gemuk" yang sempat di-commit:

```
StockService::apply(list<StockChange>)          ← hanya #4: lock berurutan, cek, catat
PurchaseOrderService::receive(id, qty, actor)   ← #1 + #5 untuk PO
SalesOrderService::fulfill(id, actor)           ← #2 + #5 untuk SO
SalesOrderPolicy::canFulfill(...)               ← #3
TransactionManagerInterface                     ← cara membuka transaksi (#5) tanpa PDO di Service
```

| Kelas setelah dipecah | Satu alasan untuk berubah | Diuji oleh |
|---|---|---|
| `StockService` | mekanisme perubahan stok (ADR-001) | `StockServiceTest` (5), `StockConcurrencyTest` (4, MySQL) |
| `PurchaseOrderService` | aturan alur PO | `PurchaseOrderServiceTest` (16), `PurchaseOrderReceiptTest` (3) |
| `SalesOrderService` | aturan alur SO | `SalesOrderServiceTest` (12), `SalesOrderFulfillmentTest` (3) |
| `SalesOrderPolicy` | aturan otorisasi SO | `SalesOrderPolicyTest` (10) |

Bahasa bersama di antara keduanya adalah objek nilai `StockChange` (SKU, gudang, delta, tipe,
referensi, pelaku). `StockService` tidak tahu apa itu PO atau SO; ia hanya menerima daftar
perubahan.

### Bukti manfaatnya (bukan klaim)

- **Koreksi K-05** (Admin boleh menyetujui SO sendiri) hanya mengubah satu baris di
  `SalesOrderPolicy::canReview()`. `StockService` dan seluruh test stok tidak tersentuh.
- **Goods issue SO** dibangun setelah PO tanpa mengubah `StockService` sama sekali; SO cukup
  membuat `StockChange` dengan delta negatif.
- Mutation test pada `FOR UPDATE` dan guard SQL (slice 3) hanya menyentuh
  `MySqlStockRepository`, dan test yang gagal hanya test stok, bukan test alur PO/SO.

## Catatan audit tambahan (lebih kecil)

| Kelas | Masalah SRP | Penanganan |
|---|---|---|
| `ProductService` (slice 1) | Aturan produk bercampur dengan parsing & validasi generik setiap field | Diekstrak ke `InputValidator` (refactor R-01) |
| `PurchaseOrderService` (slice 3) | Alur PO bercampur dengan parsing baris item form (293 baris) | Diekstrak ke `OrderLineValidator` (refactor R-02) |
| `DashboardService` vs laporan | Risiko query agregasi ditulis dua kali (dashboard & CSV) | Satu `ReportRepositoryInterface` dipakai keduanya |

## Yang sengaja **tidak** dipecah

`SalesOrderService` masih memegang lima aksi (submit, approve, reject, cancel, fulfill). Semuanya
berubah karena alasan yang sama, yaitu aturan alur SO, dan berbagi pola "kunci → cek policy →
ubah status". Memecahnya menjadi satu kelas per aksi akan menambah lapisan tanpa mengurangi
alasan untuk berubah, dan itu termasuk over-engineering yang diperingatkan brief §0.
