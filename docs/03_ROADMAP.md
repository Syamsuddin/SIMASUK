# 03 — Roadmap
Fase = vertical slice (DB → Service → halaman) yang bisa didemokan. Fitur: docs/01_PRD.md.

| Fase | Tujuan yang bisa didemokan | Fitur tercakup | Status |
|---|---|---|---|
| F1 Fondasi | Admin login, membuat akun Operator, mengisi master klasifikasi dan unit; log audit mulai tercatat | Kerangka native (router, bootstrap, migrasi, layout Tailwind), F-AUTH, F-MASTER, F-AUDIT (pencatatan) | Selesai 2026-10-26 |
| F2 Agenda | Operator mencatat surat + unggah PDF, nomor agenda terbit, surat bisa dicari dan dibuka | F-AGENDA | Gerbang mesin lulus 2026-10-26; 768px lulus; menunggu uji operator (catat 3 PDF < 3 menit) |
| F3 Disposisi | Operator menginput disposisi berjenjang, melihat pohon riwayat, menandai selesai | F-DISPO | Selesai 2026-10-26 |
| F4 Dasbor | Dasbor ringkas tampil; Admin melihat log audit; pencarian lanjut rentang tanggal & sifat | F-DASBOR, F-AUDIT (halaman), penyempurnaan pencarian | Selesai 2026-10-26 (dimulai sebelum uji manual F2/F3 tuntas, atas permintaan) |
| F5 Lembar disposisi | Operator mencetak lembar disposisi A4 dari detail surat; pimpinan mencentang unit dan instruksi di kertas | F-LEMBAR | Selesai 2026-10-26 |
| Pasca-MVP | Diputuskan per fitur | Notifikasi, rekap xlsx, peran Pimpinan | Belum |

Aturan: fase berikutnya dimulai hanya setelah fase sebelumnya lulus docs/23 + docs/24. Skema tabel `disposisi` sudah dibuat di F1 agar F3 tidak memerlukan migrasi besar.
