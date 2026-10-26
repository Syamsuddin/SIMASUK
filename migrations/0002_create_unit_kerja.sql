CREATE TABLE unit_kerja (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    nama VARCHAR(150) NOT NULL,
    jabatan_pimpinan VARCHAR(150) NULL,
    urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_unit_kerja_parent (parent_id),
    CONSTRAINT fk_unit_kerja_parent FOREIGN KEY (parent_id) REFERENCES unit_kerja (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
