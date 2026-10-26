# Arsip 23 — F4 Dasbor (F-DASBOR, F-AUDIT halaman)
Diterima 2026-10-26: kriteria terpenuhi, phpunit hijau (159 tes), tampilan 768px tanpa gulir horizontal halaman (dasbor, log audit, daftar surat berfilter rentang). Penegakan pindah ke test suite: `DasborServiceTest`, `AuditRepositoryTest`, `AuditServiceTest`, `RoleMiddlewareTest` (rute `/audit`).

## F-DASBOR & F-AUDIT halaman (F4)
- Given 3 disposisi dikirim dengan tenggat kemarin, Then kartu "Lewat tenggat" = 3 dan tabelnya memuat ketiganya.
- Given surat Diterima tanpa disposisi, Then muncul di "Belum didisposisi".
- Given Admin membuka `/audit` dan memfilter aksi `surat.cancel`, Then hanya baris pembatalan tampil; `data_json` tidak memuat `password_hash`.

```bash
vendor/bin/phpunit --filter='DasborServiceTest|AuditRepositoryTest'
```
Lulus: `OK`.
