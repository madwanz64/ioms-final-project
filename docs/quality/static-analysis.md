# Laporan Static Analysis (TEST-03)

Tool: **PHPStan 2.x** · konfigurasi [`phpstan.neon`](../../phpstan.neon) · perintah
`composer analyse` (di Docker: `docker compose exec app composer analyse`).

Cakupan: `app/`, `config/`, `public/index.php`, `scripts/check-low-stock.php`, `tests/`
(132 file PHP). Template `views/` tidak dianalisis karena berisi PHP+HTML campuran dengan
variabel dari `View::render()`; sebagai gantinya, view diperiksa lewat `php -l` dan smoke test
HTTP setiap slice.

## Hasil terakhir — 2026-10-07 (di container Docker, PHP 8.3.35)

| Level | Hasil | Status |
|---|---|---|
| **6 (konfigurasi resmi; brief minta ≥ 5)** | **0 error** | ✅ nol critical error |
| 7 (pemeriksaan tambahan) | 25 error, semuanya `argument.type` — satu pola, dijelaskan di bawah | diterima, dicatat |
| 8 (pemeriksaan tambahan) | 25 error, pola yang sama dengan level 7 | diterima, dicatat |
| 9 (pemeriksaan tambahan) | 87 error: cast dari `mixed` (`cast.string` 36, `cast.int` 20, `cast.double` 1) dan `array<mixed>` ke hydrator (`argument.type` 30) | diterima, dicatat |

### Temuan level 7–8: kontrak callable Router (25 kemunculan, satu pola)

```
public/index.php:167 — Parameter #2 $handler of method App\Core\Router::get() expects
callable(Request, User|null, array<string,string>): Response, array{DashboardController, 'index'} given.
```

Handler route **terlindungi** (mis. `DashboardController::index(Request, User)`) menerima
`User` non-null, sedangkan kontrak callable `Router::get()/post()` bertipe `User|null`,
karena router yang sama juga melayani route publik (`/login`).

**Bukan bug runtime.** `Router::dispatch()` hanya memanggil handler route non-public setelah
memastikan user sudah login. Kalau belum login, jawabannya redirect ke `/login`, atau 401 JSON
untuk `/api` (diuji di smoke test setiap slice). PHPDoc tidak bisa menyatakan "tipe parameter
bergantung pada flag `$public`".

Perbaikan ideal: pisahkan registrasi route publik dan terlindungi dengan tipe callable
berbeda. Ditunda karena menyentuh semua route tanpa mengubah perilaku
([tech-debt #3](tech-debt.md)).

### Temuan level 9: nilai `mixed` dari PDO

`PDOStatement::fetch()` mengembalikan `mixed`, sehingga setiap pembacaan kolom bertipe
`mixed`. Semua hydrator repository sudah melakukan cast eksplisit (`(int)`, `(string)`,
`RowMapper::money()`) tepat di batas repository, sehingga Service dan Entity hanya menerima
tipe yang jelas. Level 9 menolak cast dari `mixed` itu sendiri. Untuk menghilangkannya perlu
validasi tipe per kolom (mis. `is_numeric` sebelum cast) di setiap hydrator; manfaatnya kecil
karena skema database sudah menjamin tipe kolom.

## Riwayat

| Tanggal | Level 6 | Catatan |
|---|---|---|
| 2026-10-02 | 0 error | Slice 1 (auth + produk). Level 8: 1 jenis temuan (kontrak Router). |
| 2026-10-07 | 0 error | Seluruh fitur §2. Level 8 menemukan 1 temuan baru di `UserService::create()` (`Role\|null` ke konstruktor `User`). Aman di runtime karena `throwIfInvalid()`, tetapi invariannya kini dinyatakan eksplisit di kode, jadi temuan tertutup. Tersisa hanya pola Router. |
| 2026-10-07 (sore) | 0 error | Setelah perubahan K-01/K-03/K-07 dan fitur transfer (K-08): tetap 0 error, 138+ file. Output mentah: [phpstan-output.txt](../testing/phpstan-output.txt). |

Selama pengembangan, PHPStan juga menangkap masalah nyata sebelum masuk commit, antara lain:
- anotasi `array<string,string>` untuk input `receive[12]`, padahal PHP mengubah key numerik
  menjadi int (diperbaiki menjadi `array<array-key,string>` dan lookup berbasis id int);
- pemanggilan `array_values()` yang tidak berefek dan `??` pada offset yang selalu ada;
- import `use` yang tertinggal di test.
