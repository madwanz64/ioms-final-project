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
| 2026-09-11 | Claude Code (Sonnet 5) | Implementasi HTML/CSS/JS interaktif — vertical slice: Login, Dashboard 3 role, Produk (PRD-01/WH-01/FIND-01), Sales Order (SO-01) | Diminta lanjut dari wireframe ke pengembangan nyata (HTML/CSS/JS, Vanilla JS, animasi CSS tambahan), dengan data (auth, produk, order) disimpan di file JSON — backend PHP belum ada. Disepakati lewat tanya-jawab: scope vertical slice dulu (bukan 22 halaman sekaligus), JSON hanya sebagai seed awal + localStorage untuk perubahan runtime (karena browser tak bisa menulis balik ke .json), dan arah desain visual diserahkan ke AI. | `public/` berisi: `css/style.css` (design system + animasi), modul JS bersama (`data-store.js` sebagai fake repository, `auth.js`, `nav.js`, `utils.js`), 9 halaman fungsional (login, 3 dashboard, produk list/form/detail, SO list/form/detail), data seed JSON (26 produk, 14 SO) dari `scripts/generate-seed.js`, dan `scripts/static-server.js` untuk menjalankan lokal. Batasan keamanan (password plaintext client-side) dan batasan arsitektur (localStorage bukan DB bersama, tidak ada transaksi atomik sungguhan) dicatat eksplisit sebagai komentar kode, bukan disembunyikan. | Sempat menyertakan input "Qty Dikeluarkan" yang bisa diedit manual pada goods issue SO — dikoreksi sendiri setelah code review karena tidak sesuai model data brief (SO tidak punya status PartiallyFulfilled, jadi goods issue seharusnya all-or-nothing di qty penuh, bukan bisa diedit sebagian). | Karena tidak ada akses browser di lingkungan ini: sintaks tiap file JS divalidasi dengan `node --check`, seluruh JSON divalidasi dengan `JSON.parse`, referensi `id`/selector antara HTML dan JS dicocokkan manual, dan seluruh halaman di-smoke-test lewat HTTP (curl ke static server lokal) untuk memastikan tidak ada 404. Peserta perlu menguji sendiri interaksi sungguhan di browser (`node scripts/static-server.js` lalu buka `http://localhost:5173`). |
| 2026-09-11 | Claude Code (Sonnet 5) | Melengkapi sisa requirement wajib §2: Purchase Order (PO-01), Supplier, master data sederhana (Kategori/Gudang/Customer), User (USR-01), Laporan CSV (REPORT-01), 404 (ERR-01), API-01 | Diminta lanjut membangun "fitur sisanya" setelah vertical slice awal. Untuk PO+Supplier, sempat ditanya prioritas lewat pilihan bertingkat sebelum lanjut ke sisanya sekaligus tanpa tanya ulang. | Data seed diperluas (`generate-seed.js`): 5 supplier, 10 PO dengan status bervariasi termasuk PartiallyReceived, entri StockLedger tipe Receipt. Halaman baru: Supplier, Kategori, Gudang, Customer, User (CRUD lengkap, Admin only), Purchase Order (list/form/detail dengan goods receipt PARSIAL — beda dari SO yang all-or-nothing), Laporan Stock Ledger + status order dengan ekspor CSV nyata (Blob + `<a download>`), halaman 404 fungsional. Endpoint `GET /api/products/:sku/availability` diimplementasikan sungguhan di `static-server.js` (bukan simulasi) dengan skenario 200/401/404 teruji via curl, plus panel demo pemanggilannya di halaman detail produk. | - | Tiap fitur diverifikasi dengan cara yang sama seperti sesi sebelumnya (node --check, validasi JSON, cocokkan id HTML/JS, smoke-test HTTP) ditambah pengujian langsung endpoint API lewat curl untuk ketiga skenario status code sebelum dianggap selesai. Keterbatasan endpoint API (baca seed file, bukan localStorage; "token" bukan autentikasi sungguhan) dicatat eksplisit di komentar kode `static-server.js`. |

## Deklarasi

- [ ] Tidak ada source code proprietary, data client, atau credential yang dikirim ke layanan AI publik.
- [ ] Setiap output AI yang dipakai telah direview dan diverifikasi terhadap requirement brief sebelum di-commit.
- [ ] Peserta memahami dan dapat menjelaskan seluruh keputusan arsitektur pada log ini tanpa bantuan AI saat technical defense.

*Jika project ini tidak menggunakan AI sama sekali pada bagian tertentu, nyatakan secara eksplisit di sini, jangan dibiarkan kosong tanpa keterangan.*
