<?php

namespace App\Services;

/**
 * Generator QR Code SVG Murni (ISO/IEC 18004 Level L, Byte Mode, Versi 1-6)
 * Tidak membutuhkan ekstensi C tambahan maupun koneksi ke layanan eksternal.
 */
class QrCodeGenerator
{
    // Tabel kapasitas versi 1..6 untuk Level L: [total_codewords, ec_codewords_per_block, num_blocks]
    private const VERSION_INFO = [
        1 => [26, 7, 1],
        2 => [44, 10, 1],
        3 => [70, 15, 1],
        4 => [100, 20, 1],
        5 => [134, 26, 1],
        6 => [172, 18, 2],
    ];

    private const ALIGNMENT_POS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
    ];

    public static function renderSvg(string $text, int $pixelSize = 128, int $margin = 2): string
    {
        $matrix = self::encode($text);
        $n = count($matrix);
        $totalModules = $n + ($margin * 2);

        $paths = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $paths[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $pathData = implode('', $paths);
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges" role="img" aria-label="Kode QR Bukti Pendaftaran">'
            . '<rect width="100%%" height="100%%" fill="#FFFFFF"/>'
            . '<path d="%s" fill="#0B2B22"/>'
            . '</svg>',
            $totalModules,
            $totalModules,
            $pixelSize,
            $pixelSize,
            $pathData
        );
    }

    public static function encode(string $data): array
    {
        $bytes = array_values(unpack('C*', $data));
        $len = count($bytes);

        // Pilih versi terkecil (1..6) yang muat untuk mode Byte (4 bit mode + 8 bit len + 8*len + 4 bit terminator)
        $version = 6;
        foreach (self::VERSION_INFO as $v => [$totalCw, $ecCw, $blocks]) {
            $dataCw = $totalCw - ($ecCw * $blocks);
            if ($len + 2 <= $dataCw) {
                $version = $v;
                break;
            }
        }

        [$totalCw, $ecCw, $blocks] = self::VERSION_INFO[$version];
        $dataCw = $totalCw - ($ecCw * $blocks);

        // Susun bit stream: mode 0100 (Byte), panjang 8-bit, data byte, terminator 0000
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $maxBits = $dataCw * 8;
        $bits .= str_repeat('0', min(4, max(0, $maxBits - strlen($bits))));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $codewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $dataCw) {
            $codewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // Bagi ke blok & hitung Reed-Solomon EC
        $blockSize = intdiv($dataCw, $blocks);
        $dataBlocks = [];
        $ecBlocks = [];
        for ($b = 0; $b < $blocks; $b++) {
            $blk = array_slice($codewords, $b * $blockSize, $blockSize);
            $dataBlocks[] = $blk;
            $ecBlocks[] = self::reedSolomonEncode($blk, $ecCw);
        }

        // Interleave data & EC codewords
        $finalCw = [];
        for ($i = 0; $i < $blockSize; $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                $finalCw[] = $dataBlocks[$b][$i];
            }
        }
        for ($i = 0; $i < $ecCw; $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                $finalCw[] = $ecBlocks[$b][$i];
            }
        }

        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns + separators
        self::placeFinder($matrix, $reserved, 0, 0);
        self::placeFinder($matrix, $reserved, $size - 7, 0);
        self::placeFinder($matrix, $reserved, 0, $size - 7);

        // Alignment patterns
        $align = self::ALIGNMENT_POS[$version];
        foreach ($align as $ar) {
            foreach ($align as $ac) {
                if (($ar < 9 && $ac < 9) || ($ar < 9 && $ac > $size - 10) || ($ar > $size - 10 && $ac < 9)) {
                    continue;
                }
                self::placeAlignment($matrix, $reserved, $ar, $ac);
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }

        // Dark module & reservasi area format info
        $matrix[(4 * $version) + 9][8] = 1;
        $reserved[(4 * $version) + 9][8] = true;

        for ($i = 0; $i < 9; $i++) {
            if ($i < $size) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // Tempatkan bit data
        $allBits = '';
        foreach ($finalCw as $cw) {
            $allBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $bitIdx = 0;
        $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $size; $i++) {
                $row = $up ? ($size - 1 - $i) : $i;
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if (!$reserved[$row][$cc]) {
                        $bit = ($bitIdx < strlen($allBits)) ? (int) $allBits[$bitIdx++] : 0;
                        // Mask 0: (row + col) % 2 == 0
                        if ((($row + $cc) % 2) === 0) {
                            $bit ^= 1;
                        }
                        $matrix[$row][$cc] = $bit;
                    }
                }
            }
            $up = !$up;
        }

        // Format info untuk Level L (01) dan Mask 0 (000) -> BCH = 111011111000100
        $formatBits = '111011111000100';
        $fPos1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c] = $fPos1[$i];
            $matrix[$r][$c] = (int) $formatBits[$i];
        }
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = (int) $formatBits[$i];
        }
        for ($i = 7; $i < 15; $i++) {
            $matrix[8][$size - 15 + $i] = (int) $formatBits[$i];
        }

        return $matrix;
    }

    private static function placeFinder(array &$matrix, array &$reserved, int $row, int $col): void
    {
        $size = count($matrix);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $matrix[$rr][$cc] = 1;
                    } else {
                        $matrix[$rr][$cc] = 0;
                    }
                } else {
                    $matrix[$rr][$cc] = 0;
                }
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignment(array &$matrix, array &$reserved, int $centerR, int $centerC): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $rr = $centerR + $r;
                $cc = $centerC + $c;
                $matrix[$rr][$cc] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0)) ? 1 : 0;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function reedSolomonEncode(array $data, int $ecCount): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= ($gen[$j] === 0) ? 0 : $exp[$log[$gen[$j]] + $i];
            }
            $gen = $next;
        }

        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $msg[$i + $j] ^= ($gen[$j] === 0) ? 0 : $exp[$log[$gen[$j]] + $log[$coef]];
                }
            }
        }

        return array_slice($msg, count($data));
    }
}

