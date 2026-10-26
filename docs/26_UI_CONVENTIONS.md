# 26 — UI Conventions
Rumah tunggal semua fakta antarmuka. Nilai di luar tabel token TERLARANG. Bila token tidak menjawab kebutuhan, berhenti dan tanya.

## Jangkar desain
Warna identitas Pemkab HSS, diterjemahkan ke palet hijau tua + satu aksen kuning di atas netral slate, kepadatan ringkas khas aplikasi dinas. `[ASUMSI]`: hex belum diekstrak dari logo resmi; mengganti token = gerbang docs/22. Preview: `docs/_UI_PREVIEW.html` (bila beda, dokumen ini menang).

## Token
Didefinisikan sekali di blok `@theme` `resources/css/app.css`; view memakai utilitas Tailwind dari nama token (mis. `bg-primary`, `text-ink-muted`).

| Kategori | Token (`@theme`) | Nilai | Pemakaian |
|---|---|---|---|
| Warna | `--color-primary` | `#166534` | tombol utama, header tabel, nav aktif, tautan |
| Warna | `--color-primary-hover` | `#14532D` | hover/aktif tombol utama |
| Warna | `--color-primary-soft` | `#DCFCE7` | latar item nav aktif, baris terpilih |
| Warna | `--color-on-primary` | `#FFFFFF` | teks di atas primer/semantik |
| Warna | `--color-accent` | `#CA8A04` | HANYA garis kiri 4px/titik penanda sifat "Sangat Segera"; tidak pernah sebagai latar teks |
| Warna | `--color-ink` | `#0F172A` | teks utama |
| Warna | `--color-ink-muted` | `#64748B` | label, keterangan, placeholder |
| Warna | `--color-line` | `#E2E8F0` | garis, border input, pemisah |
| Warna | `--color-canvas` | `#F8FAFC` | latar halaman |
| Warna | `--color-surface` | `#FFFFFF` | kartu, tabel, modal |
| Warna | `--color-surface-muted` | `#F1F5F9` | hover baris, latar sidebar |
| Warna | `--color-success` | `#15803D` | status Selesai, toast sukses |
| Warna | `--color-warning` | `#B45309` | status Didisposisi, lewat tenggat |
| Warna | `--color-danger` | `#B91C1C` | aksi destruktif, error, badge Rahasia, status Dibatalkan |
| Warna | `--color-info` | `#1D4ED8` | status Diterima, toast info |
| Tipografi | `--font-sans` | `Inter, system-ui, sans-serif` | semua teks (WOFF2 lokal 400/500/600) |
| Tipografi | basis | 14px / 20px, 400 | isi, sel tabel, input |
| Tipografi | kecil | 12px / 16px, 500 | badge, label kolom, keterangan |
| Tipografi | H2 | 16px / 24px, 600 | judul kartu/bagian |
| Tipografi | H1 | 20px / 28px, 600 | judul halaman (satu per halaman) |
| Spasi | skala | 4 · 8 · 12 · 16 · 24 · 32 px | padding/gap hanya dari skala ini |
| Kepadatan | sel/field | padding 8px 12px; tinggi input & tombol 36px | ringkas |
| Bentuk | `--radius-md` | 6px | tombol, input, kartu, modal |
| Bentuk | pill | 9999px | badge |
| Bayangan | `--shadow-sm` | `0 1px 2px rgba(15,23,42,.08)` | kartu, tabel, dropdown; modal memakai yang sama + overlay `rgba(15,23,42,.5)` |
| Tata letak | sidebar | 240px (desktop) | navigasi |
| Tata letak | konten maks | 1280px, gutter 24px (16px di ≤ 768px) | |

## Target perangkat & breakpoint
Desktop-first. Minimum wajib layak: 768px. Di < 1024px sidebar menjadi laci yang dibuka tombol menu; di ≤ 768px tabel daftar bergulir horizontal di dalam kartunya (halaman tidak boleh bergulir horizontal) dan formulir satu kolom.

## Inventaris komponen (`resources/views/components/`)
| Komponen | Varian | File |
|---|---|---|
| Tombol | primer, garis, bahaya, teks; status memuat (spinner + nonaktif) | `button.php` |
| Field formulir | teks, tanggal, select, textarea, file (PDF, multi), checkbox; selalu label + pesan error | `field.php` |
| Tabel data | header primer, baris hover, kolom aksi kanan, paginasi 25/halaman | `table.php`, `pagination.php` |
| Badge status | Diterima (info), Didisposisi (warning), Selesai (success), Dibatalkan (danger, garis), Rahasia (danger) | `badge.php` |
| Penanda sifat | Sangat Segera = garis kiri aksen pada baris; Segera = teks warning | `sifat.php` |
| Kartu | dengan/tanpa judul H2 | `card.php` |
| Kartu statistik dasbor | angka 20px/600 + label kecil | `stat.php` |
| Dialog konfirmasi | judul, uraian dampak, tombol bahaya + Batal; opsional field alasan wajib | `confirm-dialog.php` |
| Toast | sukses, bahaya, info; hilang 5 detik; satu per aksi | `toast.php` |
| Pohon disposisi | indentasi 24px per jenjang, garis kiri `line` | `dispo-tree.php` |
| State kosong / gagal | ikon outline 24px + kalimat + aksi | `empty-state.php`, `error-state.php` |
| Kerangka memuat | blok `line` berdenyut | `skeleton.php` |
| Ikon | Heroicons Outline 24px saja | `icons/*.php` |

## Peta halaman → pola
| Halaman | Rute | Pola | Peran |
|---|---|---|---|
| Login | `/login` | autentikasi (layout `auth`, tanpa sidebar) | publik |
| Dasbor | `/` | dasbor: 3 kartu statistik + tabel "Belum didisposisi" + tabel "Lewat tenggat" | semua |
| Daftar surat | `/surat-masuk` | daftar+filter (cari, tahun, status, sifat, rentang tgl terima) | semua |
| Catat / ubah surat | `/surat-masuk/baru`, `/surat-masuk/{id}/ubah` | formulir | semua |
| Detail surat | `/surat-masuk/{id}` | detail: metadata, berkas, pohon disposisi, aksi | semua |
| Catat disposisi | `/surat-masuk/{id}/disposisi/baru` | formulir | semua |
| Pengguna | `/pengguna` | daftar+filter + formulir | admin |
| Klasifikasi arsip | `/master/klasifikasi` | daftar+filter + formulir | admin |
| Unit kerja | `/master/unit` | daftar (berindentasi hierarki) + formulir | admin |
| Log audit | `/audit` | daftar+filter (pengguna, aksi, tanggal) | admin |

Navigasi: sidebar kiri (Dasbor, Surat Masuk, lalu grup "Pengaturan" khusus admin). Topbar: nama pengguna, Ubah Password, Keluar.

Aksi yang selalu lewat dialog konfirmasi: Batalkan Surat (alasan wajib), Lepas Berkas, Tandai Surat Selesai, Nonaktifkan Pengguna, Nonaktifkan unit/klasifikasi.

## Empat state wajib (setiap halaman/komponen ber-data)
| State | Tampilan |
|---|---|
| Kosong | `empty-state` dengan kalimat spesifik ("Belum ada surat masuk di tahun 2026.") + tombol aksi bila peran boleh |
| Memuat | Halaman dirender server sehingga state memuat berlaku pada aksi: tombol submit nonaktif + spinner + label "Menyimpan…"; navigasi filter menampilkan `skeleton` di tabel |
| Gagal | `error-state` atau halaman `errors/*` sesuai docs/14, selalu dengan aksi (Coba lagi / Kembali) |
| Sukses | Redirect ke halaman hasil + toast sukses ("Surat 0127/2026 tersimpan.") |

## Bahasa & mikroteks
Bahasa Indonesia baku, nada formal dinas, kalimat pendek. Tanggal tampil `26 Okt 2026`; formulir memakai input tanggal (nilai `Y-m-d`). Nomor agenda selalu `NNNN/TAHUN`. Kata kerja tombol tetap: **Simpan**, **Batal**, **Catat Surat**, **Catat Disposisi**, **Disposisi Lanjutan**, **Tandai Selesai**, **Batalkan Surat**, **Unduh**, **Cari**, **Keluar**. Jangan memakai sinonim (Submit, Hapus, Kirim) untuk aksi yang sama.

## Aksesibilitas dasar
Kombinasi teks/latar di tabel token lulus WCAG AA (teks putih di atas primer/semantik; `ink-muted` di atas `surface`). `accent` tidak pernah dipakai untuk teks. Setiap input punya `<label for>`. Fokus keyboard terlihat: cincin 2px `primary` offset 2px. Status tidak hanya dibedakan warna: badge selalu berteks. Dialog bisa ditutup Esc dan fokus kembali ke tombol pemicunya. Lanjutan (pembaca layar penuh) `[TERBUKA]`.

## Larangan UI
- Nilai warna, ukuran, radius, bayangan, atau spasi di luar tabel di atas (termasuk class Tailwind bawaan seperti `bg-green-600`, `p-5`).
- Gradien, glassmorphism, animasi dekoratif; animasi hanya spinner, skeleton, dan masuk/keluar toast/dialog ≤ 150ms.
- Emoji di antarmuka.
- Ikon dari set selain Heroicons Outline.
- Pustaka UI atau JS baru tanpa docs/22.
- Teks placeholder/lorem tersisa di view yang di-merge.
- Halaman ber-data tanpa state kosong.

Kriteria UI per fitur diperiksa di docs/23.
