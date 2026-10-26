# 25 — Release Checklist
Urutan wajib; berhenti di langkah pertama yang gagal. Perintah: docs/11_COMMANDS.md. Gerbang & rollback: docs/22_CHANGE_POLICY.md.

| # | Langkah | Lulus bila |
|---|---|---|
| 1 | Tes penuh di branch rilis | `vendor/bin/phpunit` → `OK`, 0 failure, 0 error |
| 2 | Lint + build CSS minify | lint tanpa `Errors parsing`; `public/assets/app.css` ter-commit dan tidak berubah setelah build ulang |
| 3 | Validasi blueprint | `bash scripts/validate.sh` exit 0 |
| 4 | Konfirmasi manusia ⚠️ | Pak Syams menyetujui tag rilis `vX.Y.Z` |
| 5 | Backup | `mysqldump --single-transaction simasuk > backup-<tgl>.sql` berhasil dan ukurannya > 0; salinan `STORAGE_PATH` (rsync) selesai |
| 6 | Mode pemeliharaan | file `MAINTENANCE` di root aktif; halaman menampilkan pemberitahuan |
| 7 | Deploy kode | `git fetch && git checkout vX.Y.Z && composer install --no-dev --optimize-autoloader` sukses |
| 8 | Migrasi ⚠️ | `php scripts/migrate.php --status` menunjukkan migrasi tertunda sesuai catatan rilis; `php scripts/migrate.php` exit 0 |
| 9 | Reload | `sudo systemctl reload php8.3-fpm` dan `sudo nginx -t && sudo systemctl reload nginx` sukses |
| 10 | Matikan pemeliharaan | file `MAINTENANCE` dihapus |
| 11 | Smoke test | `php scripts/smoke.php https://<domain>` → semua `[OK]`: halaman login 200, login admin uji, daftar surat 200, unduh berkas contoh 200 `application/pdf`, header keamanan hadir |
| 12 | Pantau | 30 menit tanpa log `error`/`critical` baru |

## Rollback (bila langkah 8–12 gagal)
1. Aktifkan `MAINTENANCE`.
2. `git checkout` tag sebelumnya + `composer install --no-dev`.
3. Bila migrasi sudah berjalan: restore `backup-<tgl>.sql` (gerbang manusia).
4. Reload PHP-FPM & Nginx, smoke test, matikan `MAINTENANCE`, catat insiden.
