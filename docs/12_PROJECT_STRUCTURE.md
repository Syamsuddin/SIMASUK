# 12 — Project Structure
Pemilik struktur folder dan penamaan. Wajib penuh: tidak ada framework yang menyediakan konvensi.

## Pohon folder
```
simasuk/
├── public/                    # satu-satunya folder yang diakses web
│   ├── index.php              # front controller
│   └── assets/ app.css (hasil build, di-commit) · app.js · fonts/ · img/
├── bootstrap/app.php          # muat .env, timezone, error handler, sesi, PDO, container
├── config/
│   ├── routes.php             # daftar rute: [method, path, [Controller::class,'aksi'], ['auth','role:admin']]
│   └── app.php                # konstanta konfigurasi non-rahasia
├── app/Core/                  # namespace App\Core — infrastruktur bersama
│   Router.php · App.php · Database.php · View.php · Request.php · Response.php
│   Session.php · Csrf.php · Validator.php · FileStore.php · Logger.php · Audit.php
│   ErrorHandler.php · HttpException.php · ValidationException.php · helpers.php (e(), url(), old())
├── app/Middleware/            # AuthMiddleware · RoleMiddleware · CsrfMiddleware
├── modules/                   # namespace Modules\<Domain>
│   ├── Auth/      {Controller,Service,Repository,views}
│   ├── Pengguna/  {Controller,Service,Repository,views}
│   ├── Master/    {Controller,Service,Repository,views}   # klasifikasi + unit kerja
│   ├── Agenda/    {Controller,Service,Repository,views}   # surat_masuk, berkas, agenda_counter
│   ├── Disposisi/ {Controller,Service,Repository,views}
│   ├── Dasbor/    {Controller,Service,Repository,views}
│   └── Audit/     {Controller,Service,Repository,views}
├── resources/
│   ├── css/app.css            # sumber Tailwind: @import, @source, @theme (token docs/26)
│   └── views/ layout/ (app.php, auth.php, cetak.php) · components/ · errors/ (403,404,419,500)
├── migrations/                # 0001_create_users.sql … berurutan, tak pernah diedit setelah rilis
├── scripts/ migrate.php · seed.php · smoke.php · validate.sh · token_ledger.py
├── tests/ Unit/ · Integration/ · bootstrap.php
├── bin/tailwindcss            # binary, .gitignore
├── storage/                   # hanya untuk dev lokal (.gitignore); produksi pakai STORAGE_PATH
├── logs/                      # dev lokal (.gitignore)
└── composer.json · phpunit.xml · .env.example
```

## Lokasi jenis kode
| Jenis kode | Lokasi |
|---|---|
| Aturan bisnis, transaksi, log audit | `modules/<Domain>/Service/*Service.php` |
| SQL | `modules/<Domain>/Repository/*Repository.php` saja |
| Aturan validasi input | konstanta/metode `rules()` di Service yang bersangkutan, dieksekusi `Core\Validator` |
| HTML halaman | `modules/<Domain>/views/<aksi>.php` |
| Komponen UI bersama | `resources/views/components/<nama>.php` (inventaris: docs/26) |
| Penomoran agenda | `modules/Agenda/Service/AgendaNumberService.php` (satu-satunya penulis `agenda_counter`) |
| Penyimpanan berkas | `app/Core/FileStore.php` (satu-satunya yang menyentuh `STORAGE_PATH`) |

## Penamaan
| Hal | Konvensi | Contoh |
|---|---|---|
| Kelas | PascalCase, akhiran peran | `SuratMasukRepository`, `DisposisiService` |
| Metode, variabel | camelCase | `findByAgenda()` |
| Tabel, kolom | snake_case Bahasa Indonesia sesuai docs/07 | `surat_masuk.tgl_terima` |
| Rute | kebab-case, jamak, Bahasa Indonesia | `/surat-masuk/{id}/disposisi` |
| View | snake_case | `views/form.php`, `views/detail.php` |
| Migrasi | `NNNN_kata_kerja_objek.sql` | `0005_create_surat_masuk.sql` |
| Aksi audit | `entitas.aksi` | `disposisi.create` |
| Tes | `<Kelas>Test.php`, metode `test_<perilaku>` | `test_nomor_agenda_reset_tahun_baru` |

Setiap file PHP diawali `declare(strict_types=1);`. Satu kelas per file. Tidak ada kode yang dieksekusi di luar `public/index.php` dan `scripts/`.
