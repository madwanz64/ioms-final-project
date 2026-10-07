# Catatan Keputusan atas Requirement yang Ambigu

Brief §9 no. 12: requirement yang ambigu ditanyakan ke trainer, lalu jawabannya disimpan di sini.
Keputusan di bawah adalah **interpretasi sementara peserta** sampai dikonfirmasi trainer.
Kolom "Status" diperbarui setelah ada jawaban.

| # | Tanggal | Requirement | Ambiguitas | Interpretasi yang dipakai | Alasan | Status |
|---|---|---|---|---|---|---|
| K-01 | 2026-10-07 | §1.2 "Membuat Purchase Order — Warehouse Staff: Boleh mengusulkan" | Apa beda "mengusulkan" dengan "membuat"? | Warehouse Staff boleh **membuat PO berstatus Draft** (usulan). Hanya **Admin** yang mengubah Draft → Ordered (PO resmi dikirim ke supplier) dan yang boleh membatalkan PO. | Mengikuti semangat segregation of duties §1: pembuat usulan tidak sekaligus menyetujui pengeluaran uang ke supplier. | Menunggu konfirmasi trainer |
| K-02 | 2026-10-07 | PO-01 "Draft -> Ordered -> PartiallyReceived/Received -> Cancelled" | Dari status mana saja PO boleh dibatalkan? Notasi panah bisa dibaca "Received pun bisa Cancelled". | Pembatalan hanya dari **Draft** atau **Ordered** (belum ada barang diterima). PO PartiallyReceived/Received tidak bisa dibatalkan. | Barang yang sudah diterima sudah menambah stok dan tercatat di ledger. Membatalkan PO itu akan membuat dokumen tidak konsisten dengan ledger, kecuali ada proses retur (di luar scope). Sejalan dengan aturan SO: Cancelled hanya sebelum Fulfilled. | Menunggu konfirmasi trainer |
| K-03 | 2026-10-07 | DB-01 / §1.3 PurchaseOrder | Brief tidak mewajibkan kolom "dibuat oleh" pada PO (berbeda dengan SO). | Tabel `purchase_orders` tidak diberi `created_by`. Siapa yang menerima barang tetap tercatat di `stock_ledger.performed_by`. | Menghindari perubahan skema di luar field minimum; jejak audit stok sudah ada di ledger. | Dicatat juga di tech-debt |
