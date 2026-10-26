# 20 — Guardrails
Pemilik larangan operasional untuk agen. Detail keamanan: docs/21_SECURITY_RULES.md.

## JANGAN
1. Menambahkan framework, ORM, atau pustaka besar apa pun. PHP native modular adalah keputusan sengaja; usulan perubahan lewat docs/22.
2. Menambah dependensi Composer runtime, aset CDN, atau binary baru tanpa persetujuan.
3. Menulis SQL di luar Repository atau menyambung string ke SQL.
4. Menulis `agenda_counter` di luar `AgendaNumberService`, atau menghitung nomor agenda dengan `MAX()+1`.
5. Membuat fitur ubah nomor/tahun agenda, atau rute yang menjalankan `DELETE` pada tabel bisnis.
6. Menyimpan berkas di dalam `public/` atau menyajikan berkas lewat URL statis.
7. Hard-code rahasia, kredensial DB, atau `APP_KEY`; commit file `.env`.
8. Menonaktifkan, melonggarkan, atau melewati middleware auth/role/CSRF, termasuk "sementara untuk tes".
9. Menjalankan migrasi di produksi, menghapus data, atau melakukan rilis tanpa konfirmasi manusia.
10. Memakai warna, ukuran, atau komponen di luar docs/26 di view.
11. Mengedit file migrasi yang sudah dirilis; buat migrasi baru.
12. Mengerjakan fitur di luar docs/02 atau fitur bertanda "Nanti" di docs/01 tanpa diminta.
13. Mengedit `public/assets/app.css` manual; selalu hasil build.
