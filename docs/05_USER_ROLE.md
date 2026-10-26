# 05 — User Role
Pemilik peran dan matriks akses. Penegakan teknis: docs/21_SECURITY_RULES.md.

## Peran
| Kode | Peran | Siapa |
|---|---|---|
| `admin` | Admin | Pengelola aplikasi di BKPSDM (1–2 orang) |
| `operator` | Operator TU | Petugas tata usaha/agendaris |

Pengguna nonaktif (`is_active = 0`) tidak bisa login, apa pun perannya. Pimpinan tidak punya akun di MVP.

## Matriks akses
| Kapabilitas | admin | operator |
|---|---|---|
| Login, ubah password sendiri | ✓ | ✓ |
| Lihat dasbor, daftar, detail surat (termasuk sifat Rahasia) | ✓ | ✓ |
| Unduh berkas PDF | ✓ | ✓ |
| Catat surat baru, unggah berkas | ✓ | ✓ |
| Ubah metadata surat (selain nomor agenda & tahun) | ✓ | ✓ |
| Catat / ubah disposisi, tandai disposisi & surat selesai | ✓ | ✓ |
| Batalkan surat (status Dibatalkan) | ✓ | ✗ |
| Lepas berkas dari surat (tandai dihapus, file tetap) | ✓ | ✗ |
| Kelola pengguna (buat, nonaktifkan, reset password) | ✓ | ✗ |
| Kelola master klasifikasi & unit | ✓ | ✗ |
| Lihat log audit | ✓ | ✗ |
| Ubah nomor agenda / tahun agenda / `agenda_counter` | ✗ | ✗ |
| Hard delete data apa pun | ✗ | ✗ |

Ubah nomor agenda tidak tersedia di aplikasi. Koreksi nomor hanya lewat gerbang manusia di docs/22_CHANGE_POLICY.md.

Admin tidak boleh menonaktifkan akunnya sendiri dan tidak boleh menonaktifkan admin aktif terakhir.
