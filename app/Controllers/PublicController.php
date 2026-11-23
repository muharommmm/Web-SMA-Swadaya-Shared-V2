<?php

namespace App\Controllers;

use App\Core\ContentRepository;
use App\Core\Database;
use App\Core\Helpers;
use App\Core\View;

class PublicController
{
    /**
     * Ambil paket data konten dasar yang dibutuhkan seluruh halaman publik
     */
    public function baseKonten(): array
    {
        $raw = ContentRepository::allKonten();
        $konten = [];
        foreach ($raw as $kunci => $item) {
            $konten[$kunci] = $item['data'] ?? [];
        }

        $kontak = $konten['data_kontak'] ?? [];
        $waNomor = preg_replace('/\D+/', '', (string) ($kontak['whatsapp_nomor'] ?? ''));
        $kontak['whatsapp_link'] = 'https://wa.me/' . $waNomor . '?text=' . rawurlencode('Halo Admin SMA Swadaya, saya ingin bertanya tentang PPDB.');
        $konten['kontak'] = $kontak;

        $banner = $konten['beranda_banner'] ?? [];
        $banner['batas_waktu'] = $banner['batas_tanggal'] ?? '';
        $konten['info_pendaftaran'] = $banner;

        return $konten;
    }

    private function seo(array $konten, string $key, string $fallback): string
    {
        return (string) (($konten['data_seo'][$key] ?? '') ?: $fallback);
    }

    public function beranda(): void
    {
        $konten = $this->baseKonten();
        $tim = $konten['data_tim_testimoni']['tim'] ?? [];

        View::render('public/beranda', [
            'pageTitle' => $this->seo($konten, 'beranda_title', 'SMA Swadaya Bandung'),
            'metaDesc' => $this->seo($konten, 'beranda_desc', 'SMA Swadaya Bandung. Info sekolah, kegiatan, dan PPDB online.'),
            'canonicalUrl' => Helpers::fullUrl('/'),
            'activeMenu' => 'beranda',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'hero' => $konten['beranda_hero'] ?? [],
            'galeri' => $konten['beranda_galeri']['items'] ?? [],
            'sambutan' => $konten['profil_sambutan'] ?? [],
            'guruBeranda' => array_values(array_filter($tim, static fn($g) => !empty($g['tampil_beranda']))),
            'latestArticles' => ContentRepository::getLatestArticles(3),
            'alumni' => ContentRepository::getApprovedAlumni(3),
        ]);
    }

    public function profil(): void
    {
        $konten = $this->baseKonten();
        $timData = $konten['data_tim_testimoni'] ?? [];
        $tim = $timData['tim'] ?? [];
        // Operator bisa menyembunyikan guru yang belum punya foto (Konten Situs > Guru & tenaga pendidik)
        if (isset($timData['tampilkan_tim_tanpa_foto']) && !$timData['tampilkan_tim_tanpa_foto']) {
            $tim = array_values(array_filter($tim, static fn($g) => !empty($g['foto'])));
        }

        View::render('public/profil', [
            'pageTitle' => $this->seo($konten, 'profil_title', 'Profil SMA Swadaya'),
            'metaDesc' => $this->seo($konten, 'profil_desc', 'Identitas, visi-misi, guru, dan fasilitas SMA Swadaya.'),
            'canonicalUrl' => Helpers::fullUrl('/profil'),
            'activeMenu' => 'profil',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'tentang' => $konten['profil_tentang_legalitas'] ?? [],
            'visiMisi' => $konten['profil_visi_misi'] ?? [],
            'sambutan' => $konten['profil_sambutan'] ?? [],
            'fasilitas' => $konten['profil_fasilitas'] ?? [],
            'tim' => $tim,
        ]);
    }

    public function berita(?string $kategoriSlug = null): void
    {
        $konten = $this->baseKonten();
        $categories = ContentRepository::getCategories();
        $currentKategori = null;

        if ($kategoriSlug !== null && $kategoriSlug !== '') {
            foreach ($categories as $cat) {
                if ($cat['slug'] === $kategoriSlug) {
                    $currentKategori = $cat;
                    break;
                }
            }
            if ($currentKategori === null) {
                $this->notFound();
                return;
            }
        }

        $searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $isFiltered = $currentKategori !== null || $searchQuery !== '';

        // Tanpa filter, tulisan terbaru jadi Berita Utama dan tidak diulang di daftar
        $featured = null;
        if (!$isFiltered && $page === 1) {
            $featured = ContentRepository::getLatestArticles(1)[0] ?? null;
        }
        $pagination = ContentRepository::getPublishedArticles($kategoriSlug, $searchQuery, $page, 9);
        if ($featured !== null) {
            $pagination['items'] = array_values(array_filter($pagination['items'], static fn($a) => (int) $a['id'] !== (int) $featured['id']));
        }

        $canonicalPath = $currentKategori ? ('/berita/kategori/' . $currentKategori['slug']) : '/berita';

        View::render('public/berita', [
            'pageTitle' => $currentKategori
                ? ('Kategori ' . $currentKategori['nama'] . ' · Berita SMA Swadaya')
                : $this->seo($konten, 'berita_title', 'Berita & Artikel · SMA Swadaya'),
            'metaDesc' => $currentKategori
                ? ('Kumpulan tulisan kategori ' . $currentKategori['nama'] . ' dari SMA Swadaya Bandung.')
                : $this->seo($konten, 'berita_desc', 'Kabar kegiatan, prestasi, dan pengumuman SMA Swadaya.'),
            'canonicalUrl' => Helpers::fullUrl($canonicalPath . ($page > 1 ? '?page=' . $page : '')),
            'noIndex' => $searchQuery !== '',
            'activeMenu' => 'berita',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'categories' => $categories,
            'currentKategori' => $currentKategori,
            'searchQuery' => $searchQuery,
            'isFiltered' => $isFiltered,
            'featured' => $featured,
            'pagination' => $pagination,
        ]);
    }

    public function artikelDetail(string $slug): void
    {
        Helpers::startSecureSession();
        $allowPreview = !empty($_SESSION['operator_id']) && isset($_GET['preview']);
        $article = ContentRepository::getArticleBySlug($slug, $allowPreview);

        if ($article === null) {
            $this->notFound();
            return;
        }

        $konten = $this->baseKonten();
        $relatedArticles = ContentRepository::getRelatedArticles((int) $article['kategori_id'], (int) $article['id'], 3);
        $canonicalUrl = Helpers::fullUrl('/berita/' . $article['slug']);
        $sampul = (string) ($article['gambar_sampul'] ?? '');
        $sampulUrl = $sampul === '' ? '' : (str_starts_with($sampul, 'http') ? $sampul : Helpers::fullUrl($sampul));

        $articleSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article['judul'],
            'description' => $article['meta_description'] ?: $article['ringkasan'],
            'datePublished' => date('c', strtotime((string) ($article['published_at'] ?: $article['created_at']))),
            'dateModified' => date('c', strtotime((string) ($article['updated_at'] ?: $article['created_at']))),
            'image' => $sampulUrl !== '' ? [$sampulUrl] : null,
            'author' => ['@type' => 'EducationalOrganization', 'name' => 'SMA Swadaya', 'url' => Helpers::fullUrl('/')],
            'publisher' => [
                '@type' => 'EducationalOrganization',
                'name' => 'SMA Swadaya',
                'url' => Helpers::fullUrl('/'),
                'logo' => ['@type' => 'ImageObject', 'url' => Helpers::fullUrl('/assets/img/logo.png')],
            ],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
        ]);

        // Google memotong judul di sekitar 60 karakter: nama sekolah hanya ditambahkan bila masih muat
        $judulPenuh = $article['judul'] . ' · SMA Swadaya';

        View::render('public/artikel-detail', [
            'pageTitle' => mb_strlen($judulPenuh) <= 60 ? $judulPenuh : $article['judul'],
            'metaDesc' => $article['meta_description'] ?: $article['ringkasan'],
            'canonicalUrl' => $canonicalUrl,
            'ogType' => 'article',
            'ogImage' => $sampulUrl,
            'noIndex' => $article['status'] !== 'terbit',
            'activeMenu' => 'berita',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'articleSchema' => $articleSchema,
        ]);
    }

    public function ekstrakurikuler(): void
    {
        $konten = $this->baseKonten();

        View::render('public/ekstrakurikuler', [
            'pageTitle' => $this->seo($konten, 'ekskul_title', 'Ekstrakurikuler · SMA Swadaya'),
            'metaDesc' => $this->seo($konten, 'ekskul_desc', 'Kegiatan ekstrakurikuler di SMA Swadaya.'),
            'canonicalUrl' => Helpers::fullUrl('/ekstrakurikuler'),
            'activeMenu' => 'ekskul',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'ekskul' => $konten['data_ekskul'] ?? [],
        ]);
    }

    public function alumni(array $old = [], array $errors = []): void
    {
        Helpers::startSecureSession();
        $konten = $this->baseKonten();
        $terkirim = !empty($_SESSION['cerita_alumni_terkirim']);
        unset($_SESSION['cerita_alumni_terkirim']);

        View::render('public/alumni', [
            'pageTitle' => $this->seo($konten, 'alumni_title', 'Cerita Alumni · SMA Swadaya'),
            'metaDesc' => $this->seo($konten, 'alumni_desc', 'Kisah lulusan SMA Swadaya.'),
            'canonicalUrl' => Helpers::fullUrl('/alumni'),
            'activeMenu' => 'alumni',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'alumni' => ContentRepository::getApprovedAlumni(),
            'old' => $old,
            'errors' => $errors,
            'terkirim' => $terkirim,
        ]);
    }

    /**
     * POST /alumni: cerita masuk sebagai "menunggu", tampil setelah disetujui di Area Admin
     */
    public function kirimCeritaAlumni(): void
    {
        Helpers::startSecureSession();
        $old = [
            'nama' => trim((string) ($_POST['nama'] ?? '')),
            'tahun_lulus' => preg_replace('/\D+/', '', (string) ($_POST['tahun_lulus'] ?? '')),
            'kegiatan' => (string) ($_POST['kegiatan'] ?? ''),
            'tempat' => trim((string) ($_POST['tempat'] ?? '')),
            'kontak' => trim((string) ($_POST['kontak'] ?? '')),
            'cerita' => trim((string) ($_POST['cerita'] ?? '')),
            'setuju' => !empty($_POST['setuju']) ? '1' : '',
        ];
        $errors = [];
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if (!empty($_POST['website_url'])) {
            http_response_code(400);
            $this->alumni($old, ['form' => 'Permintaan tidak dapat diproses.']);
            return;
        }
        if (!Helpers::verifyCsrf($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            $this->alumni($old, ['form' => 'Sesi formulir kedaluwarsa. Silakan kirim ulang.']);
            return;
        }
        if (!PendaftaranController::checkRateLimit($ip, 'alumni')) {
            http_response_code(429);
            $this->alumni($old, ['form' => 'Terlalu banyak kiriman dari jaringan ini. Coba lagi 1 jam lagi.']);
            return;
        }

        if (!\App\Services\Turnstile::verify($ip)) {
            http_response_code(422);
            $this->alumni($old, ['form' => 'Verifikasi keamanan belum berhasil. Centang kotak verifikasi lalu kirim lagi.']);
            return;
        }

        $len = mb_strlen($old['nama']);
        if ($len < 3 || $len > 100) {
            $errors['nama'] = 'Nama lengkap wajib diisi (3–100 huruf).';
        }
        $tahun = (int) $old['tahun_lulus'];
        if (!preg_match('/^\d{4}$/', $old['tahun_lulus']) || $tahun < 1970 || $tahun > (int) date('Y')) {
            $errors['tahun_lulus'] = 'Tahun lulus wajib 4 digit, misalnya 2018.';
        }
        if (!in_array($old['kegiatan'], ['studi', 'karier'], true)) {
            $errors['kegiatan'] = 'Pilih kegiatan saat ini.';
        }
        if (mb_strlen($old['tempat']) > 150) {
            $errors['tempat'] = 'Maks. 150 karakter.';
        }
        $kontakDigits = (string) preg_replace('/\D+/', '', $old['kontak']);
        if (!filter_var($old['kontak'], FILTER_VALIDATE_EMAIL) && !preg_match('/^(08|62)\d{8,12}$/', $kontakDigits)) {
            $errors['kontak'] = 'Isi nomor WhatsApp (08xx) atau alamat email yang valid.';
        }
        $ceritaLen = mb_strlen($old['cerita']);
        if ($ceritaLen < 20 || $ceritaLen > 1500) {
            $errors['cerita'] = 'Cerita singkat 20–1.500 karakter.';
        }
        if ($old['setuju'] !== '1') {
            $errors['setuju'] = 'Centang persetujuan agar tim sekolah boleh menghubungimu.';
        }

        if ($errors) {
            http_response_code(422);
            $this->alumni($old, $errors);
            return;
        }

        $now = date('Y-m-d H:i:s');
        Database::connection()->prepare(
            "INSERT INTO cerita_alumni (nama, tahun_lulus, kegiatan, tempat, kontak, cerita, status, ip_address, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'menunggu', ?, ?, ?)"
        )->execute([$old['nama'], $old['tahun_lulus'], $old['kegiatan'], $old['tempat'] !== '' ? $old['tempat'] : null, $old['kontak'], $old['cerita'], $ip, $now, $now]);
        PendaftaranController::recordRateLimitHit($ip, 'alumni');

        $_SESSION['cerita_alumni_terkirim'] = true;
        header('Location: /alumni#cerita', true, 303);
    }

    public function pendaftaranForm(array $old = [], array $errors = []): void
    {
        Helpers::startSecureSession();
        $konten = $this->baseKonten();

        View::render('public/pendaftaran', [
            'pageTitle' => $this->seo($konten, 'pendaftaran_title', 'Formulir PPDB · SMA Swadaya'),
            'metaDesc' => $this->seo($konten, 'pendaftaran_desc', 'Daftar siswa baru SMA Swadaya secara online.'),
            'canonicalUrl' => Helpers::fullUrl('/pendaftaran'),
            'activeMenu' => 'pendaftaran',
            'konten' => $konten,
            'kontak' => $konten['kontak'],
            'old' => $old,
            'errors' => $errors,
            'pilihanEkskul' => PendaftaranController::pilihanEkskul(),
            'hideFloatingWa' => true,
        ]);
    }

    public function kebijakanPrivasi(): void
    {
        $konten = $this->baseKonten();

        View::render('public/kebijakan-privasi', [
            'pageTitle' => 'Kebijakan Privasi & Pelindungan Data Pribadi · SMA Swadaya',
            'metaDesc' => 'Ringkasan kebijakan privasi dan pelindungan data pribadi pendaftar PPDB SMA Swadaya sesuai UU No. 27 Tahun 2022.',
            'canonicalUrl' => Helpers::fullUrl('/kebijakan-privasi'),
            'konten' => $konten,
            'kontak' => $konten['kontak'],
        ]);
    }

    public function sitemapXml(): void
    {
        ContentRepository::promoteScheduledArticles();
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT slug, updated_at, published_at FROM artikel WHERE status = 'terbit' ORDER BY published_at DESC");
        $articles = $stmt ? $stmt->fetchAll() : [];

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
        }

        $urls = [
            ['/', '1.0', 'weekly'],
            ['/profil', '0.8', 'monthly'],
            ['/berita', '0.8', 'weekly'],
            ['/ekstrakurikuler', '0.7', 'monthly'],
            ['/alumni', '0.6', 'monthly'],
            ['/pendaftaran', '0.9', 'weekly'],
        ];

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $priority, $freq]) {
            echo '  <url><loc>' . Helpers::e(Helpers::fullUrl($path)) . "</loc><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
        }
        foreach ($articles as $art) {
            $lastmod = date('Y-m-d', strtotime((string) ($art['updated_at'] ?: $art['published_at'])));
            echo '  <url><loc>' . Helpers::e(Helpers::fullUrl('/berita/' . $art['slug'])) . "</loc><lastmod>{$lastmod}</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>\n";
        }
        echo "</urlset>\n";
    }

    public function notFound(): void
    {
        http_response_code(404);
        $konten = $this->baseKonten();

        View::render('public/404', [
            'pageTitle' => 'Halaman Tidak Ditemukan · SMA Swadaya',
            'metaDesc' => 'Halaman yang Anda cari tidak ditemukan di situs SMA Swadaya.',
            'noIndex' => true,
            'konten' => $konten,
            'kontak' => $konten['kontak'],
        ]);
    }
}
