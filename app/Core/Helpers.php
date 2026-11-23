<?php

namespace App\Core;

class Helpers
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Folder web (tempat index.php, assets/, uploads/). Lokal: public_html/. Di hosting: folder subdomain, sedangkan kode ada di _private/.
     */
    public static function webDir(): string
    {
        return defined('WEB_DIR') ? WEB_DIR : dirname(__DIR__, 2) . '/public_html';
    }

    public static function url(string $path = '/'): string
    {
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    public static function fullUrl(string $path = '/'): string
    {
        $base = rtrim((string) Env::get('APP_URL', 'https://sma.swadaya.sch.id'), '/');
        if ($path === '' || $path === '/') {
            return $base . '/';
        }
        return $base . ($path[0] === '/' ? $path : '/' . $path);
    }

    /**
     * Gabungkan jalan + kelurahan, kecamatan, kota, provinsi, kode pos dari baris pendaftar.
     * Data lama (sebelum alamat terstruktur) hanya punya alamat_lengkap, jadi bagian kosong dilewati.
     */
    public static function alamatPendaftar(array $p): string
    {
        $parts = array_filter([
            trim((string) ($p['alamat_lengkap'] ?? '')),
            trim((string) ($p['kelurahan'] ?? '')),
            !empty($p['kecamatan']) ? 'Kec. ' . $p['kecamatan'] : '',
            trim((string) ($p['kota'] ?? '')),
            trim(($p['provinsi'] ?? '') . ' ' . ($p['kode_pos'] ?? '')),
        ]);
        return implode(', ', $parts);
    }

    /**
     * "2026-10-14 08:00:00" -> "14 Oktober 2026"
     */
    public static function tanggalPanjang(?string $datetime): string
    {
        $ts = $datetime ? strtotime($datetime) : false;
        if ($ts === false) {
            return '';
        }
        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /**
     * Inisial avatar: "Reza Pratama, S.Ds." -> "RP"; placeholder "[Nama]" -> "?"
     */
    public static function inisial(string $nama): string
    {
        $nama = trim((string) preg_replace('/,.*$/', '', $nama));
        if ($nama === '' || str_starts_with($nama, '[')) {
            return '?';
        }
        $kata = preg_split('/\s+/', $nama) ?: [];
        $huruf = mb_substr($kata[0], 0, 1) . (count($kata) > 1 ? mb_substr(end($kata), 0, 1) : '');
        return mb_strtoupper($huruf);
    }

    /**
     * Ikon garis (24x24, stroke currentColor) yang dipakai berulang di tampilan publik & admin
     */
    public static function icon(string $name, int $size = 18): string
    {
        static $paths = [
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
            'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
            'external' => '<path d="M7 17 17 7M8 7h9v9"/>',
            'wa' => '<path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3.2 3.1L3.5 20.5Z"/><path d="M9 9.5c.3 2 2.2 4.2 5.5 5l1-1.4-1.8-1-.9.8c-1-.4-1.9-1.3-2.3-2.3l.8-.9-1-1.8L9 9.5Z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 9"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4.5 4.2-7 8-7s7 2.5 8 7"/>',
            'pin' => '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'check' => '<path d="m5 12 5 5 9-10"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
            'print' => '<path d="M7 8V3h10v5"/><rect x="3" y="8" width="18" height="9" rx="2"/><path d="M7 14h10v7H7z"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
            'youtube' => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>',
            'facebook' => '<path d="M15 3h-2.5A3.5 3.5 0 0 0 9 6.5V9H6.5v3.5H9V21h3.5v-8.5H15l.5-3.5h-3V7a1 1 0 0 1 1-1H15z"/>',
            'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.5A8 8 0 1 1 21 12Z"/>',
            'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/>',
        ];
        $size = max(10, $size);
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }

    public static function formatTanggal(?string $datetime, bool $singkat = false): string
    {
        if (!$datetime) {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return (string) $datetime;
        }
        $bulan = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $d = (int) date('j', $ts);
        $m = $bulan[(int) date('n', $ts)] ?? date('M', $ts);
        $y = date('Y', $ts);
        return $singkat ? "{$d} {$m}" : "{$d} {$m} {$y}";
    }

    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    public static function csrfField(): string
    {
        $token = self::e(self::csrfToken());
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals((string) $_SESSION['_csrf_token'], (string) $token);
    }

    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $savePath = session_save_path();
        if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
            $customSessionDir = dirname(__DIR__, 2) . '/storage/sessions';
            if (!is_dir($customSessionDir)) {
                @mkdir($customSessionDir, 0700, true);
                @file_put_contents($customSessionDir . '/.htaccess', "Require all denied\n");
            }
            if (is_dir($customSessionDir) && is_writable($customSessionDir)) {
                session_save_path($customSessionDir);
            }
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('SMASWADAYA_SESS');
            @session_start();
        } elseif (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Penyaring HTML aman (setara daftar putih HTML Purifier sesuai PRD)
     * Hanya mengizinkan tag dasar: p, h2, h3, strong, b, em, i, ul, ol, li, blockquote, a, img, br
     */
    public static function sanitizeRichHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Buang elemen berbahaya beserta isinya
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|svg|math)[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select| meta|link)[^>]*>#is', '', $html);

        $allowedTags = '<p><h2><h3><strong><b><em><i><ul><ol><li><blockquote><a><img><br>';
        $clean = strip_tags($html, $allowedTags);

        // Bersihkan atribut event handler (on*) dan javascript:/data: URI menggunakan DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="__root">' . $clean . '</div></body></html>';
        $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="__root"]//*');
        if ($nodes) {
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $tagName = strtolower($node->tagName);
                $attrsToRemove = [];
                foreach ($node->attributes as $attr) {
                    $attrName = strtolower($attr->name);
                    $attrVal = trim($attr->value);
                    if ($tagName === 'a' && in_array($attrName, ['href', 'title'], true)) {
                        if ($attrName === 'href' && !preg_match('#^(https?://|mailto:|/|\#)#i', $attrVal)) {
                            $attrsToRemove[] = $attr->name;
                        }
                        continue;
                    }
                    if ($tagName === 'img' && in_array($attrName, ['src', 'alt', 'width', 'height', 'loading'], true)) {
                        if ($attrName === 'src' && !preg_match('#^(https?://|/)#i', $attrVal)) {
                            $attrsToRemove[] = $attr->name;
                        }
                        continue;
                    }
                    $attrsToRemove[] = $attr->name;
                }
                foreach ($attrsToRemove as $rm) {
                    $node->removeAttribute($rm);
                }
            }
        }

        $root = $dom->getElementById('__root');
        if (!$root) {
            return '';
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }
        return trim($output);
    }
}
