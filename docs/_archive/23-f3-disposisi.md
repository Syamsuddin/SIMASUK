# Arsip 23 — F3 Disposisi (F-DISPO)
Diterima 2026-10-26: kriteria terpenuhi, phpunit hijau (159 tes), tampilan 768px tanpa gulir horizontal halaman dibuktikan lewat Chrome headless pada server nyata (formulir disposisi, galat validasi, disposisi lanjutan, detail dengan pohon berindentasi, dialog tandai selesai disposisi dan surat). Penegakan pindah ke test suite: `DisposisiServiceTest`, `DisposisiRepositoryTest`.

## F-DISPO — Disposisi berjenjang (F3)
- Given surat Diterima, When Operator mencatat disposisi ke 2 unit, Then 2 baris `disposisi` status dikirim dan surat menjadi Didisposisi.
- Given disposisi induk ke Bidang PSDM, When Disposisi Lanjutan ke Subbidang, Then `parent_id` terisi dan pohon menampilkan indentasi.
- Given `parent_id` milik surat lain (dimanipulasi di request), Then ditolak 422.
- Given tenggat sebelum `tgl_terima`, Then ditolak di field tenggat.
- Given surat Selesai atau Dibatalkan, Then tombol disposisi tak tampil dan POST langsung ditolak.
- Given surat ditandai Selesai, Then disposisi berstatus dikirim ikut selesai dengan `catatan_selesai` "Ditutup bersama surat".

```bash
vendor/bin/phpunit --filter='DisposisiServiceTest|DisposisiRepositoryTest'
```
Lulus: `OK`.
