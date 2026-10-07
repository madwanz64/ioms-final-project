# Analisis SonarQube

SonarQube Community Build dijalankan lokal di Docker (profile `sonar` di `compose.yaml`)
dengan Quality Gate bawaan **"Sonar way"** dan Quality Profile bawaan "Sonar way" untuk
PHP, JavaScript, CSS, dan HTML. Tidak ada aturan atau ambang gate yang diubah.

> Kriteria lulus SonarQube untuk penilaian belum disebutkan rinci di brief. Yang dipakai di
> sini adalah gate bawaan; bila trainer memakai ambang lain, angka di bawah tetap bisa dipakai
> sebagai pembanding.

## Hasil akhir (2026-10-07)

**Quality Gate: Passed**

| Kondisi gate (kode baru) | Ambang | Hasil |
|---|---|---|
| Coverage kode baru | ≥ 80% | **85,1%** (67 baris baru yang perlu dicakup) |
| Duplikasi kode baru | ≤ 3% | **0,0%** |
| Issue baru | 0 | **0** |

| Keseluruhan kode | Scan pertama | Akhir |
|---|---|---|
| Bug (Reliability) | 7 (rating C) | **0 (A)** |
| Vulnerability (Security) | 1 (rating B) | **0 (A)** |
| Code smell (Maintainability) | 84 (rating A) | **0 (A)** |
| Security hotspot | 0 | 0 |
| Coverage | 58,9% | **66,2%** |
| Duplikasi | 1,2% | 1,2% |
| Test (PHPUnit, dibaca dari junit.xml) | 169 | **194** |

Ukuran: 7,1 ribu baris kode (PHP 5.875 · JavaScript 787 · CSS 414), 151 file.

Coverage per lapisan (`app/`):

| Lapisan | Coverage | Keterangan |
|---|---|---|
| `app/Repository` | 91,8% | integration test ke MySQL `ioms_test` |
| `app/Service` | 85,0% | unit test dengan repository in-memory |
| `app/Entity` | 76,1% | |
| `app/Core` | 53,8% | Router kini diuji; Session/Database/Response::send bergantung pada runtime HTTP |
| `app/Controller` | 0% | diuji lewat smoke test HTTP dan uji browser, bukan PHPUnit (tech-debt #18) |

Screenshot: [overview keseluruhan](screenshots/sonarqube-overview.jpg) ·
[kode baru](screenshots/sonarqube-new-code.jpg) · [coverage per folder](screenshots/sonarqube-coverage.jpg).

## Catatan penting tentang "Passed"

1. **Gate pada scan pertama tidak berarti apa-apa.** Scan pertama langsung berstatus *OK*
   dengan daftar kondisi kosong, karena "Sonar way" hanya menilai *kode baru* dan pada analisis
   pertama seluruh kode menjadi baseline. Karena itu ukuran yang dipakai untuk perbaikan adalah
   angka keseluruhan (7 bug, 1 vulnerability, 84 code smell).
2. **Gate sempat gagal, dan itu benar.** Setelah perbaikan, baris yang diubah dihitung sebagai
   kode baru dan coverage-nya 60,6% (< 80%), sehingga gate **Failed**. Penyebabnya nyata:
   `LocalImageStorage` belum punya test sama sekali, dan jalur error repository belum diuji.
   Test ditambahkan (lihat di bawah), bukan ambangnya yang diturunkan.
3. **Coverage keseluruhan 66,2%, bukan 80%.** Controller tidak diuji dengan PHPUnit. Ini
   dicatat sebagai tech-debt #18, tidak disembunyikan dengan exclusion. Selain controller,
   yang masih rendah: `DashboardService` (27%), `Request`/`Session`/`View` (runtime HTTP), dan
   enum status yang label/warnanya hanya dipakai view.
4. **Rating Security A bukan bukti bebas SQL injection/XSS.** SonarQube Community Build
   menampilkan peringatan *"Limited security analysis"*: analisis *taint* untuk injection (SQL
   injection, XSS, dan sejenisnya) tidak tersedia di edisi ini. Perlindungan terhadap keduanya
   dibuktikan dengan cara lain: seluruh query memakai prepared statement (tidak ada input yang
   digabung ke SQL), semua output view lewat `e()` (`htmlspecialchars`), dan test/uji manual di
   [hasil-test.md](../testing/hasil-test.md).

## Cara menjalankan ulang

```bash
# 1. Jalankan server SonarQube (pertama kali ±1–2 menit), buka http://localhost:9000
docker compose --profile sonar up -d sonarqube
#    Login pertama admin/admin -> ganti password, lalu buat token
#    (My Account > Security > Global Analysis Token) dan simpan di .env:
#    SONAR_TOKEN=squ_...        (.env tidak di-commit)

# 2. Coverage + hasil test (PHPUnit + PCOV di container app), salin ke ./build
docker compose up --build -d
docker compose exec app composer test:coverage
docker compose cp app:/var/www/html/build ./build

# 3. Scan
docker compose --profile sonar run --rm sonar-scanner
```

Container scanner memasang folder project di path yang sama dengan container app
(`/var/www/html`), sehingga path file di `build/coverage.xml` cocok dengan file yang dianalisis.
Konfigurasi analisis ada di [`sonar-project.properties`](../../sonar-project.properties).

## Cakupan analisis

| Dianalisis | Tidak dianalisis / tidak dihitung |
|---|---|
| `app/`, `config/`, `public/` (front controller, CSS, JS), `views/`, `scripts/`, `database/`; test di `tests/` | `vendor/`, `docs/`, `prototype/` (arsip prototype HTML/JS sebelum fase PHP, tidak dijalankan aplikasi), `public/uploads/` (file unggahan) |

Coverage hanya dihitung untuk `app/`. Template (`views/`), front controller, konfigurasi, dan
script CLI tetap dianalisis untuk bug/smell, tetapi tidak dihitung coverage-nya karena tidak
diuji dengan PHPUnit (diverifikasi lewat smoke test HTTP dan uji browser).

## Temuan dan tindakan

### Diperbaiki

| Aturan | Jumlah | Contoh lokasi | Tindakan |
|---|---|---|---|
| php:S112 exception generik | 10 | repository, `LocalImageStorage`, `View`, `Response` | Exception khusus: `PersistenceException` (lock di luar transaksi, baris stok hilang, terima melebihi pesanan), `ImageStorageException`, `InfrastructureException` |
| php:S3776 Cognitive Complexity | 2 | `Router::dispatch` (22), `OrderLineValidator::validate` (19) | Extract Method: `handle()` + `authorize()`; `checkProduct()` + `parseLine()` — refactor-log R-04 |
| php:S1142 terlalu banyak `return` | 3 | `AuthService::attempt`, `InputValidator::wholeNumber`, `LocalImageStorage::validate` | `match (true)` / satu ekspresi; perilaku anti-timing login dipertahankan |
| php:S1192 literal berulang (kode PHP) | 4 | `'/login'`, `'/purchase-orders/'`, `' wajib diisi.'`, `'SELECT …'` | Konstanta `Router::LOGIN_PATH`, `REQUIRED_SUFFIX`, `SELECT_USERS`; helper `redirectToOrder()` |
| php:S2003 `require` autoload | 2 | `public/index.php`, `scripts/check-low-stock.php` | `require_once` |
| php:S1481 variabel tak terpakai | 1 | `$view` di `View::partial` | Diberikan lewat `extract(['view' => $this] + …)` |
| Web:InputWithoutLabelCheck | 1 | `partials/form-field.php` | Atribut `id` ditulis langsung di elemen input (sebelumnya di string `$attrs`, sehingga label `for` tidak terdeteksi) |
| Web:S5256 tabel tanpa `<th>` | 1 | tabel akun demo di halaman login | Header kolom untuk pembaca layar (`.sr-only`) + `<tbody>` |
| Web:S6819 `role="status"` | 1 | pesan flash di `layout.php` | Elemen `<output>` (role status implisit) |
| css:S4666 / S4658 | 2 | `.table-wrap` ganda, blok kosong | Digabung / dihapus |
| JS (S7772, S7781, S7754, S7778) | 11 | script generator seed & static server prototype | `node:` prefix, `replaceAll`, `some()`, satu `push()`; **output seed dibuat ulang dan identik** (tidak ada diff pada `database/` dan `prototype/data/`) |

### Dikecualikan dengan alasan

Pengecualian ditulis di `sonar-project.properties` (ikut versi kontrol), bukan ditandai manual
di UI, sehingga siapa pun yang mengulang scan mendapat hasil yang sama.

| Aturan | Lingkup | Alasan |
|---|---|---|
| php:S1172 parameter tak terpakai (34) | `app/Controller/**` | Semua handler mengikuti kontrak seragam `fn(Request, ?User, array $params)` yang dipanggil `Router`. Parameter yang tidak dipakai satu handler tidak bisa dihapus tanpa memecah kontrak. |
| php:S1192 literal berulang (16) | `views/**` | Nama partial (`partials/form-field`), URL menu, dan atribut ` selected` lebih jelas ditulis langsung di HTML; konstanta di template menurunkan keterbacaan. |
| php:S2003 `require` | `app/Core/View.php` | **Wajib** `require`: partial yang sama dirender berkali-kali per request; `require_once` membuat render kedua kosong (bug nyata). |
| php:S2003 `require` | `public/index.php`, `scripts/check-low-stock.php` | Tinggal `$config = require config.php`: file konfigurasi mengembalikan array dan tidak mendefinisikan apa pun. |
| php:S2092 cookie tanpa `secure` | `app/Core/Session.php` | Flag `secure` diisi dinamis (`true` bila request HTTPS). Aplikasi demo berjalan di `http://localhost`; memaksa `true` membuat session tidak pernah terkirim dan login tidak bisa dipakai. Di balik HTTPS flag otomatis aktif. |

## Temuan tambahan selama perbaikan

- **Router tidak punya unit test.** Saat memverifikasi refactor `Router`, mutasi yang mematikan
  seluruh guard (tamu, role) **tidak membuat satu test pun gagal** (169 tetap hijau); guard
  selama ini hanya terverifikasi lewat smoke test HTTP. Ditambahkan `tests/Unit/RouterTest.php`
  (10 test). Mutasi ulang: guard tamu dimatikan → 3 test gagal; cek role dimatikan → 1 gagal;
  cek CSRF dimatikan → 1 gagal. Semua mutasi dikembalikan.
- **`LocalImageStorage` tidak bisa diuji di CLI** karena `is_uploaded_file()` /
  `move_uploaded_file()` selalu gagal tanpa upload HTTP. Keduanya dijadikan dependency opsional
  di constructor (default tetap fungsi asli PHP, wiring produksi tidak berubah). Test baru
  membuktikan file PHP yang diberi nama `.jpg` ditolak berdasarkan isi; mutasi yang mematikan
  pemeriksaan isi → test tersebut gagal.
- Test baru lain: `OrderLineValidatorTest` (batas 50 baris, error per baris),
  `RepositoryGuardTest` (lock di luar transaksi ditolak di 3 repository, baris stok tidak ada,
  guard SQL penerimaan melebihi pesanan, urutan daftar user).
- Laporan coverage per file memperlihatkan `PartyService` (validasi supplier/customer) dan
  `MySqlCategoryRepository` **0%**. Keduanya kini diuji (`MasterDataServiceTest`,
  `MasterDataRepositoryTest`).

Total test: 169 → **194** (504 assertion), semua lulus lokal dan di container. PHPStan level 6
tetap 0 error.

## Versi alat

| Alat | Versi |
|---|---|
| SonarQube Community Build (image `sonarqube:community`) | 26.9.0.129388 |
| SonarScanner CLI (image `sonarsource/sonar-scanner-cli`) | 8.1.0.6389 |
| Analyzer PHP / JS-CSS / HTML | 3.60 / 13.8 / 3.31 |
| PCOV (driver coverage di image app) | 1.0.12 |

Server memakai database embedded bawaan (hanya untuk evaluasi, sesuai peringatan SonarQube);
data analisis tersimpan di volume Docker `sonar-data` dan tidak ikut repository.
