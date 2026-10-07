# Tech-Debt Register (DESIGN-03)

Keterbatasan dan jalan pintas yang diambil dengan sadar, beserta perbaikan idealnya.

| # | Tanggal | Area | Keterbatasan / jalan pintas | Dampak | Perbaikan ideal |
|---|---|---|---|---|---|
| 1 | 2026-10-02 | Upload gambar | Saat gambar produk diganti, file lama tidak dihapus dari `public/uploads/products`. Bila penyimpanan ke database gagal setelah file dipindahkan, file juga tertinggal (orphan). | Pemakaian disk bertambah; tidak ada dampak keamanan karena nama acak. | Tambah `ImageStorageInterface::delete()` dan panggil setelah update sukses / saat insert gagal; atau script pembersih orphan. |
| 2 | 2026-10-02 | Upload gambar | File melebihi `post_max_size` PHP membuat `$_POST` kosong sehingga request ditolak 403 (token CSRF tidak terbaca), bukan pesan "ukuran terlalu besar". | Pesan kurang jelas; data tetap aman (tidak tersimpan). Dimitigasi cek ukuran di JS sebelum submit. | Deteksi `CONTENT_LENGTH > post_max_size` di front controller dan tampilkan pesan ukuran. |
| 3 | 2026-10-02 | Router / tipe | Handler route terlindungi bertipe `User` non-null, sedangkan kontrak router `User\|null` (temuan PHPStan level 8). | Tidak ada di runtime; hanya presisi tipe statis. | Pisahkan registrasi route publik vs terlindungi dengan tipe callable berbeda. |
| 4 | 2026-10-02 | Dashboard | Dashboard baru menampilkan ringkasan stok; nilai inventori dan order per status (DASH-01) menyusul bersama modul PO/SO. Dashboard Sales masih empty state. | Requirement DASH-01 belum lengkap. | Tambah query agregasi order setelah modul PO/SO selesai. |
| 5 | 2026-10-02 | Lingkungan | Belum ada Docker Compose; app & integration test baru diuji di PHP + MySQL lokal. | Requirement wajib §5.1 belum terpenuhi. | Dockerfile (php-apache/php-fpm) + `compose.yaml` dengan service MySQL; jalankan test di container. |
| 6 | 2026-10-07 | User | Halaman "profil sendiri" (§1.2: semua role boleh) belum ada; nama/password user hanya bisa diubah Admin. | Sales/Warehouse belum bisa ganti password sendiri. | Halaman `/profile` untuk ubah nama & password dengan verifikasi password lama. |
| 7 | 2026-10-07 | User | Dua Admin yang menyimpan email sama hampir bersamaan: validasi service lolos untuk keduanya, lalu UNIQUE KEY menolak yang kedua sebagai PDOException → halaman 500 (data tetap aman). | Pesan error kurang ramah pada kasus sangat jarang. | Tangkap SQLSTATE 23000 di repository dan ubah menjadi ValidationException "Email sudah dipakai". |
| 8 | 2026-10-07 | Master data | Daftar kategori/gudang/supplier/customer/user tanpa pencarian & pagination (FIND-01 hanya mewajibkan produk & order). | Kurang nyaman bila data membesar. | Pakai ulang pola `PaginatedResult` + criteria seperti produk. |
