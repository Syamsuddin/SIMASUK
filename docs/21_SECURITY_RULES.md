# 21 — Security Rules
Pemilik keamanan. Wajib penuh: tidak ada middleware framework yang menanggungnya. Peran: docs/05_USER_ROLE.md. Kolom: docs/07_DATA_MODEL.md.

## Autentikasi
- Password: `password_hash($p, PASSWORD_ARGON2ID)`, verifikasi `password_verify`, `password_needs_rehash` saat login sukses. Minimal 10 karakter.
- Login gagal: `failed_attempts` naik; pada 5 kali → `locked_until = NOW() + 15 menit`. Pesan tetap umum: "Username atau password salah."
- Pengguna nonaktif ditolak dengan pesan yang sama.
- Setelah login sukses: `session_regenerate_id(true)`, reset `failed_attempts`.
- Logout hanya lewat POST ber-CSRF; hancurkan sesi dan cookie.

## Sesi
Diatur sekali di `bootstrap/app.php`: `session.use_strict_mode=1`, cookie `HttpOnly`, `SameSite=Lax`, `Secure` bila `SESSION_SECURE=1`, nama `SIMASUKSESS`. Idle timeout 60 menit (cek `last_activity` di sesi); absolut 10 jam.

## Otorisasi
- Setiap rute di `config/routes.php` wajib mendeklarasikan middleware; rute tanpa `auth` hanya `/login` (GET/POST).
- `role:admin` pada semua kapabilitas Admin di matriks docs/05.
- Service mengecek ulang aturan status (mis. surat Dibatalkan) tanpa bergantung pada tombol yang disembunyikan di view.
- ID di URL selalu diambil ulang dari DB; 404 bila tak ada.

## CSRF
Token per sesi (`random_bytes(32)`), disisipkan helper `csrf_field()` di setiap formulir, dicek `hash_equals` pada semua POST. Gagal → 419. Tidak ada aksi ubah data lewat GET.

## Validasi input
- Semua input lewat `Core\Validator` dengan aturan di Service; tipe di-cast setelah lolos.
- Tanggal format `Y-m-d`, dicek `checkdate`. Enum dicek terhadap daftar tetap. FK dicek `exists` dan `is_active`.
- Panjang teks sesuai docs/07; trim spasi; tolak karakter kontrol.

## Output
Semua data dinamis di view lewat `e()` (`htmlspecialchars` ENT_QUOTES, UTF-8). Dilarang `<?= $var ?>` tanpa `e()`. Header: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, CSP `default-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'` (tanpa inline script; JS hanya `app.js`).

## Basis data
PDO prepared statement untuk semua query, `ATTR_EMULATE_PREPARES=false`. Nama kolom untuk sorting dipilih dari whitelist, bukan dari input. Akun DB aplikasi hanya punya SELECT/INSERT/UPDATE pada tabel aplikasi; migrasi memakai akun terpisah di produksi `[ASUMSI]`.

## Berkas
- Validasi: `finfo_file` = `application/pdf` dan 5 byte pertama `%PDF-`; ukuran ≤ `UPLOAD_MAX_MB`; maks 5 berkas aktif per surat (docs/01).
- Simpan di `STORAGE_PATH/surat/{tahun}/{bin2hex(random_bytes(16))}.pdf`, izin 0640; `nama_asli` hanya untuk header unduhan (disanitasi).
- Unduh lewat `GET /berkas/{id}` (auth): header `Content-Type: application/pdf`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`. Berkas dengan `deleted_at` → 404 untuk Operator.

## Data sensitif
- Surat `rahasia`: perihal tidak ditulis ke log aplikasi; tampil dengan badge Rahasia. Kedua peran boleh melihat (docs/05). Pembatasan lebih ketat `[TERBUKA]` saat peran Pimpinan hadir.
- `log_audit.data_json` tidak boleh memuat `password_hash` atau token.

## Rahasia
Hanya di `.env` (izin 0600, di luar `public/`). `.env` dan `storage/` masuk `.gitignore`.

## Rate limit
Login: penguncian akun di atas + jeda 1 detik per percobaan gagal. Rute lain tidak dibatasi di MVP (aplikasi intranet berakun).

## Kepatuhan
Prinsip SPBE (keamanan informasi) dan kearsipan dinamis; retensi arsip `[TERBUKA]`. Tidak ada data dihapus permanen oleh aplikasi.
