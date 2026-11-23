<?php

namespace App\Core;

use PDO;

class Seeder
{
    public static function ensureInitialized(PDO $pdo, string $driver): void
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM konten");
            if ($stmt && (int) $stmt->fetchColumn() > 0) {
                return;
            }
        } catch (\Throwable $e) {
            // Tabel belum ada, buat skema di bawah
        }

        self::createTables($pdo, $driver);
        self::seedData($pdo);
    }

    private static function createTables(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $schemaFile = dirname(__DIR__, 2) . '/database/schema.sql';
            if (is_file($schemaFile)) {
                $sql = (string) file_get_contents($schemaFile);
                // Hapus baris komentar SQL (--) lalu jalankan tiap statement satu per satu
                $cleanSql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
                $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
                foreach ($statements as $stmt) {
                    if ($stmt !== '') {
                        $pdo->exec($stmt);
                    }
                }
                return;
            }
        }

        // Skema kompatibel SQLite & umum
        $queries = [
            "CREATE TABLE IF NOT EXISTS operator (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama VARCHAR(100) NOT NULL DEFAULT 'Operator Sekolah',
                email VARCHAR(150) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until DATETIME NULL DEFAULT NULL,
                last_login_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS konten (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kunci VARCHAR(80) NOT NULL UNIQUE,
                kelompok VARCHAR(50) NOT NULL,
                judul_bagian VARCHAR(120) NOT NULL,
                data_json TEXT NOT NULL,
                riwayat_json TEXT NULL,
                ringkasan_perubahan VARCHAR(150) NULL DEFAULT 'Data awal',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS kategori (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                urutan INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS artikel (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                judul VARCHAR(200) NOT NULL,
                slug VARCHAR(220) NOT NULL UNIQUE,
                ringkasan VARCHAR(350) NOT NULL,
                isi TEXT NOT NULL,
                gambar_sampul VARCHAR(255) NULL DEFAULT NULL,
                alt_gambar VARCHAR(200) NULL DEFAULT NULL,
                kategori_id INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draf',
                published_at DATETIME NULL DEFAULT NULL,
                penulis VARCHAR(100) NOT NULL DEFAULT 'Admin',
                meta_description VARCHAR(200) NULL DEFAULT NULL,
                menit_baca INTEGER NOT NULL DEFAULT 3,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (kategori_id) REFERENCES kategori(id)
            )",
            "CREATE TABLE IF NOT EXISTS pendaftar (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nomor_registrasi VARCHAR(30) NOT NULL UNIQUE,
                token_bukti CHAR(32) NOT NULL UNIQUE,
                program VARCHAR(20) NOT NULL,
                jalur VARCHAR(20) NOT NULL DEFAULT 'reguler',
                kelas_tujuan VARCHAR(30) NOT NULL,
                status_siswa VARCHAR(20) NOT NULL,
                nama_lengkap VARCHAR(100) NOT NULL,
                nik CHAR(16) NOT NULL,
                nisn VARCHAR(10) NULL DEFAULT NULL,
                tempat_lahir VARCHAR(50) NOT NULL,
                tanggal_lahir DATE NOT NULL,
                jenis_kelamin VARCHAR(15) NOT NULL,
                agama VARCHAR(25) NOT NULL,
                alamat_lengkap VARCHAR(255) NOT NULL,
                nomor_wa VARCHAR(20) NOT NULL,
                email VARCHAR(150) NULL DEFAULT NULL,
                jenjang_terakhir VARCHAR(50) NOT NULL,
                sekolah_asal VARCHAR(100) NOT NULL,
                tahun_keluar CHAR(4) NOT NULL,
                provinsi VARCHAR(100) NULL DEFAULT NULL,
                kota VARCHAR(100) NULL DEFAULT NULL,
                kecamatan VARCHAR(100) NULL DEFAULT NULL,
                kelurahan VARCHAR(100) NULL DEFAULT NULL,
                kode_pos CHAR(5) NULL DEFAULT NULL,
                alamat_wali VARCHAR(400) NULL DEFAULT NULL,
                nama_ortu VARCHAR(100) NULL DEFAULT NULL,
                wa_ortu VARCHAR(20) NULL DEFAULT NULL,
                nama_wali VARCHAR(100) NULL DEFAULT NULL,
                wa_wali VARCHAR(20) NULL DEFAULT NULL,
                nama_ayah VARCHAR(100) NULL DEFAULT NULL,
                pekerjaan_ayah VARCHAR(100) NULL DEFAULT NULL,
                nama_ibu VARCHAR(100) NULL DEFAULT NULL,
                pekerjaan_ibu VARCHAR(100) NULL DEFAULT NULL,
                minat_ekskul VARCHAR(255) NULL DEFAULT NULL,
                catatan_operator VARCHAR(500) NULL DEFAULT NULL,
                persetujuan_pdp INTEGER NOT NULL DEFAULT 1,
                status_pendaftaran VARCHAR(30) NOT NULL DEFAULT 'baru',
                status_tidak_melanjutkan_at DATETIME NULL DEFAULT NULL,
                email_lembaga_terkirim INTEGER NOT NULL DEFAULT 0,
                email_pendaftar_terkirim INTEGER NOT NULL DEFAULT 0,
                email_error_log VARCHAR(255) NULL DEFAULT NULL,
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS berkas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pendaftar_id INTEGER NOT NULL,
                jenis_berkas VARCHAR(40) NOT NULL,
                label_berkas VARCHAR(80) NOT NULL,
                nama_file_acak VARCHAR(120) NOT NULL,
                nama_asli VARCHAR(200) NOT NULL,
                ukuran_byte INTEGER NOT NULL,
                mime_type VARCHAR(80) NOT NULL,
                status_periksa VARCHAR(20) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS cerita_alumni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama VARCHAR(100) NOT NULL,
                tahun_lulus VARCHAR(4) NULL DEFAULT NULL,
                kegiatan VARCHAR(20) NOT NULL,
                tempat VARCHAR(150) NULL DEFAULT NULL,
                kontak VARCHAR(150) NOT NULL,
                cerita TEXT NOT NULL,
                foto VARCHAR(255) NULL DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'menunggu',
                unggulan INTEGER NOT NULL DEFAULT 0,
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS log_aktivitas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                jenis VARCHAR(40) NOT NULL,
                pesan VARCHAR(255) NOT NULL,
                referensi VARCHAR(100) NULL DEFAULT NULL,
                aktor VARCHAR(100) NOT NULL DEFAULT 'Operator',
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $q) {
            $pdo->exec($q);
        }
    }

    public static function getDefaultKontenSections(): array
    {
        // Teks bertanda [ ... ] wajib diganti operator dengan data resmi sekolah (Area Admin > Konten Situs)
        $section = static fn(string $kunci, string $kelompok, string $judul, array $data): array => [
            'kunci' => $kunci,
            'kelompok' => $kelompok,
            'judul_bagian' => $judul,
            'ringkasan_perubahan' => 'Data awal',
            'updated_at' => '2026-11-21 08:00:00',
            'data' => $data,
            'riwayat' => [],
        ];
        $foto = static fn(string $judul, string $label, bool $unggulan = false): array => ['judul' => $judul, 'label' => $label, 'foto' => '', 'tautan' => '', 'unggulan' => $unggulan];
        $ekskul = static fn(string $nama, string $kategori, string $deskripsi, bool $wajib = false): array => [
            'nama' => $nama, 'kategori' => $kategori, 'deskripsi' => $deskripsi,
            'jadwal' => '[Hari, jam]', 'pembina' => '[Nama pembina]', 'foto' => '', 'wajib' => $wajib,
        ];
        $guru = static fn(string $nama, string $peran, string $ringkasan = '', bool $beranda = false): array => [
            'nama' => $nama, 'peran' => $peran, 'ringkasan' => $ringkasan, 'foto' => '', 'tampil_beranda' => $beranda,
        ];

        return [
            $section('beranda_hero', 'Beranda', 'Hero', [
                'judul' => 'Membina Masa Depan Mandiri Melalui',
                'kata_berganti' => 'Kelas Inovatif, Desain Grafis, Kebun Hidroponik',
                'subjudul' => 'SMA Swadaya membentuk kebiasaan, karakter mandiri, dan keterampilan nyata siswa melalui pembelajaran kelas inovatif, studio desain grafis modern, serta kebun hidroponik produktif.',
                'tombol_utama' => 'Daftar Sekarang',
                'tombol_kedua' => 'Eksplor Profil',
                'foto' => '',
                'alt_foto' => 'Ilustrasi dua siswa SMA berseragam melompat gembira',
            ]),
            $section('beranda_banner', 'Beranda', 'Info PPDB', [
                'tahun_ajaran' => '2026/2027',
                'batas_tanggal' => '[tanggal]',
                'kuota' => '160',
                'aktif' => true,
            ]),
            $section('beranda_galeri', 'Beranda', 'Galeri kegiatan', [
                'items' => [
                    $foto('Dokumentasi Panen Raya Hidroponik', 'Green Campus'),
                    $foto('Pameran Karya Desain Grafis', 'Karya Vokasi', true),
                    $foto('Robotika & Coding Club', 'Ekstrakurikuler'),
                    $foto('Praktikum Laboratorium Sains', 'Pembelajaran'),
                    $foto('Proyek P5 Kurikulum Merdeka', 'Projek Siswa'),
                ],
            ]),
            $section('profil_sambutan', 'Beranda', 'Sambutan kepala sekolah', [
                'nama_ketua' => 'Yon Aidil, S.Si',
                'jabatan' => 'Kepala SMA Swadaya',
                'salam' => 'Salam sejahtera, saya Kepala Sekolah SMA Swadaya.',
                'kutipan' => '“Setiap anak punya potensi. Tugas kami adalah memberi ruang, kesempatan, dan bimbingan agar potensi itu tumbuh.”',
                'isi_sambutan' => 'Selamat datang di SMA Swadaya. Kami berkomitmen mendidik generasi muda yang berdaya saing melalui kurikulum berbasis praktik nyata, pembentukan karakter mandiri, serta lingkungan belajar yang inklusif dan menyenangkan.',
                'isi_lanjutan' => '[Paragraf lanjutan sambutan kepala sekolah]',
                'foto_ketua' => '',
            ]),
            $section('profil_tentang_legalitas', 'Profil', 'Identitas sekolah', [
                'hero_judul' => 'Sekolah yang membentuk siswa mandiri lewat praktik nyata',
                'hero_subjudul' => 'Kenali sejarah, visi, para pendidik, dan fasilitas yang mendukung belajar siswa SMA Swadaya setiap hari.',
                'akreditasi' => '[Akreditasi]',
                'kurikulum' => 'Kurikulum Merdeka',
                'tahun_berdiri' => '[Tahun]',
                'tentang_judul' => 'Belajar di kelas, studio, dan kebun',
                'tentang_paragraf_1' => 'SMA Swadaya Bandung memadukan pembelajaran Kurikulum Merdeka dengan praktik langsung. Siswa tidak hanya memahami teori, tetapi juga merancang karya di studio desain grafis, merawat kebun hidroponik, dan bereksperimen di laboratorium sains.',
                'tentang_paragraf_2' => '[Sejarah singkat berdirinya SMA Swadaya]',
                'motto' => 'Unggul, Berkarakter & Berprestasi',
                'foto_gedung' => '',
            ]),
            $section('profil_visi_misi', 'Profil', 'Visi & misi', [
                'visi' => 'Menjadi sekolah yang melahirkan generasi mandiri, berkarakter, dan siap berkarya melalui pembelajaran berbasis praktik nyata.',
                'misi' => [
                    'Menyelenggarakan pembelajaran Kurikulum Merdeka yang aktif dan berbasis proyek.',
                    'Membekali siswa keterampilan nyata di bidang desain, teknologi, dan agrobisnis.',
                    'Membentuk kebiasaan disiplin, jujur, dan mandiri dalam keseharian sekolah.',
                    'Menciptakan lingkungan belajar yang inklusif, aman, dan menyenangkan.',
                ],
            ]),
            $section('data_tim_testimoni', 'Profil', 'Guru & tenaga pendidik', [
                'tampilkan_tim_tanpa_foto' => true,
                'tim' => [
                    $guru('Yon Aidil, S.Si', 'Kepala Sekolah', 'Lulusan Universitas Padjadjaran Jurusan Pendidikan S1. Selain mengajar, beliau senang berinteraksi dengan anak-anak.', true),
                    $guru('Ahmad Fauzi, S.Sn., M.Ds.', 'Instruktur Desain Grafis & Multimedia', '[Ringkasan latar belakang pendidikan dan pengalaman mengajar]', true),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                    $guru('[Nama guru]', '[Mata pelajaran]'),
                ],
            ]),
            $section('profil_fasilitas', 'Profil', 'Fasilitas', [
                'judul_seksi' => 'Ruang belajar untuk praktik nyata',
                'items' => [
                    ['nama' => 'Studio Desain Grafis', 'deskripsi' => 'Komputer desain dan perangkat cetak untuk karya siswa', 'foto' => ''],
                    ['nama' => 'Greenhouse Hidroponik', 'deskripsi' => 'Kebun NFT yang dirawat mandiri oleh siswa', 'foto' => ''],
                    ['nama' => 'Laboratorium Sains Terpadu', 'deskripsi' => 'Praktikum IPA dengan alat yang lengkap', 'foto' => ''],
                    ['nama' => 'Lab Robotika & Coding', 'deskripsi' => 'Ruang praktik robotika dan pemrograman', 'foto' => ''],
                ],
            ]),
            $section('data_ekskul', 'Ekstrakurikuler', 'Daftar ekstrakurikuler', [
                'hero_judul' => 'Temukan minat, asah keterampilan',
                'hero_subjudul' => 'Dari robotika sampai hidroponik, setiap siswa bisa memilih kegiatan yang sesuai minatnya dan belajar langsung dari praktik.',
                'items' => [
                    $ekskul('Robotika & Coding Club', 'Teknologi', 'Merakit robot sederhana dan belajar pemrograman, dari kode blok sampai Python.'),
                    $ekskul('Desain Grafis & Percetakan', 'Seni & Desain', 'Mengolah ide menjadi poster, logo, dan kemasan yang siap dicetak.'),
                    $ekskul('Agrobisnis Hidroponik', 'Sains & Lingkungan', 'Menanam sayur di greenhouse sekolah dan belajar memasarkan hasil panen.'),
                    $ekskul('Karya Ilmiah Remaja', 'Sains & Lingkungan', 'Meneliti masalah di sekitar sekolah dan menyusun laporan ilmiah sederhana.'),
                    $ekskul('Pramuka', 'Karakter', 'Melatih kemandirian, kerja sama, dan kepemimpinan melalui kegiatan kepramukaan.', true),
                    $ekskul('Jurnalistik & Konten Kreatif', 'Seni & Desain', 'Meliput kegiatan sekolah dan mengelola konten media sosial sekolah.'),
                    $ekskul('Futsal', 'Olahraga', 'Latihan teknik dasar, strategi tim, dan persiapan turnamen antarsekolah.'),
                    $ekskul('Paduan Suara', 'Seni & Desain', 'Berlatih vokal dan tampil di upacara serta acara sekolah.'),
                ],
            ]),
            $section('data_kontak', 'Semua halaman', 'Kontak & footer', [
                'nama_lembaga' => 'SMA Swadaya',
                'tagline' => 'Unggul, Berkarakter & Berprestasi',
                'npsn' => '[NPSN]',
                'alamat_lengkap' => 'Jl. Terusan Pasirkoja No. 181, Jamika, Kec. Bojongloa Kaler, Kota Bandung, Jawa Barat 40231',
                'email' => 'support@sma.swadaya.sch.id',
                'whatsapp_tampil' => '0857-0486-4168',
                'whatsapp_nomor' => '6285704864168',
                'google_maps_url' => 'https://maps.google.com/?q=Jl.+Terusan+Pasirkoja+No.+181+Jamika+Bojongloa+Kaler+Bandung',
                'google_maps_embed' => 'https://www.google.com/maps?q=Jl.+Terusan+Pasirkoja+No.+181,+Jamika,+Bojongloa+Kaler,+Kota+Bandung&output=embed',
                'portal_siswa_url' => 'https://ujiansma.swadaya.sch.id',
                'instagram_url' => '',
                'youtube_url' => '',
                'facebook_url' => '',
            ]),
            $section('data_seo', 'Semua halaman', 'SEO tiap halaman', [
                'beranda_title' => 'SMA Swadaya Bandung · Unggul, Berkarakter & Berprestasi',
                'beranda_desc' => 'SMA Swadaya Bandung: kelas inovatif, studio desain grafis, dan kebun hidroponik. PPDB 2026/2027 dibuka, daftar online dari HP.',
                'profil_title' => 'Profil SMA Swadaya Bandung',
                'profil_desc' => 'Identitas, visi-misi, sambutan kepala sekolah, guru, dan fasilitas SMA Swadaya Bandung.',
                'berita_title' => 'Berita & Artikel · SMA Swadaya',
                'berita_desc' => 'Kabar kegiatan, prestasi siswa, pengumuman, dan artikel dari SMA Swadaya Bandung.',
                'ekskul_title' => 'Ekstrakurikuler · SMA Swadaya',
                'ekskul_desc' => 'Robotika, desain grafis, hidroponik, pramuka, dan kegiatan ekstrakurikuler lain di SMA Swadaya.',
                'alumni_title' => 'Cerita Alumni · SMA Swadaya',
                'alumni_desc' => 'Kisah lulusan SMA Swadaya yang melanjutkan studi dan berkarier di berbagai bidang.',
                'pendaftaran_title' => 'Formulir PPDB · SMA Swadaya',
                'pendaftaran_desc' => 'Daftar siswa baru SMA Swadaya secara online: empat langkah singkat, nomor pendaftaran langsung diterima.',
            ]),
        ];
    }

    private static function seedData(PDO $pdo): void
    {
        // 1. Operator default
        $stmtOp = $pdo->prepare("INSERT INTO operator (nama, email, password_hash, last_login_at) VALUES (?, ?, ?, ?)");
        $stmtOp->execute([
            'Admin',
            'support@sma.swadaya.sch.id',
            password_hash('rahasia123', PASSWORD_DEFAULT),
            null,
        ]);

        // 2. Konten situs
        $stmtKonten = $pdo->prepare(
            "INSERT INTO konten (kunci, kelompok, judul_bagian, data_json, riwayat_json, ringkasan_perubahan, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach (self::getDefaultKontenSections() as $sec) {
            $stmtKonten->execute([
                $sec['kunci'],
                $sec['kelompok'],
                $sec['judul_bagian'],
                json_encode($sec['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($sec['riwayat'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $sec['ringkasan_perubahan'],
                $sec['updated_at'],
            ]);
        }

        // 3. Kategori berita
        $categories = [
            ['id' => 1, 'nama' => 'Kegiatan', 'slug' => 'kegiatan', 'urutan' => 1],
            ['id' => 2, 'nama' => 'Artikel', 'slug' => 'artikel', 'urutan' => 2],
            ['id' => 3, 'nama' => 'Pengumuman', 'slug' => 'pengumuman', 'urutan' => 3],
            ['id' => 4, 'nama' => 'Prestasi', 'slug' => 'prestasi', 'urutan' => 4],
        ];
        $stmtKat = $pdo->prepare("INSERT INTO kategori (id, nama, slug, urutan) VALUES (?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $stmtKat->execute([$cat['id'], $cat['nama'], $cat['slug'], $cat['urutan']]);
        }

        // 4. Berita contoh dari desain. Isi lengkap bertanda [ ... ] diganti lewat Area Admin.
        $isi = static fn(string $ringkasan): string => '<p>' . $ringkasan . '</p>' . "\n" . '<p>[Isi berita lengkap]</p>';
        $articles = [
            ['Siswa Desain Grafis SMA Swadaya Menangkan Kompetisi Desain Kemasan', 'siswa-desain-grafis-sma-swadaya-menangkan-kompetisi-desain-kemasan', 'Kemasan ramah lingkungan rancangan tim siswa kelas XII meraih Juara 1 dalam Festival Desain Pelajar Bandung.', 4, '2026-10-14 08:00:00', 4],
            ['Persiapan Panen Raya Sayur Hidroponik Angkatan 2026', 'persiapan-panen-raya-sayur-hidroponik-angkatan-2026', 'Greenhouse Swadaya memakai sistem nutrient film technique (NFT) yang dirawat mandiri oleh siswa.', 1, '2026-10-12 08:00:00', 3],
            ['Kunjungan Industri Siswa Desain Grafis ke Percetakan Mitra', 'kunjungan-industri-siswa-desain-grafis-ke-percetakan-mitra', 'Siswa mempelajari operasional mesin cetak offset, persiapan prepress, dan pengendalian mutu kemasan cetak.', 1, '2026-09-28 08:00:00', 3],
            ['Tips Memilih Ekstrakurikuler Sesuai Minat Anak', 'tips-memilih-ekstrakurikuler-sesuai-minat-anak', 'Panduan singkat bagi orang tua untuk membantu anak menemukan kegiatan yang cocok di kelas X.', 2, '2026-09-20 08:00:00', 3],
            ['Jadwal dan Alur PPDB SMA Swadaya Tahun Ajaran 2026/2027', 'jadwal-dan-alur-ppdb-sma-swadaya-tahun-ajaran-2026-2027', 'Informasi gelombang pendaftaran, berkas yang perlu disiapkan, dan cara mendaftar secara online.', 3, '2026-09-15 08:00:00', 3],
            ['Projek P5: Siswa Kelas X Mengolah Sampah Kantin Menjadi Kompos', 'projek-p5-siswa-kelas-x-mengolah-sampah-kantin-menjadi-kompos', 'Projek penguatan profil pelajar Pancasila bertema gaya hidup berkelanjutan.', 1, '2026-09-05 08:00:00', 3],
            ['Mengapa Belajar Coding Penting Sejak SMA?', 'mengapa-belajar-coding-penting-sejak-sma', 'Manfaat berpikir komputasional untuk mata pelajaran lain dan kehidupan sehari-hari.', 2, '2026-08-28 08:00:00', 3],
        ];

        $stmtArt = $pdo->prepare(
            "INSERT INTO artikel (judul, slug, ringkasan, isi, gambar_sampul, alt_gambar, kategori_id, status, published_at, penulis, meta_description, menit_baca, updated_at)
             VALUES (?, ?, ?, ?, '', ?, ?, 'terbit', ?, 'Admin', ?, ?, ?)"
        );
        foreach ($articles as [$judul, $slug, $ringkasan, $kategoriId, $terbit, $menit]) {
            $stmtArt->execute([$judul, $slug, $ringkasan, $isi($ringkasan), $judul, $kategoriId, $terbit, $ringkasan, $menit, $terbit]);
        }

        // 5. Cerita alumni dari desain (kutipan masih placeholder sampai alumni mengirim ceritanya)
        $stmtAlumni = $pdo->prepare(
            "INSERT INTO cerita_alumni (nama, tahun_lulus, kegiatan, tempat, kontak, cerita, status, unggulan, created_at, updated_at)
             VALUES (?, NULL, ?, ?, '-', ?, 'disetujui', ?, ?, ?)"
        );
        $stmtAlumni->execute(['Reza Pratama, S.Ds.', 'karier', 'Senior UI/UX Designer & Co-Founder Arsa Studio', '[Kutipan cerita alumni tentang pengalaman di SMA Swadaya]', 1, '2026-11-21 08:00:00', '2026-11-21 08:00:00']);
        $stmtAlumni->execute(['Sarah Amanda, S.Ked.', 'studi', 'Dokter Muda & Mahasiswa Profesi FK Unpad', '[Kutipan cerita alumni]', 0, '2026-11-21 08:00:00', '2026-11-21 08:00:00']);
    }
}
