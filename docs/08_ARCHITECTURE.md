# 08 — Architecture
Pemilik pola arsitektur. Teknologi & versi: docs/09_STACK.md. Lokasi file: docs/12_PROJECT_STRUCTURE.md.

## Lapisan
```
Nginx → public/index.php (front controller)
          └─ bootstrap/app.php : .env, timezone, error handler, sesi, PDO, container sederhana
               └─ Router (config/routes.php: [method, path, handler, middleware[]])
                    └─ Middleware: auth → role → csrf
                         └─ Controller (modules/<domain>/Controller)  — tipis
                              └─ Service (modules/<domain>/Service)   — aturan bisnis + transaksi
                                   └─ Repository (modules/<domain>/Repository) — SQL via PDO
                              └─ View (modules/<domain>/views + resources/views/layout)
```

## Tanggung jawab
| Lapisan | Boleh | Tidak boleh |
|---|---|---|
| Controller | Baca request, panggil Validator + Service, pilih view/redirect, set flash | SQL, aturan bisnis, `$_SESSION` selain lewat `Session` |
| Service | Aturan bisnis, transaksi, cek status, tulis log audit, simpan berkas | Akses `$_POST`/`$_GET`, echo HTML |
| Repository | Query PDO prepared, mapping baris ↔ array | Aturan bisnis, transaksi lintas aggregate |
| View | Tampilkan data dengan `e()`, pakai komponen `resources/views/components` | Query, logika bisnis |

## Keputusan pengganti framework (profil native)
| Keputusan | Pilihan | Alasan |
|---|---|---|
| Routing | Array rute di `config/routes.php` + `Core\Router` (cocok method + pola `{id}` numerik) | Rute sedikit (< 50); mudah diaudit |
| Bootstrap/DI | Container array sederhana `Core\App` (factory per kelas) | Hindari pustaka DI |
| Templating | PHP polos + `Core\View::render($view, $data, $layout)` + helper `e()` | Tanpa mesin template |
| Akses DB | Satu `Core\Database` (PDO, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, `time_zone='+08:00'`) | Prepared statement konsisten |
| Validasi | `Core\Validator` berbasis aturan array (`required`, `date`, `max:500`, `in:…`, `exists:tabel,kolom`) | Satu tempat aturan input |
| Migrasi | `scripts/migrate.php` menjalankan `migrations/*.sql` berurutan, mencatat di tabel `schema_migrations` | Tanpa tool migrasi eksternal |
| Sesi | PHP native session, opsi cookie di bootstrap | docs/21 |
| Berkas | `Core\FileStore` menulis ke `STORAGE_PATH` di luar `public/`; unduh lewat Controller berotorisasi | Berkas Rahasia tak bisa diakses langsung |
| CSS | Tailwind v4 Standalone CLI, keluaran `public/assets/app.css` di-commit | Tanpa Node di server |
| Mode pemeliharaan | `public/index.php` mengembalikan 503 + halaman pemberitahuan bila file `MAINTENANCE` ada di root | Dipakai docs/25 saat deploy |
| Nomor agenda | `Agenda\Service\AgendaNumberService::next(tahun)` dengan `SELECT … FOR UPDATE` pada `agenda_counter` di dalam transaksi pemanggil | Aman dari balapan dua operator |

## Integrasi eksternal
Tidak ada di MVP. SRIKANDI hanya nomor rujukan teks (docs/02_SCOPE.md).
