# Arsip 23 — F5 Lembar disposisi (F-LEMBAR)
Diterima 2026-10-26: kriteria terpenuhi, phpunit hijau (164 tes), lembar dengan 8 unit aktif dan perihal 300 karakter tercetak ke PDF A4 tepat 1 halaman dengan toolbar tersembunyi, 768px dan 1280px tanpa gulir horizontal halaman (Chrome headless pada server nyata, DB terpisah). Penegakan pindah ke test suite: `DisposisiServiceTest` (`test_lembar_*`), `RouterTest` (rute bermiddleware).

## F-LEMBAR — Cetak lembar disposisi (F5)
- Given surat Diterima atau Didisposisi, When Admin atau Operator membuka `/surat-masuk/{id}/lembar-disposisi`, Then tampil LEMBAR DISPOSISI berkop instansi dengan nomor agenda `NNNN/TAHUN`, asal, nomor dan tanggal surat, tanggal terima, sifat, kode klasifikasi, dan perihal, tanpa sidebar/topbar.
- Given unit aktif berhierarki dan satu unit nonaktif, Then "Diteruskan kepada" memuat kotak centang unit aktif berurutan hierarki dan berindentasi + 2 baris kosong; unit nonaktif tidak tampil.
- Given tidak ada unit aktif, Then "Diteruskan kepada" tetap berisi baris kosong untuk ditulis tangan.
- Given surat Selesai atau Dibatalkan, Then tombol "Cetak Lembar Disposisi" tak tampil di detail dan GET langsung dialihkan ke detail dengan toast surat tertutup.
- Given id surat tidak ada, Then 404. Given perihal berisi `<script>`, Then tampil sebagai teks.
- Given lembar dibuka, Then `log_audit` tidak bertambah (hanya baca).
- Given 8 unit aktif dan perihal 300 karakter, When dicetak ke PDF A4, Then tepat 1 halaman dan toolbar tidak ikut tercetak; di 768px tanpa gulir horizontal halaman.

```bash
vendor/bin/phpunit --filter='test_lembar'
```
Lulus: `OK`. Butir cetak PDF dan 768px dibuktikan lewat Chrome headless (`Page.printToPDF` dengan ukuran halaman CSS).
