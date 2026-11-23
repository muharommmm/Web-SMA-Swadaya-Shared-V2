-- Ganti tautan Portal CBT ke https://ujiansmp.swadaya.sch.id.
-- Untuk database yang sudah terisi (produksi). Database baru sudah memakai alamat ini dari seeder.
-- Alternatif tanpa SQL: Admin > Konten Situs > Kontak & footer > "Tautan Portal CBT".
-- Jalankan sekali di phpMyAdmin > SQL.

UPDATE `konten`
SET `data_json` = REPLACE(`data_json`, 'cbt.smp.swadaya.sch.id', 'ujiansmp.swadaya.sch.id'),
    `updated_at` = NOW()
WHERE `kunci` = 'data_kontak' AND `data_json` LIKE '%cbt.smp.swadaya.sch.id%';
