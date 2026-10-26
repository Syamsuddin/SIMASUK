# 09 — Stack
Pemilik daftar teknologi dan versi.

| Lapisan | Teknologi | Versi |
|---|---|---|
| Bahasa | PHP (ekstensi: pdo_mysql, fileinfo, mbstring, json, sodium) | 8.3 |
| Framework | **Tidak ada** (profil native modular) | — |
| Autoload | Composer PSR-4 `App\\` → `app/`, `Modules\\` → `modules/` | Composer 2 |
| Basis data | MySQL, InnoDB, utf8mb4 | 8.0 |
| CSS | Tailwind CSS Standalone CLI (binary di `bin/tailwindcss`, tidak di-commit) | 4.x |
| Font | Inter, file WOFF2 lokal di `public/assets/fonts/` | 4.x |
| Ikon | Heroicons Outline 24px, SVG inline disalin ke `resources/views/components/icons/` | 2.x |
| JS | Vanilla JS ES2020, satu file `public/assets/app.js` (dialog konfirmasi, toast, tombol memuat) | — |
| Tes | PHPUnit | 11 |
| Web server | Nginx + PHP-FPM | Ubuntu 24.04 |

Dependensi Composer runtime: **nol**. Dev: `phpunit/phpunit` saja.

## Teknologi terlarang
| Terlarang | Alasan |
|---|---|
| Framework PHP apa pun (Laravel, CodeIgniter, Slim, Symfony full) | Keputusan sengaja: native modular |
| ORM (Eloquent standalone, Doctrine) | Akses DB lewat Repository + PDO |
| jQuery, Alpine, React, Vue, htmx | Interaksi cukup vanilla JS |
| Tailwind via CDN Play di produksi | Tidak layak produksi, butuh internet |
| Node.js/npm di server produksi | CSS dibangun di mesin dev |
| Font/ikon dari CDN | Server pemda bisa tanpa akses internet keluar |
| Pustaka UI berbasis Tailwind (DaisyUI, Flowbite) | Komponen ditulis sendiri dari token docs/26 |

Menambah dependensi apa pun wajib lewat docs/22_CHANGE_POLICY.md.
