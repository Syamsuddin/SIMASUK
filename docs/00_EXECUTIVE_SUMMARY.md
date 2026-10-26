# 00 — Executive Summary

## Masalah
Surat masuk BKPSDM Kabupaten Hulu Sungai Selatan dicatat di buku agenda kertas. Lembar disposisi pimpinan tersimpan terpisah dari suratnya. Akibatnya surat sulit dicari, posisi tindak lanjut tidak terlihat, dan tenggat disposisi mudah terlewat. SRIKANDI hanya menampung naskah yang memang lewat SRIKANDI; surat fisik dan surat dari luar jaringan SRIKANDI tidak tercatat di sana.

## Solusi
SIMASUK adalah buku agenda dan disposisi surat masuk digital untuk satu OPD. Operator TU mencatat setiap surat, mengunggah scan PDF, lalu menginput disposisi berjenjang yang ditulis pimpinan di lembar kertas. Nomor agenda terbit otomatis dan unik per tahun. Untuk surat yang juga ada di SRIKANDI, nomor SRIKANDI disimpan sebagai rujukan.

## Nilai & metrik sukses
- 100% surat masuk tercatat dengan nomor agenda unik per tahun `[ASUMSI]`.
- Satu surat tercatat lengkap (metadata + PDF) dalam ≤ 3 menit `[ASUMSI]`.
- Setiap surat bisa ditelusuri ke disposisi terakhirnya dalam satu halaman detail.

## Pengguna sasaran
Admin dan Operator TU BKPSDM HSS. Pimpinan tidak login di MVP (lihat docs/02_SCOPE.md).

## Status & cakupan
Greenfield. Satu OPD, satu instalasi di server pemda. Web PHP 8.3 native modular + Tailwind + MySQL 8. Fitur: docs/01_PRD.md. Urutan rilis: docs/03_ROADMAP.md.
