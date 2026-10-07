# ADR-002 · Otorisasi Sales Order: Policy di layer Service, bukan hanya di router/UI

- **Status:** Diterima
- **Tanggal:** 2026-10-07
- **Terkait:** SO-01, §1.2 (segregation of duties), §4.2, §8.2 (critical failure: authorization hanya di frontend)

## Context

Aturan SO tidak bisa dinyatakan hanya dengan role:

- Sales boleh membuat & mengajukan SO, tetapi **hanya miliknya sendiri**.
- Approve/reject hanya Admin, dan brief menegaskan Sales tidak boleh menyetujui order —
  "meskipun endpoint approve tetap tersedia" — dan aturan ini "wajib ditegakkan pada
  authorization layer di server, bukan hanya disembunyikan lewat UI".
- §1: "tidak ada satu peran yang bisa membuat sekaligus menyetujui transaksi yang sama".
- Sebagian aturan bergantung pada **status** order saat itu, yang bisa berubah oleh request
  lain (dua Admin menekan approve/reject bersamaan).

Router kita (`Router::get/post` dengan daftar role) hanya bisa memeriksa role, tidak bisa
memeriksa pemilik order atau status.

## Decision

1. Semua aturan "siapa boleh apa pada order ini" dikumpulkan di **`SalesOrderPolicy`**
   (kelas murni, tanpa I/O): `canView`, `canSubmit`, `canReview`, `canFulfill`, `canCancel`.
2. **`SalesOrderService` menegakkan policy** di setiap aksi, di dalam transaksi, pada baris
   order yang sudah **dikunci** (`lockById` = `SELECT ... FOR UPDATE`). Pelanggaran
   menghasilkan `AuthorizationException` (controller → 403). Order yang tidak boleh dilihat
   menghasilkan `NotFoundException` (→ 404) agar keberadaannya tidak bocor.
3. **View memakai policy yang sama** hanya untuk menentukan tombol yang tampil, sehingga
   aturan tidak ditulis dua kali dan UI tidak bisa "berbeda pendapat" dengan server.
4. Router tetap menyaring role kasar (mis. Warehouse tidak bisa membuat SO). Endpoint
   approve/reject **sengaja dibuka untuk semua role yang login**, sehingga penolakan Sales
   terbukti berasal dari authorization layer, persis seperti skenario brief.
5. Segregation of duties diterapkan **per user**: pembuat order tidak pernah boleh
   menyetujui/menolak order itu, **termasuk Admin** (lihat K-05 di
   `docs/planning/catatan-keputusan.md`).

## Consequences

**Positif**
- Aturan bisa di-unit-test langsung tanpa HTTP/DB (`SalesOrderPolicyTest`: matriks approve
  6 kasus), dan ditegakkan walau service dipanggil langsung (`SalesOrderServiceTest`).
- Karena dicek setelah lock, dua aksi bersamaan pada order yang sama diproses berurutan;
  aksi kedua melihat status terbaru dan ditolak bila sudah tidak berlaku.
- Menambah aturan baru cukup di satu tempat.

**Negatif / risiko yang diterima**
- Dengan satu akun Admin (seed minimum §7.1), SO yang dibuat Admin sendiri tidak bisa
  disetujui siapa pun. Ini disengaja (lebih aman), dan solusinya menambah Admin kedua.
  Dicatat di tech-debt sebagai keterbatasan operasional.
- Ada dua lapis pemeriksaan role (router & policy). Duplikasi ini disengaja (defense in
  depth), bukan logic bisnis yang tersalin.
