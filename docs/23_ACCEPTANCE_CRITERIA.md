# 23 — Acceptance Criteria
Hanya fitur AKTIF. Fitur yang diterima dipindah ke `docs/_archive/23-<fitur>.md`. Fitur: docs/01. Selesai = kriteria di sini + docs/24. Perintah: docs/11.

Kriteria UI umum untuk setiap halaman fitur (pola: docs/26): empat state tampil · pesan validasi muncul di bawah field · layak di 768px tanpa gulir horizontal halaman · aksi destruktif lewat dialog konfirmasi · hanya token/komponen docs/26.

## F-AUTH — Login + kelola pengguna (F1)
- Given admin aktif, When login dengan password benar, Then masuk ke Dasbor dan `last_login_at` terisi.
- Given password salah 5×, When percobaan ke-6 dengan password benar dalam 15 menit, Then ditolak "Username atau password salah." dan `log_audit` mencatat `auth.locked`.
- Given pengguna nonaktif, When login, Then ditolak dengan pesan yang sama.
- Given Operator login, When membuka `/pengguna`, Then 403.
- Given Admin, When menonaktifkan admin aktif terakhir atau dirinya sendiri, Then ditolak dengan pesan aturan.
- Given POST apa pun tanpa token CSRF, Then 419.

```bash
vendor/bin/phpunit --filter='AuthServiceTest|RoleMiddlewareTest|CsrfTest|PenggunaServiceTest'
```
Lulus: `OK`. Gagal: ada failure/error.

## F-MASTER — Klasifikasi & unit (F1)
- Given kode klasifikasi sudah ada, When Admin menyimpan kode sama, Then ditolak "Kode klasifikasi sudah dipakai."
- Given unit dinonaktifkan, Then unit tidak muncul di pilihan tujuan disposisi, tetapi disposisi lama tetap menampilkan namanya.
- Given unit punya anak, When Admin memilih unit itu sendiri/anaknya sebagai induk, Then ditolak (hierarki tidak boleh melingkar).

```bash
vendor/bin/phpunit --filter='KlasifikasiServiceTest|UnitKerjaServiceTest'
```
Lulus: `OK`.

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

## F-DASBOR & F-AUDIT halaman (F4)
- Given 3 disposisi dikirim dengan tenggat kemarin, Then kartu "Lewat tenggat" = 3 dan tabelnya memuat ketiganya.
- Given surat Diterima tanpa disposisi, Then muncul di "Belum didisposisi".
- Given Admin membuka `/audit` dan memfilter aksi `surat.cancel`, Then hanya baris pembatalan tampil; `data_json` tidak memuat `password_hash`.

```bash
vendor/bin/phpunit --filter='DasborServiceTest|AuditRepositoryTest'
```
Lulus: `OK`.
