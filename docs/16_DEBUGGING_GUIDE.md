# 16 — Debugging Guide
Lokasi log: docs/15_OBSERVABILITY.md. Perintah: docs/11_COMMANDS.md. Kelas error: docs/14_ERROR_HANDLING.md.

## Gejala → diagnosa
| Gejala | Langkah |
|---|---|
| Halaman 500 dengan kode error | Cari `error_id` di log hari itu; baca `context` dan trace |
| Tampilan tanpa gaya / class baru tak berefek | Build ulang CSS; cek path view baru tercakup `@source` di `resources/css/app.css` |
| Unggah gagal tanpa pesan validasi aplikasi | Batas di PHP/Nginx terlampaui sebelum aplikasi; cek `post_max_size`, `upload_max_filesize`, `client_max_body_size` (docs/10) dan log Nginx 413 |
| 419 terus-menerus | Sesi tidak tersimpan: cek izin folder sesi PHP, `SESSION_SECURE=1` di HTTP, jam server |
| Nomor agenda tahun salah | Cek `date.timezone` PHP dan `time_zone` sesi MySQL; tahun diambil dari `tgl_terima` |
| Berkas tak bisa diunduh | Cek `STORAGE_PATH` dan pemilik file `www-data`; bandingkan `sha256` |
| Migrasi berhenti di tengah | Cek `schema_migrations`; MySQL melakukan commit implisit pada DDL sehingga sebagian tabel bisa sudah dibuat |

## Jebakan proyek
1. **Tailwind standalone hanya memindai path di `@source`.** Class di view baru di luar pola itu tidak masuk `app.css`; tampilannya rusak tanpa error. Pola `@source` wajib mencakup `modules/**/views/*.php` dan `resources/views/**/*.php`.
2. **Batas unggah ada di tiga tempat**: php.ini, Nginx, dan validasi aplikasi. Bila batas server lebih kecil dari batas aplikasi, `$_FILES` dan `$_POST` kosong dan token CSRF ikut hilang sehingga muncul 419 yang menyesatkan.
3. **Nomor agenda dari `MAX(no_agenda)+1` pasti bertabrakan** saat dua operator menyimpan bersamaan. Satu-satunya jalur: `AgendaNumberService` dengan `SELECT … FOR UPDATE` pada `agenda_counter` di dalam transaksi yang sama dengan `INSERT surat_masuk`.
4. **Zona waktu dua lapis**: PHP `Asia/Makassar` dan sesi MySQL `+08:00` harus selaras. Surat yang diterima 31 Desember pukul 23.30 WITA tercatat di tahun yang salah bila salah satunya UTC.
5. **DDL MySQL tidak transaksional.** Satu file migrasi sebaiknya berisi satu perubahan skema agar kegagalan mudah dipulihkan.
6. **`finfo` membaca isi, ekstensi tidak berarti apa-apa.** Jangan memvalidasi berkas dari `$_FILES['type']` (dikirim peramban, bisa dipalsukan).
