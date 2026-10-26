CREATE TABLE klasifikasi_arsip (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode VARCHAR(20) NOT NULL,
    uraian VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_klasifikasi_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
