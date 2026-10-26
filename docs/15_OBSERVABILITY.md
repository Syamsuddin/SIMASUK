# 15 — Observability
Pemilik logging dan metrik.

## Dua jenis catatan
| Catatan | Rumah | Tujuan | Pembaca |
|---|---|---|---|
| Log aplikasi | file `LOG_PATH/app-YYYY-MM-DD.log` | Diagnosa teknis | Pengembang |
| Log audit | tabel `log_audit` (docs/07) | Jejak aksi pengguna atas data | Admin, auditor |

Keduanya tidak saling menggantikan.

## Format log aplikasi
Satu baris JSON per kejadian, ditulis `App\Core\Logger`:
`{"ts":"2026-10-26T11:20:05+08:00","level":"error","error_id":"9f3a1c2e","user_id":3,"method":"POST","path":"/surat-masuk","msg":"…","context":{…}}`

Level: `debug` (hanya local) · `info` · `notice` · `warning` · `error` · `critical`. Produksi mencatat ≥ `info`.

## Yang dicatat
- Semua exception 403/419/500 (docs/14).
- Login berhasil (`info`), login gagal dan penguncian akun (`warning`) — juga ke `log_audit`.
- Unggah berkas ditolak (alasan + ukuran + MIME terdeteksi, tanpa isi berkas).
- Durasi request > 2 detik (`notice`).

## Yang dilarang dicatat
Password, isi `.env`, token CSRF, isi berkas, perihal surat bersifat `rahasia`.

## Rotasi
File harian; logrotate server menyimpan 90 hari. Log audit tidak dirotasi dan tidak dihapus.

## Metrik
`[TERBUKA]` pasca-MVP. Dasbor aplikasi (F-DASBOR) adalah metrik bisnis, bukan monitoring teknis.
