# 04 — Domain Model
Pemilik istilah dan entitas konseptual. Skema fisik: docs/07_DATA_MODEL.md.

## Glosarium
| Istilah | Makna di SIMASUK |
|---|---|
| Surat masuk | Naskah dinas yang diterima BKPSDM dari pihak luar atau internal pemda, dicatat satu kali |
| Nomor agenda | Nomor urut penerimaan surat dalam satu tahun, dimulai dari 1 setiap 1 Januari; ditampilkan `NNNN/TAHUN` (mis. `0127/2026`) |
| Tahun agenda | Tahun dari tanggal terima surat (bukan tanggal surat, bukan jam server) |
| Tanggal terima | Tanggal surat diterima TU BKPSDM |
| Asal surat | Nama instansi/pengirim, teks bebas |
| Sifat surat | Satu dari: Biasa, Segera, Sangat Segera, Rahasia |
| Kode klasifikasi | Kode arsip dari master klasifikasi (mis. `800.1.2`), dipilih saat pencatatan |
| Nomor SRIKANDI | Nomor rujukan bila surat juga tercatat di SRIKANDI; tidak divalidasi ke SRIKANDI |
| Berkas | File scan PDF milik satu surat; satu surat boleh punya beberapa berkas |
| Disposisi | Perintah pimpinan kepada satu unit tujuan atas satu surat: instruksi + tenggat |
| Disposisi induk / lanjutan | Disposisi dari Kepala adalah induk; disposisi dari Kabid ke Kasubbag/staf adalah lanjutan yang menunjuk induknya |
| Unit tujuan | Unit kerja/jabatan penerima disposisi (Sekretariat, Bidang, Subbag), berhierarki |
| Status surat | Diterima → Didisposisi → Selesai; atau Dibatalkan (hanya Admin) |
| Lewat tenggat | Disposisi berstatus Dikirim yang tenggatnya sudah lewat dari hari ini |
| Pembatalan | Penandaan surat tidak berlaku (salah catat/duplikat) oleh Admin; data tidak dihapus |

## Entitas inti & relasi konseptual
- **Surat masuk** punya satu kode klasifikasi, banyak berkas, dan banyak disposisi.
- **Disposisi** milik satu surat, ditujukan ke satu unit, dan boleh punya satu disposisi induk (membentuk pohon per surat).
- **Unit kerja** berhierarki (Kepala Badan → Sekretariat/Bidang → Subbag/Subbidang).
- **Penghitung agenda** menyimpan nomor terakhir per tahun; satu-satunya sumber nomor agenda.
- **Pengguna** melakukan aksi; setiap aksi ubah data menghasilkan **log audit**.
