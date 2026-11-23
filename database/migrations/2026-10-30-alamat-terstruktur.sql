-- Alamat terstruktur (provinsi > kota > kecamatan > kelurahan + kode pos) dan alamat wali.
-- alamat_lengkap kini hanya berisi jalan, nomor, RT/RW.
-- Jalankan sekali di database yang sudah ada (phpMyAdmin > SQL).
ALTER TABLE `pendaftar`
    ADD COLUMN `provinsi` VARCHAR(100) NULL DEFAULT NULL AFTER `tahun_keluar`,
    ADD COLUMN `kota` VARCHAR(100) NULL DEFAULT NULL AFTER `provinsi`,
    ADD COLUMN `kecamatan` VARCHAR(100) NULL DEFAULT NULL AFTER `kota`,
    ADD COLUMN `kelurahan` VARCHAR(100) NULL DEFAULT NULL AFTER `kecamatan`,
    ADD COLUMN `kode_pos` CHAR(5) NULL DEFAULT NULL AFTER `kelurahan`,
    ADD COLUMN `alamat_wali` VARCHAR(400) NULL DEFAULT NULL AFTER `kode_pos`;
