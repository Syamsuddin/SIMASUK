# 13 — Testing
Pemilik strategi tes. Perintah: docs/11_COMMANDS.md.

## Jenis tes
| Suite | Isi | Basis data |
|---|---|---|
| `unit` (`tests/Unit`) | Service murni, Validator, Router, Csrf, helper `e()`; Repository di-stub | Tidak ada |
| `integration` (`tests/Integration`) | Repository + Service dengan PDO nyata, transaksi, constraint, FileStore ke folder sementara | `DB_TEST_NAME`, dimigrasi lalu dikosongkan per tes |

Target cakupan: semua Service bertanda kritikal di bawah punya tes; tidak ada target persentase global.

## Wajib dites (alur kritikal)
| Area | Kasus wajib |
|---|---|
| Penomoran agenda | nomor urut naik; reset ke 1 saat tahun `tgl_terima` berganti; dua koneksi PDO paralel tidak menghasilkan nomor kembar (tes integrasi dengan dua koneksi) |
| Pencatatan surat | validasi field; `tgl_surat > tgl_terima` ditolak; gagal simpan berkas membatalkan transaksi tanpa memakai nomor |
| Unggah berkas | non-PDF (MIME palsu `.pdf`) ditolak; > 10 MB ditolak; path tersimpan di luar webroot; nama file acak |
| Disposisi | status surat berubah ke Didisposisi; disposisi lanjutan lintas surat ditolak; tenggat < tgl_terima ditolak; surat Selesai/Dibatalkan menolak disposisi baru |
| Otorisasi | Operator ditolak 403 pada rute Admin (tes Router + RoleMiddleware dengan tabel rute nyata) |
| CSRF | POST tanpa token valid → 419 |
| Auth | password salah 5× → akun terkunci 15 menit; pengguna nonaktif tidak bisa login |
| Audit | setiap aksi ubah di Service menulis satu baris `log_audit` tanpa `password_hash` |

## Aturan
- Tes baru ditulis bersama kode fitur (vertical slice), bukan sesudahnya.
- Perbaikan bug wajib membawa tes regresi yang gagal sebelum perbaikan (docs/18).
- Dilarang menghapus atau melemahkan tes agar hijau.
- Pemeriksaan UI (empat state, breakpoint) dilakukan manual sesuai daftar di docs/23.
