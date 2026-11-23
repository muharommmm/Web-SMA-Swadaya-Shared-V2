<?php

declare(strict_types=1);

// Di hosting subdomain: kode ada di _private/ (tertutup), file web di folder ini. Lokal: kode satu tingkat di atas public_html/.
$rootDir = is_dir(__DIR__ . '/_private/app') ? __DIR__ . '/_private' : dirname(__DIR__);
define('WEB_DIR', __DIR__);

// Dukungan server bawaan PHP (php -S) untuk file statis di public_html
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

require_once $rootDir . '/vendor/autoload.php';

use App\Controllers\OperatorController;
use App\Controllers\PendaftaranController;
use App\Controllers\PublicController;
use App\Core\Env;

Env::load($rootDir . '/.env');
date_default_timezone_set(Env::get('TIMEZONE', 'Asia/Jakarta'));

// Konfigurasi pencatatan error ke storage/logs/app.log (display_errors = 0 di produksi)
$appDebug = strtolower(Env::get('APP_DEBUG', 'false')) === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = $rootDir . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/app.log');

// Header keamanan HTTP sesuai PRD Bagian 11
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; frame-src 'self' https://www.google.com https://maps.google.com https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com https://cloudflareinsights.com;");
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
// HEAD dijawab seperti GET (server membuang isinya); dipakai monitor uptime & pemeriksa tautan
if ($method === 'HEAD') {
    $method = 'GET';
}
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($uriPath, '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

try {
    $publicController = new PublicController();

    if ($method === 'GET' && $path === '/') {
        $publicController->beranda();
        exit;
    }

    if ($method === 'GET' && $path === '/profil') {
        $publicController->profil();
        exit;
    }

    // Alamat lama (sebelum redesain) dialihkan permanen agar tautan yang sudah tersebar tetap jalan
    if ($method === 'GET' && ($path === '/program' || $path === '/blog' || str_starts_with($path, '/blog/'))) {
        $baru = $path === '/program' ? '/profil' : '/berita' . substr($path, 5);
        $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        header('Location: ' . $baru . ($query !== '' ? '?' . $query : ''), true, 301);
        exit;
    }

    if ($method === 'GET' && $path === '/berita') {
        $publicController->berita(null);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/berita/kategori/([a-z0-9\-]+)$#', $path, $matches)) {
        $publicController->berita($matches[1]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/berita/([a-z0-9\-]+)$#', $path, $matches)) {
        $publicController->artikelDetail($matches[1]);
        exit;
    }

    if ($method === 'GET' && $path === '/ekstrakurikuler') {
        $publicController->ekstrakurikuler();
        exit;
    }

    if ($method === 'GET' && $path === '/alumni') {
        $publicController->alumni();
        exit;
    }

    if ($method === 'POST' && $path === '/alumni') {
        $publicController->kirimCeritaAlumni();
        exit;
    }

    if ($method === 'GET' && $path === '/pendaftaran') {
        $publicController->pendaftaranForm();
        exit;
    }

    if ($method === 'POST' && $path === '/pendaftaran') {
        (new PendaftaranController())->submit();
        exit;
    }

    if ($method === 'GET' && $path === '/pendaftaran/berhasil') {
        (new PendaftaranController())->berhasil();
        exit;
    }

    if ($method === 'GET' && preg_match('#^/pendaftaran/bukti/([a-f0-9]{32})$#', $path, $matches)) {
        (new PendaftaranController())->bukti($matches[1]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/pendaftaran/bukti/([a-f0-9]{32})/pdf$#', $path, $matches)) {
        (new PendaftaranController())->buktiPdf($matches[1]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/pendaftaran/bukti/([a-f0-9]{32})/foto$#', $path, $matches)) {
        (new PendaftaranController())->buktiFoto($matches[1]);
        exit;
    }

    if ($method === 'GET' && $path === '/kebijakan-privasi') {
        $publicController->kebijakanPrivasi();
        exit;
    }

    if ($method === 'GET' && $path === '/sitemap.xml') {
        $publicController->sitemapXml();
        exit;
    }

    // ==================== RUTE AREA OPERATOR ====================
    if (str_starts_with($path, '/operator')) {
        $operatorController = new OperatorController();

        if ($method === 'GET' && $path === '/operator/login') {
            $operatorController->loginForm();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/login') {
            $operatorController->loginSubmit();
            exit;
        }
        if (in_array($method, ['GET', 'POST'], true) && $path === '/operator/logout') {
            $operatorController->logout();
            exit;
        }
        if ($method === 'GET' && $path === '/operator') {
            $operatorController->dashboard();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/kirim-ulang-email') {
            $operatorController->retryEmail();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/konten') {
            $operatorController->konten();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/konten') {
            $operatorController->kontenSave();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/konten/pulihkan') {
            $operatorController->kontenRestore();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/artikel') {
            $operatorController->artikelIndex();
            exit;
        }
        // URL sesuai PRD: /operator/artikel/baru dan /operator/artikel/{id}
        if ($method === 'GET' && preg_match('#^/operator/artikel/(baru|\d+)$#', $path, $matches)) {
            if ($matches[1] !== 'baru') {
                $_GET['id'] = $matches[1];
            }
            $operatorController->artikelForm();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/artikel/tulis') {
            $operatorController->artikelForm();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/artikel/simpan') {
            $operatorController->artikelSave();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/artikel/status') {
            $operatorController->artikelToggleStatus();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/artikel/hapus') {
            $operatorController->artikelDelete();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/akun') {
            $operatorController->akun();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/akun') {
            $operatorController->akunSave();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/pendaftar') {
            $operatorController->pendaftarIndex();
            exit;
        }
        if ($method === 'GET' && $path === '/operator/pendaftar/ekspor') {
            $operatorController->pendaftarEkspor();
            exit;
        }
        if (preg_match('#^/operator/pendaftar/(\d+)(?:/(status|berkas|cetak))?$#', $path, $matches)) {
            $aksi = $matches[2] ?? '';
            if ($method === 'GET' && $aksi === '') {
                $operatorController->pendaftarDetail((int) $matches[1]);
                exit;
            }
            if ($method === 'GET' && $aksi === 'cetak') {
                $operatorController->pendaftarCetak((int) $matches[1]);
                exit;
            }
            if ($method === 'POST' && $aksi === 'status') {
                $operatorController->pendaftarStatus((int) $matches[1]);
                exit;
            }
            if ($method === 'POST' && $aksi === 'berkas') {
                $operatorController->pendaftarBerkasPeriksa((int) $matches[1]);
                exit;
            }
        }
        if ($method === 'GET' && preg_match('#^/operator/berkas/(\d+)$#', $path, $matches)) {
            $operatorController->berkasFile((int) $matches[1]);
            exit;
        }
        if ($method === 'GET' && $path === '/operator/alumni') {
            $operatorController->alumniIndex();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/alumni/aksi') {
            $operatorController->alumniAksi();
            exit;
        }
        if ($method === 'POST' && $path === '/operator/pendaftar/hapus') {
            $operatorController->hapusPendaftar();
            exit;
        }
    }

    // Rute tidak ditemukan -> Halaman 404
    $publicController->notFound();
} catch (\Throwable $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($appDebug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Gangguan Teknis · SMA Swadaya</title></head><body style="font-family:system-ui,sans-serif;padding:48px 20px;text-align:center;color:#102A2A;"><h1>Maaf, terjadi gangguan teknis sementara</h1><p>Silakan muat ulang halaman beberapa saat lagi atau hubungi operator melalui WhatsApp.</p><p><a href="/" style="color:#10AEAD;font-weight:600;">Kembali ke Beranda</a></p></body></html>';
    }
}

