<?php

namespace App\Services;

/**
 * Layanan Validasi, Pembersihan EXIF (GD Re-encode), dan Penyimpanan Berkas Unggahan
 * Sesuai PRD Bagian 9.5 & Bagian 11 (disimpan di luar public_html: storage/uploads/)
 */
class UploadService
{
    public const FILE_SPECS = [
        'pas_foto' => [
            'label' => 'Pas foto 3x4',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
            'max_bytes' => 1 * 1024 * 1024, // 1 MB
        ],
        'kk' => [
            'label' => 'Kartu Keluarga',
            'mimes' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
            'max_bytes' => 2 * 1024 * 1024,
        ],
        'akta' => [
            'label' => 'Akta kelahiran',
            'mimes' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
            'max_bytes' => 2 * 1024 * 1024,
        ],
        'rapor' => [
            'label' => 'Rapor kelas 4–6 atau SKL',
            'mimes' => ['application/pdf' => 'pdf'],
            'max_bytes' => 5 * 1024 * 1024, // 5 MB: hasil pindai beberapa semester
        ],
        'sertifikat' => [
            'label' => 'Sertifikat prestasi',
            'mimes' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
            'max_bytes' => 2 * 1024 * 1024,
        ],
    ];

    public const MAX_TOTAL_BYTES = 12 * 1024 * 1024; // 12 MB

    /**
     * Validasi seluruh berkas unggahan; sertifikat prestasi wajib untuk jalur Prestasi
     */
    public static function validateUploads(array $files, string $jalur): array
    {
        $errors = [];
        $validFiles = [];
        $totalBytes = 0;

        $requiredKeys = ['pas_foto', 'kk', 'akta', 'rapor'];
        if ($jalur === 'prestasi') {
            $requiredKeys[] = 'sertifikat';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach (self::FILE_SPECS as $key => $spec) {
            $file = $files[$key] ?? null;
            $isRequired = in_array($key, $requiredKeys, true);

            if (!$file || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                if ($isRequired) {
                    $errors[$key] = "Berkas {$spec['label']} wajib diunggah.";
                }
                continue;
            }

            $errCode = (int) ($file['error'] ?? UPLOAD_ERR_OK);
            if ($errCode !== UPLOAD_ERR_OK) {
                $errors[$key] = "Berkas {$spec['label']} gagal diunggah (kode error {$errCode}). Periksa ukuran file Anda.";
                continue;
            }

            $tmpPath = (string) $file['tmp_name'];
            $isValidUpload = (PHP_SAPI === 'cli') ? is_file($tmpPath) : is_uploaded_file($tmpPath);
            if (!$isValidUpload) {
                $errors[$key] = "Berkas {$spec['label']} tidak valid.";
                continue;
            }

            $size = (int) filesize($tmpPath);
            if ($size <= 0) {
                $errors[$key] = "Berkas {$spec['label']} kosong atau rusak.";
                continue;
            }

            if ($size > $spec['max_bytes']) {
                $maxMb = round($spec['max_bytes'] / (1024 * 1024), 1);
                $errors[$key] = "Ukuran berkas {$spec['label']} melebihi batas maksimal {$maxMb} MB.";
                continue;
            }

            $totalBytes += $size;

            // Daftar putih ekstensi nama file DAN tipe isi asli (finfo) wajib lolos keduanya
            $allowedExts = strtoupper(implode(', ', array_unique(array_values($spec['mimes']))));
            $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            $extOk = in_array($ext, array_values($spec['mimes']), true) || ($ext === 'jpeg' && in_array('jpg', $spec['mimes'], true));
            if (!$extOk) {
                $errors[$key] = "Format berkas {$spec['label']} tidak didukung. Gunakan format {$allowedExts}.";
                continue;
            }

            $mime = (string) $finfo->file($tmpPath);
            if (!isset($spec['mimes'][$mime])) {
                $errors[$key] = "Format berkas {$spec['label']} tidak didukung. Gunakan format {$allowedExts}.";
                continue;
            }

            if ($mime === 'image/jpeg' || $mime === 'image/png') {
                $imgInfo = @getimagesize($tmpPath);
                if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                    $errors[$key] = "Berkas gambar {$spec['label']} rusak atau tidak dapat dibaca.";
                    continue;
                }
            } elseif ($mime === 'application/pdf') {
                $fh = @fopen($tmpPath, 'rb');
                $header = $fh ? (string) fread($fh, 5) : '';
                if ($fh) {
                    fclose($fh);
                }
                if ($header !== '%PDF-') {
                    $errors[$key] = "Berkas PDF {$spec['label']} tidak valid.";
                    continue;
                }
            }

            $validFiles[$key] = [
                'jenis_berkas' => $key,
                'label_berkas' => $spec['label'],
                'tmp_name' => $tmpPath,
                'original_name' => self::sanitizeOriginalName((string) ($file['name'] ?? ($key . '.' . $spec['mimes'][$mime]))),
                'mime_type' => $mime,
                'ext' => $spec['mimes'][$mime],
                'size' => $size,
            ];
        }

        if ($totalBytes > self::MAX_TOTAL_BYTES) {
            $errors['berkas_total'] = 'Total ukuran seluruh berkas melampaui batas maksimal 12 MB.';
        }

        return [
            'errors' => $errors,
            'valid_files' => $validFiles,
        ];
    }

    /**
     * Simpan berkas ke storage/uploads/{YYYY}/{nomor_registrasi}/
     */
    public static function storeFiles(array $validFiles, string $nomorRegistrasi): array
    {
        $year = date('Y');
        $safeReg = preg_replace('/[^A-Za-z0-9\-]/', '', $nomorRegistrasi);
        $relDir = $year . '/' . $safeReg;
        $baseUploadDir = dirname(__DIR__, 2) . '/storage/uploads';
        $targetDir = $baseUploadDir . '/' . $relDir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $savedRecords = [];

        foreach ($validFiles as $key => $info) {
            $randomSuffix = bin2hex(random_bytes(8));
            $fileName = $key . '_' . $randomSuffix . '.' . $info['ext'];
            $destFull = $targetDir . '/' . $fileName;
            $destRel = $relDir . '/' . $fileName;

            $reencoded = false;
            if ($info['mime_type'] === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
                $im = @imagecreatefromjpeg($info['tmp_name']);
                if ($im !== false) {
                    $reencoded = @imagejpeg($im, $destFull, 86);
                    imagedestroy($im);
                }
            } elseif ($info['mime_type'] === 'image/png' && function_exists('imagecreatefrompng')) {
                $im = @imagecreatefrompng($info['tmp_name']);
                if ($im !== false) {
                    imagesavealpha($im, true);
                    $reencoded = @imagepng($im, $destFull, 6);
                    imagedestroy($im);
                }
            }

            if (!$reencoded) {
                if (PHP_SAPI === 'cli') {
                    copy($info['tmp_name'], $destFull);
                } else {
                    move_uploaded_file($info['tmp_name'], $destFull);
                }
            }

            @chmod($destFull, 0644);
            $finalSize = is_file($destFull) ? (int) filesize($destFull) : (int) $info['size'];

            $savedRecords[] = [
                'jenis_berkas' => $key,
                'label_berkas' => $info['label_berkas'],
                'nama_file_acak' => $destRel,
                'nama_asli' => $info['original_name'],
                'ukuran_byte' => $finalSize,
                'ukuran_kb' => max(1, (int) round($finalSize / 1024)),
                'mime_type' => $info['mime_type'],
                'full_path' => $destFull,
            ];
        }

        return $savedRecords;
    }

    /**
     * Path absolut berkas dari nilai kolom berkas.nama_file_acak (relatif ke storage/uploads)
     */
    public static function fullPath(string $relPath): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/' . ltrim(str_replace('..', '', $relPath), '/');
    }

    private static function sanitizeOriginalName(string $name): string
    {
        $base = basename($name);
        $clean = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $base);
        return mb_substr(trim((string) $clean), 0, 120) ?: 'berkas';
    }
}

