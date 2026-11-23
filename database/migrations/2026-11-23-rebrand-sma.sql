-- Ganti identitas SMP Swadaya menjadi SMA Swadaya pada konten yang sudah ada di database.
-- Jalankan sekali. Data pendaftar PPDB lama sengaja tidak disentuh (arsip).

UPDATE `konten` SET `data_json` =
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`data_json`,
    'SMP Swadaya', 'SMA Swadaya'),
    'ujiansmp.swadaya.sch.id', 'ujiansma.swadaya.sch.id'),
    'smp.swadaya.sch.id', 'sma.swadaya.sch.id'),
    'siswa SMP', 'siswa SMA'),
    'Sejak SMP', 'Sejak SMA'),
    'kelas IX', 'kelas XII'),
    'kelas VII', 'kelas X'),
    'Kelas VII', 'Kelas X'),
    'SD/MI', 'SMP/MTs'),
    'SMP', 'SMA')
WHERE `data_json` LIKE '%SMP%' OR `data_json` LIKE '%SD/MI%' OR `data_json` LIKE '%kelas VII%' OR `data_json` LIKE '%kelas IX%';

UPDATE `artikel` SET
  `judul` = REPLACE(REPLACE(REPLACE(`judul`, 'SMP Swadaya', 'SMA Swadaya'), 'Sejak SMP', 'Sejak SMA'), 'Kelas VII', 'Kelas X'),
  `slug` = REPLACE(REPLACE(`slug`, 'smp-swadaya', 'sma-swadaya'), 'sejak-smp', 'sejak-sma'),
  `slug` = REPLACE(`slug`, 'kelas-vii', 'kelas-x'),
  `ringkasan` = REPLACE(REPLACE(REPLACE(`ringkasan`, 'SMP Swadaya', 'SMA Swadaya'), 'kelas IX', 'kelas XII'), 'kelas VII', 'kelas X'),
  `isi` = REPLACE(REPLACE(REPLACE(REPLACE(`isi`, 'SMP Swadaya', 'SMA Swadaya'), 'kelas IX', 'kelas XII'), 'kelas VII', 'kelas X'), 'Kelas VII', 'Kelas X'),
  `alt_gambar` = REPLACE(REPLACE(`alt_gambar`, 'SMP Swadaya', 'SMA Swadaya'), 'Sejak SMP', 'Sejak SMA'),
  `meta_description` = REPLACE(REPLACE(`meta_description`, 'SMP Swadaya', 'SMA Swadaya'), 'kelas VII', 'kelas X');

UPDATE `cerita_alumni` SET `cerita` = REPLACE(`cerita`, 'SMP Swadaya', 'SMA Swadaya') WHERE `cerita` LIKE '%SMP Swadaya%';
