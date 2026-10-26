# 14 — Error Handling
Pemilik pola error. Wajib penuh: tidak ada exception handler bawaan framework.

## Handler global
`bootstrap/app.php` memasang `App\Core\ErrorHandler`:
- `set_error_handler` mengubah warning/notice menjadi `ErrorException`.
- `set_exception_handler` menangkap semua exception yang lolos.
- `register_shutdown_function` menangkap fatal error.
- `display_errors=0` di semua lingkungan; detail hanya ke log (docs/15).

## Klasifikasi
| Exception | HTTP | Ditampilkan ke pengguna | Dicatat |
|---|---|---|---|
| `ValidationException` (field → pesan) | 422 / redirect balik | Pesan per field di bawah input + ringkasan di atas formulir; isian lama dipertahankan via `old()` | Tidak |
| `HttpException(403)` | 403 | Halaman `errors/403`: "Anda tidak memiliki akses ke halaman ini." | `warning` + user_id + rute |
| `HttpException(404)` | 404 | `errors/404`: "Data tidak ditemukan." | Tidak |
| `HttpException(419)` (CSRF/sesi kedaluwarsa) | 419 | `errors/419`: "Sesi berakhir. Muat ulang halaman lalu coba lagi." | `notice` |
| `DomainRuleException` (aturan bisnis, mis. surat sudah dibatalkan) | redirect balik | Toast bahaya berisi pesan aturan | `info` |
| `PDOException` | 500 | `errors/500`: "Terjadi kesalahan. Kode: {error_id}" | `error` + error_id + trace |
| Lainnya (`Throwable`) | 500 | sama dengan di atas | `critical` + error_id + trace |

`error_id` = 8 karakter heksadesimal acak, sama di layar dan di log agar Operator bisa melapor.

## Format pesan
- Bahasa Indonesia, kalimat lengkap, tanpa istilah teknis, menyebut cara memperbaiki bila bisa ("Ukuran berkas maks 10 MB.").
- Konstanta pesan validasi di `Core\Validator`; jangan menulis pesan berbeda untuk aturan yang sama.

## Transaksi
Service membuka transaksi dengan `Database::transaction(callable)`. Exception di dalamnya → rollback, file yang sudah ditulis `FileStore` di transaksi itu dihapus, exception dilempar ulang.

## Larangan
- Jangan pernah `echo` pesan exception, SQL, path server, atau stack trace ke pengguna, termasuk saat `APP_ENV=local` di peramban (pakai log).
- Jangan menelan exception dengan `catch` kosong.
- Jangan memakai `@` untuk membungkam error.
- Jangan mengembalikan `false`/`null` sebagai sinyal gagal dari Service; lempar exception bertipe.
