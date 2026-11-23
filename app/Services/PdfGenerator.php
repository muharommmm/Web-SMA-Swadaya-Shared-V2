<?php

namespace App\Services;

use App\Core\Helpers;

/**
 * Generator PDF A4 Murni (Standar PDF 1.4, 1 Halaman A4) untuk Bukti Pendaftaran
 * Menghasilkan dokumen PDF resmi lengkap dengan Kop Lembaga, Biodata, Daftar Berkas,
 * dan Kode QR Vektor tanpa memerlukan pustaka biner eksternal di shared hosting.
 */
class PdfGenerator
{
    public static function generateBuktiPendaftaran(array $pendaftar, array $berkasList, string $buktiUrl): string
    {
        $kontak = \App\Core\ContentRepository::get('data_kontak');
        $ops = [];

        // Ukuran A4 dalam point: 595.28 x 841.89
        $marginX = 42.0;
        $contentW = 595.28 - ($marginX * 2);

        // 1. Kotak Kop Hijau Tua /ksen di bagian atas
        $ops[] = "0.063 0.682 0.678 rg"; // #10AEAD
        $ops[] = sprintf("%.2f %.2f %.2f %.2f re f", $marginX, 748, $contentW, 56);

        $ops[] = "1 1 1 rg";
        $ops[] = self::textCmd('F2', 15, $marginX + 16, 780, 'SMA SWADAYA');
        $ops[] = self::textCmd('F1', 9.5, $marginX + 16, 765, 'NPSN ' . ($kontak['npsn'] ?? '-') . '  |  ' . ($kontak['alamat_footer'] ?? ''));
        $ops[] = self::textCmd('F1', 9, $marginX + 16, 753, 'Email: ' . ($kontak['email'] ?? 'support@sma.swadaya.sch.id'));

        // 2. Judul Dokumen & Kotak Nomor Registrasi
        $ops[] = "0.04 0.17 0.13 rg"; // #0B2B22
        $ops[] = self::textCmd('F2', 14, $marginX, 720, 'BUKTI PENDAFTARAN SISWA BARU');
        $ops[] = "0.29 0.34 0.32 rg";
        $ops[] = self::textCmd('F1', 9.5, $marginX, 705, 'Tahun Ajaran ' . ($pendaftar['tahun_ajaran'] ?? '2027/2028') . '  Â·  Tanggal Daftar: ' . Helpers::formatTanggal($pendaftar['created_at'] ?? date('Y-m-d H:i:s')));

        // Kotak Nomor Registrasi
        $ops[] = "0.93 0.94 0.98 rg"; // #EEF0FB
        $ops[] = "0.02 0.47 0.34 RG 1 w";
        $ops[] = sprintf("%.2f %.2f %.2f %.2f re B", $marginX, 650, 360, 42);

        $ops[] = "0.29 0.34 0.32 rg";
        $ops[] = self::textCmd('F1', 8.5, $marginX + 14, 677, 'NOMOR REGISTRASI RESMI');
        $ops[] = "0.18 0.21 0.50 rg"; // #2E367F
        $ops[] = self::textCmd('F2', 16, $marginX + 14, 659, (string) ($pendaftar['no_registrasi'] ?? '-'));

        // 3. Gambar Kode QR Vektor di Kanan Atas (di samping kotak Nomor Registrasi)
        $qrMatrix = QrCodeGenerator::encode($buktiUrl);
        $qrN = count($qrMatrix);
        $qrBoxSize = 96.0;
        $qrX = $marginX + $contentW - $qrBoxSize;
        $qrY = 636.0;

        $ops[] = "1 1 1 rg 0.86 0.89 0.87 RG 0.8 w";
        $ops[] = sprintf("%.2f %.2f %.2f %.2f re B", $qrX, $qrY, $qrBoxSize, $qrBoxSize);

        $modSize = ($qrBoxSize - 12.0) / max(1, $qrN);
        $ops[] = "0.04 0.17 0.13 rg";
        for ($r = 0; $r < $qrN; $r++) {
            for ($c = 0; $c < $qrN; $c++) {
                if ($qrMatrix[$r][$c] === 1) {
                    $mx = $qrX + 6.0 + ($c * $modSize);
                    $my = $qrY + $qrBoxSize - 6.0 - (($r + 1) * $modSize);
                    $ops[] = sprintf("%.2f %.2f %.2f %.2f re f", $mx, $my, $modSize + 0.15, $modSize + 0.15);
                }
            }
        }
        $ops[] = "0.35 0.40 0.38 rg";
        $ops[] = self::textCmd('F1', 7.5, $qrX + 10, $qrY - 10, 'Pindai QR untuk verifikasi');

        // 4. Tabel Biodata & Program
        $jalurLabel = \App\Controllers\PendaftaranController::JALUR[$pendaftar['jalur'] ?? ''] ?? 'Reguler';
        $ortu = static fn(string $nama, string $kerja): string => ($pendaftar[$nama] ?? '') !== '' && $pendaftar[$nama] !== null
            ? $pendaftar[$nama] . (!empty($pendaftar[$kerja]) ? ' (' . $pendaftar[$kerja] . ')' : '')
            : '-';
        $jkLabel = ($pendaftar['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki';
        $ttl = ($pendaftar['tempat_lahir'] ?? '-') . ', ' . Helpers::formatTanggal($pendaftar['tanggal_lahir'] ?? '');

        $rows = [
            ['Jalur Pendaftaran', 'Kelas 10, jalur ' . $jalurLabel],
            ['Nama Lengkap', (string) ($pendaftar['nama_lengkap'] ?? '-')],
            ['NIK / NISN', ($pendaftar['nik'] ?? '-') . ' / ' . (!empty($pendaftar['nisn']) ? $pendaftar['nisn'] : '-')],
            ['Tempat, Tanggal Lahir', $ttl],
            ['Jenis Kelamin & Agama', $jkLabel . '  Â·  ' . ($pendaftar['agama'] ?? '-')],
            ['Alamat', self::truncate(implode(', ', array_filter([(string) ($pendaftar['alamat_lengkap'] ?? ''), (string) ($pendaftar['kelurahan'] ?? ''), !empty($pendaftar['kecamatan']) ? 'Kec. ' . $pendaftar['kecamatan'] : ''])) ?: '-', 70)],
            ['Kota, Provinsi', self::truncate(trim(($pendaftar['kota'] ?? '') . ', ' . ($pendaftar['provinsi'] ?? '') . ' ' . ($pendaftar['kode_pos'] ?? ''), ', ') ?: '-', 70)],
            ['Nomor WhatsApp', (string) ($pendaftar['no_wa'] ?? '-')],
            ['Email', !empty($pendaftar['email']) ? (string) $pendaftar['email'] : '-'],
            ['Asal Sekolah', ((string) ($pendaftar['sekolah_asal'] ?? '') ?: '-') . (!empty($pendaftar['tahun_lulus']) ? ' (lulus ' . $pendaftar['tahun_lulus'] . ')' : '')],
            ['Ayah', self::truncate($ortu('nama_ayah', 'pekerjaan_ayah'), 70)],
            ['Ibu', self::truncate($ortu('nama_ibu', 'pekerjaan_ibu'), 70)],
            ['Minat Ekskul', self::truncate((string) (($pendaftar['minat_ekskul'] ?? '') ?: '-'), 70)],
        ];

        $y = 605.0;
        $ops[] = "0.04 0.17 0.13 rg";
        $ops[] = self::textCmd('F2', 11, $marginX, $y, 'A. RINGKASAN DATA PENDAFTAR');
        $y -= 10.0;

        $rowH = 22.0;
        foreach ($rows as $idx => [$label, $val]) {
            $y -= $rowH;
            if ($idx % 2 === 0) {
                $ops[] = "0.96 0.97 0.96 rg";
                $ops[] = sprintf("%.2f %.2f %.2f %.2f re f", $marginX, $y, $contentW, $rowH);
            }
            $ops[] = "0.86 0.89 0.87 RG 0.5 w";
            $ops[] = sprintf("%.2f %.2f m %.2f %.2f l S", $marginX, $y, $marginX + $contentW, $y);

            $ops[] = "0.29 0.34 0.32 rg";
            $ops[] = self::textCmd('F1', 9.5, $marginX + 10, $y + 7, $label);
            $ops[] = "0.04 0.17 0.13 rg";
            $ops[] = self::textCmd('F2', 9.5, $marginX + 155, $y + 7, $val);
        }

        // 5. Daftar Berkas yang Diunggah
        $y -= 28.0;
        $ops[] = "0.04 0.17 0.13 rg";
        $ops[] = self::textCmd('F2', 11, $marginX, $y, 'B. STATUS UNGGAH BERKAS DOKUMEN');
        $y -= 8.0;

        $uploadedTypes = [];
        foreach ($berkasList as $b) {
            $uploadedTypes[$b['jenis_berkas']] = $b;
        }

        $docLabels = [
            'pas_foto' => '1. Pas Foto 3x4',
            'kk' => '2. Kartu Keluarga (KK)',
            'akta' => '3. Akta Kelahiran',
            'rapor' => '4. Rapor Kelas 4-6 atau SKL',
            'sertifikat' => '5. Sertifikat Prestasi (Jalur Prestasi)',
        ];
        if (($pendaftar['jalur'] ?? '') !== 'prestasi') {
            unset($docLabels['sertifikat']);
        }

        $colW = ($contentW - 12.0) / 2.0;
        $i = 0;
        foreach ($docLabels as $key => $title) {
            $col = $i % 2;
            if ($col === 0) {
                $y -= 24.0;
            }
            $bx = $marginX + ($col * ($colW + 12.0));
            $has = isset($uploadedTypes[$key]);

            $ops[] = $has ? "0.93 0.99 0.96 rg 0.65 0.95 0.82 RG" : "0.96 0.97 0.96 rg 0.86 0.89 0.87 RG";
            $ops[] = sprintf("%.2f %.2f %.2f %.2f re B", $bx, $y, $colW, 20.0);

            $ops[] = "0.04 0.17 0.13 rg";
            $ops[] = self::textCmd('F1', 9, $bx + 8, $y + 6, $title);
            $statusTxt = $has ? '[Sudah Diunggah]' : '[Tidak Diunggah]';
            $ops[] = $has ? "0.02 0.47 0.34 rg" : "0.45 0.50 0.48 rg";
            $ops[] = self::textCmd('F2', 8.5, $bx + $colW - 86, $y + 6, $statusTxt);
            $i++;
        }

        // 6. Kotak Langkah Selanjutnya & Catatan Kaki
        $y -= 42.0;
        $ops[] = "0.96 0.97 0.96 rg 0.86 0.89 0.87 RG 0.8 w";
        $ops[] = sprintf("%.2f %.2f %.2f %.2f re B", $marginX, $y - 54, $contentW, 72);

        $ops[] = "0.04 0.17 0.13 rg";
        $ops[] = self::textCmd('F2', 10, $marginX + 12, $y + 2, 'LANGKAH SELANJUTNYA:');
        $ops[] = "0.25 0.30 0.28 rg";
        $ops[] = self::textCmd('F1', 9, $marginX + 12, $y - 13, '1. Simpan atau cetak bukti pendaftaran ini sebagai tanda bukti sah penerimaan berkas.');
        $ops[] = self::textCmd('F1', 9, $marginX + 12, $y - 26, '2. Petugas operator SMA Swadaya akan memeriksa kelengkapan berkas Anda dan menghubungi melalui WhatsApp.');
        $ops[] = self::textCmd('F1', 9, $marginX + 12, $y - 39, '3. Tautan Bukti Digital: ' . $buktiUrl);

        $ops[] = "0.45 0.50 0.48 rg";
        $ops[] = self::textCmd('F1', 8, $marginX, 36, 'Dokumen ini diterbitkan secara elektronik oleh Sistem Pendaftaran Online SMA Swadaya Â· Dicetak pada ' . date('d/m/Y H:i') . ' WIB');

        $stream = implode("\n", $ops);
        return self::compilePdfDocument($stream);
    }

    private static function textCmd(string $font, float $size, float $x, float $y, string $text): string
    {
        $clean = self::pdfEscape($text);
        return sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET", $font, $size, $x, $y, $clean);
    }

    private static function pdfEscape(string $str): string
    {
        // Konversi karakter UTF-8 umum ke WinAnsi/ASCII agar terbaca bersih di font standar PDF
        $str = str_replace(['â', 'â', 'Â·', 'â¢', 'â', 'â', 'â'], ['-', '-', '-', '*', '"', '"', "'"], $str);
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
        if ($converted !== false) {
            $str = $converted;
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $str);
    }

    private static function truncate(string $str, int $max): string
    {
        $str = trim((string) preg_replace('/\s+/', ' ', $str));
        if (mb_strlen($str) <= $max) {
            return $str;
        }
        return mb_substr($str, 0, $max - 3) . '...';
    }

    private static function compilePdfDocument(string $contentStream): string
    {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}

