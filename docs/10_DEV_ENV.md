# 10 — Dev Environment
Pemilik setup lingkungan. Versi: docs/09_STACK.md. Perintah lengkap: docs/11_COMMANDS.md.

## Prasyarat lokal
PHP 8.3 CLI (pdo_mysql, fileinfo, mbstring, sodium) · Composer 2 · MySQL 8.0 · binary Tailwind v4 Standalone untuk OS pengembang, diletakkan di `bin/tailwindcss` (executable, masuk `.gitignore`).

## Langkah setup lokal
1. `composer install`
2. Salin `.env.example` → `.env`, isi nilai.
3. Buat dua basis data: `simasuk` dan `simasuk_test`.
4. Jalankan migrasi dan seeder awal (perintah: docs/11). Seeder membuat satu admin dari `SEED_ADMIN_USERNAME`/`SEED_ADMIN_PASSWORD` dan contoh unit kerja.
5. Build CSS sekali, lalu jalankan server dev.
6. Pastikan folder `STORAGE_PATH` ada dan bisa ditulis PHP.

## Variabel `.env` (nama saja)
| Variabel | Kegunaan |
|---|---|
| APP_ENV | `local` / `production` (mempengaruhi tampilan error, docs/14) |
| APP_URL | URL dasar |
| APP_KEY | 32 byte acak base64, untuk token CSRF/HMAC |
| DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS | koneksi utama |
| DB_TEST_NAME | basis data uji |
| STORAGE_PATH | path absolut folder berkas, di luar `public/` |
| LOG_PATH | path absolut file log |
| SESSION_SECURE | `1` di produksi (HTTPS) |
| UPLOAD_MAX_MB | default 10 |
| SEED_ADMIN_USERNAME, SEED_ADMIN_PASSWORD | hanya dipakai seeder |

## Server produksi
Ubuntu 24.04, Nginx (root ke `public/`, `client_max_body_size 55m`), PHP 8.3-FPM (`upload_max_filesize=10M`, `post_max_size=55M`, `max_file_uploads=5`, `date.timezone=Asia/Makassar`), MySQL 8.0, HTTPS wajib. `STORAGE_PATH` disarankan `/var/lib/simasuk/storage`, dimiliki user `www-data`.
