# Database

`schema-and-seed.sql` di folder ini **digenerate otomatis**, jangan diedit manual.
Untuk mengubah struktur tabel atau data seed, edit `scripts/generate-sql-seed.js`
lalu jalankan ulang:

```
node scripts/generate-sql-seed.js
```

Seed data-nya sengaja dikonversi dari file yang sama dengan prototype JS
(`public/data/*.json`) supaya keduanya tetap konsisten — kecuali kolom
`password` pada `users`, yang di sini sudah berupa hash bcrypt asli (bukan
plaintext seperti di prototype JS), siap dipakai `password_verify()` PHP.

## Cara import (setelah MySQL 8 terpasang)

```
mysql -u root -p < database/schema-and-seed.sql
```

Skrip ini aman dijalankan berulang kali — otomatis membuat database `ioms`
kalau belum ada, dan **menimpa ulang** semua tabel (`DROP TABLE IF EXISTS`)
setiap kali dijalankan, jadi selalu mulai dari kondisi bersih.

## Akun demo (setelah seed masuk)

| Role | Email | Password |
|---|---|---|
| Admin | admin@ioms.test | admin123 |
| Sales | sinta@ioms.test / doni@ioms.test | sales123 |
| Warehouse Staff | rudi@ioms.test | gudang123 |
| Warehouse Staff (nonaktif, untuk uji AUTH-01) | agus@ioms.test | gudang123 |

## Yang belum bisa diverifikasi

Belum ada MySQL/Docker terpasang di lingkungan pengembangan saat file ini
dibuat, jadi skema ini **belum pernah benar-benar dijalankan** terhadap
MySQL sungguhan — hanya ditinjau manual (sintaks, urutan dependency antar
tabel, penanganan NULL, kecocokan tipe data dengan `public/data/*.json`).
Wajib diverifikasi dengan `mysql -u root -p < database/schema-and-seed.sql`
begitu MySQL tersedia, sebelum dianggap final.
