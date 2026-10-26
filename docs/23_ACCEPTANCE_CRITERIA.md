# 23 — Acceptance Criteria
Hanya fitur AKTIF. Fitur yang diterima dipindah ke `docs/_archive/23-<fitur>.md`. Fitur: docs/01. Selesai = kriteria di sini + docs/24. Perintah: docs/11.

Kriteria UI umum untuk setiap halaman fitur (pola: docs/26): empat state tampil · pesan validasi muncul di bawah field · layak di 768px tanpa gulir horizontal halaman · aksi destruktif lewat dialog konfirmasi · hanya token/komponen docs/26.

## F-AGENDA — Agenda surat masuk (F2)
- Given belum ada surat tahun 2026, When surat pertama dengan `tgl_terima` 2026 disimpan, Then `no_agenda = 1`, tampil `0001/2026`.
- Given surat terakhir 2025 bernomor 412, When surat dengan `tgl_terima` 2026-01-02 disimpan, Then nomornya 1 (reset).
- Given dua koneksi menyimpan surat bersamaan, Then nomor berbeda dan berurutan, tidak ada error UNIQUE.
- Given berkas `.pdf` berisi PNG, When diunggah, Then ditolak "Berkas harus PDF." dan tidak ada nomor terpakai.
- Given berkas 10,5 MB, Then ditolak "Ukuran berkas maks 10 MB."
- Given `tgl_surat` setelah `tgl_terima`, Then ditolak di field `tgl_surat`.
- Given surat lain dengan no_surat + asal + tgl_surat sama, Then tampil peringatan dengan tautan; simpan hanya bila "Tetap simpan" dicentang.
- Given berkas tersimpan, Then file ada di `STORAGE_PATH/surat/<tahun>/` dengan nama 32 heksa dan tidak punya URL statis; unduh lewat `/berkas/{id}` mengembalikan `application/pdf`.
- Given filter "Rahasia" + kata "HCDP", Then daftar hanya memuat surat yang cocok, paginasi 25.
- Given Admin membatalkan surat dengan alasan, Then status Dibatalkan, nomor tidak dipakai ulang, `log_audit` `surat.cancel` tercatat; Operator tidak melihat tombol dan POST langsung → 403.

```bash
vendor/bin/phpunit --filter='AgendaNumberServiceTest|SuratMasukServiceTest|FileStoreTest|SuratMasukRepositoryTest'
```
Lulus: `OK`, termasuk tes konkurensi dua koneksi. Manual: catat satu surat dengan PDF 3 berkas < 3 menit.

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
