# Backlog

> Urutan mengikuti brief §2 ("vertical slice dahulu: login → master data → PO → SO →
> dashboard/laporan; API, upload, dan job setelah alur inti stabil"). Commit merujuk riwayat
> git yang sebenarnya. Disusun 2026-10-07 dari riwayat kerja.

## Fase sebelum PHP

| # | Item | Status | Commit |
|---|---|---|---|
| B-01 | Wireframe semua halaman | ✅ | `docs/planning/wireframes/` |
| B-02 | Prototype HTML/CSS/JS (localStorage) untuk validasi alur & UI | ✅ | `c780f8a`…`71c531c` (2026-09-11) |
| B-03 | ERD + schema & seed MySQL 8 (DB-01) | ✅ | `45c30fc` |
| B-04 | Seed memenuhi minimum §7.1 & FIND-01 | ✅ | `fd28e85` |
| B-05 | Ledger seed konsisten dengan stok (§1.3) | ✅ | `8737897` |
| B-06 | Class diagram initial sebelum coding (DESIGN-01) | ✅ | `e82e59a` |

## Fase PHP (vertical slice)

| # | Item | Requirement | Status | Commit |
|---|---|---|---|---|
| B-10 | Fondasi: router, request/response, session, CSRF, PDO, view | ARCH-01 | ✅ | `99f7158` |
| B-11 | Login/logout, guard role, produk + upload gambar | AUTH-01/02, PRD-01, WH-01, FIND-01, ERR-01 | ✅ | `48f148d` |
| B-12 | Refactor R-01 `InputValidator` | DESIGN-03 | ✅ | `6e129d6` |
| B-13 | Master data & manajemen user | USR-01, WH-01 | ✅ | `11a6de2` |
| B-14 | ADR-001 anti-oversell + PO & goods receipt | PO-01, ARCH-02 | ✅ | `b1ec2ad` |
| B-15 | Refactor R-02 `OrderLineValidator` | DESIGN-03 | ✅ | `afe9aed` |
| B-16 | SO, approval, goods issue + ADR-002 | SO-01 | ✅ | `f8362d8`, `0b35e27` |
| B-17 | Dashboard 3 role + CSV | DASH-01, REPORT-01 | ✅ | `413d611` |
| B-18 | API JSON + Fetch, script low-stock | API-01, JOB-01 | ✅ | `c7b966b`, `8114ccf` |
| B-19 | Zona waktu bisnis (bug) | VAL-01 | ✅ | `3dd1a3d` |
| B-20 | Docker Compose + uji dari clone bersih | §5.1, §5.2 | ✅ | `630fd50`, `1f6e59f` |
| B-21 | Profil sendiri | §1.2 | ✅ | `eed3abe` |
| B-22 | Refactor R-03 `RowMapper` | DESIGN-03 | ✅ | `842dc18` |
| B-23 | Static analysis report, class diagram as-built, SRP audit, dokumen planning | TEST-03, DESIGN-01/03 | ✅ | `cc74e9f` |
| B-24 | Warehouse boleh menandai PO Ordered | K-01 | ✅ | `66940c9` |
| B-25 | Pembuat PO tercatat (`created_by`) | K-03 | ✅ | `7efc322` |
| B-26 | Harga jual SO dari form | K-07 | ✅ | `00b843f` |
| B-27 | Transfer stok antar-gudang (+ SAVEPOINT untuk transaksi bersarang) | K-08 | ✅ | `810a8b5`, `2a42d5e` |

## Sisa sebelum submission

| # | Item | Requirement | Status | Catatan |
|---|---|---|---|---|
| B-30 | Screenshot desktop & 360px (login, dashboard, daftar, detail, form; berisi data & kosong) | UI-01, VIEW-01 | ✅ | 47 screenshot otomatis (Chrome headless), `docs/testing/screenshots/` |
| B-31 | Cek Fetch API di browser (petunjuk stok form SO/transfer, tombol muat ulang stok) | API-01 | ✅ | Diuji di Chrome sungguhan, lihat hasil-test slice 9–10 |
| B-32 | `docs/quality/critique.md` | DESIGN-04 | ⏳ | Menunggu cuplikan kode dari assessor |
| B-33 | Konfirmasi interpretasi K-01…K-07 ke trainer | §9 no. 12 | ✅ | Dijawab 2026-10-07; K-01, K-03, K-07 mengubah implementasi |
| B-34 | Uji akhir dari folder bersih, lalu tag release final | §5.1, §7 | ⏳ | |

## Kandidat bonus (§4.4) — hanya setelah semua wajib stabil

- Audit trail perubahan master data.
- Grafik dashboard (SVG buatan sendiri).
- Email simulasi (Mailhog) saat SO menunggu persetujuan.
