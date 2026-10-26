# 07 — Data Model
Sumber kebenaran skema. Dokumen lain merujuk, tidak menyalin. Engine: MySQL 8.0, InnoDB, `utf8mb4_unicode_ci`. Migrasi: `migrations/NNNN_nama.sql` (perintah di docs/11_COMMANDS.md). Semua `datetime` disimpan dalam WITA (+08:00).

## Tabel: users
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| username | varchar(50) | UNIQUE, NOT NULL | huruf kecil, `[a-z0-9._]` |
| nama | varchar(120) | NOT NULL | |
| password_hash | varchar(255) | NOT NULL | `PASSWORD_ARGON2ID` (docs/21) |
| role | enum('admin','operator') | NOT NULL | peran: docs/05 |
| is_active | tinyint(1) | NOT NULL, default 1 | |
| failed_attempts | tinyint unsigned | NOT NULL, default 0 | rate limit login |
| locked_until | datetime | NULL | |
| last_login_at | datetime | NULL | |
| created_at / updated_at | datetime | NOT NULL | |

## Tabel: unit_kerja
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| parent_id | int unsigned | FK→unit_kerja.id, NULL | hierarki |
| nama | varchar(150) | NOT NULL | mis. "Bidang PSDM" |
| jabatan_pimpinan | varchar(150) | NULL | mis. "Kepala Bidang PSDM" |
| urutan | smallint unsigned | NOT NULL, default 0 | urutan tampil |
| is_active | tinyint(1) | NOT NULL, default 1 | nonaktif = tak muncul di pilihan |
| created_at / updated_at | datetime | NOT NULL | |

## Tabel: klasifikasi_arsip
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| kode | varchar(20) | UNIQUE, NOT NULL | mis. `800.1.2` |
| uraian | varchar(255) | NOT NULL | |
| is_active | tinyint(1) | NOT NULL, default 1 | |
| created_at / updated_at | datetime | NOT NULL | |

## Tabel: agenda_counter
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| tahun | smallint unsigned | PK | |
| last_no | int unsigned | NOT NULL, default 0 | hanya diubah `AgendaNumberService` |

## Tabel: surat_masuk
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| tahun | smallint unsigned | NOT NULL | = YEAR(tgl_terima); immutable |
| no_agenda | int unsigned | NOT NULL | immutable |
| tgl_terima | date | NOT NULL | |
| no_surat | varchar(100) | NOT NULL | |
| tgl_surat | date | NOT NULL | ≤ tgl_terima |
| asal | varchar(200) | NOT NULL | |
| perihal | varchar(500) | NOT NULL | |
| sifat | enum('biasa','segera','sangat_segera','rahasia') | NOT NULL, default 'biasa' | |
| klasifikasi_id | int unsigned | FK→klasifikasi_arsip.id, NOT NULL | |
| no_srikandi | varchar(100) | NULL | rujukan, tanpa validasi |
| jumlah_lampiran | varchar(50) | NULL | teks bebas, mis. "1 berkas" |
| status | enum('diterima','didisposisi','selesai','dibatalkan') | NOT NULL, default 'diterima' | |
| cancelled_at | datetime | NULL | |
| cancel_reason | varchar(255) | NULL | wajib bila dibatalkan |
| created_by / updated_by | int unsigned | FK→users.id | |
| created_at / updated_at | datetime | NOT NULL | |

Constraint: UNIQUE(tahun, no_agenda). CHECK(tgl_surat <= tgl_terima).
Indeks: (tahun, status), (tgl_terima), (no_surat, asal, tgl_surat) untuk cek ganda, FULLTEXT(perihal, asal) untuk pencarian.

## Tabel: berkas_surat
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| surat_id | int unsigned | FK→surat_masuk.id, NOT NULL | ON DELETE RESTRICT |
| path | varchar(255) | NOT NULL, UNIQUE | relatif ke `STORAGE_PATH`, mis. `surat/2026/ab12….pdf` |
| nama_asli | varchar(255) | NOT NULL | hanya untuk tampilan/unduh |
| ukuran | int unsigned | NOT NULL | byte, ≤ 10485760 |
| sha256 | char(64) | NOT NULL | integritas |
| deleted_at | datetime | NULL | dilepas Admin; file tetap ada |
| created_by | int unsigned | FK→users.id | |
| created_at | datetime | NOT NULL | |

## Tabel: disposisi
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | int unsigned | PK, auto | |
| surat_id | int unsigned | FK→surat_masuk.id, NOT NULL | ON DELETE RESTRICT |
| parent_id | int unsigned | FK→disposisi.id, NULL | induk; harus surat_id yang sama (dicek Service) |
| unit_tujuan_id | int unsigned | FK→unit_kerja.id, NOT NULL | |
| tgl_disposisi | date | NOT NULL | tanggal di lembar kertas |
| instruksi | varchar(500) | NOT NULL | |
| catatan | varchar(500) | NULL | |
| tenggat | date | NULL | ≥ tgl_terima surat |
| status | enum('dikirim','selesai') | NOT NULL, default 'dikirim' | |
| selesai_at | datetime | NULL | |
| catatan_selesai | varchar(500) | NULL | |
| created_by | int unsigned | FK→users.id | |
| created_at / updated_at | datetime | NOT NULL | |

Indeks: (surat_id), (status, tenggat) untuk "lewat tenggat".

## Tabel: log_audit
| Kolom | Tipe | Constraint | Catatan |
|---|---|---|---|
| id | bigint unsigned | PK, auto | append-only, tak pernah di-UPDATE/DELETE |
| user_id | int unsigned | FK→users.id, NULL | NULL untuk aksi sistem/login gagal |
| aksi | varchar(40) | NOT NULL | mis. `surat.create`, `surat.cancel`, `auth.login_failed` |
| entitas | varchar(40) | NULL | nama tabel |
| entitas_id | int unsigned | NULL | |
| data_json | json | NULL | nilai sebelum/sesudah; tanpa password |
| ip | varchar(45) | NULL | |
| created_at | datetime | NOT NULL | |

Indeks: (entitas, entitas_id), (created_at).

## Relasi
klasifikasi_arsip 1—N surat_masuk · surat_masuk 1—N berkas_surat · surat_masuk 1—N disposisi · disposisi 1—N disposisi (lanjutan) · unit_kerja 1—N disposisi · unit_kerja 1—N unit_kerja · users 1—N log_audit. Semua FK `ON DELETE RESTRICT`; tidak ada `DELETE` pada tabel bisnis.

## Data sensitif
Surat bersifat `rahasia` dan seluruh berkas: aturan akses & penyimpanan di docs/21_SECURITY_RULES.md. `password_hash` tidak pernah ikut `data_json` log audit.
