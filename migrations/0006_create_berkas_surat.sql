CREATE TABLE berkas_surat (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    surat_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    nama_asli VARCHAR(255) NOT NULL,
    ukuran INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    deleted_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_berkas_path (path),
    KEY idx_berkas_surat (surat_id),
    CONSTRAINT fk_berkas_surat FOREIGN KEY (surat_id) REFERENCES surat_masuk (id) ON DELETE RESTRICT,
    CONSTRAINT fk_berkas_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
