-- Pisahkan data orang tua dari wali/kontak darurat.
-- Kolom nama_wali & wa_wali kini dipakai untuk wali / kontak darurat.
-- Jalankan sekali di database yang sudah ada (phpMyAdmin > SQL).
ALTER TABLE `pendaftar`
    ADD COLUMN `nama_ortu` VARCHAR(100) NULL DEFAULT NULL AFTER `tahun_keluar`,
    ADD COLUMN `wa_ortu` VARCHAR(20) NULL DEFAULT NULL AFTER `nama_ortu`;
