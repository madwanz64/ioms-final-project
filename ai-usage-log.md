# AI Usage Log

Log ini mencatat seluruh penggunaan AI selama pengerjaan Final Project Inventory & Order
Management System, sesuai kewajiban **DISCLOSE, REVIEW, VERIFY, TEST** pada
Project Brief §6.2.

Setiap entri wajib diisi jujur. Jika suatu sesi tidak menghasilkan perubahan yang dipakai,
tetap dicatat dengan status ditolak pada kolom Output.

## Cara Mengisi

| Kolom | Isi |
|---|---|
| Tanggal | Tanggal sesi (YYYY-MM-DD) |
| Tool | Nama AI yang dipakai (mis. Claude Code, ChatGPT) |
| Tujuan | Requirement ID terkait (mis. `ARCH-02`, `SO-01`) atau tugas umum (mis. "setup Docker") |
| Ringkasan Prompt | Ringkasan singkat permintaan yang diajukan ke AI (disanitasi - tanpa credential/data sensitif) |
| Output Digunakan | Apa yang diambil dari hasil AI dan dipakai di kode/dokumen final |
| Output Ditolak | Apa yang disarankan AI tapi tidak dipakai, dan alasannya |
| Verifikasi | Bagaimana output diverifikasi (test yang dijalankan, requirement yang dicek, review manual) |

## Entri Log

| Tanggal | Tool | Tujuan | Ringkasan Prompt | Output Digunakan | Output Ditolak | Verifikasi |
|---|---|---|---|---|---|---|
| 2026-09-04 | Claude Code (Sonnet 5) | Wireframe HTML/CSS seluruh alur — VIEW-01, UI-01, DASH-01, FIND-01 | Diminta membuat wireframe low-fidelity (HTML + CSS murni, tanpa JS/PHP/SQL/Docker) untuk seluruh halaman aplikasi sesuai brief: login, dashboard 3 role, master data, Purchase Order, Sales Order, laporan, dan error state; termasuk anotasi perbedaan akses per role sesuai matriks §1.2. | 22 file HTML wireframe + 1 `wireframe.css` bersama di `docs/planning/wireframes/`, mengikuti struktur data §1.3 dan alur status PO/SO §1.3. | - | Ditinjau manual oleh peserta di browser (Chrome DevTools, mode responsive 425px & desktop) dengan menyusuri navigasi antar halaman. |
| 2026-09-04 | Claude Code (Sonnet 5) | Perbaikan bug responsive — UI-01 | Peserta menemukan lewat inspect element: (1) nav sidebar di layar sempit overflow horizontal tanpa indikasi bisa di-scroll, item menu tersembunyi; (2) tabel "Produk di bawah reorder point" pada dashboard tidak bisa di-scroll horizontal walau tabel lain di halaman lain bisa. Diminta dianalisis dan diperbaiki. | Nav diubah jadi flex-wrap di breakpoint mobile agar semua item menu selalu terlihat; ditambahkan `min-width:0` pada `.panel` untuk mengatasi default `min-width:auto` pada grid item yang mencegah `overflow-x:auto` bekerja; tabel item baris PO/SO dibungkus `.line-items-scroll`. | Sempat mencoba trik `display:table` untuk membuat tabel item baris scrollable — dibatalkan sendiri karena berisiko merusak kesejajaran kolom header/body, diganti dengan wrapper div `overflow-x:auto` yang lebih aman. | Peserta mengecek ulang tampilan di Chrome DevTools responsive mode setelah perbaikan dan mengonfirmasi kedua bug tidak muncul lagi. |

## Deklarasi

- [ ] Tidak ada source code proprietary, data client, atau credential yang dikirim ke layanan AI publik.
- [ ] Setiap output AI yang dipakai telah direview dan diverifikasi terhadap requirement brief sebelum di-commit.
- [ ] Peserta memahami dan dapat menjelaskan seluruh keputusan arsitektur pada log ini tanpa bantuan AI saat technical defense.

*Jika project ini tidak menggunakan AI sama sekali pada bagian tertentu, nyatakan secara eksplisit di sini, jangan dibiarkan kosong tanpa keterangan.*
