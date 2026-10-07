# Scope

> Ringkasan scope dari Project Brief, disusun 2026-10-07. Keputusan atas bagian yang ambigu
> ada di [catatan-keputusan.md](catatan-keputusan.md).

## Dalam scope (wajib, §2–§5)

| Area | Isi | Status |
|---|---|---|
| Autentikasi & user | Login/logout berbasis session, 3 role, manajemen user oleh Admin, profil sendiri | ✅ |
| Master data | Produk (+gambar), kategori, gudang, supplier, customer — nonaktif, bukan hapus | ✅ |
| Stok multi-gudang | Baris stok per produk per gudang; stok hanya berubah lewat ledger | ✅ |
| Purchase Order | Draft → Ordered → PartiallyReceived/Received, Cancelled; goods receipt transaksional | ✅ |
| Sales Order | Draft → PendingApproval → Approved → Fulfilled / Cancelled; approval di server; goods issue anti-oversell | ✅ |
| Daftar & laporan | Search/filter/sort/pagination, dashboard 3 role, CSV, empty state | ✅ |
| API & job | 1 endpoint JSON (availability), script low-stock | ✅ |
| Kualitas | Controller/Service/Repository + interface, unit & integration test, PHPStan, ADR, refactor log, SRP audit, tech-debt | ✅ |
| Lingkungan | Docker Compose (app + MySQL 8), `.env.example` | ✅ |

## Di luar scope (§4.3)

Microservices, message queue, cloud deployment, CI/CD, Kubernetes, notifikasi real-time,
aplikasi mobile, cron scheduler otomatis (script cukup dijalankan manual), dan automated
end-to-end test (browser).

Hal lain yang **sengaja tidak dibuat** karena tidak diminta brief:
- retur barang / pembatalan PO yang sudah menerima barang (K-02);
- edit PO/SO Draft (tech-debt #10), diskon atau harga per order (K-07);
- reservasi stok saat SO disetujui (tech-debt #14);
- registrasi publik (dilarang §2.1);
- fitur bonus §4.4 (email simulasi, audit trail master data, grafik). Bisa dikerjakan setelah
  semua requirement wajib stabil.

## Batasan teknis yang dipegang (§4)

- PHP 8.2+ native tanpa framework/ORM/DI container; Composer hanya untuk autoload & dev
  dependency.
- Vanilla JS + Fetch API, CSS buatan sendiri (tanpa framework CSS/JS).
- MySQL 8 dengan PDO prepared statement, transaksi eksplisit, constraint & index.
- Tidak ada secret/credential aktif di repository (`.env` di-ignore; `.env.example` berisi
  contoh nilai development).
