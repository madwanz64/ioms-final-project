# Laporan Static Analysis (TEST-03)

Tool: PHPStan 2.x · konfigurasi [`phpstan.neon`](../../phpstan.neon) · perintah `composer analyse`.
Cakupan: `app/`, `config/`, `public/index.php`, `tests/` (template `views/` tidak dianalisis).

## 2026-10-02 — slice 1 (Auth + Produk)

| Level | Hasil |
|---|---|
| 6 (konfigurasi resmi, brief minta ≥ 5) | **0 error** |
| 8 (dicoba sebagai pemeriksaan tambahan) | 1 jenis temuan, dijelaskan di bawah |

### Temuan level 8 yang tidak diperbaiki

`argument.type` di `public/index.php`: handler route terlindungi (mis.
`DashboardController::index(Request, User)`) menerima `User` non-null, sedangkan
kontrak callable `Router::get()` bertipe `User|null`.

Ini bukan bug runtime. Router hanya memanggil handler route non-public setelah
memastikan user sudah login (lihat `Router::dispatch`). PHPDoc tidak bisa
menyatakan "tipe parameter bergantung pada flag `$public`". Ada dua cara
memperbaikinya, tapi untuk saat ini dinilai tidak sebanding:

- melonggarkan controller ke `?User` lalu menambahkan pengecekan null di setiap method, atau
- membuat method registrasi terpisah untuk route publik dan terlindungi.

Dicatat juga di [tech-debt.md](tech-debt.md).
