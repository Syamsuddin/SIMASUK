# 18 — Repair Rules
Pemilik aturan perbaikan bug.

## Alur
1. **Reproduksi**: tulis langkah atau tes yang menunjukkan bug. Tanpa reproduksi, jangan ubah kode.
2. **Akar masalah**: telusuri sampai lapisan penyebab (Controller/Service/Repository/view/CSS). Cek jebakan di docs/16 lebih dulu.
3. **Tes regresi**: tes yang gagal sebelum perbaikan (docs/13).
4. **Perbaikan minimal** di lapisan penyebab.
5. **Verifikasi**: seluruh suite hijau + DoD (docs/24).

## Larangan
- Perbaikan tidak melebihi scope bug; temuan lain dicatat terpisah, tidak ikut diperbaiki.
- Tidak ada refactor luas, ganti nama, atau pemindahan file dalam commit perbaikan bug.
- Tidak memperbaiki dengan menonaktifkan validasi, otorisasi, CSRF, atau constraint DB.
- Bug yang menyangkut data tersimpan (nomor agenda ganda, status salah) diperbaiki dengan skrip koreksi yang disetujui manusia (docs/22), bukan `UPDATE` manual langsung di produksi.
- Bila perbaikan menuntut perubahan skema (docs/07), hentikan dan ikuti rute "Ubah skema DB" di INDEX.md.
