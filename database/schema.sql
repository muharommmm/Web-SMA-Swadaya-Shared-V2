-- =============================================================================
-- Skema Database Website SMA Swadaya (MySQL 5.7+ / MariaDB 10.4+)
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- 1. Tabel operator (Akun login area operator)
CREATE TABLE IF NOT EXISTS `operator` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL DEFAULT 'Operator Sekolah',
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel konten (Isi bagian halaman yang bisa diedit + riwayat 5 versi terakhir)
CREATE TABLE IF NOT EXISTS `konten` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kunci` VARCHAR(80) NOT NULL UNIQUE COMMENT 'Identifier bagian: beranda_hero, profil_legalitas, data_program, dll',
    `kelompok` VARCHAR(50) NOT NULL COMMENT 'Beranda, Profil, Data',
    `judul_bagian` VARCHAR(120) NOT NULL,
    `data_json` LONGTEXT NOT NULL COMMENT 'Data aktif dalam format JSON',
    `riwayat_json` LONGTEXT NULL COMMENT 'Array JSON berisi maksimal 5 versi terakhir',
    `ringkasan_perubahan` VARCHAR(150) NULL DEFAULT 'Data awal',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel kategori (Kategori artikel blog)
CREATE TABLE IF NOT EXISTS `kategori` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `urutan` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel artikel (Artikel blog yang ditulis admin)
CREATE TABLE IF NOT EXISTS `artikel` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `ringkasan` VARCHAR(350) NOT NULL,
    `isi` LONGTEXT NOT NULL,
    `gambar_sampul` VARCHAR(255) NULL DEFAULT NULL,
    `alt_gambar` VARCHAR(200) NULL DEFAULT NULL,
    `kategori_id` INT UNSIGNED NOT NULL,
    `status` ENUM('draf', 'terbit', 'terjadwal', 'tersembunyi') NOT NULL DEFAULT 'draf',
    `published_at` DATETIME NULL DEFAULT NULL,
    `penulis` VARCHAR(100) NOT NULL DEFAULT 'Admin',
    `meta_description` VARCHAR(200) NULL DEFAULT NULL,
    `menit_baca` INT UNSIGNED NOT NULL DEFAULT 3,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_artikel_status_pub` (`status`, `published_at`),
    INDEX `idx_artikel_kategori` (`kategori_id`),
    CONSTRAINT `fk_artikel_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel pendaftar (Data formulir pendaftaran siswa)
CREATE TABLE IF NOT EXISTS `pendaftar` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nomor_registrasi` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Format PPDB-2627-0001 (tahun ajaran 2026/2027)',
    `token_bukti` CHAR(32) NOT NULL UNIQUE COMMENT 'Token acak 32 karakter untuk URL cetak bukti',
    `program` VARCHAR(20) NOT NULL COMMENT 'sma',
    `jalur` VARCHAR(20) NOT NULL DEFAULT 'reguler' COMMENT 'reguler, prestasi',
    `kelas_tujuan` VARCHAR(30) NOT NULL,
    `status_siswa` VARCHAR(20) NOT NULL COMMENT 'baru, pindahan',
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `nik` CHAR(16) NOT NULL,
    `nisn` VARCHAR(10) NULL DEFAULT NULL,
    `tempat_lahir` VARCHAR(50) NOT NULL,
    `tanggal_lahir` DATE NOT NULL,
    `jenis_kelamin` VARCHAR(15) NOT NULL,
    `agama` VARCHAR(25) NOT NULL,
    `alamat_lengkap` VARCHAR(255) NOT NULL,
    `nomor_wa` VARCHAR(20) NOT NULL,
    `email` VARCHAR(150) NULL DEFAULT NULL,
    `jenjang_terakhir` VARCHAR(50) NOT NULL,
    `sekolah_asal` VARCHAR(100) NOT NULL,
    `tahun_keluar` CHAR(4) NOT NULL,
    `provinsi` VARCHAR(100) NULL DEFAULT NULL,
    `kota` VARCHAR(100) NULL DEFAULT NULL,
    `kecamatan` VARCHAR(100) NULL DEFAULT NULL,
    `kelurahan` VARCHAR(100) NULL DEFAULT NULL,
    `kode_pos` CHAR(5) NULL DEFAULT NULL,
    `alamat_wali` VARCHAR(400) NULL DEFAULT NULL,
    `nama_ortu` VARCHAR(100) NULL DEFAULT NULL,
    `wa_ortu` VARCHAR(20) NULL DEFAULT NULL,
    `nama_wali` VARCHAR(100) NULL DEFAULT NULL,
    `wa_wali` VARCHAR(20) NULL DEFAULT NULL,
    `nama_ayah` VARCHAR(100) NULL DEFAULT NULL,
    `pekerjaan_ayah` VARCHAR(100) NULL DEFAULT NULL,
    `nama_ibu` VARCHAR(100) NULL DEFAULT NULL,
    `pekerjaan_ibu` VARCHAR(100) NULL DEFAULT NULL,
    `minat_ekskul` VARCHAR(255) NULL DEFAULT NULL,
    `catatan_operator` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Catatan untuk orang tua saat status diubah',
    `persetujuan_pdp` TINYINT(1) NOT NULL DEFAULT 1,
    `status_pendaftaran` VARCHAR(20) NOT NULL DEFAULT 'baru' COMMENT 'baru, perlu_perbaikan, terverifikasi, diterima, tidak_diterima',
    `status_tidak_melanjutkan_at` DATETIME NULL DEFAULT NULL COMMENT 'Waktu status tidak_diterima, dasar penghapusan otomatis 30 hari',
    `email_lembaga_terkirim` TINYINT(1) NOT NULL DEFAULT 0,
    `email_pendaftar_terkirim` TINYINT(1) NOT NULL DEFAULT 0,
    `email_error_log` VARCHAR(255) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pendaftar_ip_waktu` (`ip_address`, `created_at`),
    INDEX `idx_pendaftar_retensi` (`status_pendaftaran`, `status_tidak_melanjutkan_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel berkas (Berkas unggahan pendaftar yang disimpan di storage/uploads)
CREATE TABLE IF NOT EXISTS `berkas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pendaftar_id` INT UNSIGNED NOT NULL,
    `jenis_berkas` VARCHAR(40) NOT NULL COMMENT 'pas_foto, kk, akta, rapor, sertifikat',
    `label_berkas` VARCHAR(80) NOT NULL,
    `nama_file_acak` VARCHAR(120) NOT NULL,
    `nama_asli` VARCHAR(200) NOT NULL,
    `ukuran_byte` INT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(80) NOT NULL,
    `status_periksa` VARCHAR(20) NULL DEFAULT NULL COMMENT 'sesuai, perbaikan',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_berkas_pendaftar` (`pendaftar_id`),
    CONSTRAINT `fk_berkas_pendaftar` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel log_aktivitas (Catatan login, kegagalan email, perubahan konten)
CREATE TABLE IF NOT EXISTS `log_aktivitas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `jenis` VARCHAR(40) NOT NULL COMMENT 'login, email_gagal, konten, artikel, sistem',
    `pesan` VARCHAR(255) NOT NULL,
    `referensi` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Misal PPDB-2627-0012 atau beranda_hero',
    `aktor` VARCHAR(100) NOT NULL DEFAULT 'Operator',
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_waktu` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. Tabel cerita_alumni (Kiriman formulir halaman Alumni; tampil setelah disetujui operator)
CREATE TABLE IF NOT EXISTS `cerita_alumni` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `tahun_lulus` VARCHAR(4) NULL DEFAULT NULL,
    `kegiatan` VARCHAR(20) NOT NULL COMMENT 'studi, karier',
    `tempat` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Sekolah, kampus, atau tempat kerja (tampil di kartu)',
    `kontak` VARCHAR(150) NOT NULL COMMENT 'WhatsApp atau email, hanya untuk tim sekolah',
    `cerita` TEXT NOT NULL,
    `foto` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'menunggu' COMMENT 'menunggu, disetujui, ditolak',
    `unggulan` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tampil sebagai Kisah Pilihan',
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_alumni_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
