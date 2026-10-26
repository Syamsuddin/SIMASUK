# 22 — Change Policy
Pemilik kebijakan perubahan. Prinsip: friksi sebanding irreversibilitas.

## Git
- Branch per fitur/perbaikan: `fitur/<id>-<ringkas>`, `perbaikan/<ringkas>`. Dilarang commit langsung ke `main`.
- Merge ke `main` hanya oleh Pak Syams (pemilik produk) setelah DoD (docs/24) terpenuhi.
- Commit kecil, pesan Bahasa Indonesia berbentuk kalimat perintah ("Tambah validasi tenggat disposisi").
- `public/assets/app.css` di-commit bersama perubahan view yang mempengaruhinya.

## Operasi irreversibel → gerbang manusia
Agen WAJIB berhenti, menjelaskan dampak, dan menunggu "ya" eksplisit sebelum:

| Operasi | Syarat tambahan |
|---|---|
| Migrasi yang menghapus/mengubah kolom atau tabel berisi data | Backup DB terverifikasi; rencana pemulihan tertulis |
| Mengubah/mengatur ulang `agenda_counter` atau `no_agenda`/`tahun` surat | Skrip koreksi tertulis + daftar baris terdampak ditunjukkan dulu |
| Pembatalan surat massal atau skrip ubah status massal | Daftar baris terdampak ditunjukkan dulu |
| Menghapus file di `STORAGE_PATH` | Dilarang di MVP kecuali perintah eksplisit |
| Menambah dependensi/framework/pustaka | Alasan + alternatif native |
| Mengubah aturan keamanan (docs/21) atau middleware | Penjelasan risiko |
| Mengubah token desain (docs/26) | Preview ulang |
| Rilis ke produksi | docs/25 berurutan |

## Operasi bebas friksi
Menambah tes, memperbaiki teks antarmuka sesuai docs/26, menambah migrasi aditif (tabel/kolom baru nullable) di lokal.

## Rollback
- Kode: deploy ulang tag rilis sebelumnya.
- DB: migrasi hanya maju; rollback = restore backup yang dibuat tepat sebelum migrasi (docs/25).
- Berkas: tidak pernah dihapus, sehingga tidak perlu dipulihkan.

## Pembaruan blueprint
Fakta berubah → ubah dokumen pemiliknya + `docs/_MANIFEST.json`, perbarui dokumen di `referenced_by`, lalu `bash scripts/validate.sh`.
