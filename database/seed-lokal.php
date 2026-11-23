<?php
/**
 * Isi database lokal dengan data awal (Seeder). Hanya untuk APP_ENV=local.
 *
 *   php database/seed-lokal.php          buat & isi bila belum ada
 *   php database/seed-lokal.php --reset  hapus database lokal lalu isi ulang dari awal
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load($root . '/.env');
if (Env::get('APP_ENV', 'production') !== 'local') {
    fwrite(STDERR, "Ditolak: APP_ENV di .env bukan 'local'. Skrip ini hanya untuk komputer pengembangan.\n");
    exit(1);
}

if (in_array('--reset', $argv, true)) {
    if (strtolower((string) Env::get('DB_CONNECTION', 'mysql')) === 'mysql') {
        try {
            $pdo = new PDO(
                'mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', '3306') . ';charset=utf8mb4',
                (string) Env::get('DB_USERNAME', 'root'),
                (string) Env::get('DB_PASSWORD', ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', (string) Env::get('DB_DATABASE', 'sma_swadaya')) . '`');
            echo "Database MySQL lokal dihapus.\n";
        } catch (Throwable $e) {
            echo "MySQL tidak bisa dihubungi ({$e->getMessage()}), lanjut dengan SQLite.\n";
        }
    }
    foreach (glob($root . '/storage/database.sqlite*') ?: [] as $f) {
        unlink($f);
    }
}

// Database::connection() membuat database, tabel, dan data awal bila masih kosong
$pdo = Database::connection();
echo 'Driver: ' . Database::driver() . "\n";
foreach (['konten', 'kategori', 'artikel', 'cerita_alumni', 'operator', 'pendaftar'] as $t) {
    echo str_pad($t, 15) . $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn() . " baris\n";
}
echo "\nLogin admin: " . $pdo->query('SELECT email FROM operator ORDER BY id LIMIT 1')->fetchColumn() . " / rahasia123 (bawaan)\n";
