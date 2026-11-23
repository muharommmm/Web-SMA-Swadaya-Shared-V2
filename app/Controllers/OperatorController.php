<?php

namespace App\Controllers;

use App\Core\ContentRepository;
use App\Core\Database;
use App\Core\Helpers;
use App\Core\View;
use App\Services\PdfGenerator;
use App\Services\SmtpMailer;
use App\Services\UploadService;
use PDO;

class OperatorController
{
    private const SESSION_TIMEOUT_SECONDS = 1800; // 30 menit sesuai PRD
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Memastikan operator sudah login dan sesi belum kedaluwarsa (30 menit idle)
     */
    private function requireAuth(): array
    {
        Helpers::startSecureSession();

        if (empty($_SESSION['operator_id'])) {
            $this->redirect('/operator/login');
            return [];
        }

        $now = time();
        if (!empty($_SESSION['operator_last_active']) && ($now - (int) $_SESSION['operator_last_active']) > self::SESSION_TIMEOUT_SECONDS) {
            unset($_SESSION['operator_id'], $_SESSION['operator_nama'], $_SESSION['operator_email'], $_SESSION['operator_last_active']);
            $this->setFlash('error', 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 30 menit. Silakan masuk kembali.');
            $this->redirect('/operator/login');
            return [];
        }

        $_SESSION['operator_last_active'] = $now;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['operator_id']]);
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$operator) {
            unset($_SESSION['operator_id']);
            $this->redirect('/operator/login');
            return [];
        }

        // Jalankan pemeliharaan ringan: terbitkan artikel terjadwal yang sudah waktunya & bersihkan retensi pendaftar > 30 hari
        $this->publishScheduledArticles($pdo);
        $this->cleanupExpiredRegistrants($pdo);

        return $operator;
    }

    private function setFlash(string $type, string $message): void
    {
        Helpers::startSecureSession();
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function consumeFlash(): ?array
    {
        Helpers::startSecureSession();
        if (!empty($_SESSION['_flash'])) {
            $flash = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            return $flash;
        }
        return null;
    }

    private function redirect(string $path): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path, true, 303);
        }
        if (PHP_SAPI === 'cli') {
            echo 'REDIRECT:' . $path;
            return;
        }
        exit;
    }

    private function logActivity(string $jenis, string $pesan, ?string $referensi = null, string $aktor = 'Operator'): void
    {
        $pdo = Database::connection();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $stmt = $pdo->prepare('INSERT INTO log_aktivitas (jenis, pesan, referensi, aktor, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$jenis, $pesan, $referensi, $aktor, $ip, date('Y-m-d H:i:s')]);
    }

    private function publishScheduledArticles(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE artikel SET status = 'terbit', updated_at = ? WHERE status = 'terjadwal' AND published_at IS NOT NULL AND published_at <= ?")
            ->execute([$now, $now]);
    }

    /**
     * Kebijakan Retensi Data Pribadi (UU PDP / PRD Bab 6.3):
     * Menghapus otomatis data & berkas calon siswa berstatus 'tidak_diterima' > 30 hari.
     */
    public function cleanupExpiredRegistrants(PDO $pdo): void
    {
        $days = max(1, (int) \App\Core\Env::get('RETENTION_DAYS_INACTIVE', '30'));
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stmt = $pdo->prepare("SELECT id, nomor_registrasi FROM pendaftar WHERE status_pendaftaran = 'tidak_diterima' AND status_tidak_melanjutkan_at IS NOT NULL AND status_tidak_melanjutkan_at <= ?");
        $stmt->execute([$cutoff]);
        $expiredList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiredList as $exp) {
            $this->deleteRegistrantAndFiles($pdo, (int) $exp['id'], (string) $exp['nomor_registrasi'], 'Sistem (Retensi 30 Hari UU PDP)');
        }
    }

    private function deleteRegistrantAndFiles(PDO $pdo, int $id, string $nomorRegistrasi, string $aktor): void
    {
        // Folder tahun mengikuti tanggal unggah (bukan tahun ajaran), jadi hapus berdasarkan path yang tercatat
        $stmt = $pdo->prepare('SELECT nama_file_acak FROM berkas WHERE pendaftar_id = ?');
        $stmt->execute([$id]);
        $dirs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rel) {
            $full = UploadService::fullPath((string) $rel);
            if (is_file($full)) {
                @unlink($full);
            }
            $dirs[dirname($full)] = true;
        }
        foreach (array_keys($dirs) as $dir) {
            @rmdir($dir); // hanya terhapus bila sudah kosong
        }

        $pdo->prepare('DELETE FROM berkas WHERE pendaftar_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM pendaftar WHERE id = ?')->execute([$id]);
        $this->logActivity('sistem', "Data & berkas pendaftar {$nomorRegistrasi} dihapus permanen", $nomorRegistrasi, $aktor);
    }

    /**
     * GET /operator/login
     */
    public function loginForm(?string $errorMsg = null, string $oldEmail = ''): void
    {
        Helpers::startSecureSession();
        if (!empty($_SESSION['operator_id'])) {
            $this->redirect('/operator');
            return;
        }

        $flash = $this->consumeFlash();
        if (!$errorMsg && $flash && ($flash['type'] ?? '') === 'error') {
            $errorMsg = $flash['message'];
        }

        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
        }

        View::renderPartial('operator/login', [
            'error' => $errorMsg,
            'oldEmail' => $oldEmail,
        ]);
    }

    /**
     * POST /operator/login
     */
    public function loginSubmit(): void
    {
        Helpers::startSecureSession();

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->loginForm('Sesi formulir telah kedaluwarsa. Silakan coba lagi.');
            return;
        }

        // Turnstile diperiksa sebelum menyentuh database: bot tidak bisa menebak kata sandi
        if (!\App\Services\Turnstile::verify((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
            http_response_code(422);
            $this->loginForm('Verifikasi keamanan belum berhasil. Centang kotak verifikasi lalu coba lagi.', trim((string) ($_POST['email'] ?? '')));
            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            http_response_code(422);
            $this->loginForm('Email dan kata sandi wajib diisi.', $email);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM operator WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($operator && !empty($operator['locked_until'])) {
            $lockTs = strtotime((string) $operator['locked_until']);
            if ($lockTs && $lockTs > time()) {
                $sisaMenit = max(1, (int) ceil(($lockTs - time()) / 60));
                http_response_code(429);
                $this->loginForm("Akun dikunci sementara karena 5 kali percobaan gagal. Silakan coba lagi dalam {$sisaMenit} menit.", $email);
                return;
            }
        }

        if (!$operator || !password_verify($password, (string) $operator['password_hash'])) {
            if ($operator) {
                $attempts = ((int) $operator['failed_attempts']) + 1;
                $lockedUntil = null;
                if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                    $lockedUntil = date('Y-m-d H:i:s', time() + (self::LOCKOUT_MINUTES * 60));
                }
                $pdo->prepare('UPDATE operator SET failed_attempts = ?, locked_until = ?, updated_at = ? WHERE id = ?')
                    ->execute([$attempts, $lockedUntil, date('Y-m-d H:i:s'), (int) $operator['id']]);
            }
            http_response_code(401);
            $this->loginForm('Email atau kata sandi tidak sesuai.', $email);
            return;
        }

        // Login berhasil: reset percobaan gagal & regenerasi ID sesi
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $nowStr = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE operator SET failed_attempts = 0, locked_until = NULL, last_login_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$nowStr, $nowStr, (int) $operator['id']]);

        $_SESSION['operator_id'] = (int) $operator['id'];
        $_SESSION['operator_nama'] = (string) $operator['nama'];
        $_SESSION['operator_email'] = (string) $operator['email'];
        $_SESSION['operator_last_active'] = time();

        $this->logActivity('login', 'Operator masuk dari ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Peramban Web'), null, (string) $operator['nama']);
        $this->redirect('/operator');
    }

    /**
     * GET|POST /operator/logout
     */
    public function logout(): void
    {
        Helpers::startSecureSession();
        unset($_SESSION['operator_id'], $_SESSION['operator_nama'], $_SESSION['operator_email'], $_SESSION['operator_last_active']);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
        $this->redirect('/operator/login');
    }

    public const STATUS = [
        'baru' => ['Baru', 'Belum diperiksa panitia'],
        'perlu_perbaikan' => ['Perlu perbaikan', 'Minta orang tua mengunggah ulang berkas'],
        'terverifikasi' => ['Terverifikasi', 'Berkas lengkap dan sesuai'],
        'diterima' => ['Diterima', 'Calon siswa diterima di SMA Swadaya'],
        'tidak_diterima' => ['Tidak diterima', 'Sertakan alasan pada catatan'],
    ];

    /**
     * Jumlah pendaftar per status (semua status selalu ada, nilai 0 bila kosong)
     */
    private function statusCounts(PDO $pdo): array
    {
        $counts = array_fill_keys(array_keys(self::STATUS), 0);
        foreach ($pdo->query('SELECT status_pendaftaran, COUNT(*) AS n FROM pendaftar GROUP BY status_pendaftaran')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts[$r['status_pendaftaran']] = (int) $r['n'];
        }
        return $counts;
    }

    /**
     * "Hari ini, 08.42" / "Kemarin, 19.03" / "18 Nov, 14.11"
     */
    public static function waktuSingkat(?string $datetime): string
    {
        $ts = $datetime ? strtotime($datetime) : false;
        if (!$ts) {
            return '-';
        }
        $hari = date('Y-m-d', $ts);
        $label = $hari === date('Y-m-d') ? 'Hari ini' : ($hari === date('Y-m-d', strtotime('-1 day')) ? 'Kemarin' : Helpers::formatTanggal($datetime, true));
        return $label . ', ' . date('H.i', $ts);
    }

    /**
     * GET /operator
     */
    public function dashboard(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        $pdo = Database::connection();
        $counts = $this->statusCounts($pdo);
        $total = array_sum($counts);
        $banner = ContentRepository::get('beranda_banner');
        $kuota = max(0, (int) ($banner['kuota'] ?? 0));

        // Pendaftar per minggu, 8 minggu terakhir (minggu dimulai Senin)
        $awal = strtotime('monday this week', strtotime('-7 weeks'));
        $minggu = array_fill(0, 8, 0);
        $stmt = $pdo->prepare('SELECT created_at FROM pendaftar WHERE created_at >= ?');
        $stmt->execute([date('Y-m-d 00:00:00', $awal)]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $i = (int) floor((strtotime((string) $c) - $awal) / (7 * 86400));
            if ($i >= 0 && $i < 8) {
                $minggu[$i]++;
            }
        }
        $mingguIni = $minggu[7];

        $terbaru = $pdo->query('SELECT id, nomor_registrasi, nama_lengkap, sekolah_asal, jalur, status_pendaftaran, created_at FROM pendaftar ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

        // Perlu tindakan
        $tertua = $pdo->query("SELECT created_at FROM pendaftar WHERE status_pendaftaran = 'baru' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $alumniMenunggu = (int) $pdo->query("SELECT COUNT(*) FROM cerita_alumni WHERE status = 'menunggu'")->fetchColumn();
        $draf = (int) $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'draf'")->fetchColumn();
        $drafTerakhir = $pdo->query("SELECT updated_at FROM artikel WHERE status = 'draf' ORDER BY updated_at DESC LIMIT 1")->fetchColumn();
        $emailGagal = $pdo->query('SELECT id, nomor_registrasi FROM pendaftar WHERE email_lembaga_terkirim = 0 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

        $hour = (int) date('G');
        $sapaan = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
        $hariMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        View::renderOperator('operator/dashboard', [
            'pageTitle' => 'Dashboard · Admin SMA Swadaya',
            'activeMenu' => 'dashboard',
            'operatorNama' => $operator['nama'],
            'tanggalHariIni' => $hariMap[(int) date('w')] . ', ' . Helpers::tanggalPanjang(date('Y-m-d')),
            'sapaan' => $sapaan,
            'banner' => $banner,
            'counts' => $counts,
            'total' => $total,
            'kuota' => $kuota,
            'minggu' => $minggu,
            'mingguAwal' => $awal,
            'mingguIni' => $mingguIni,
            'terbaru' => $terbaru,
            'tertuaBaru' => $tertua ?: null,
            'alumniMenunggu' => $alumniMenunggu,
            'draf' => $draf,
            'drafTerakhir' => $drafTerakhir ?: null,
            'emailGagal' => $emailGagal,
            'flash' => $this->consumeFlash(),
        ]);
    }


    /**
     * POST /operator/kirim-ulang-email
     */
    public function retryEmail(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator');
            return;
        }

        $referensi = trim((string) ($_POST['referensi'] ?? ''));
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM pendaftar WHERE nomor_registrasi = ? LIMIT 1');
        $stmt->execute([$referensi]);
        $pendaftar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendaftar) {
            // Bila berasal dari contoh log seed, bersihkan log email_gagal tersebut
            $pdo->prepare("DELETE FROM log_aktivitas WHERE jenis = 'email_gagal' AND referensi = ?")->execute([$referensi]);
            $this->logActivity('sistem', "Pengiriman ulang email notifikasi {$referensi} diproses", $referensi, $operator['nama']);
            $this->setFlash('success', "Pengiriman ulang email untuk {$referensi} telah dijadwalkan ulang.");
            $this->redirect('/operator');
            return;
        }

        $stmtBerkas = $pdo->prepare('SELECT * FROM berkas WHERE pendaftar_id = ? ORDER BY id ASC');
        $stmtBerkas->execute([(int) $pendaftar['id']]);
        $berkasRows = $stmtBerkas->fetchAll(PDO::FETCH_ASSOC);

        foreach ($berkasRows as &$bf) {
            $bf['full_path'] = UploadService::fullPath((string) $bf['nama_file_acak']);
        }
        unset($bf);

        $pendaftar = $this->pendaftarUntukDokumen($pendaftar);

        $buktiUrl = Helpers::fullUrl('/pendaftaran/bukti/' . $pendaftar['token_bukti']);
        $pdfBinary = PdfGenerator::generateBuktiPendaftaran($pendaftar, $berkasRows, $buktiUrl);

        $sent = SmtpMailer::sendRegistrationEmails($pendaftar, $berkasRows, $buktiUrl, $pdfBinary);
        if ($sent) {
            $pdo->prepare('UPDATE pendaftar SET email_lembaga_terkirim = 1, email_pendaftar_terkirim = ? WHERE id = ?')
                ->execute([!empty($pendaftar['email']) ? 1 : 0, (int) $pendaftar['id']]);
            $pdo->prepare("DELETE FROM log_aktivitas WHERE jenis = 'email_gagal' AND referensi = ?")
                ->execute([$referensi]);
            $this->logActivity('pendaftaran', "Email notifikasi {$referensi} berhasil dikirim ulang", $referensi, $operator['nama']);
            $this->setFlash('success', "Email notifikasi untuk {$referensi} berhasil dikirim ulang.");
        } else {
            $this->setFlash('error', "Gagal mengirim email {$referensi}. Pastikan pengaturan SMTP di .env sudah aktif.");
        }

        $this->redirect('/operator/pendaftar/' . (int) $pendaftar['id']);
    }

    /**
     * GET /operator/konten
     */
    public function konten(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        $activeKey = trim((string) ($_GET['bagian'] ?? 'beranda_hero'));
        $allSections = ContentRepository::allSections();

        $sectionsByGroup = [];
        $currentSection = null;

        foreach ($allSections as $sec) {
            $grp = $sec['kelompok'] ?? 'Umum';
            $sectionsByGroup[$grp][] = $sec;
            if ($sec['kunci'] === $activeKey) {
                $currentSection = $sec;
            }
        }

        if (!$currentSection && !empty($allSections)) {
            $currentSection = $allSections[0];
        }

        View::renderOperator('operator/konten', [
            'pageTitle' => 'Konten Situs · Admin SMA Swadaya',
            'activeMenu' => 'konten',
            'operatorNama' => $operator['nama'],
            'sectionsByGroup' => $sectionsByGroup,
            'currentSection' => $currentSection,
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * POST /operator/konten
     */
    public function kontenSave(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/konten');
            return;
        }

        $kunci = trim((string) ($_POST['kunci'] ?? 'beranda_hero'));
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM konten WHERE kunci = ? LIMIT 1');
        $stmt->execute([$kunci]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->setFlash('error', 'Bagian konten tidak ditemukan.');
            $this->redirect('/operator/konten');
            return;
        }

        $currentData = json_decode((string) $row['data_json'], true);
        $currentData = ContentRepository::withDefaults($kunci, is_array($currentData) ? $currentData : []);

        $newData = $currentData;

        if (isset($_POST['json_raw']) && is_string($_POST['json_raw'])) {
            $decodedRaw = json_decode($_POST['json_raw'], true);
            if (!is_array($decodedRaw)) {
                $this->setFlash('error', 'Format JSON daftar item tidak valid. Periksa tanda kutip atau koma.');
                $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                return;
            }
            $newData = $decodedRaw;
        } elseif (isset($_POST['fields']) && is_array($_POST['fields'])) {
            $newData = $this->mergeKontenFields($currentData, $_POST['fields']);
        }

        // Tangani daftar baris teks bersarang (mis. syarat pendaftaran & pilihan kelas pada data_program)
        if (isset($_POST['fields_lines']) && is_array($_POST['fields_lines'])) {
            foreach ($_POST['fields_lines'] as $fieldKey => $subArr) {
                if (!is_array($subArr)) {
                    continue;
                }
                foreach ($subArr as $subKey => $leafArr) {
                    if (!is_array($leafArr)) {
                        continue;
                    }
                    foreach ($leafArr as $leafKey => $rawLines) {
                        $lines = preg_split('/\r\n|\r|\n/', (string) $rawLines) ?: [];
                        $cleanLines = array_values(array_filter(
                            array_map('trim', $lines),
                            static fn(string $line): bool => $line !== ''
                        ));
                        if (!isset($newData[$fieldKey]) || !is_array($newData[$fieldKey])) {
                            $newData[$fieldKey] = [];
                        }
                        if (!isset($newData[$fieldKey][$subKey]) || !is_array($newData[$fieldKey][$subKey])) {
                            $newData[$fieldKey][$subKey] = [];
                        }
                        $newData[$fieldKey][$subKey][$leafKey] = $cleanLines;
                    }
                }
            }
        }

        // 1. Tangani penghapusan & unggahan foto Hero Beranda
        if (($_POST['hapus_foto_hero'] ?? '0') === '1') {
            $newData['foto'] = '';
            $newData['foto_url'] = '';
        }

        $heroUploadErr = $_FILES['foto_upload']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($heroUploadErr !== UPLOAD_ERR_NO_FILE) {
            if ($heroUploadErr === UPLOAD_ERR_OK && !empty($_FILES['foto_upload']['tmp_name'])) {
                $uploadedUrl = $this->processPublicMediaUpload($_FILES['foto_upload'], 'konten-' . $kunci);
                if ($uploadedUrl) {
                    $newData['foto_url'] = $uploadedUrl;
                    $newData['foto'] = $uploadedUrl;
                } else {
                    $this->setFlash('error', 'Gagal mengunggah foto hero. Pastikan format JPG/PNG/WebP dan ukuran maksimal 5 MB.');
                    $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                    return;
                }
            } else {
                $this->setFlash('error', 'Gagal mengunggah foto hero. Ukuran file melebihi batas atau unggahan terputus.');
                $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                return;
            }
        }

        // 2. Tangani penghapusan & unggahan foto tingkat atas (mis. foto_gedung, foto_ketua)
        if (isset($_POST['hapus_foto_files']) && is_array($_POST['hapus_foto_files'])) {
            foreach ($_POST['hapus_foto_files'] as $fKey => $flag) {
                if ((string) $flag === '1' && array_key_exists($fKey, $newData)) {
                    $newData[$fKey] = '';
                }
            }
        }

        if (isset($_FILES['foto_files']['error']) && is_array($_FILES['foto_files']['error'])) {
            foreach ($_FILES['foto_files']['error'] as $fKey => $errCode) {
                if ($errCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $singleFile = [
                    'name' => $_FILES['foto_files']['name'][$fKey] ?? '',
                    'type' => $_FILES['foto_files']['type'][$fKey] ?? '',
                    'tmp_name' => $_FILES['foto_files']['tmp_name'][$fKey] ?? '',
                    'error' => $errCode,
                    'size' => $_FILES['foto_files']['size'][$fKey] ?? 0,
                ];
                if ($errCode === UPLOAD_ERR_OK && !empty($singleFile['tmp_name'])) {
                    $uploadedUrl = $this->processPublicMediaUpload($singleFile, "konten-{$kunci}-{$fKey}");
                    if ($uploadedUrl) {
                        $newData[$fKey] = $uploadedUrl;
                    } else {
                        $this->setFlash('error', "Gagal mengunggah foto ({$fKey}). Pastikan format JPG/PNG/WebP dan ukuran maksimal 5 MB.");
                        $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                        return;
                    }
                } else {
                    $this->setFlash('error', "Gagal mengunggah foto ({$fKey}). Ukuran file terlalu besar atau unggahan tidak lengkap.");
                    $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                    return;
                }
            }
        }

        // 3. Tangani penghapusan & unggahan foto item bersarang (mis. fasilitas, program, guru dan staf, testimoni)
        if (isset($_POST['hapus_foto_nested']) && is_array($_POST['hapus_foto_nested'])) {
            foreach ($_POST['hapus_foto_nested'] as $fieldKey => $subArr) {
                if (!is_array($subArr)) {
                    continue;
                }
                foreach ($subArr as $subKey => $leafArr) {
                    if (!is_array($leafArr)) {
                        continue;
                    }
                    foreach ($leafArr as $leafKey => $flag) {
                        if ((string) $flag === '1' && isset($newData[$fieldKey][$subKey]) && is_array($newData[$fieldKey][$subKey])) {
                            $newData[$fieldKey][$subKey][$leafKey] = '';
                        }
                    }
                }
            }
        }

        if (isset($_FILES['foto_nested']['error']) && is_array($_FILES['foto_nested']['error'])) {
            foreach ($_FILES['foto_nested']['error'] as $fieldKey => $subArr) {
                if (!is_array($subArr)) {
                    continue;
                }
                foreach ($subArr as $subKey => $leafArr) {
                    if (!is_array($leafArr)) {
                        continue;
                    }
                    foreach ($leafArr as $leafKey => $errCode) {
                        if ($errCode === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        $singleFile = [
                            'name' => $_FILES['foto_nested']['name'][$fieldKey][$subKey][$leafKey] ?? '',
                            'type' => $_FILES['foto_nested']['type'][$fieldKey][$subKey][$leafKey] ?? '',
                            'tmp_name' => $_FILES['foto_nested']['tmp_name'][$fieldKey][$subKey][$leafKey] ?? '',
                            'error' => $errCode,
                            'size' => $_FILES['foto_nested']['size'][$fieldKey][$subKey][$leafKey] ?? 0,
                        ];
                        if ($errCode === UPLOAD_ERR_OK && !empty($singleFile['tmp_name'])) {
                            $uploadedUrl = $this->processPublicMediaUpload($singleFile, "konten-{$kunci}-{$fieldKey}-{$subKey}");
                            if ($uploadedUrl) {
                                if (!isset($newData[$fieldKey][$subKey]) || !is_array($newData[$fieldKey][$subKey])) {
                                    $newData[$fieldKey][$subKey] = [];
                                }
                                $newData[$fieldKey][$subKey][$leafKey] = $uploadedUrl;
                            } else {
                                $itemNum = ((int) $subKey) + 1;
                                $this->setFlash('error', "Gagal mengunggah foto pada Item #{$itemNum}. Pastikan format JPG/PNG/WebP dan ukuran maksimal 5 MB.");
                                $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                                return;
                            }
                        } else {
                            $itemNum = ((int) $subKey) + 1;
                            $this->setFlash('error', "Gagal mengunggah foto pada Item #{$itemNum}. Ukuran file terlalu besar atau unggahan terputus.");
                            $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
                            return;
                        }
                    }
                }
            }
        }

        $catatanPerubahan = trim((string) ($_POST['ringkasan_perubahan'] ?? ''));
        $ringkasanAktif = $catatanPerubahan !== '' ? $catatanPerubahan : ('Diperbarui oleh ' . $operator['nama']);

        // Simpan versi lama ke riwayat_json (maksimal 5 versi terakhir sesuai PRD)
        $riwayat = json_decode((string) ($row['riwayat_json'] ?? '[]'), true);
        if (!is_array($riwayat)) {
            $riwayat = [];
        }
        array_unshift($riwayat, [
            'waktu' => date('Y-m-d H:i:s'),
            'oleh' => $operator['nama'],
            'ringkasan' => $row['ringkasan_perubahan'] ?: ('Sebelum perubahan ' . ($row['judul_bagian'] ?? $kunci)),
            'data' => $currentData,
        ]);
        $riwayat = array_slice($riwayat, 0, 5);

        $nowStr = date('Y-m-d H:i:s');
        $stmtUp = $pdo->prepare('UPDATE konten SET data_json = ?, riwayat_json = ?, ringkasan_perubahan = ?, updated_at = ? WHERE kunci = ?');
        $stmtUp->execute([
            json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($riwayat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $ringkasanAktif,
            $nowStr,
            $kunci,
        ]);

        ContentRepository::clearCache();
        $this->logActivity('konten', 'Bagian ' . ($row['kelompok'] ?? '') . ' › ' . ($row['judul_bagian'] ?? $kunci) . ' diperbarui', $kunci, $operator['nama']);
        $this->setFlash('success', 'Perubahan konten "' . ($row['judul_bagian'] ?? $kunci) . '" berhasil disimpan dan langsung tayang.');
        $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
    }

    /**
     * Menggabungkan input formulir terstruktur ke struktur JSON asli tanpa merusak tipe array maupun boolean bersarang
     */
    private function mergeKontenFields(array $currentData, array $postedFields): array
    {
        $result = $currentData;

        foreach ($postedFields as $fieldKey => $val) {
            if (isset($_POST['json_fields'][$fieldKey])) {
                $decodedSub = json_decode((string) $val, true);
                if (is_array($decodedSub)) {
                    $result[$fieldKey] = $decodedSub;
                }
            } elseif (is_array($val)) {
                $origGroup = (isset($currentData[$fieldKey]) && is_array($currentData[$fieldKey])) ? $currentData[$fieldKey] : [];
                $result[$fieldKey] = $this->mergeKontenFields($origGroup, $val);
            } elseif (is_bool($currentData[$fieldKey] ?? null)) {
                $result[$fieldKey] = ($val === '1' || $val === 'true' || $val === true);
            } else {
                $result[$fieldKey] = trim((string) $val);
            }
        }

        // Tangani checkbox boolean yang tidak terkirim bila tidak memiliki input hidden
        foreach ($currentData as $ck => $cv) {
            if (is_bool($cv) && !array_key_exists($ck, $postedFields)) {
                $result[$ck] = false;
            }
        }

        return $result;
    }

    /**
     * POST /operator/konten/pulihkan
     */
    public function kontenRestore(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/konten');
            return;
        }

        $kunci = trim((string) ($_POST['kunci'] ?? ''));
        $rawIdx = $_POST['versi_index'] ?? ($_POST['index'] ?? -1);
        $versiIndex = is_numeric($rawIdx) ? (int) $rawIdx : -1;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM konten WHERE kunci = ? LIMIT 1');
        $stmt->execute([$kunci]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->setFlash('error', 'Bagian konten tidak ditemukan.');
            $this->redirect('/operator/konten');
            return;
        }

        $riwayat = json_decode((string) ($row['riwayat_json'] ?? '[]'), true);
        if (!is_array($riwayat) || !isset($riwayat[$versiIndex]['data']) || !is_array($riwayat[$versiIndex]['data'])) {
            $this->setFlash('error', 'Data versi riwayat tidak ditemukan atau belum memiliki snapshot.');
            $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
            return;
        }

        $currentData = json_decode((string) $row['data_json'], true) ?: [];
        $restoredData = $riwayat[$versiIndex]['data'];

        array_unshift($riwayat, [
            'waktu' => date('Y-m-d H:i:s'),
            'oleh' => $operator['nama'],
            'ringkasan' => 'Sebelum pemulihan versi',
            'data' => $currentData,
        ]);
        $riwayat = array_slice($riwayat, 0, 5);

        $nowStr = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE konten SET data_json = ?, riwayat_json = ?, ringkasan_perubahan = ?, updated_at = ? WHERE kunci = ?')
            ->execute([
                json_encode($restoredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($riwayat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Dipulihkan oleh ' . $operator['nama'],
                $nowStr,
                $kunci,
            ]);

        ContentRepository::clearCache();
        $this->logActivity('konten', 'Bagian ' . ($row['kelompok'] ?? '') . ' › ' . ($row['judul_bagian'] ?? $kunci) . ' dipulihkan ke versi sebelumnya', $kunci, $operator['nama']);
        $this->setFlash('success', 'Konten berhasil dipulihkan ke versi terpilih.');
        $this->redirect('/operator/konten?bagian=' . urlencode($kunci));
    }

    /**
     * GET /operator/artikel
     */
    public function artikelIndex(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        $pdo = Database::connection();

        $counts = [
            'all' => (int) $pdo->query('SELECT COUNT(*) FROM artikel')->fetchColumn(),
            'terbit' => (int) $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'terbit'")->fetchColumn(),
            'draf' => (int) $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'draf'")->fetchColumn(),
            'terjadwal' => (int) $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'terjadwal'")->fetchColumn(),
        ];

        $filterStatus = trim((string) ($_GET['status'] ?? ''));
        $filterKategori = trim((string) ($_GET['kategori'] ?? ''));
        $searchQuery = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['halaman'] ?? 1));
        $perPage = 10;

        $where = [];
        $params = [];

        if (in_array($filterStatus, ['terbit', 'draf', 'terjadwal'], true)) {
            $where[] = 'a.status = ?';
            $params[] = $filterStatus;
        }
        if ($filterKategori !== '') {
            $where[] = 'k.slug = ?';
            $params[] = $filterKategori;
        }
        if ($searchQuery !== '') {
            $where[] = 'a.judul LIKE ?';
            $params[] = '%' . $searchQuery . '%';
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM artikel a LEFT JOIN kategori k ON k.id = a.kategori_id {$whereSql}");
        $stmtCount->execute($params);
        $totalFiltered = (int) $stmtCount->fetchColumn();
        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmtList = $pdo->prepare("
            SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
            FROM artikel a
            LEFT JOIN kategori k ON k.id = a.kategori_id
            {$whereSql}
            ORDER BY a.updated_at DESC, a.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtList->execute($params);
        $articles = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $categories = $pdo->query('SELECT * FROM kategori ORDER BY urutan ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

        View::renderOperator('operator/artikel-index', [
            'pageTitle' => 'Kelola Artikel · Admin SMA Swadaya',
            'activeMenu' => 'artikel',
            'operatorNama' => $operator['nama'],
            'counts' => $counts,
            'filterStatus' => $filterStatus,
            'filterKategori' => $filterKategori,
            'searchQuery' => $searchQuery,
            'categories' => $categories,
            'articles' => $articles,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalFiltered' => $totalFiltered,
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * GET /operator/artikel/tulis
     */
    public function artikelForm(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        $pdo = Database::connection();
        $id = (int) ($_GET['id'] ?? 0);
        $article = [];

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM artikel WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $categories = $pdo->query('SELECT * FROM kategori ORDER BY urutan ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

        View::renderOperator('operator/artikel-form', [
            'pageTitle' => (!empty($article['id']) ? 'Ubah Artikel' : 'Tulis Artikel') . ' · Admin SMA Swadaya',
            'activeMenu' => 'artikel',
            'operatorNama' => $operator['nama'],
            'article' => $article,
            'categories' => $categories,
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * POST /operator/artikel/simpan
     */
    public function artikelSave(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/artikel');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $rawSlug = trim((string) ($_POST['slug'] ?? ''));
        $rawIsi = (string) ($_POST['isi'] ?? '');
        $kategoriId = max(1, (int) ($_POST['kategori_id'] ?? 1));
        $status = trim((string) ($_POST['status'] ?? 'draf'));
        if (($_POST['QuickAction'] ?? '') === 'save_draft') {
            $status = 'draf';
        }
        if (!in_array($status, ['draf', 'terbit', 'terjadwal'], true)) {
            $status = 'draf';
        }

        $altGambar = trim((string) ($_POST['alt_gambar'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $publishedAtInput = trim((string) ($_POST['published_at'] ?? ''));

        if ($judul === '') {
            $this->setFlash('error', 'Judul artikel wajib diisi.');
            $this->redirect($id > 0 ? "/operator/artikel/tulis?id={$id}" : '/operator/artikel/tulis');
            return;
        }

        // Buat slug otomatis bila kosong
        $slug = $this->slugify($rawSlug !== '' ? $rawSlug : $judul);
        $pdo = Database::connection();
        $slug = $this->ensureUniqueSlug($pdo, $slug, $id);

        // Sanitasi HTML menggunakan whitelist setara HTML Purifier sesuai PRD
        $cleanIsi = Helpers::sanitizeRichHtml($rawIsi);

        // Hitung estimasi waktu baca & ringkasan otomatis
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($cleanIsi)));
        $wordCount = str_word_count($plainText);
        $menitBaca = max(1, (int) ceil($wordCount / 180));
        $ringkasan = $metaDescription !== '' ? mb_substr($metaDescription, 0, 220) : mb_substr($plainText, 0, 180);

        $nowStr = date('Y-m-d H:i:s');
        $publishedAt = null;
        if ($status === 'terjadwal' && $publishedAtInput !== '') {
            $tsPub = strtotime($publishedAtInput);
            $publishedAt = $tsPub ? date('Y-m-d H:i:s', $tsPub) : $nowStr;
        } elseif ($status === 'terbit') {
            $publishedAt = $nowStr;
        }

        // Cek gambar sampul lama bila edit
        $existingArticle = null;
        if ($id > 0) {
            $stEx = $pdo->prepare('SELECT * FROM artikel WHERE id = ? LIMIT 1');
            $stEx->execute([$id]);
            $existingArticle = $stEx->fetch(PDO::FETCH_ASSOC);
            if ($existingArticle && $status === 'terbit' && !empty($existingArticle['published_at']) && $existingArticle['status'] === 'terbit') {
                $publishedAt = $existingArticle['published_at'];
            }
        }

        $gambarSampul = $existingArticle['gambar_sampul'] ?? null;
        if (($_POST['hapus_gambar_sampul'] ?? '0') === '1') {
            $gambarSampul = null;
        }

        $coverFile = null;
        if (isset($_FILES['gambar_sampul']) && ($_FILES['gambar_sampul']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $coverFile = $_FILES['gambar_sampul'];
        } elseif (isset($_FILES['gambar_sampul_file']) && ($_FILES['gambar_sampul_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $coverFile = $_FILES['gambar_sampul_file'];
        }

        if ($coverFile !== null) {
            if (($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($coverFile['tmp_name'])) {
                $uploadedCover = $this->processPublicMediaUpload($coverFile, 'artikel-' . $slug);
                if ($uploadedCover) {
                    $gambarSampul = $uploadedCover;
                } else {
                    $this->setFlash('error', 'Gagal mengunggah gambar sampul. Pastikan format JPG/PNG/WebP dan ukuran maksimal 5 MB.');
                    $this->redirect($id > 0 ? "/operator/artikel/tulis?id={$id}" : '/operator/artikel/tulis');
                    return;
                }
            } else {
                $this->setFlash('error', 'Gagal mengunggah gambar sampul. Ukuran file melebihi batas atau unggahan terputus.');
                $this->redirect($id > 0 ? "/operator/artikel/tulis?id={$id}" : '/operator/artikel/tulis');
                return;
            }
        }

        if ($id > 0 && $existingArticle) {
            $stmtUp = $pdo->prepare('
                UPDATE artikel
                SET kategori_id = ?, judul = ?, slug = ?, ringkasan = ?, isi = ?,
                    gambar_sampul = ?, alt_gambar = ?, meta_description = ?,
                    status = ?, menit_baca = ?, published_at = ?, updated_at = ?
                WHERE id = ?
            ');
            $stmtUp->execute([
                $kategoriId, $judul, $slug, $ringkasan, $cleanIsi,
                $gambarSampul, $altGambar, $metaDescription,
                $status, $menitBaca, $publishedAt, $nowStr,
                $id,
            ]);
            $this->logActivity('artikel', "Artikel “{$judul}” diperbarui ({$status})", $slug, $operator['nama']);
            $this->setFlash('success', "Artikel “{$judul}” berhasil diperbarui.");
        } else {
            $stmtIns = $pdo->prepare('
                INSERT INTO artikel (
                    kategori_id, judul, slug, ringkasan, isi,
                    gambar_sampul, alt_gambar, meta_description,
                    penulis, status, menit_baca,
                    published_at, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmtIns->execute([
                $kategoriId, $judul, $slug, $ringkasan, $cleanIsi,
                $gambarSampul, $altGambar, $metaDescription,
                $operator['nama'], $status, $menitBaca,
                $publishedAt, $nowStr, $nowStr,
            ]);
            $this->logActivity('artikel', "Artikel baru “{$judul}” disimpan ({$status})", $slug, $operator['nama']);
            $this->setFlash('success', "Artikel “{$judul}” berhasil disimpan.");
        }

        $this->redirect('/operator/artikel');
    }

    /**
     * POST /operator/artikel/status
     */
    public function artikelToggleStatus(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/artikel');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = ($_POST['status'] ?? '') === 'terbit' ? 'terbit' : 'draf';
        $nowStr = date('Y-m-d H:i:s');

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT judul, slug, published_at FROM artikel WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $art = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($art) {
            $pubAt = ($newStatus === 'terbit' && empty($art['published_at'])) ? $nowStr : $art['published_at'];
            $pdo->prepare('UPDATE artikel SET status = ?, published_at = ?, updated_at = ? WHERE id = ?')
                ->execute([$newStatus, $pubAt, $nowStr, $id]);
            $this->logActivity('artikel', "Status artikel “{$art['judul']}” diubah menjadi {$newStatus}", $art['slug'], $operator['nama']);
            $this->setFlash('success', "Status artikel “{$art['judul']}” diubah menjadi {$newStatus}.");
        }

        $this->redirect('/operator/artikel');
    }

    /**
     * POST /operator/artikel/hapus
     */
    public function artikelDelete(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/artikel');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT judul, slug FROM artikel WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $art = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($art) {
            $pdo->prepare('DELETE FROM artikel WHERE id = ?')->execute([$id]);
            $this->logActivity('artikel', "Artikel “{$art['judul']}” dihapus", $art['slug'], $operator['nama']);
            $this->setFlash('success', "Artikel “{$art['judul']}” telah dihapus.");
        }

        $this->redirect('/operator/artikel');
    }

    /**
     * GET /operator/akun
     */
    public function akun(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        View::renderOperator('operator/akun', [
            'pageTitle' => 'Pengaturan · Admin SMA Swadaya',
            'activeMenu' => 'akun',
            'operatorNama' => $operator['nama'],
            'operator' => $operator,
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * POST /operator/akun
     */
    public function akunSave(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/akun');
            return;
        }

        $nama = trim((string) ($_POST['nama'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $passwordLama = (string) ($_POST['password_lama'] ?? '');
        $passwordBaru = (string) ($_POST['password_baru'] ?? '');
        $passwordKonfirmasi = (string) ($_POST['password_konfirmasi'] ?? '');

        if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', 'Nama dan alamat email yang valid wajib diisi.');
            $this->redirect('/operator/akun');
            return;
        }

        $pdo = Database::connection();
        $newHash = (string) $operator['password_hash'];

        if ($passwordBaru !== '' || strtolower($email) !== strtolower((string) $operator['email'])) {
            if ($passwordLama === '' || !password_verify($passwordLama, (string) $operator['password_hash'])) {
                $this->setFlash('error', 'Kata sandi saat ini tidak sesuai.');
                $this->redirect('/operator/akun');
                return;
            }
        }

        if ($passwordBaru !== '') {
            if (strlen($passwordBaru) < 8) {
                $this->setFlash('error', 'Kata sandi baru minimal harus 8 karakter.');
                $this->redirect('/operator/akun');
                return;
            }
            if ($passwordBaru !== $passwordKonfirmasi) {
                $this->setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
                $this->redirect('/operator/akun');
                return;
            }
            $newHash = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $nowStr = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE operator SET nama = ?, email = ?, password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([$nama, $email, $newHash, $nowStr, (int) $operator['id']]);

        $_SESSION['operator_nama'] = $nama;
        $_SESSION['operator_email'] = $email;

        $this->logActivity('sistem', 'Pengaturan profil akun operator diperbarui', null, $nama);
        $this->setFlash('success', 'Pengaturan akun operator berhasil diperbarui.');
        $this->redirect('/operator/akun');
    }

    /**
     * POST /operator/pendaftar/hapus
     */
    public function hapusPendaftar(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }

        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid.');
            $this->redirect('/operator/pendaftar');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT nomor_registrasi FROM pendaftar WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $nomorRegistrasi = (string) $stmt->fetchColumn();

        if ($nomorRegistrasi !== '') {
            $this->deleteRegistrantAndFiles($pdo, $id, $nomorRegistrasi, $operator['nama']);
            $this->setFlash('success', "Data dan seluruh berkas pendaftar {$nomorRegistrasi} telah dihapus permanen.");
        }

        $this->redirect('/operator/pendaftar');
    }


    // ==================== PENDAFTAR PPDB ====================

    /**
     * Berkas wajib per jalur (sama dengan UploadService::validateUploads)
     */
    private static function jumlahBerkasWajib(string $jalur): int
    {
        return $jalur === 'prestasi' ? 5 : 4;
    }

    /**
     * Filter daftar pendaftar dari query string: [WHERE sql, params, nilai filter]
     */
    private function filterPendaftar(): array
    {
        $f = [
            'status' => (string) ($_GET['status'] ?? ''),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'jalur' => (string) ($_GET['jalur'] ?? ''),
            'urut' => (string) ($_GET['urut'] ?? 'terbaru'),
        ];
        $where = ['1=1'];
        $params = [];
        if (isset(self::STATUS[$f['status']])) {
            $where[] = 'p.status_pendaftaran = ?';
            $params[] = $f['status'];
        } else {
            $f['status'] = '';
        }
        if (isset(PendaftaranController::JALUR[$f['jalur']])) {
            $where[] = 'p.jalur = ?';
            $params[] = $f['jalur'];
        } else {
            $f['jalur'] = '';
        }
        if ($f['q'] !== '') {
            $where[] = '(p.nama_lengkap LIKE ? OR p.nomor_registrasi LIKE ? OR p.sekolah_asal LIKE ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        return [implode(' AND ', $where), $params, $f];
    }

    /**
     * GET /operator/pendaftar
     */
    public function pendaftarIndex(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $pdo = Database::connection();
        [$whereSql, $params, $filter] = $this->filterPendaftar();
        $order = ['terbaru' => 'p.id DESC', 'terlama' => 'p.id ASC', 'nama' => 'p.nama_lengkap ASC'][$filter['urut']] ?? 'p.id DESC';

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pendaftar p WHERE {$whereSql}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

        $stmt = $pdo->prepare("SELECT p.id, p.nomor_registrasi, p.nama_lengkap, p.sekolah_asal, p.jalur, p.status_pendaftaran, p.created_at,
                (SELECT COUNT(*) FROM berkas b WHERE b.pendaftar_id = p.id) AS jumlah_berkas
            FROM pendaftar p WHERE {$whereSql} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage));
        $stmt->execute($params);

        $banner = ContentRepository::get('beranda_banner');
        View::renderOperator('operator/pendaftar', [
            'pageTitle' => 'Pendaftar PPDB · Admin SMA Swadaya',
            'activeMenu' => 'pendaftar',
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'counts' => $this->statusCounts($pdo),
            'filter' => $filter,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'tahunAjaran' => $banner['tahun_ajaran'] ?? '',
            'kuota' => (int) ($banner['kuota'] ?? 0),
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * GET /operator/pendaftar/ekspor: CSV (dibuka Excel) sesuai filter yang sedang aktif
     */
    public function pendaftarEkspor(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $pdo = Database::connection();
        [$whereSql, $params] = $this->filterPendaftar();
        $stmt = $pdo->prepare("SELECT p.* FROM pendaftar p WHERE {$whereSql} ORDER BY p.id ASC");
        $stmt->execute($params);

        $this->logActivity('pendaftaran', 'Data pendaftar diekspor ke Excel', null, $operator['nama']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pendaftar-ppdb-' . date('Ymd-Hi') . '.csv"');
        header('Cache-Control: private, no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        $kolom = [
            'nomor_registrasi' => 'Nomor pendaftaran', 'created_at' => 'Masuk', 'status_pendaftaran' => 'Status', 'jalur' => 'Jalur',
            'nama_lengkap' => 'Nama lengkap', 'nik' => 'NIK', 'nisn' => 'NISN', 'tempat_lahir' => 'Tempat lahir', 'tanggal_lahir' => 'Tanggal lahir',
            'jenis_kelamin' => 'L/P', 'agama' => 'Agama', 'sekolah_asal' => 'Asal sekolah', 'tahun_keluar' => 'Tahun lulus',
            'alamat' => 'Alamat', 'nama_ayah' => 'Nama ayah', 'pekerjaan_ayah' => 'Pekerjaan ayah', 'nama_ibu' => 'Nama ibu',
            'pekerjaan_ibu' => 'Pekerjaan ibu', 'nomor_wa' => 'WhatsApp', 'email' => 'Email', 'minat_ekskul' => 'Minat ekskul',
            'catatan_operator' => 'Catatan panitia',
        ];
        fputcsv($out, array_values($kolom), ';');
        while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $p['alamat'] = Helpers::alamatPendaftar($p);
            $p['status_pendaftaran'] = self::STATUS[$p['status_pendaftaran']][0] ?? $p['status_pendaftaran'];
            $p['jalur'] = PendaftaranController::JALUR[$p['jalur']] ?? $p['jalur'];
            $baris = [];
            foreach (array_keys($kolom) as $k) {
                $v = (string) ($p[$k] ?? '');
                // NIK/NISN sebagai teks agar Excel tidak membulatkan; nilai berawalan rumus dinetralkan
                $baris[] = in_array($k, ['nik', 'nisn', 'nomor_wa'], true) && $v !== '' ? "'" . $v : (preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v);
            }
            fputcsv($out, $baris, ';');
        }
        fclose($out);
    }

    private function findPendaftar(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM pendaftar WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        return $p ?: null;
    }

    /**
     * Data pendaftar dengan alias yang dipakai PdfGenerator & SmtpMailer
     */
    private function pendaftarUntukDokumen(array $p): array
    {
        $p['no_registrasi'] = $p['nomor_registrasi'];
        $p['alamat'] = Helpers::alamatPendaftar($p);
        $p['no_wa'] = $p['nomor_wa'];
        $p['tahun_lulus'] = $p['tahun_keluar'];
        $p['tahun_ajaran'] = ContentRepository::get('beranda_banner')['tahun_ajaran'] ?? '';
        return $p;
    }

    /**
     * Nomor WhatsApp format internasional (08xx -> 628xx)
     */
    private static function waInternasional(string $nomor): string
    {
        return (string) preg_replace('/^0/', '62', (string) preg_replace('/\D+/', '', $nomor));
    }

    /**
     * GET /operator/pendaftar/{id}
     */
    public function pendaftarDetail(int $id): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $pdo = Database::connection();
        $p = $this->findPendaftar($pdo, $id);
        if ($p === null) {
            http_response_code(404);
            $this->setFlash('error', 'Pendaftar tidak ditemukan atau sudah dihapus.');
            $this->redirect('/operator/pendaftar');
            return;
        }

        $stmtB = $pdo->prepare('SELECT * FROM berkas WHERE pendaftar_id = ? ORDER BY id ASC');
        $stmtB->execute([$id]);
        $berkas = $stmtB->fetchAll(PDO::FETCH_ASSOC);
        $stmtL = $pdo->prepare('SELECT pesan, aktor, created_at FROM log_aktivitas WHERE referensi = ? ORDER BY id DESC LIMIT 20');
        $stmtL->execute([$p['nomor_registrasi']]);

        $aktif = (int) ($_GET['berkas'] ?? 0);
        $berkasAktif = null;
        foreach ($berkas as $b) {
            if ((int) $b['id'] === $aktif || $berkasAktif === null) {
                $berkasAktif = $b;
            }
            if ((int) $b['id'] === $aktif) {
                break;
            }
        }

        View::renderOperator('operator/pendaftar-detail', [
            'pageTitle' => $p['nama_lengkap'] . ' · Pendaftar PPDB',
            'activeMenu' => 'pendaftar',
            'p' => $p,
            'alamat' => Helpers::alamatPendaftar($p),
            'berkas' => $berkas,
            'berkasAktif' => $berkasAktif,
            'riwayat' => $stmtL->fetchAll(PDO::FETCH_ASSOC),
            'waOrtu' => self::waInternasional((string) $p['nomor_wa']),
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * POST /operator/pendaftar/{id}/status
     */
    public function pendaftarStatus(int $id): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $back = '/operator/pendaftar/' . $id;
        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
            $this->redirect($back);
            return;
        }
        $pdo = Database::connection();
        $p = $this->findPendaftar($pdo, $id);
        $status = (string) ($_POST['status'] ?? '');
        if ($p === null || !isset(self::STATUS[$status])) {
            $this->setFlash('error', 'Status tidak valid.');
            $this->redirect($p ? $back : '/operator/pendaftar');
            return;
        }
        $catatan = mb_substr(trim((string) ($_POST['catatan'] ?? '')), 0, 500);

        $pdo->prepare('UPDATE pendaftar SET status_pendaftaran = ?, catatan_operator = ?, status_tidak_melanjutkan_at = ? WHERE id = ?')
            ->execute([$status, $catatan !== '' ? $catatan : null, $status === 'tidak_diterima' ? date('Y-m-d H:i:s') : null, $id]);
        $label = self::STATUS[$status][0];
        $this->logActivity('pendaftaran', 'Status diubah ke ' . $label . ($catatan !== '' ? ': ' . mb_substr($catatan, 0, 120) : ''), $p['nomor_registrasi'], $operator['nama']);

        // WhatsApp tidak bisa dikirim otomatis dari server: siapkan tautan pesan untuk dibuka admin
        $_SESSION['_flash'] = ['type' => 'success', 'message' => "Status {$p['nama_lengkap']} disimpan: {$label}."];
        $wa = self::waInternasional((string) $p['nomor_wa']);
        if (!empty($_POST['kirim_wa']) && $wa !== '') {
            $pesan = "Halo Bapak/Ibu, kami dari panitia PPDB SMA Swadaya.\nStatus pendaftaran {$p['nama_lengkap']} ({$p['nomor_registrasi']}): *{$label}*."
                . ($catatan !== '' ? "\nCatatan: {$catatan}" : '') . "\nTerima kasih.";
            $_SESSION['_flash']['link'] = 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan);
        }
        $this->redirect($back);
    }

    /**
     * POST /operator/pendaftar/{id}/berkas: tandai satu berkas Sesuai / Perlu perbaikan
     */
    public function pendaftarBerkasPeriksa(int $id): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $berkasId = (int) ($_POST['berkas_id'] ?? 0);
        $back = '/operator/pendaftar/' . $id . '?berkas=' . $berkasId;
        $hasil = (string) ($_POST['hasil'] ?? '');
        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null) || !in_array($hasil, ['sesuai', 'perbaikan'], true)) {
            $this->setFlash('error', 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
            $this->redirect($back);
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT b.label_berkas, p.nomor_registrasi FROM berkas b JOIN pendaftar p ON p.id = b.pendaftar_id WHERE b.id = ? AND b.pendaftar_id = ?');
        $stmt->execute([$berkasId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare('UPDATE berkas SET status_periksa = ? WHERE id = ?')->execute([$hasil, $berkasId]);
            $this->logActivity('pendaftaran', $row['label_berkas'] . ($hasil === 'sesuai' ? ' sesuai' : ' perlu perbaikan'), $row['nomor_registrasi'], $operator['nama']);
        }
        $this->redirect($back);
    }

    /**
     * GET /operator/berkas/{id}: buka berkas pendaftar (hanya admin; berkas disimpan di luar public_html)
     */
    public function berkasFile(int $berkasId): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM berkas WHERE id = ? LIMIT 1');
        $stmt->execute([$berkasId]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        $path = $b ? UploadService::fullPath((string) $b['nama_file_acak']) : '';
        if (!$b || !is_file($path)) {
            http_response_code(404);
            echo 'Berkas tidak ditemukan.';
            return;
        }
        $mime = in_array($b['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true) ? $b['mime_type'] : 'application/octet-stream';
        $nama = preg_replace('/[^A-Za-z0-9._\-]/', '_', (string) $b['nama_asli']);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . (isset($_GET['unduh']) ? 'attachment' : 'inline') . '; filename="' . $nama . '"');
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    /**
     * GET /operator/pendaftar/{id}/cetak: PDF bukti pendaftaran (tanpa batas masa berlaku token publik)
     */
    public function pendaftarCetak(int $id): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $pdo = Database::connection();
        $p = $this->findPendaftar($pdo, $id);
        if ($p === null) {
            $this->redirect('/operator/pendaftar');
            return;
        }
        $stmtB = $pdo->prepare('SELECT * FROM berkas WHERE pendaftar_id = ? ORDER BY id ASC');
        $stmtB->execute([$id]);
        $berkas = $stmtB->fetchAll(PDO::FETCH_ASSOC);
        foreach ($berkas as &$b) {
            $b['ukuran_kb'] = max(1, (int) round(((int) $b['ukuran_byte']) / 1024));
        }
        unset($b);
        $pdf = PdfGenerator::generateBuktiPendaftaran($this->pendaftarUntukDokumen($p), $berkas, Helpers::fullUrl('/pendaftaran/bukti/' . $p['token_bukti']));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Bukti-' . preg_replace('/[^A-Za-z0-9\-]/', '', $p['nomor_registrasi']) . '.pdf"');
        header('Cache-Control: private, no-store');
        echo $pdf;
    }

    // ==================== CERITA ALUMNI ====================

    /**
     * GET /operator/alumni
     */
    public function alumniIndex(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $pdo = Database::connection();
        $tab = (string) ($_GET['status'] ?? 'menunggu');
        if (!in_array($tab, ['menunggu', 'disetujui', 'ditolak'], true)) {
            $tab = 'menunggu';
        }
        $counts = ['menunggu' => 0, 'disetujui' => 0, 'ditolak' => 0];
        foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM cerita_alumni GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $stmt = $pdo->prepare('SELECT * FROM cerita_alumni WHERE status = ? ORDER BY unggulan DESC, created_at DESC, id DESC');
        $stmt->execute([$tab]);

        View::renderOperator('operator/alumni', [
            'pageTitle' => 'Cerita Alumni · Admin SMA Swadaya',
            'activeMenu' => 'alumni',
            'tab' => $tab,
            'counts' => $counts,
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'flash' => $this->consumeFlash(),
        ]);
    }

    /**
     * POST /operator/alumni/aksi: setujui, tolak, jadikan kisah pilihan, atau hapus
     */
    public function alumniAksi(): void
    {
        $operator = $this->requireAuth();
        if (empty($operator)) {
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $aksi = (string) ($_POST['aksi'] ?? '');
        $kembali = '/operator/alumni?status=' . rawurlencode((string) ($_POST['tab'] ?? 'menunggu'));
        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
            $this->redirect($kembali);
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT nama FROM cerita_alumni WHERE id = ?');
        $stmt->execute([$id]);
        $nama = (string) $stmt->fetchColumn();
        if ($nama === '') {
            $this->redirect($kembali);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $pesan = '';
        if ($aksi === 'setujui' || $aksi === 'tolak') {
            $status = $aksi === 'setujui' ? 'disetujui' : 'ditolak';
            $pdo->prepare('UPDATE cerita_alumni SET status = ?, unggulan = CASE WHEN ? = \'ditolak\' THEN 0 ELSE unggulan END, updated_at = ? WHERE id = ?')->execute([$status, $status, $now, $id]);
            $pesan = $aksi === 'setujui' ? "Cerita {$nama} disetujui dan tampil di halaman Alumni." : "Cerita {$nama} ditolak.";
        } elseif ($aksi === 'unggulan') {
            // Hanya satu kisah pilihan
            $pdo->exec('UPDATE cerita_alumni SET unggulan = 0');
            $pdo->prepare("UPDATE cerita_alumni SET unggulan = 1, status = 'disetujui', updated_at = ? WHERE id = ?")->execute([$now, $id]);
            $pesan = "Cerita {$nama} dijadikan Kisah Pilihan.";
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM cerita_alumni WHERE id = ?')->execute([$id]);
            $pesan = "Cerita {$nama} dihapus permanen.";
        }
        if ($pesan !== '') {
            $this->logActivity('alumni', $pesan, 'alumni-' . $id, $operator['nama']);
            $this->setFlash('success', $pesan);
        }
        $this->redirect($kembali);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = (string) preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : ('artikel-' . date('YmdHis'));
    }

    private function ensureUniqueSlug(PDO $pdo, string $baseSlug, int $excludeId = 0): string
    {
        $slug = $baseSlug;
        $counter = 2;
        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM artikel WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
            if (!$stmt->fetchColumn()) {
                return $slug;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }

    /**
     * Memvalidasi & mengompresi gambar unggahan operator (sampul artikel / foto konten) ke WebP/JPG di public_html/uploads/media/
     */
    private function processPublicMediaUpload(array $file, string $prefix): ?string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_file($tmpName) || $size <= 0 || $size > 5 * 1024 * 1024) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $imgInfo = @getimagesize($tmpName);
        if ($imgInfo === false || ($imgInfo[0] ?? 0) < 10 || ($imgInfo[1] ?? 0) < 10) {
            return null;
        }

        $mediaDir = \App\Core\Helpers::webDir() . '/uploads/media';
        if (!is_dir($mediaDir)) {
            @mkdir($mediaDir, 0755, true);
        }

        $safePrefix = preg_replace('/[^a-z0-9\-]/i', '-', strtolower($prefix));
        $randomSuffix = bin2hex(random_bytes(6));

        if (function_exists('imagecreatefromjpeg')) {
            $src = null;
            if ($mime === 'image/jpeg') {
                $src = @imagecreatefromjpeg($tmpName);
            } elseif ($mime === 'image/png') {
                $src = @imagecreatefrompng($tmpName);
            } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($tmpName);
            }

            if ($src) {
                $origW = imagesx($src);
                $origH = imagesy($src);
                $maxW = 1200; // cukup tajam untuk layar 2x di kolom terlebar (~600px)
                $targetW = $origW;
                $targetH = $origH;

                if ($origW > $maxW) {
                    $targetW = $maxW;
                    $targetH = (int) round($origH * ($maxW / $origW));
                }

                $canvas = imagecreatetruecolor($targetW, $targetH);
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $white);
                imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

                if (function_exists('imagewebp')) {
                    $fileName = "{$safePrefix}-{$randomSuffix}.webp";
                    $destPath = $mediaDir . '/' . $fileName;
                    @imagewebp($canvas, $destPath, 82);
                } else {
                    $fileName = "{$safePrefix}-{$randomSuffix}.jpg";
                    $destPath = $mediaDir . '/' . $fileName;
                    @imagejpeg($canvas, $destPath, 85);
                }

                imagedestroy($src);
                imagedestroy($canvas);

                if (is_file($destPath)) {
                    return '/uploads/media/' . $fileName;
                }
            }
        }

        $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
        $fileName = "{$safePrefix}-{$randomSuffix}.{$ext}";
        $destPath = $mediaDir . '/' . $fileName;
        if (@copy($tmpName, $destPath)) {
            return '/uploads/media/' . $fileName;
        }

        return null;
    }
}
