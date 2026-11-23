<?php

namespace App\Controllers;

use App\Core\ContentRepository;
use App\Core\Database;
use App\Core\Env;
use App\Core\Helpers;
use App\Core\View;
use App\Services\PdfGenerator;
use App\Services\SmtpMailer;
use App\Services\Turnstile;
use App\Services\UploadService;
use PDO;

class PendaftaranController
{
    public const JALUR = ['reguler' => 'Reguler', 'prestasi' => 'Prestasi'];

    /**
     * Pilihan minat ekskul di formulir = nama ekskul di Konten Situs (Ekstrakurikuler)
     */
    public static function pilihanEkskul(): array
    {
        return array_values(array_filter(array_map(
            static fn($e) => trim((string) ($e['nama'] ?? '')),
            (array) (ContentRepository::get('data_ekskul')['items'] ?? [])
        )));
    }

    /**
     * "2026/2027" -> "2627" untuk nomor pendaftaran PPDB-2627-0001
     */
    public static function kodeTahunAjaran(string $tahunAjaran): string
    {
        if (preg_match('/^\d{2}(\d{2})\D+\d{2}(\d{2})$/', trim($tahunAjaran), $m)) {
            return $m[1] . $m[2];
        }
        $y = (int) date('Y');
        return substr((string) $y, 2) . substr((string) ($y + 1), 2);
    }

    public function submit(): void
    {
        Helpers::startSecureSession();
        $publicCtrl = new PublicController();

        $old = [
            'jalur' => strtolower(trim((string) ($_POST['jalur'] ?? 'reguler'))),
            'nama_lengkap' => trim((string) ($_POST['nama_lengkap'] ?? '')),
            'nik' => preg_replace('/\D+/', '', (string) ($_POST['nik'] ?? '')),
            'nisn' => preg_replace('/\D+/', '', (string) ($_POST['nisn'] ?? '')),
            'tempat_lahir' => trim((string) ($_POST['tempat_lahir'] ?? '')),
            'tanggal_lahir' => trim((string) ($_POST['tanggal_lahir'] ?? '')),
            'jenis_kelamin' => strtoupper(trim((string) ($_POST['jenis_kelamin'] ?? ''))),
            'agama' => trim((string) ($_POST['agama'] ?? '')),
            'alamat' => trim((string) ($_POST['alamat'] ?? '')),
            'provinsi' => trim((string) ($_POST['provinsi'] ?? '')),
            'kota' => trim((string) ($_POST['kota'] ?? '')),
            'kecamatan' => trim((string) ($_POST['kecamatan'] ?? '')),
            'kelurahan' => trim((string) ($_POST['kelurahan'] ?? '')),
            'kode_pos' => preg_replace('/\D+/', '', (string) ($_POST['kode_pos'] ?? '')),
            'sekolah_asal' => trim((string) ($_POST['sekolah_asal'] ?? '')),
            'tahun_lulus' => preg_replace('/\D+/', '', (string) ($_POST['tahun_lulus'] ?? '')),
            'nama_ayah' => trim((string) ($_POST['nama_ayah'] ?? '')),
            'pekerjaan_ayah' => trim((string) ($_POST['pekerjaan_ayah'] ?? '')),
            'nama_ibu' => trim((string) ($_POST['nama_ibu'] ?? '')),
            'pekerjaan_ibu' => trim((string) ($_POST['pekerjaan_ibu'] ?? '')),
            'no_wa' => trim((string) ($_POST['no_wa'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            // Hanya pilihan dari daftar ekskul yang disimpan
            'minat_ekskul' => array_values(array_intersect(
                array_map('strval', (array) ($_POST['minat_ekskul'] ?? [])),
                self::pilihanEkskul()
            )),
            'persetujuan_pdp' => !empty($_POST['persetujuan_pdp']) ? '1' : '',
        ];

        $errors = [];

        // 1. Honeypot anti-spam
        if (!empty($_POST['website_url'])) {
            http_response_code(400);
            $errors['form'] = 'Permintaan tidak dapat diproses.';
            $publicCtrl->pendaftaranForm($old, $errors);
            return;
        }

        // 2. Validasi CSRF
        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? $_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $errors['form'] = 'Sesi formulir telah kedaluwarsa. Silakan periksa kembali isian Anda dan kirim ulang.';
            $publicCtrl->pendaftaranForm($old, $errors);
            return;
        }

        // 3. Pembatasan laju (maks. RATE_LIMIT_MAX_REGISTRATIONS_PER_HOUR pendaftaran per IP per jam)
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (!self::checkRateLimit($ipAddress)) {
            http_response_code(429);
            $errors['form'] = 'Batas pendaftaran dari jaringan ini sudah tercapai. Silakan coba lagi 1 jam lagi atau hubungi operator lewat WhatsApp.';
            $publicCtrl->pendaftaranForm($old, $errors);
            return;
        }

        // 4. Verifikasi Cloudflare Turnstile (wajib di produksi)
        if (!Turnstile::verify($ipAddress)) {
            $errors['captcha'] = 'Verifikasi keamanan belum berhasil. Silakan centang ulang kotak verifikasi lalu kirim lagi.';
        }

        // 5. Validasi Kolom Formulir
        if (!isset(self::JALUR[$old['jalur']])) {
            $errors['jalur'] = 'Jalur pendaftaran wajib dipilih (Reguler atau Prestasi).';
        }

        $namaLen = mb_strlen($old['nama_lengkap']);
        if ($namaLen < 3 || $namaLen > 100) {
            $errors['nama_lengkap'] = 'Nama lengkap wajib diisi sesuai akta kelahiran (3–100 huruf).';
        }

        if (!preg_match('/^\d{16}$/', $old['nik'])) {
            $errors['nik'] = 'NIK wajib terdiri dari 16 digit angka.';
        }

        if ($old['nisn'] !== '' && !preg_match('/^\d{10}$/', $old['nisn'])) {
            $errors['nisn'] = 'NISN harus terdiri dari 10 digit angka (atau kosongkan bila belum memiliki).';
        }

        if ($old['tempat_lahir'] === '' || mb_strlen($old['tempat_lahir']) > 50) {
            $errors['tempat_lahir'] = 'Tempat lahir wajib diisi (maks. 50 karakter).';
        }

        $usia = null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['tanggal_lahir'])) {
            $errors['tanggal_lahir'] = 'Tanggal lahir wajib diisi dengan format yang benar.';
        } else {
            try {
                $dob = new \DateTimeImmutable($old['tanggal_lahir']);
                $now = new \DateTimeImmutable('today');
                if ($dob >= $now) {
                    $errors['tanggal_lahir'] = 'Tanggal lahir tidak boleh hari ini atau di masa depan.';
                } else {
                    $usia = $dob->diff($now)->y;
                }
            } catch (\Throwable $e) {
                $errors['tanggal_lahir'] = 'Tanggal lahir tidak valid.';
            }
        }

        if (!in_array($old['jenis_kelamin'], ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = 'Jenis kelamin wajib dipilih.';
        }

        $agamaValid = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
        if (!in_array($old['agama'], $agamaValid, true)) {
            $errors['agama'] = 'Agama wajib dipilih.';
        }

        if (mb_strlen($old['alamat']) < 5 || mb_strlen($old['alamat']) > 255) {
            $errors['alamat'] = 'Alamat jalan wajib diisi, misalnya Jl. Merdeka No. 10 RT 01/RW 02 (maks. 255 karakter).';
        }

        // Wilayah dikirim sebagai kode Kemendagri, nama & kode pos diambil dari data server (bukan dari browser)
        $wilayah = $this->resolveWilayah($old['provinsi'], $old['kota'], $old['kecamatan'], $old['kelurahan']);
        foreach (['provinsi' => 'Provinsi', 'kota' => 'Kota/kabupaten', 'kecamatan' => 'Kecamatan', 'kelurahan' => 'Kelurahan/desa'] as $key => $label) {
            if ($wilayah[$key] === '') {
                $errors[$key] = "{$label} wajib dipilih.";
                break; // tingkat di bawahnya pasti ikut kosong
            }
        }
        $wilayah['kode_pos'] = $old['kode_pos'] !== '' ? $old['kode_pos'] : $wilayah['kode_pos'];
        if ($wilayah['kelurahan'] !== '' && !preg_match('/^\d{5}$/', $wilayah['kode_pos'])) {
            $errors['kode_pos'] = 'Kode pos wajib 5 digit angka.';
        }
        $wilayah['alamat_lengkap'] = $old['alamat'];
        $alamatPendaftar = Helpers::alamatPendaftar($wilayah);

        if ($old['sekolah_asal'] === '' || mb_strlen($old['sekolah_asal']) > 100) {
            $errors['sekolah_asal'] = 'Nama SMP/MTs asal wajib diisi (maks. 100 karakter).';
        }

        $tahun = (int) $old['tahun_lulus'];
        if (!preg_match('/^\d{4}$/', $old['tahun_lulus']) || $tahun < 1970 || $tahun > (int) date('Y') + 1) {
            $errors['tahun_lulus'] = 'Tahun lulus wajib 4 digit, misalnya ' . date('Y') . '.';
        }

        // Orang tua: minimal satu nama (ayah atau ibu); semua kolom maks. 100 karakter
        if ($old['nama_ayah'] === '' && $old['nama_ibu'] === '') {
            $errors['nama_ayah'] = 'Isi nama ayah atau nama ibu (minimal salah satu).';
        }
        foreach (['nama_ayah' => 'Nama ayah', 'pekerjaan_ayah' => 'Pekerjaan ayah', 'nama_ibu' => 'Nama ibu', 'pekerjaan_ibu' => 'Pekerjaan ibu'] as $key => $label) {
            if (mb_strlen($old[$key]) > 100) {
                $errors[$key] = "{$label} maks. 100 karakter.";
            }
        }

        // Format 08xx atau 62xx, 10–14 digit
        if (!preg_match('/^(08|62)\d{8,12}$/', (string) preg_replace('/\D+/', '', $old['no_wa']))) {
            $errors['no_wa'] = 'Nomor WhatsApp orang tua wajib diisi dengan format 08xx atau 62xx (10–14 digit).';
        }

        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format alamat email tidak valid.';
        }

        if ($old['persetujuan_pdp'] !== '1') {
            $errors['persetujuan_pdp'] = 'Anda wajib menyetujui pernyataan kebenaran data dan kebijakan privasi.';
        }

        // 6. Cek duplikasi NIK pada periode pendaftaran berjalan
        $bannerInfo = ContentRepository::get('beranda_banner');
        $tahunAjaran = $bannerInfo['tahun_ajaran'] ?? '2026/2027';
        $pdo = Database::connection();

        if (preg_match('/^\d{16}$/', $old['nik'])) {
            $stmtDup = $pdo->prepare("SELECT nomor_registrasi FROM pendaftar WHERE nik = ? AND status_pendaftaran != 'tidak_diterima' LIMIT 1");
            $stmtDup->execute([$old['nik']]);
            $existingReg = $stmtDup->fetchColumn();
            if ($existingReg) {
                $errors['nik'] = "NIK {$old['nik']} sudah terdaftar pada Tahun Ajaran {$tahunAjaran} (Nomor Registrasi: {$existingReg}). Silakan hubungi operator melalui WhatsApp jika Anda membutuhkan cetak ulang bukti pendaftaran.";
            }
        }

        // 7. Validasi Unggah Berkas
        $uploadResult = UploadService::validateUploads($_FILES, $old['jalur']);
        if (!empty($uploadResult['errors'])) {
            $errors += $uploadResult['errors'];
        }

        if (!empty($errors)) {
            http_response_code(422);
            $publicCtrl->pendaftaranForm($old, $errors);
            return;
        }

        // 8. Nomor pendaftaran unik PPDB-2627-0001 (2 digit akhir tiap tahun dari tahun ajaran 2026/2027)
        $prefix = 'PPDB-' . self::kodeTahunAjaran($tahunAjaran) . '-';

        $pdo->beginTransaction();
        try {
            $stmtLast = $pdo->prepare("SELECT nomor_registrasi FROM pendaftar WHERE nomor_registrasi LIKE ? ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$prefix . '%']);
            $lastNo = (string) $stmtLast->fetchColumn();

            $nextSeq = 1;
            if ($lastNo !== '' && preg_match('/-(\d{4,})$/', $lastNo, $m)) {
                $nextSeq = ((int) $m[1]) + 1;
            }
            $nomorRegistrasi = $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $tokenBukti = bin2hex(random_bytes(16));
            $nowStr = date('Y-m-d H:i:s');
            $minatEkskul = implode(', ', $old['minat_ekskul']);

            $savedFiles = UploadService::storeFiles($uploadResult['valid_files'], $nomorRegistrasi);

            // PPDB hanya untuk kelas 10 siswa baru lulusan SMP/MTs; kolom lama diisi tetap agar laporan lama tetap terbaca
            $stmtInsert = $pdo->prepare("
                INSERT INTO pendaftar (
                    nomor_registrasi, token_bukti, program, jalur, kelas_tujuan, status_siswa,
                    nama_lengkap, nik, nisn, tempat_lahir, tanggal_lahir,
                    jenis_kelamin, agama, alamat_lengkap, nomor_wa, email,
                    jenjang_terakhir, sekolah_asal, tahun_keluar,
                    provinsi, kota, kecamatan, kelurahan, kode_pos,
                    nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu, minat_ekskul, persetujuan_pdp,
                    status_pendaftaran, email_lembaga_terkirim, email_pendaftar_terkirim,
                    ip_address, created_at
                ) VALUES (
                    ?, ?, 'sma', ?, 'Kelas 10', 'baru',
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    'SMP/MTs', ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, 1,
                    'baru', 0, 0,
                    ?, ?
                )
            ");

            $nullable = static fn(string $v): ?string => $v !== '' ? $v : null;
            $stmtInsert->execute([
                $nomorRegistrasi,
                $tokenBukti,
                $old['jalur'],
                $old['nama_lengkap'],
                $old['nik'],
                $nullable($old['nisn']),
                $old['tempat_lahir'],
                $old['tanggal_lahir'],
                $old['jenis_kelamin'],
                $old['agama'],
                $old['alamat'],
                $old['no_wa'],
                $nullable($old['email']),
                $old['sekolah_asal'],
                $old['tahun_lulus'],
                $wilayah['provinsi'],
                $wilayah['kota'],
                $wilayah['kecamatan'],
                $wilayah['kelurahan'],
                $wilayah['kode_pos'],
                $nullable($old['nama_ayah']),
                $nullable($old['pekerjaan_ayah']),
                $nullable($old['nama_ibu']),
                $nullable($old['pekerjaan_ibu']),
                $nullable($minatEkskul),
                $ipAddress,
                $nowStr,
            ]);

            $pendaftarId = (int) $pdo->lastInsertId();

            $stmtBerkas = $pdo->prepare("
                INSERT INTO berkas (pendaftar_id, jenis_berkas, label_berkas, nama_file_acak, nama_asli, ukuran_byte, mime_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($savedFiles as $bf) {
                $stmtBerkas->execute([
                    $pendaftarId,
                    $bf['jenis_berkas'],
                    $bf['label_berkas'],
                    $bf['nama_file_acak'],
                    $bf['nama_asli'],
                    $bf['ukuran_byte'],
                    $bf['mime_type'],
                    $nowStr,
                ]);
            }

            // Catat di log_aktivitas (juga tampil sebagai riwayat di detail pendaftar)
            $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (jenis, pesan, referensi, aktor, ip_address, created_at) VALUES ('pendaftaran', ?, ?, 'Calon Siswa', ?, ?)");
            $stmtLog->execute([
                'Formulir dikirim, ' . count($savedFiles) . ' berkas diunggah',
                $nomorRegistrasi,
                $ipAddress,
                $nowStr,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Catat pengiriman untuk rate limiting IP
        self::recordRateLimitHit($ipAddress);

        // 9. Buat Bukti PDF & Kirim Email SMTP Berlampiran (Dual-Copy)
        $pendaftarData = array_merge($old, $wilayah, [
            'alamat' => $alamatPendaftar,
            'minat_ekskul' => $minatEkskul,
            'id' => $pendaftarId,
            'nomor_registrasi' => $nomorRegistrasi,
            'no_registrasi' => $nomorRegistrasi,
            'token_bukti' => $tokenBukti,
            'tahun_ajaran' => $tahunAjaran,
            'created_at' => $nowStr,
        ]);
        $buktiUrl = Helpers::fullUrl('/pendaftaran/bukti/' . $tokenBukti);
        $pdfBinary = PdfGenerator::generateBuktiPendaftaran($pendaftarData, $savedFiles, $buktiUrl);

        $emailSent = SmtpMailer::sendRegistrationEmails($pendaftarData, $savedFiles, $buktiUrl, $pdfBinary);
        if ($emailSent) {
            $stmtUp = $pdo->prepare("UPDATE pendaftar SET email_lembaga_terkirim = 1, email_pendaftar_terkirim = ? WHERE id = ?");
            $stmtUp->execute([$old['email'] !== '' ? 1 : 0, $pendaftarId]);
        } else {
            $stmtLogFail = $pdo->prepare("INSERT INTO log_aktivitas (jenis, pesan, referensi, aktor, created_at) VALUES ('email_gagal', ?, ?, 'Sistem', ?)");
            $stmtLogFail->execute([
                "Email notifikasi {$nomorRegistrasi} gagal",
                $nomorRegistrasi,
                date('Y-m-d H:i:s'),
            ]);
        }

        // 10. Arahkan ke halaman berhasil (nomor registrasi + tombol cetak bukti)
        $_SESSION['pendaftaran_berhasil'] = ['nomor' => $nomorRegistrasi, 'token' => $tokenBukti, 'nama' => $old['nama_lengkap']];
        $targetPath = '/pendaftaran/berhasil';
        if (!headers_sent()) {
            header('Location: ' . $targetPath, true, 303);
        }
        if (PHP_SAPI === 'cli') {
            echo "REDIRECT:" . $targetPath;
        }
    }

    public function berhasil(): void
    {
        Helpers::startSecureSession();
        $data = $_SESSION['pendaftaran_berhasil'] ?? null;
        if (empty($data['token'])) {
            header('Location: /pendaftaran', true, 303);
            return;
        }

        $konten = (new PublicController())->baseKonten();
        View::render('public/pendaftaran-berhasil', [
            'pageTitle' => 'Pendaftaran Berhasil · SMA Swadaya',
            'metaDesc' => 'Pendaftaran online SMA Swadaya berhasil dikirim.',
            'noIndex' => true,
            'hideFloatingWa' => true,
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'hasil' => $data,
        ]);
    }

    public function bukti(string $token): void
    {
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow');
        }

        $record = $this->findValidRegistrationByToken($token);
        if ($record === null) {
            (new PublicController())->notFound();
            return;
        }

        $rawKonten = ContentRepository::allKonten();
        $konten = [];
        foreach ($rawKonten as $kunci => $item) {
            $konten[$kunci] = $item['data'] ?? [];
        }
        $konten['kontak'] = $konten['data_kontak'] ?? [];

        View::render('public/bukti-pendaftaran', [
            'pageTitle' => 'Bukti Pendaftaran ' . $record['pendaftar']['no_registrasi'] . ' · SMA Swadaya',
            'metaDesc' => 'Lembar bukti pendaftaran resmi siswa baru SMA Swadaya.',
            'noIndex' => true,
            'hideFloatingWa' => true,
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'pendaftar' => $record['pendaftar'],
            'berkasList' => $record['berkas'],
        ]);
    }

    public function buktiPdf(string $token): void
    {
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow');
        }

        $record = $this->findValidRegistrationByToken($token);
        if ($record === null) {
            (new PublicController())->notFound();
            return;
        }

        $p = $record['pendaftar'];
        $buktiUrl = Helpers::fullUrl('/pendaftaran/bukti/' . $token);
        $pdfBinary = PdfGenerator::generateBuktiPendaftaran($p, $record['berkas'], $buktiUrl);
        $filename = 'Bukti-Pendaftaran-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $p['no_registrasi']) . '.pdf';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfBinary));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        echo $pdfBinary;
    }

    public function buktiFoto(string $token): void
    {
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow');
        }

        $record = $this->findValidRegistrationByToken($token);
        if ($record === null) {
            http_response_code(404);
            return;
        }

        foreach ($record['berkas'] as $b) {
            if ($b['jenis_berkas'] === 'pas_foto') {
                $fullPath = UploadService::fullPath((string) $b['nama_file_acak']);
                if (is_file($fullPath)) {
                    if (!headers_sent()) {
                        header('Content-Type: ' . ($b['mime_type'] ?: 'image/jpeg'));
                        header('Content-Length: ' . filesize($fullPath));
                        header('Cache-Control: private, max-age=3600');
                    }
                    readfile($fullPath);
                    return;
                }
            }
        }

        http_response_code(404);
    }

    private function findValidRegistrationByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM pendaftar WHERE token_bukti = ? LIMIT 1");
        $stmt->execute([$token]);
        $pendaftar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendaftar) {
            return null;
        }

        // Periksa masa berlaku token (default 30 hari sejak tanggal daftar)
        $expireDays = max(1, (int) Env::get('BUKTI_TOKEN_EXPIRE_DAYS', '30'));
        $createdTs = strtotime((string) $pendaftar['created_at']);
        if ($createdTs && (time() - $createdTs) > ($expireDays * 86400)) {
            return null;
        }

        $bannerInfo = ContentRepository::get('beranda_banner');
        $tahunAjaran = $bannerInfo['tahun_ajaran'] ?? '2026/2027';

        // Alias agar kompatibel di seluruh template & PDF generator
        $pendaftar['no_registrasi'] = $pendaftar['nomor_registrasi'];
        $pendaftar['alamat'] = Helpers::alamatPendaftar($pendaftar);
        $pendaftar['no_wa'] = $pendaftar['nomor_wa'] ?? '';
        $pendaftar['tahun_lulus'] = ($pendaftar['tahun_keluar'] ?? '') !== '-' ? $pendaftar['tahun_keluar'] : '';
        $pendaftar['sekolah_asal'] = ($pendaftar['sekolah_asal'] ?? '') !== '-' ? $pendaftar['sekolah_asal'] : '';
        $pendaftar['tahun_ajaran'] = $tahunAjaran;

        $stmtBerkas = $pdo->prepare("SELECT * FROM berkas WHERE pendaftar_id = ? ORDER BY id ASC");
        $stmtBerkas->execute([$pendaftar['id']]);
        $berkasRows = $stmtBerkas->fetchAll(PDO::FETCH_ASSOC);

        foreach ($berkasRows as &$b) {
            $b['ukuran_kb'] = max(1, (int) round(((int) ($b['ukuran_byte'] ?? 1024)) / 1024));
            $b['nama_file_asli'] = $b['nama_asli'] ?? '';
            $b['path_file'] = $b['nama_file_acak'] ?? '';
        }
        unset($b);

        return [
            'pendaftar' => $pendaftar,
            'berkas' => $berkasRows,
        ];
    }

    /**
     * Cocokkan kode wilayah dengan data di public_html/assets/wilayah (lihat database/wilayah/build.py).
     * Kode yang tidak cocok dengan induknya menghasilkan nama kosong.
     */
    private function resolveWilayah(string $prov, string $kota, string $kec, string $kel): array
    {
        $hasil = ['provinsi' => '', 'kota' => '', 'kecamatan' => '', 'kelurahan' => '', 'kode_pos' => ''];
        $dir = Helpers::webDir() . '/assets/wilayah/';
        $load = static function (string $name) use ($dir): array {
            $file = $dir . $name . '.json';
            return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        };
        $find = static function (array $list, string $kode): ?array {
            foreach ($list as $row) {
                if (($row[0] ?? null) === $kode) {
                    return $row;
                }
            }
            return null;
        };

        if (!preg_match('/^\d{2}$/', $prov) || !($p = $find($load('provinsi'), $prov))) {
            return $hasil;
        }
        $hasil['provinsi'] = $p[1];
        if (!preg_match('/^' . $prov . '\.\d{2}$/', $kota) || !($k = $find($load($prov), $kota))) {
            return $hasil;
        }
        $hasil['kota'] = $k[1];
        if (!($c = $find($load($kota), $kec))) {
            return $hasil;
        }
        $hasil['kecamatan'] = $c[1];
        if ($d = $find((array) ($c[2] ?? []), $kel)) {
            $hasil['kelurahan'] = $d[1];
            $hasil['kode_pos'] = (string) ($d[2] ?? '');
        }
        return $hasil;
    }

    public static function checkRateLimit(string $ip, string $bucket = 'pendaftaran'): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        $file = dirname(__DIR__, 2) . '/storage/logs/rate_limit_' . $bucket . '.json';
        if (!is_file($file)) {
            return true;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || empty($data[$ip])) {
            return true;
        }
        $cutoff = time() - 3600;
        $recent = array_filter($data[$ip], fn($ts) => $ts >= $cutoff);
        return count($recent) < max(1, (int) Env::get('RATE_LIMIT_MAX_REGISTRATIONS_PER_HOUR', '5'));
    }

    public static function recordRateLimitHit(string $ip, string $bucket = 'pendaftaran'): void
    {
        $file = dirname(__DIR__, 2) . '/storage/logs/rate_limit_' . $bucket . '.json';
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $cutoff = time() - 3600;
        foreach ($data as $k => $list) {
            $data[$k] = array_values(array_filter((array) $list, fn($ts) => $ts >= $cutoff));
            if (empty($data[$k])) {
                unset($data[$k]);
            }
        }
        $data[$ip][] = time();
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
