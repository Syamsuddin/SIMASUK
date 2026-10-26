# 06 — Business Process
Pemilik alur. Peran: docs/05_USER_ROLE.md. Kolom dan constraint: docs/07_DATA_MODEL.md. Aturan keamanan: docs/21_SECURITY_RULES.md.

## P1 — Pencatatan surat masuk (Operator)
1. Operator membuka "Catat Surat", mengisi tanggal terima, nomor surat, tanggal surat, asal, perihal, sifat, kode klasifikasi, nomor SRIKANDI (opsional), dan memilih 1–5 berkas PDF.
2. Sistem memvalidasi isian dan berkas (PDF via `finfo`, ≤ 10 MB).
3. Sistem mengecek dugaan surat ganda: nomor surat + asal + tanggal surat yang sama pada surat tidak dibatalkan.
4. Dalam satu transaksi: kunci baris `agenda_counter` tahun terima (`SELECT … FOR UPDATE`), naikkan nomor, simpan surat berstatus Diterima, simpan berkas, tulis log audit.
5. Tampil detail surat dengan nomor agenda `NNNN/TAHUN` dan toast sukses.

Cabang/gagal:
- Validasi gagal → formulir tampil lagi dengan pesan per field; isian dipertahankan; berkas wajib dipilih ulang.
- Dugaan ganda → peringatan berisi tautan ke surat yang mirip; Operator boleh lanjut dengan mencentang "Tetap simpan".
- Gagal simpan berkas → transaksi dibatalkan, nomor agenda tidak terpakai, file yang sudah tertulis dihapus.

## P2 — Disposisi berjenjang (Operator)
1. Operator mencetak lembar disposisi dari detail surat ("Cetak Lembar Disposisi") dan menyerahkannya bersama surat; pimpinan mencentang unit tujuan dan instruksi, menulis batas waktu dan catatan, lalu lembar kembali ke TU.
2. Di detail surat, Operator klik "Catat Disposisi": memilih unit tujuan (boleh lebih dari satu), instruksi, tenggat, catatan, dan tanggal disposisi.
3. Sistem menyimpan satu disposisi per unit tujuan berstatus Dikirim. Bila surat masih Diterima, statusnya menjadi Didisposisi.
4. Untuk disposisi lanjutan (Kabid → Kasubbag), Operator klik "Disposisi Lanjutan" pada disposisi induk; langkah 2–3 berulang dengan `parent_id` terisi.
5. Saat tindak lanjut dilaporkan, Operator menandai disposisi Selesai. Operator menandai surat Selesai bila pekerjaan surat dianggap tuntas; disposisi yang masih Dikirim ikut ditutup Selesai dengan catatan otomatis.

Cabang/gagal:
- Unit tujuan nonaktif tidak muncul di pilihan.
- Tenggat sebelum tanggal terima → ditolak.
- Surat Dibatalkan atau Selesai → tombol disposisi dan tombol cetak lembar tidak tampil; permintaan langsung ke rute ditolak 403/422.
- Disposisi lanjutan hanya boleh dari disposisi milik surat yang sama.

## P3 — Pembatalan surat (Admin)
Admin membuka detail → "Batalkan Surat" → dialog konfirmasi wajib isi alasan → status Dibatalkan, `cancelled_at`/`cancel_reason` terisi, log audit tercatat. Nomor agenda tidak dipakai ulang. Surat batal tetap tampil di daftar dengan filter "Dibatalkan".

## P4 — Pergantian tahun
Tidak ada proses manual. Surat pertama dengan tanggal terima di tahun baru membuat baris `agenda_counter` tahun itu dan mendapat nomor 1.
