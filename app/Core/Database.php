<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $connection = strtolower((string) Env::get('DB_CONNECTION', 'mysql'));
        $host = (string) Env::get('DB_HOST', '127.0.0.1');
        $port = (string) Env::get('DB_PORT', '3306');
        $dbName = (string) Env::get('DB_DATABASE', 'sma_swadaya');
        $user = (string) Env::get('DB_USERNAME', 'root');
        $pass = (string) Env::get('DB_PASSWORD', '');
        $fallbackSqlite = Env::bool('DB_FALLBACK_SQLITE', true);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($connection === 'mysql') {
            try {
                $mysqlPdo = self::connectMysql($host, $port, $dbName, $user, $pass, $options);
                Seeder::ensureInitialized($mysqlPdo, 'mysql');
                self::$pdo = $mysqlPdo;
                self::$driver = 'mysql';
                return self::$pdo;
            } catch (\Throwable $e) {
                error_log('[Database] MySQL connection/init warning: ' . $e->getMessage());
                if (!$fallbackSqlite) {
                    throw $e;
                }
            }
        }

        // Fallback atau mode SQLite (penyimpanan di luar public_html: storage/database.sqlite)
        $storageDir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $sqlitePath = $storageDir . '/database.sqlite';
        self::$pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
        self::$pdo->exec('PRAGMA foreign_keys = ON;');
        self::$pdo->exec('PRAGMA journal_mode = WAL;');
        self::$driver = 'sqlite';

        Seeder::ensureInitialized(self::$pdo, 'sqlite');

        return self::$pdo;
    }

    private static function connectMysql(
        string $host,
        string $port,
        string $dbName,
        string $user,
        string $pass,
        array $options
    ): PDO {
        $xamppSocket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
        $dsnCandidates = [
            "mysql:host={$host};port={$port};charset=utf8mb4",
        ];
        if (is_evaluable_socket($xamppSocket)) {
            $dsnCandidates[] = "mysql:unix_socket={$xamppSocket};charset=utf8mb4";
        }
        if ($host === 'localhost') {
            $dsnCandidates[] = "mysql:host=127.0.0.1;port={$port};charset=utf8mb4";
        }

        $lastException = null;
        $safeDb = str_replace('`', '``', $dbName);

        foreach ($dsnCandidates as $baseDsn) {
            try {
                // Coba langsung dengan dbname
                $dsnWithDb = str_replace(';charset=utf8mb4', ";dbname={$dbName};charset=utf8mb4", $baseDsn);
                return new PDO($dsnWithDb, $user, $pass, $options);
            } catch (PDOException $e) {
                $lastException = $e;
                // Kode 1049 = Unknown database -> buat database otomatis di lokal bila belum ada
                if ((int) $e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                    try {
                        $pdoNoDb = new PDO($baseDsn, $user, $pass, $options);
                        $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdoNoDb->exec("USE `{$safeDb}`");
                        return $pdoNoDb;
                    } catch (PDOException $inner) {
                        $lastException = $inner;
                    }
                }
            }
        }

        throw $lastException ?? new PDOException('Gagal terhubung ke MySQL.');
    }

    public static function driver(): string
    {
        if (self::$pdo === null) {
            self::connection();
        }
        return self::$driver;
    }
}

function is_evaluable_socket(string $path): bool
{
    return @file_exists($path);
}
