# 24 — Definition of Done
Berlaku untuk setiap task. Perintah: docs/11_COMMANDS.md.

- [ ] Kriteria terima fitur di docs/23 terpenuhi, termasuk empat state UI bila menyentuh halaman.
- [ ] `vendor/bin/phpunit` hijau; tes baru ditulis untuk perilaku baru (docs/13).
- [ ] Tanpa regresi: tidak ada tes yang dihapus, di-skip, atau dilemahkan.
- [ ] Lint sintaks PHP bersih.
- [ ] Struktur dan penamaan sesuai docs/12; SQL hanya di Repository.
- [ ] Tidak melanggar docs/20 dan docs/21 (rute baru bermiddleware, output lewat `e()`, POST ber-CSRF).
- [ ] View hanya memakai token dan komponen docs/26; CSS dibangun ulang dan ikut di-commit.
- [ ] Aksi ubah data menulis `log_audit`.
- [ ] Migrasi baru berjalan bersih di DB kosong dan di DB uji.
- [ ] Bila sebuah fakta berubah: dokumen pemilik + `_MANIFEST.json` diperbarui dan `bash scripts/validate.sh` lulus.
