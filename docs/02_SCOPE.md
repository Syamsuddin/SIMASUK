# 02 — Scope
Gerbang anti scope-creep. Permintaan di luar daftar In-scope → berhenti dan tanya.

## In-scope (MVP)
- Satu OPD: BKPSDM Kabupaten Hulu Sungai Selatan, satu instalasi.
- Dua peran login: Admin dan Operator TU (docs/05_USER_ROLE.md).
- Fitur bertanda MVP di docs/01_PRD.md.
- Fitur pasca-MVP yang sudah diberi fase di docs/03_ROADMAP.md (F5: cetak lembar disposisi).
- Disposisi diinput Operator berdasarkan lembar kertas pimpinan; pimpinan tidak login.
- Nomor SRIKANDI sebagai teks rujukan opsional pada surat.

## Out-of-scope (eksplisit)
- **Surat keluar** dan penomoran naskah keluar.
- **Integrasi API SRIKANDI** dalam bentuk apa pun (tarik, kirim, sinkron).
- **TTE / tanda tangan elektronik** dan verifikasi BSrE.
- **OCR** isi berkas scan; pencarian hanya atas metadata.
- **Multi-OPD / multi-tenant**; tidak ada kolom `opd_id`.
- **Aplikasi mobile native** dan PWA offline.
- **Login pimpinan/penerima disposisi** (fase berikutnya, docs/03_ROADMAP.md).
- **Notifikasi** WhatsApp, Telegram, atau email di MVP.
- **Hard delete** surat, berkas, atau disposisi oleh peran mana pun.

## Asumsi & batasan
- Kode hex identitas HSS belum resmi; token di docs/26_UI_CONVENTIONS.md berstatus `[ASUMSI]`.
- Isi awal master kode klasifikasi arsip disediakan BKPSDM `[TERBUKA]`.
- Server produksi milik pemda dengan akses SSH (docs/10_DEV_ENV.md).
