# Naskah Presentasi & Demo — IOMS (Intermediate Programmer)

Acuan: *Guidelines Presentation Final Project* (§2 Persiapan Wajib, §3 Susunan 10 Menit,
§4 Fokus Intermediate, §5 Ketentuan Demo, §6 Checklist) dan *Project Brief* (§5.2 Pemeriksaan
Langsung, §8.1 Demo & Technical Defense).

Format sesi: **10 menit presentasi + demo**, lalu **10 menit feedback & tanya jawab**. Waktu
tetap berjalan bila aplikasi gagal; gunakan bukti cadangan (bagian F).

| Bagian | Durasi | Inti |
|---|---|---|
| A. Pembukaan | 1 menit | project, masalah, role, status, kontribusi pribadi + penggunaan AI |
| B. Demo alur utama | 4 menit | PO → goods receipt → SO → approval → goods issue → stock ledger; pembatasan role; oversell ditolak |
| C. Implementasi teknis | 3 menit | layer & DIP, satu request ditelusuri sampai database, transaksi & anti-oversell, frontend/API, keamanan, Docker |
| D. Bukti kualitas | 1 menit | unit + integration test, PHPStan, SonarQube |
| E. Refleksi | 1 menit | kendala, solusi, keterbatasan, prioritas berikutnya |

---

## 0. Persiapan (H-1 dan 30 menit sebelum sesi)

### 0.1 Checklist Guidelines §2 & §6

| Item | Cara memenuhi | Status |
|---|---|---|
| Repository final & bisa diakses | Push semua commit; repo `madwanz64/ioms-final-project` (Public) | ☐ push |
| Link repo dikumpulkan lewat form | Batas form 4 Okt 2026. Bila belum, konfirmasi ke trainer | ☐ |
| Release/tag final | `git tag -a v1.0.0 -m "Release final"` lalu `git push origin v1.0.0`, **setelah** critique.md selesai | ☐ |
| README diuji dari environment bersih | `git clone` ke folder baru → `ioms up` → login → `ioms test` | ☐ |
| Docker & semua service bisa dibangun | dibuktikan langkah di atas | ☐ |
| Data demo aman & konsisten | `ioms reset` (data seed, tanpa data asli/PII) + persiapan 0.3 | ☐ |
| Unit + integration test & static analysis | `ioms test`, `ioms analyse`; output di `docs/testing/` | ✅ 194 test, PHPStan 0 error |
| SonarQube lulus | Gate "Sonar way" Passed — `docs/quality/sonarqube.md` | ✅ |
| `docs/quality/critique.md` | Ditulis setelah asesor memberi cuplikan kode (DESIGN-04) | ☐ |
| Bukti cadangan | screenshot sudah ada (bagian F); **rekam video demo 3–4 menit** | ☐ rekam |
| Latihan ≤ 10 menit | latih minimal 2 kali dengan timer | ☐ |
| Mampu menjelaskan tanpa AI | baca ulang ADR-001, ADR-002, R-01…R-04 | ☐ |

### 0.2 Siapkan layar (30 menit sebelum sesi)

1. `ioms up`, lalu `ioms reset` (jawab `y`). Data kembali ke seed awal.
2. `ioms sonar`, buka http://localhost:9000, login, buka project IOMS (tab *Overall Code*).
3. **Tiga jendela browser dengan session terpisah** supaya tidak perlu login–logout saat demo:
   - Chrome biasa → login **admin@ioms.test** / admin123
   - Chrome Incognito → login **sinta@ioms.test** / sales123
   - Edge (atau profil Chrome lain) → login **rudi@ioms.test** / gudang123
4. Terminal di folder project (untuk `ioms test`, `ioms low-stock`).
5. VS Code dengan tab yang sudah dibuka (urutan sesuai bagian C):
   `public/index.php`, `app/Controller/SalesOrderController.php`, `app/Service/SalesOrderService.php`,
   `app/Service/StockService.php`, `app/Repository/StockRepositoryInterface.php`,
   `app/Repository/MySqlStockRepository.php`, `tests/Fake/InMemoryStockRepository.php`,
   `database/schema-and-seed.sql`, `docs/architecture/adr-001-mekanisme-anti-oversell.md`,
   `docs/architecture/class-diagram-as-built.md`.

### 0.3 Siapkan skenario oversell (± 2 menit, setelah `ioms reset`)

Stok **SKU-0006 Monitor LED 24"** di Gudang Jakarta = **2**.

1. Jendela Sinta → *Sales Order* → *Buat SO*: customer bebas, gudang **Gudang Jakarta**,
   item **SKU-0006 qty 2** → simpan → **Ajukan**. Ulangi sekali lagi (dua SO, masing-masing qty 2).
   Keduanya boleh dibuat karena saat dibuat stoknya memang cukup (2 ≥ 2).
2. Jendela Admin → setujui **kedua** SO tersebut. Catat nomornya (mis. SO-2026-0017 dan -0018).

Hasil: dua SO Approved yang total kebutuhannya 4, sedangkan stok hanya 2.

---

## A. Pembukaan (1 menit)

Yang disampaikan (Guidelines §3: nama project, masalah, pengguna & role, status, kontribusi pribadi):

> "Saya [nama], project saya **Inventory & Order Management System**. Masalahnya: tim gudang dan
> sales perlu mencatat stok di **beberapa gudang**, memproses pembelian dan penjualan, dan angka
> stok harus selalu bisa dipertanggungjawabkan, termasuk saat dua proses berjalan bersamaan.
> Ada tiga role dengan tugas yang sengaja dipisah: **Admin**, **Sales**, dan **Warehouse Staff**;
> Sales tidak bisa menyetujui order miliknya sendiri.
> Status: semua requirement wajib §2 berjalan dan bisa dijalankan dengan Docker Compose; 194 test
> lulus; SonarQube Quality Gate *Sonar way* lulus. Yang belum: critique.md menunggu cuplikan dari asesor."

**Kontribusi pribadi & penggunaan AI — wajib disampaikan terbuka (Guidelines §5).**
Isi dengan jujur dan dengan kata-kata Anda sendiri. Kerangka:

> "Dalam pengerjaan saya memakai **Claude Code** sebagai asisten; semua sesi tercatat di
> `ai-usage-log.md`, termasuk output yang ditolak dan cara verifikasinya. Yang saya kerjakan dan
> putuskan sendiri: [isi — contoh yang tercatat: memilih mekanisme anti-oversell yang paling aman
> (ADR-001), mengonsultasikan 8 hal ambigu ke trainer dan menerapkan jawabannya (K-01…K-08,
> termasuk koreksi K-05), menetapkan scope, mereview dan menguji hasil…]."

> ⚠️ Jangan mengklaim menulis sendiri bagian yang dibuat AI. Brief §8.2: menyembunyikan
> penggunaan AI yang material dan tidak mampu menjelaskan keputusan sendiri termasuk *critical failure*.

---

## B. Demo alur utama (4 menit)

Fokus Intermediate (§4): PO, SO, penerimaan & pengeluaran barang, stock ledger, pembatasan
tiga role, anti-oversell. Produk demo: **SKU-0002 Mouse Wireless** — stok Jakarta **1**,
Surabaya **1**, reorder point **15** (low stock).

| # | Jendela | Langkah | Yang ditunjukkan / diucapkan | Data berubah |
|---|---|---|---|---|
| 1 | Rudi (Warehouse) | Buka **Dashboard** | antrean goods receipt & issue, produk low-stock — semua dari query agregasi | — |
| 2 | Rudi | *Purchase Order* → **PO-2026-0006** (Ordered, SKU-0002 0/15, Gudang Jakarta) → isi **Terima sekarang = 10** → simpan | penerimaan sebagian (partial) diperbolehkan; sisa 5 tetap tercatat | status → **PartiallyReceived**; stok Jakarta **1 → 11**; ledger **Receipt +10** |
| 3 | Sinta (Sales) | *Sales Order* → *Buat SO*: gudang **Jakarta**, item **SKU-0002**, qty **8** | petunjuk "**Stok tersedia: 11 pcs**" muncul tanpa reload → **Fetch API** ke `GET /api/products/SKU-0002/availability` | — |
| 4 | Sinta | Simpan (Draft) → **Ajukan untuk Persetujuan** | alur status Draft → **PendingApproval**; tidak ada tombol Approve untuk Sales | status SO |
| 5 | Sinta | Buka Console browser (F12), jalankan potongan di bawah tabel | **server** menolak dengan **403**, bukan hanya tombolnya disembunyikan (segregation of duties di authorization layer) | tidak berubah |
| 6 | Admin | Buka SO tadi → **Setujui** | Admin menyetujui; `approved_by` tercatat | → **Approved** |
| 7 | Rudi | Buka SO tadi → **Proses Goods Issue** → OK pada dialog konfirmasi | stok dikurangi + ledger ditulis dalam **satu transaksi** | → **Fulfilled**; stok Jakarta **11 → 3**; ledger **Issue −8** |
| 8 | Rudi | *Produk & Stok* → **SKU-0002** | stok per gudang (Jakarta 3, Surabaya 1, total 4) dan riwayat ledger: Receipt +10, Issue −8 | bukti perubahan data |
| 9 | Rudi | Buka dua SO Monitor LED dari persiapan 0.3 → goods issue SO pertama, lalu SO kedua | pertama berhasil (stok 2 → 0); kedua **ditolak**: "Stok SKU-0006 tidak mencukupi: tersedia 0, dibutuhkan 2" → **tidak ada oversell** | stok tetap 0, SO kedua tetap Approved |

Potongan untuk langkah 5 (Console, di halaman detail SO milik Sinta):

```js
fetch(location.pathname + '/approve', {
  method: 'POST',
  body: new URLSearchParams({ _csrf: document.querySelector('input[name=_csrf]').value }),
}).then(r => console.log('status', r.status));   // -> status 403
```

(Diverifikasi 2026-10-07 lewat HTTP terhadap aplikasi Docker: seluruh langkah B-2 s.d. B-9 berjalan dengan angka di tabel — stok Jakarta 1 → 11 → 3, POST approve oleh Sales → 403, goods issue kedua → "Stok SKU-0006 tidak mencukupi: tersedia 0, dibutuhkan 2.". Data demo sudah berubah setelah latihan, jadi **selalu `ioms reset` sebelum sesi**.)

**Bila waktu tersisa** (pilih salah satu): `ioms low-stock` di terminal (JOB-01, script terpisah
dari web), atau *Transfer Stok* 3 pcs Jakarta → Surabaya (perpindahan stok: Issue + Receipt
dengan ref TRF dalam satu transaksi).

---

## C. Implementasi teknis (3 menit)

Guidelines §2 meminta bukti teknis berupa **source code, struktur folder, skema database, query,
dan konfigurasi**. Telusuri **satu request goods issue** (langkah B-7) dari atas ke bawah:

| Urutan | File : baris | Yang dijelaskan |
|---|---|---|
| 1. Struktur | folder `app/Controller`, `app/Service`, `app/Repository`, `app/Entity`, `views/`, `tests/Unit`, `tests/Integration` | tiga layer pragmatis (ARCH-01); dependency mengarah Controller → Service → Repository |
| 2. Route & guard | `public/index.php:234` | route POST `/sales-orders/{id}/fulfill` hanya untuk Admin & Warehouse; Router memeriksa login, role, CSRF di server |
| 3. Composition root | `public/index.php:112-113` | `new StockService(new MySqlStockRepository($pdo))` — constructor injection manual, tanpa DI container |
| 4. Controller | `app/Controller/SalesOrderController.php:123` | hanya HTTP: ambil id, panggil service, flash + redirect |
| 5. Service | `app/Service/SalesOrderService.php:175` | dalam **satu transaksi**: kunci SO, cek status Approved, cek `SalesOrderPolicy::canFulfill`, buat `StockChange`, `stock->apply()`, ubah status |
| 6. Anti-oversell | `app/Service/StockService.php:29` | lapis 1: kunci baris stok `FOR UPDATE` **berurutan SKU lalu gudang** (cegah deadlock), cek cukup → pesan "tersedia X, dibutuhkan Y" |
| 7. Repository + query | `app/Repository/MySqlStockRepository.php:30` dan `:65-66` | `SELECT … FOR UPDATE`; lapis 2: `UPDATE … SET quantity = quantity + :delta WHERE … AND quantity + :delta >= 0` |
| 8. Skema | `database/schema-and-seed.sql:124` | lapis 3: `CHECK (quantity >= 0)`; FK & index (mis. `idx_ledger_product_warehouse`) |
| 9. DIP | `app/Repository/StockRepositoryInterface.php` → `MySqlStockRepository` & `tests/Fake/InMemoryStockRepository.php` | satu interface, dua implementasi: MySQL asli dan in-memory untuk unit test |
| 10. Keputusan | `docs/architecture/adr-001-mekanisme-anti-oversell.md` | mengapa pessimistic lock + guard SQL + CHECK, bukan optimistic locking |

Kalimat inti ARCH-02:

> "Dua goods issue untuk produk dan gudang yang sama: transaksi pertama mengunci baris stok dengan
> `FOR UPDATE`; transaksi kedua **menunggu**. Setelah yang pertama commit, yang kedua membaca stok
> terbaru, melihat stok kurang, dan ditolak. Kalaupun lock terlewat, `UPDATE` bersyarat dan
> `CHECK` di database tetap menolak stok negatif."

Sisa waktu (sebut singkat, tunjukkan bila ditanya):
- **Frontend**: Vanilla JS `public/js/app.js:124` (`fetchAvailability`) → endpoint JSON
  `ProductApiController::availability` (200 / 401 / 404, `application/json`).
- **Keamanan**: `password_hash`/`password_verify` (`UserService:179`, `AuthService:40`),
  `session_regenerate_id` setelah login (`Session.php:53`), CSRF di semua POST, prepared statement di
  semua query, output lewat `e()` (`app/helpers.php:9`).
- **Dashboard (DASH-01)**: query agregasi `app/Repository/MySqlReportRepository.php:32`
  (`SUM(quantity * buy_price)`); laporan CSV memakai repository yang sama.
- **Docker**: `compose.yaml` (service `db` mysql:8.0 dengan healthcheck + import seed otomatis,
  service `app` php:8.3-apache), konfigurasi lewat `.env` (contoh di `.env.example`).
- **Class diagram**: `docs/planning/class-diagram-initial.md` vs `docs/architecture/class-diagram-as-built.md`.

---

## D. Bukti kualitas (1 menit)

1. Terminal: `ioms test` → **194 test, 504 assertion, OK** (156 unit + 38 integration).
2. `ioms analyse` → PHPStan level 6 **No errors**.
3. Browser SonarQube → **Quality Gate Passed** (Sonar way):

> "Scan pertama ada 7 bug, 1 vulnerability, 84 code smell. Saya perbaiki — exception khusus,
> memecah method yang terlalu kompleks di Router dan validator, aksesibilitas form — sekarang 0/0/0,
> rating A. Gate sempat **gagal** karena coverage kode baru 60%; saya tambah test, bukan menurunkan
> ambang. Dari situ ketahuan Router belum pernah diuji PHPUnit. Coverage keseluruhan 66%; controller
> belum diuji PHPUnit dan saya catat sebagai tech-debt #18."

Detail: `docs/quality/sonarqube.md`, `docs/testing/hasil-test.md`.

---

## E. Refleksi (1 menit)

| | Isi (pilih 2–3, sampaikan dengan kata sendiri) |
|---|---|
| Kendala → solusi | Transaksi bersarang tidak membatalkan pekerjaannya sendiri saat gagal → **SAVEPOINT** di `Database::transactional`. Ledger seed tidak cocok dengan stok → generator seed merekonsiliasi. Jam PHP tersimpan UTC → `APP_TIMEZONE` + timezone session MySQL. Router ternyata tanpa test → ditemukan lewat mutation test, lalu `RouterTest` |
| Keterbatasan (tech-debt) | Controller belum diuji PHPUnit (#18); penolakan SO tidak mencatat siapa yang menolak (#13); stok baru dikunci saat goods issue, bukan saat approve (#14); gambar lama tidak dihapus (#1) |
| Prioritas berikutnya | test controller untuk alur PO/SO; jejak audit penolakan; reservasi stok saat Approved bila bisnis membutuhkan |

---

## F. Bukti cadangan (bila perangkat/jaringan bermasalah)

| Bukti | Lokasi |
|---|---|
| 53 screenshot: 28 desktop + 25 layar 360px (login, dashboard 3 role, produk, PO, SO, transfer, laporan, 403/404) | `docs/testing/screenshots/` |
| Hasil uji browser (responsif 50/50, Fetch API 9/9) | `docs/testing/ui-check-result.json`, `docs/testing/hasil-test.md` |
| Output PHPUnit & PHPStan | `docs/testing/phpunit-testdox.txt`, `docs/testing/phpstan-output.txt` |
| SonarQube | `docs/quality/screenshots/sonarqube-*.jpg` |
| Rekaman demo | **buat sendiri** (mis. Xbox Game Bar `Win+Alt+R`) dengan alur bagian B |

---

## G. Tanya jawab (10 menit) — permintaan yang mungkin muncul

Brief §5.2: asesor dapat meminta menjalankan Docker, menjalankan unit & integration test,
menelusuri class diagram ke kode, mendemokan skenario concurrency, membahas satu ADR, atau
membuat perubahan kecil dengan test tetap hijau.

| Permintaan / pertanyaan | Jawaban & yang dibuka |
|---|---|
| "Jalankan unit dan integration test terpisah" | `ioms test-unit` (156, tanpa database) dan `ioms test-int` (38, MySQL nyata). Lihat bagian H |
| "Buktikan skenario concurrency ARCH-02" | `docker compose exec app vendor/bin/phpunit --filter StockConcurrencyTest` (4 test). Jelaskan `testSecondConnectionCannotTouchStockRowLockedByFirstTransaction`: koneksi kedua mencoba `FOR UPDATE NOWAIT` pada baris yang dikunci → error MySQL **3572** (tanpa NOWAIT ia akan menunggu) |
| "Telusuri satu kelas dari diagram ke kode" | pilih `StockService` di `class-diagram-as-built.md` → panah ke `StockRepositoryInterface` (interface) → buka `app/Service/StockService.php` |
| "Mengapa Repository + interface?" | Service bisa diuji tanpa database (InMemory*), query terkumpul di satu tempat; ADR-001/ADR-002 |
| "Mengapa pessimistic lock, bukan optimistic?" | ADR-001 tabel opsi: dengan lock, request kedua **menunggu lalu membaca stok terbaru** dan bisa ditolak dengan pesan jelas; optimistic lock butuh kolom `version` + logika retry, paling rumit dijelaskan dan diuji. Konsekuensi yang diterima: request kedua tertahan sebentar. Lock diambil dengan urutan tetap (SKU, lalu gudang) untuk mencegah deadlock |
| "Kenapa conditional update saja tidak cukup?" | ADR-001 opsi B: atomik, tetapi tanpa lock, Service tidak bisa memeriksa "stok cukup" dan memberi pesan sebelum menulis. Karena itu B dipakai sebagai lapis kedua, bukan mekanisme utama |
| "Di mana Sales dicegah approve?" | `app/Service/SalesOrderPolicy.php:51` (`canReview`: hanya Admin & status PendingApproval), dipanggil di `SalesOrderService::approve`; diuji di `SalesOrderPolicyTest` dan integration `testSalesCannotApproveOwnOrderAndApprovalRecordsApprover` |
| "Tunjukkan satu transaksi multi-tabel dan satu index" (DB-01) | goods receipt / issue (C-5 s.d. C-8); index `idx_ledger_product_warehouse` mempercepat riwayat ledger per produk/gudang |
| "Ubah sedikit logic dan pastikan test tetap hijau" (safe-refactor) | contoh aman: di `app/Service/OrderLineValidator.php` ubah pesan/konstanta lalu `ioms test-unit`. Kalau aturan bisnis yang diubah, test yang relevan **harus** gagal dulu — itu bukti test bekerja |
| "Tunjukkan satu refactor" | `docs/quality/refactor-log.md` R-01 (Extract Class `InputValidator`) atau R-04 (Router); commit berawalan `refactor:` di `git log --oneline` |
| "Kenapa ada transfer stok?" | jawaban trainer K-08 ("perpindahan stok" = transfer antar-gudang), `docs/planning/catatan-keputusan.md` |
| "Apa saja yang dibantu AI?" | buka `ai-usage-log.md`; jelaskan apa yang Anda review, tolak, dan verifikasi |
| Critique exercise (DESIGN-04) | tulis di `docs/quality/critique.md`: smell, prinsip SOLID yang dilanggar, arah refactor |

---

## H. Di mana integration test-nya? (TEST-02)

| | |
|---|---|
| Folder | `tests/Integration/` — terpisah dari `tests/Unit/`, didaftarkan sebagai test suite **Integration** di `phpunit.xml.dist` |
| Database | MySQL 8 **nyata** di container `db`, database khusus **`ioms_test`** (dibangun ulang dari `database/schema-and-seed.sql` setiap run; tiap test dibungkus transaksi lalu di-rollback). Test menolak jalan bila nama database tidak berakhiran `_test`, jadi database aplikasi aman |
| Menjalankan | `ioms test-int` atau `docker compose exec app composer test:integration`; ikut juga di `ioms test` |
| Jumlah | **38 test** di 10 file (minimum brief: 3) |
| Bukti tertulis | `docs/testing/phpunit-testdox.txt` (bagian `Tests\Integration\…`), `docs/testing/hasil-test.md` |

Yang paling mewakili contoh di brief:

| Contoh di brief | Test |
|---|---|
| goods receipt benar-benar menambah stok end-to-end | `PurchaseOrderReceiptTest::testPartialThenFullReceiptUpdatesStockLedgerAndStatus` |
| goods issue kedua ditolak ketika stok sudah habis | `SalesOrderFulfillmentTest::testSecondGoodsIssueIsRejectedAfterFirstUsesUpTheStock`, `StockConcurrencyTest::testSecondIssueIsRejectedAfterFirstIssueUsedUpTheStock` |
| concurrency (dua transaksi) | `StockConcurrencyTest::testSecondConnectionCannotTouchStockRowLockedByFirstTransaction` (koneksi MySQL kedua) |
| lapisan pertahanan terakhir | `StockConcurrencyTest::testSqlGuardRejectsOversellEvenWithoutLockAndWritesNothing`, `…testDatabaseCheckConstraintIsTheLastLineOfDefence` |
| segregation of duties di server | `SalesOrderFulfillmentTest::testSalesCannotApproveOwnOrderAndApprovalRecordsApprover` |
| stok = jumlah ledger | `MySqlProductRepositoryTest::testStockPerWarehouseMatchesLedgerHistory`, `StockTransferTest::testTransferMovesStockKeepsTotalsAndLedgerConsistent` |

Bedanya dengan unit test (`tests/Unit/`, 156 test): unit test memakai repository **in-memory**
(`tests/Fake/`), tanpa database, session, atau jaringan — membuktikan aturan bisnis (TEST-01).
Integration test membuktikan query, transaksi, lock, dan constraint MySQL benar-benar bekerja.
