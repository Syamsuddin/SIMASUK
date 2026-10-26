# CLAUDE.md
File ini selalu aktif. Untuk hal di luar ini → buka INDEX.md.

## Proyek
SIMASUK — agenda dan disposisi surat masuk digital BKPSDM Kab. Hulu Sungai Selatan, pelengkap SRIKANDI. Detail: docs/00_EXECUTIVE_SUMMARY.md. Scope: docs/02_SCOPE.md.

## Prinsip kerja agen (non-negotiable)
1. Friksi sebanding irreversibilitas.
2. Lapisan deterministik (validasi, constraint, tes) di bawah penalaran.
3. Human-in-the-loop untuk high-stakes (migrasi DB, hapus data, keamanan, rilis).
4. Jangan refactor keputusan yang disengaja diam-diam.
5. Hormati scope (docs/02). Out-of-scope → berhenti & tanya.

## Stack  (sumber: docs/09)
PHP 8.3 native modular (TANPA framework) · MySQL 8.0 · Tailwind v4 Standalone CLI · vanilla JS · PHPUnit 11 — Terlarang: framework PHP, ORM, jQuery/Alpine/htmx, CDN, Node di server.

## Struktur & konvensi  (sumber: docs/12)
`public/index.php` → Router → Middleware → `modules/<Domain>/{Controller,Service,Repository,views}`. Controller tipis; aturan bisnis + transaksi di Service; SQL hanya di Repository (PDO prepared). Nomor agenda hanya lewat `AgendaNumberService`; berkas hanya lewat `Core\FileStore`.

## Perintah penting  (sumber: docs/11)
run: `php -S localhost:8000 -t public` · test: `vendor/bin/phpunit` · migrasi ⚠️: `php scripts/migrate.php` · CSS: `./bin/tailwindcss -i resources/css/app.css -o public/assets/app.css --minify`

## Guardrail inti  (penuh: docs/20, 21, 22)
Jangan tambah framework/dependensi · jangan SQL di luar Repository · jangan hitung nomor agenda dengan MAX()+1 · jangan DELETE tabel bisnis atau ubah nomor agenda · jangan simpan berkas di `public/` · setiap rute bermiddleware, POST ber-CSRF, output lewat `e()` · migrasi produksi & rilis = konfirmasi manusia.

## Antarmuka  (sumber: docs/26_UI_CONVENTIONS.md)
Hijau identitas HSS di atas netral slate, kepadatan ringkas. Hanya token & komponen docs/26 · tanpa gradien/emoji · empat state wajib di setiap halaman ber-data.

## Alur per-task  (penuh: docs/17, 19)
Baca INDEX.md → muat dokumen relevan → konfirmasi scope → vertical slice → tes (docs/13) → DoD (docs/24).

## Definisi selesai (ringkas; penuh: docs/24)
Kriteria docs/23 terpenuhi · phpunit hijau tanpa tes dilemahkan · konvensi docs/12 · audit tercatat · CSS dibangun ulang.

## Untuk apa pun di luar ini → INDEX.md
