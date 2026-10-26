# 11 — Commands
Pemilik semua perintah. Dokumen lain merujuk ke sini. Jalankan dari root proyek.

| Tujuan | Perintah |
|---|---|
| Pasang dependensi | `composer install` |
| Server dev | `php -S localhost:8000 -t public` |
| Build CSS (sekali, minify) | `./bin/tailwindcss -i resources/css/app.css -o public/assets/app.css --minify` |
| Build CSS (pantau saat dev) | `./bin/tailwindcss -i resources/css/app.css -o public/assets/app.css --watch` |
| Migrasi ⚠️ | `php scripts/migrate.php` |
| Status migrasi | `php scripts/migrate.php --status` |
| Migrasi DB uji | `php scripts/migrate.php --env=testing` |
| Seeder awal | `php scripts/seed.php` |
| Semua tes | `vendor/bin/phpunit` |
| Tes unit saja | `vendor/bin/phpunit --testsuite=unit` |
| Tes integrasi saja | `vendor/bin/phpunit --testsuite=integration` |
| Satu tes | `vendor/bin/phpunit --filter=AgendaNumberServiceTest` |
| Lint sintaks | `find app modules scripts public -name '*.php' -print0 \| xargs -0 -n1 php -l` |
| Smoke test (setelah deploy) | `php scripts/smoke.php https://<domain>` |
| Validasi blueprint | `bash scripts/validate.sh` |
| Deploy ⚠️ | ikuti urutan docs/25_RELEASE_CHECKLIST.md |

Catatan:
- `migrate.php` hanya maju (tanpa down). Migrasi yang mengubah/menghapus kolom berisi data = gerbang manusia (docs/22).
- `seed.php` menolak berjalan bila `APP_ENV=production` dan tabel `users` sudah berisi.
- Tes integrasi memakai `DB_TEST_NAME` dan mengosongkan tabelnya di `setUp()`; jangan arahkan ke DB utama.
