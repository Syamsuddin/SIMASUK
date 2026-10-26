# 01 — PRD
Pemilik daftar fitur dan user story. Kriteria terima per fitur: docs/23_ACCEPTANCE_CRITERIA.md.

## Daftar fitur
| ID | Fitur | Deskripsi | Prioritas |
|---|---|---|---|
| F-AUTH | Login + kelola pengguna | Login username/password; Admin membuat, menonaktifkan, dan mereset password akun | MVP |
| F-MASTER | Master klasifikasi & unit | Admin mengelola kode klasifikasi arsip dan unit tujuan disposisi (berhierarki) | MVP |
| F-AGENDA | Agenda surat masuk | Catat surat, nomor agenda otomatis per tahun, unggah scan PDF, daftar/cari/filter, detail | MVP |
| F-DISPO | Disposisi berjenjang | Operator mencatat disposisi dan sub-disposisi dari lembar kertas pimpinan; riwayat pohon; tandai selesai | MVP |
| F-DASBOR | Dasbor ringkas | Jumlah surat bulan ini, surat belum didisposisi, disposisi lewat tenggat | MVP |
| F-AUDIT | Log audit | Setiap ubah data tercatat; Admin bisa melihatnya | MVP |
| F-LEMBAR | Cetak lembar disposisi | Halaman cetak A4 berisi data surat, nomor agenda, dan daftar unit aktif untuk dicentang pimpinan; PDF lewat dialog cetak peramban (tanpa pustaka) | F5 |
| — | Notifikasi WhatsApp/Telegram | Pemberitahuan disposisi baru | Nanti |
| — | Rekap bulanan xlsx | Ekspor rekap surat per bulan | Nanti |
| — | Peran Pimpinan login | Pimpinan mendisposisi langsung di aplikasi | Nanti |

## User story (MVP)
- **F-AUTH**: Sebagai Admin, saya ingin membuat dan menonaktifkan akun Operator agar hanya petugas TU aktif yang bisa mencatat surat.
- **F-MASTER**: Sebagai Admin, saya ingin mengelola kode klasifikasi dan unit tujuan agar pilihan di formulir selalu sesuai struktur organisasi terkini.
- **F-AGENDA**: Sebagai Operator, saya ingin mencatat surat dan mengunggah scan-nya agar nomor agenda terbit otomatis dan surat bisa dicari kembali.
- **F-AGENDA**: Sebagai Operator, saya ingin mencari surat berdasarkan nomor agenda, nomor surat, asal, perihal, dan rentang tanggal agar permintaan pimpinan cepat dijawab.
- **F-DISPO**: Sebagai Operator, saya ingin menginput disposisi beserta tenggatnya, termasuk disposisi lanjutan dari Kabid ke Kasubbag, agar jejak tindak lanjut lengkap.
- **F-DISPO**: Sebagai Operator, saya ingin menandai surat selesai agar daftar kerja hanya berisi surat yang masih berjalan.
- **F-DASBOR**: Sebagai Admin atau Operator, saya ingin melihat surat yang belum didisposisi dan disposisi lewat tenggat agar tidak ada yang tertinggal.
- **F-AUDIT**: Sebagai Admin, saya ingin melihat siapa mengubah apa dan kapan agar setiap koreksi data bisa dipertanggungjawabkan.

## User story (pasca-MVP)
- **F-LEMBAR**: Sebagai Operator, saya ingin mencetak lembar disposisi dari detail surat agar pimpinan tinggal mencentang unit dan instruksi, lalu saya menyalinnya ke Catat Disposisi tanpa menulis ulang data surat.

## Kebutuhan non-fungsional
- Waktu muat halaman daftar ≤ 1 detik untuk 10.000 surat (indeks: docs/07_DATA_MODEL.md).
- Berkas PDF maks 10 MB per berkas; maks 5 berkas per surat `[ASUMSI]`.
- Bahasa antarmuka Bahasa Indonesia, zona waktu WITA (+08:00).
- Berjalan di peramban desktop modern; tetap layak dipakai di lebar 768 px (docs/26_UI_CONVENTIONS.md).
