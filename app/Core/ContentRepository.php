<?php

namespace App\Core;

use PDO;

class ContentRepository
{
    private static ?array $kontenCache = null;

    /**
     * Ambil seluruh data bagian konten sebagai array asosiatif [kunci => data]
     */
    public static function allKonten(): array
    {
        if (self::$kontenCache !== null) {
            return self::$kontenCache;
        }

        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT kunci, kelompok, judul_bagian, data_json, riwayat_json, ringkasan_perubahan, updated_at FROM konten ORDER BY id ASC");
        $rows = $stmt ? $stmt->fetchAll() : [];

        $result = [];
        foreach ($rows as $row) {
            $data = json_decode((string) $row['data_json'], true);
            $data = self::withDefaults((string) $row['kunci'], is_array($data) ? $data : []);
            $riwayat = json_decode((string) ($row['riwayat_json'] ?? '[]'), true);
            $result[$row['kunci']] = [
                'kunci' => $row['kunci'],
                'kelompok' => $row['kelompok'],
                'judul_bagian' => $row['judul_bagian'],
                'data' => is_array($data) ? $data : [],
                'riwayat' => is_array($riwayat) ? $riwayat : [],
                'ringkasan_perubahan' => $row['ringkasan_perubahan'],
                'updated_at' => $row['updated_at'],
            ];
        }

        // Bagian baru dari update kode yang belum ada di database lama: tampil dengan data bawaan
        foreach (Seeder::getDefaultKontenSections() as $sec) {
            $result[$sec['kunci']] ??= $sec;
        }

        self::$kontenCache = $result;
        return $result;
    }

    /**
     * Lengkapi data bagian dengan pengaturan bawaan Seeder yang belum tersimpan di database
     * (mis. opsi baru setelah update kode), agar muncul di editor dan tersimpan dengan tipe yang benar.
     */
    public static function withDefaults(string $kunci, array $data): array
    {
        static $defaults = null;
        if ($defaults === null) {
            $defaults = array_column(Seeder::getDefaultKontenSections(), 'data', 'kunci');
        }
        return $data + ($defaults[$kunci] ?? []);
    }

    public static function allSections(): array
    {
        return array_values(self::allKonten());
    }

    public static function clearCache(): void
    {
        self::$kontenCache = null;
    }

    public static function get(string $kunci, array $default = []): array
    {
        $all = self::allKonten();
        return $all[$kunci]['data'] ?? $default;
    }

    public static function getSection(string $kunci): ?array
    {
        $all = self::allKonten();
        return $all[$kunci] ?? null;
    }

    /**
     * Terbitkan otomatis artikel terjadwal yang waktunya sudah lewat (tanpa cron sesuai PRD)
     */
    public static function promoteScheduledArticles(): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE artikel SET status = 'terbit' WHERE status = 'terjadwal' AND published_at IS NOT NULL AND published_at <= ?");
        $stmt->execute([$now]);
    }

    public static function getLatestArticles(int $limit = 3): array
    {
        self::promoteScheduledArticles();
        $pdo = Database::connection();
        $sql = "SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
                FROM artikel a
                JOIN kategori k ON k.id = a.kategori_id
                WHERE a.status = 'terbit'
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT " . (int) $limit;
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public static function getCategories(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT * FROM kategori ORDER BY urutan ASC, id ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public static function getPublishedArticles(?string $kategoriSlug = null, ?string $searchQuery = null, int $page = 1, int $perPage = 9): array
    {
        self::promoteScheduledArticles();
        $pdo = Database::connection();

        $where = ["a.status = 'terbit'"];
        $params = [];

        if ($kategoriSlug !== null && $kategoriSlug !== '') {
            $where[] = "k.slug = ?";
            $params[] = $kategoriSlug;
        }

        if ($searchQuery !== null && trim($searchQuery) !== '') {
            $q = '%' . trim($searchQuery) . '%';
            $where[] = "(a.judul LIKE ? OR a.ringkasan LIKE ? OR a.isi LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM artikel a JOIN kategori k ON k.id = a.kategori_id WHERE {$whereSql}";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
                FROM artikel a
                JOIN kategori k ON k.id = a.kategori_id
                WHERE {$whereSql}
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public static function getArticleBySlug(string $slug, bool $allowPreview = false): ?array
    {
        self::promoteScheduledArticles();
        $pdo = Database::connection();
        $sql = "SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
                FROM artikel a
                JOIN kategori k ON k.id = a.kategori_id
                WHERE a.slug = ?";
        if (!$allowPreview) {
            $sql .= " AND a.status = 'terbit'";
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getRelatedArticles(int $kategoriId, int $excludeArticleId, int $limit = 3): array
    {
        $pdo = Database::connection();
        $sql = "SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
                FROM artikel a
                JOIN kategori k ON k.id = a.kategori_id
                WHERE a.status = 'terbit' AND a.kategori_id = ? AND a.id != ?
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT " . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$kategoriId, $excludeArticleId]);
        $rows = $stmt->fetchAll();

        if (count($rows) < $limit) {
            $needed = $limit - count($rows);
            $excludeIds = array_merge([$excludeArticleId], array_column($rows, 'id'));
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sqlMore = "SELECT a.*, k.nama AS kategori_nama, k.slug AS kategori_slug
                        FROM artikel a
                        JOIN kategori k ON k.id = a.kategori_id
                        WHERE a.status = 'terbit' AND a.id NOT IN ({$placeholders})
                        ORDER BY a.published_at DESC, a.id DESC
                        LIMIT " . (int) $needed;
            $stmtMore = $pdo->prepare($sqlMore);
            $stmtMore->execute($excludeIds);
            $rows = array_merge($rows, $stmtMore->fetchAll());
        }

        return $rows;
    }

    /**
     * Cerita alumni yang sudah disetujui operator; kisah pilihan (unggulan) lebih dulu
     */
    public static function getApprovedAlumni(int $limit = 0): array
    {
        $sql = "SELECT * FROM cerita_alumni WHERE status = 'disetujui' ORDER BY unggulan DESC, created_at DESC, id DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }
        $stmt = Database::connection()->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }
}
