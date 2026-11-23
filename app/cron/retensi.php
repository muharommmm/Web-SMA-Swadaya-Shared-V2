<?php
/**
 * Cron harian cPanel: hapus data & berkas pendaftar berstatus "tidak melanjutkan"
 * yang melewati RETENTION_DAYS_INACTIVE (UU PDP).
 * Contoh perintah cron: php /home/akun/app/cron/retensi.php
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/vendor/autoload.php';

App\Core\Env::load($rootDir . '/.env');
date_default_timezone_set(App\Core\Env::get('TIMEZONE', 'Asia/Jakarta'));

(new App\Controllers\OperatorController())->cleanupExpiredRegistrants(App\Core\Database::connection());
echo '[' . date('Y-m-d H:i:s') . "] Retensi data pendaftar selesai.\n";
