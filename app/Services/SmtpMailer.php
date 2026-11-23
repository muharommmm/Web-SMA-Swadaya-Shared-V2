<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Helpers;

/**
 * Pengirim Email SMTP Berlampiran (Multipart MIME)
 * Mendukung SMTP TLS/SSL (Port 587 STARTTLS / Port 465 SSL) untuk shared hosting & Gmail App Password,
 * serta mencatat log pengiriman/kegagalan ke storage/logs/mail.log sesuai PRD.
 */
class SmtpMailer
{
    /**
     * Kirim email pendaftaran baru ke sekolah (beserta seluruh lampiran berkas)
     * dan salinan bukti pendaftaran ke email pendaftar (bila diisi).
     */
    public static function sendRegistrationEmails(array $pendaftar, array $berkasSavedPaths, string $buktiUrl, string $pdfBinary): bool
    {
        $toSekolah = Env::get('MAIL_ADMIN_RECIPIENTS', 'support@sma.swadaya.sch.id');
        $progName = 'Jalur ' . (\App\Controllers\PendaftaranController::JALUR[$pendaftar['jalur'] ?? ''] ?? 'Reguler');

        $subjectSekolah = sprintf(
            '[Pendaftar Baru] %s · %s · %s',
            $pendaftar['no_registrasi'] ?? '',
            $pendaftar['nama_lengkap'] ?? '',
            $progName
        );

        $htmlSekolah = self::buildSekolahEmailHtml($pendaftar, $progName, $buktiUrl);

        // Siapkan lampiran untuk email sekolah: Bukti PDF + seluruh berkas unggahan
        $attachmentsSekolah = [
            [
                'filename' => 'Bukti-Pendaftaran-' . ($pendaftar['no_registrasi'] ?? 'SMA-Swadaya') . '.pdf',
                'mime' => 'application/pdf',
                'content' => $pdfBinary,
            ],
        ];
        foreach ($berkasSavedPaths as $b) {
            if (!empty($b['full_path']) && is_file($b['full_path'])) {
                $origName = $b['nama_asli'] ?? ($b['nama_file_asli'] ?? '');
                $attachmentsSekolah[] = [
                    'filename' => $b['jenis_berkas'] . '_' . basename($origName ?: $b['full_path']),
                    'mime' => $b['mime_type'] ?: 'application/octet-stream',
                    'content' => (string) file_get_contents($b['full_path']),
                ];
            }
        }

        // MAIL_ADMIN_RECIPIENTS boleh berisi beberapa alamat dipisah koma
        $sentSekolah = true;
        foreach (array_filter(array_map('trim', explode(',', (string) $toSekolah))) as $rcpt) {
            $sentSekolah = self::sendMimeMail($rcpt, $subjectSekolah, $htmlSekolah, $attachmentsSekolah) && $sentSekolah;
        }

        // Bila pendaftar mengisi alamat email, kirimkan juga salinan bukti pendaftaran
        if (!empty($pendaftar['email']) && filter_var($pendaftar['email'], FILTER_VALIDATE_EMAIL)) {
            $subjectUser = 'Pendaftaran Anda di SMA Swadaya diterima';
            $htmlUser = self::buildRegistrantEmailHtml($pendaftar, $progName, $buktiUrl);
            $attachmentsUser = [
                [
                    'filename' => 'Bukti-Pendaftaran-' . ($pendaftar['no_registrasi'] ?? 'SMA-Swadaya') . '.pdf',
                    'mime' => 'application/pdf',
                    'content' => $pdfBinary,
                ],
            ];
            self::sendMimeMail($pendaftar['email'], $subjectUser, $htmlUser, $attachmentsUser);
        }

        return $sentSekolah;
    }

    public static function sendMimeMail(string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        $host = trim(Env::get('MAIL_HOST', 'smtp.gmail.com'));
        $port = (int) Env::get('MAIL_PORT', '587');
        $user = trim(Env::get('MAIL_USERNAME', ''));
        $pass = trim(Env::get('MAIL_PASSWORD', ''));
        $encryption = strtolower(trim(Env::get('MAIL_ENCRYPTION', 'tls')));
        $fromAddress = trim(Env::get('MAIL_FROM_ADDRESS', $user ?: 'support@sma.swadaya.sch.id'));
        $fromName = trim(Env::get('MAIL_FROM_NAME', 'SMA Swadaya'));

        // Jika kredensial SMTP belum dikonfigurasi, catat ke storage/logs/mail.log agar tidak gagal diam-diam
        if ($user === '' || $pass === '' || $host === '') {
            self::logMail("SKIP SMTP (kredensial MAIL_USERNAME/MAIL_PASSWORD di .env belum diisi) -> Tujuan: {$to} | Subjek: {$subject} | Lampiran: " . count($attachments) . " file");
            return false;
        }

        $boundary = '=_SMASWADAYA_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . " <{$fromAddress}>",
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . ($_SERVER['HTTP_HOST'] ?? 'sma.swadaya.sch.id') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $body = "This is a multi-part message in MIME format.\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody), 76, "\r\n") . "\r\n";

        foreach ($attachments as $att) {
            $fname = preg_replace('/[^a-zA-Z0-9._\-]/', '_', (string) ($att['filename'] ?? 'berkas.dat'));
            $mime = $att['mime'] ?? 'application/octet-stream';
            $encoded = chunk_split(base64_encode((string) ($att['content'] ?? '')), 76, "\r\n");

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$fname}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$fname}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $encoded . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        try {
            $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);
            $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
            if (!$fp) {
                throw new \RuntimeException("Koneksi SMTP gagal ({$errno}): {$errstr}");
            }
            stream_set_timeout($fp, 12);

            self::expectSmtp($fp, [220]);
            self::sendCmd($fp, 'EHLO sma.swadaya.sch.id', [250]);

            if ($encryption === 'tls') {
                self::sendCmd($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Gagal mengaktifkan enkripsi STARTTLS');
                }
                self::sendCmd($fp, 'EHLO sma.swadaya.sch.id', [250]);
            }

            self::sendCmd($fp, 'AUTH LOGIN', [334]);
            self::sendCmd($fp, base64_encode($user), [334]);
            self::sendCmd($fp, base64_encode($pass), [235]);

            self::sendCmd($fp, "MAIL FROM:<{$fromAddress}>", [250]);
            self::sendCmd($fp, "RCPT TO:<{$to}>", [250, 251]);
            self::sendCmd($fp, 'DATA', [354]);

            // Dot-stuffing sesuai RFC 5321
            $safeData = preg_replace('/^\./m', '..', $rawMessage);
            fwrite($fp, $safeData . "\r\n.\r\n");
            self::expectSmtp($fp, [250]);

            self::sendCmd($fp, 'QUIT', [221, 250]);
            fclose($fp);

            self::logMail("SUKSES SMTP -> Tujuan: {$to} | Subjek: {$subject}");
            return true;
        } catch (\Throwable $e) {
            self::logMail("GAGAL SMTP -> Tujuan: {$to} | Subjek: {$subject} | Pesan: " . $e->getMessage());
            return false;
        }
    }

    private static function sendCmd($fp, string $cmd, array $expectedCodes): string
    {
        fwrite($fp, $cmd . "\r\n");
        return self::expectSmtp($fp, $expectedCodes);
    }

    private static function expectSmtp($fp, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException("Respons SMTP tidak sesuai (diharapkan " . implode('/', $expectedCodes) . ", diterima {$code}): " . trim($response));
        }
        return $response;
    }

    private static function encodeHeader(string $str): string
    {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    public static function logMail(string $message): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** 3201********0001: tampilkan 4 digit awal dan akhir saja */
    public static function maskNik(string $nik): string
    {
        return strlen($nik) > 8 ? substr($nik, 0, 4) . str_repeat('*', strlen($nik) - 8) . substr($nik, -4) : $nik;
    }

    private static function buildSekolahEmailHtml(array $p, string $progName, string $buktiUrl): string
    {
        $e = [Helpers::class, 'e'];
        return '<!DOCTYPE html><html lang="id"><body style="font-family:sans-serif;color:#102A2A;line-height:1.6;">'
            . '<h2 style="color:#10AEAD;margin-bottom:4px;">Pendaftaran Siswa Baru — ' . $e($p['no_registrasi'] ?? '') . '</h2>'
            . '<p style="margin-top:0;color:#4A5F5F;">Berikut ringkasan data pendaftar baru yang diterima melalui website resmi SMA Swadaya:</p>'
            . '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;border-color:#E1E4F4;width:100%;max-width:640px;font-size:14px;">'
            . '<tr><th align="left" style="background:#EEF0FB;width:36%;">Nomor Pendaftaran</th><td><strong>' . $e($p['no_registrasi'] ?? '') . '</strong></td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Jalur</th><td>' . $e($progName) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Nama Lengkap</th><td>' . $e($p['nama_lengkap'] ?? '') . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">NIK / NISN</th><td>' . $e(self::maskNik((string) ($p['nik'] ?? '')) . ' / ' . ($p['nisn'] ?: '-')) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Tempat, Tanggal Lahir</th><td>' . $e(($p['tempat_lahir'] ?? '') . ', ' . ($p['tanggal_lahir'] ?? '')) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Jenis Kelamin / Agama</th><td>' . $e((($p['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki') . ' / ' . ($p['agama'] ?? '')) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Alamat Lengkap</th><td>' . $e($p['alamat'] ?? '') . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Nomor WhatsApp</th><td>' . $e($p['no_wa'] ?? '') . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Email</th><td>' . $e($p['email'] ?: '-') . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Asal Sekolah</th><td>' . $e(($p['sekolah_asal'] ?: '-') . ' (lulus ' . ($p['tahun_lulus'] ?: '-') . ')') . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Ayah</th><td>' . $e(trim(($p['nama_ayah'] ?: '-') . ' ' . (($p['pekerjaan_ayah'] ?? '') !== '' ? '(' . $p['pekerjaan_ayah'] . ')' : ''))) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Ibu</th><td>' . $e(trim(($p['nama_ibu'] ?: '-') . ' ' . (($p['pekerjaan_ibu'] ?? '') !== '' ? '(' . $p['pekerjaan_ibu'] . ')' : ''))) . '</td></tr>'
            . '<tr><th align="left" style="background:#EEF0FB;">Minat Ekskul</th><td>' . $e(($p['minat_ekskul'] ?? '') ?: '-') . '</td></tr>'
            . '</table>'
            . '<p>Seluruh berkas dokumen yang diunggah beserta PDF Bukti Pendaftaran telah dilampirkan langsung pada email ini.</p>'
            . '<p><a href="' . $e($buktiUrl) . '" style="color:#10AEAD;font-weight:bold;">Lihat Halaman Bukti Pendaftaran Online</a></p>'
            . '</body></html>';
    }

    private static function buildRegistrantEmailHtml(array $p, string $progName, string $buktiUrl): string
    {
        $e = [Helpers::class, 'e'];
        $kontak = \App\Core\ContentRepository::get('data_kontak');
        $waNomor = preg_replace('/\D+/', '', (string) ($kontak['whatsapp_nomor'] ?? ''));
        return '<!DOCTYPE html><html lang="id"><body style="font-family:sans-serif;color:#102A2A;line-height:1.6;">'
            . '<h2 style="color:#10AEAD;margin-bottom:4px;">Bukti Pendaftaran SMA Swadaya</h2>'
            . '<p>Halo <strong>' . $e($p['nama_lengkap'] ?? '') . '</strong>,</p>'
            . '<p>Terima kasih telah mendaftar di <strong>SMA Swadaya</strong>. Data dan berkas pendaftaran Anda telah kami terima dengan rincian sebagai berikut:</p>'
            . '<ul>'
            . '<li><strong>Nomor Pendaftaran:</strong> ' . $e($p['no_registrasi'] ?? '') . '</li>'
            . '<li><strong>Jalur Pendaftaran:</strong> ' . $e($progName) . '</li>'
            . '<li><strong>Tanggal Pendaftaran:</strong> ' . $e(Helpers::formatTanggal($p['created_at'] ?? date('Y-m-d H:i:s'))) . '</li>'
            . '</ul>'
            . '<p>Bukti pendaftaran resmi dalam format PDF telah kami lampirkan pada email ini. Anda juga dapat membuka atau mencetak bukti pendaftaran kapan saja melalui tautan berikut:</p>'
            . '<p><a href="' . $e($buktiUrl) . '" style="display:inline-block;padding:12px 22px;background:#10AEAD;color:#FFFFFF;text-decoration:none;border-radius:999px;font-weight:bold;">Buka &amp; Cetak Bukti Pendaftaran</a></p>'
            . '<p style="font-size:13px;color:#4A5F5F;">Langkah berikutnya: operator kami memeriksa kelengkapan berkas Anda lalu menghubungi lewat nomor WhatsApp yang terdaftar.</p>'
            . ($waNomor !== '' ? '<p style="font-size:13px;color:#4A5F5F;">Ada pertanyaan? Hubungi kami di WhatsApp <a href="https://wa.me/' . $e($waNomor) . '" style="color:#10AEAD;">' . $e($waNomor) . '</a>.</p>' : '')
            . '</body></html>';
    }
}
