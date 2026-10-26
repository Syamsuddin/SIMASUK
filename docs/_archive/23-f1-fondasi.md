# Arsip 23 — F1 Fondasi (F-AUTH, F-MASTER)
Diterima 2026-10-26: kriteria terpenuhi, phpunit hijau (74 tes). Penegakan pindah ke test suite.

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

