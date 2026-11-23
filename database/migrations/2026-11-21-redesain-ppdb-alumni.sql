-- Redesain SMP Swadaya: jalur & data ayah/ibu pendaftar, status verifikasi baru, cek per berkas, cerita alumni.
-- Hanya untuk database yang dibuat SEBELUM redesain. Database baru sudah memakai database/schema.sql.
-- Jalankan sekali lewat phpMyAdmin > SQL.

ALTER TABLE `pendaftar`
    ADD COLUMN `jalur` VARCHAR(20) NOT NULL DEFAULT 'reguler' AFTER `program`,
    ADD COLUMN `nama_ayah` VARCHAR(100) NULL DEFAULT NULL AFTER `wa_wali`,
    ADD COLUMN `pekerjaan_ayah` VARCHAR(100) NULL DEFAULT NULL AFTER `nama_ayah`,
    ADD COLUMN `nama_ibu` VARCHAR(100) NULL DEFAULT NULL AFTER `pekerjaan_ayah`,
    ADD COLUMN `pekerjaan_ibu` VARCHAR(100) NULL DEFAULT NULL AFTER `nama_ibu`,
    ADD COLUMN `minat_ekskul` VARCHAR(255) NULL DEFAULT NULL AFTER `pekerjaan_ibu`,
    ADD COLUMN `catatan_operator` VARCHAR(500) NULL DEFAULT NULL AFTER `minat_ekskul`,
    MODIFY `status_pendaftaran` VARCHAR(20) NOT NULL DEFAULT 'baru';

UPDATE `pendaftar` SET `status_pendaftaran` = 'tidak_diterima' WHERE `status_pendaftaran` = 'tidak_melanjutkan';

ALTER TABLE `berkas` ADD COLUMN `status_periksa` VARCHAR(20) NULL DEFAULT NULL AFTER `mime_type`;

CREATE TABLE IF NOT EXISTS `cerita_alumni` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `tahun_lulus` VARCHAR(4) NULL DEFAULT NULL,
    `kegiatan` VARCHAR(20) NOT NULL,
    `tempat` VARCHAR(150) NULL DEFAULT NULL,
    `kontak` VARCHAR(150) NOT NULL,
    `cerita` TEXT NOT NULL,
    `foto` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'menunggu',
    `unggulan` TINYINT(1) NOT NULL DEFAULT 0,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_alumni_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
