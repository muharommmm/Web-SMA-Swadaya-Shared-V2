<?php
use App\Core\Helpers;

$kontak = $kontak ?? ($konten['kontak'] ?? []);
$namaLembaga = $kontak['nama_lembaga'] ?? 'SMA Swadaya';
$pageTitle = $pageTitle ?? ($namaLembaga . ' · Sekolah Menengah Atas');
$metaDesc = $metaDesc ?? 'Sekolah Menengah Atas Swadaya. Info sekolah, kegiatan, dan pendaftaran siswa baru online.';
$canonicalUrl = $canonicalUrl ?? Helpers::fullUrl($_SERVER['REQUEST_URI'] ?? '/');
$ogType = $ogType ?? 'website';

// Gambar pratinjau WhatsApp/Facebook: sampul artikel (dari controller), lalu foto hero, terakhir logo
$heroKonten = $konten['beranda_hero'] ?? [];
$ogImage = ($ogImage ?? '') ?: (($heroKonten['foto_url'] ?? '') ?: (($heroKonten['foto'] ?? '') ?: '/assets/img/logo.png'));
$ogImage = str_starts_with($ogImage, 'http') ? $ogImage : Helpers::fullUrl($ogImage);

// Nomor WhatsApp lembaga sekaligus telepon, dalam format internasional untuk Google
$waDigits = preg_replace('/^0/', '62', (string) preg_replace('/\D+/', '', (string) ($kontak['whatsapp_nomor'] ?? '')));

$orgSchema = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'EducationalOrganization',
    'name' => $namaLembaga,
    'identifier' => $kontak['npsn'] ?? '-',
    'url' => Helpers::fullUrl('/'),
    'logo' => Helpers::fullUrl('/assets/img/logo.png'),
    'email' => $kontak['email'] ?? 'support@sma.swadaya.sch.id',
    'telephone' => $waDigits !== '' ? '+' . $waDigits : null,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $kontak['alamat_lengkap'] ?? '',
        'addressCountry' => 'ID',
    ],
    'sameAs' => array_values(array_filter([$kontak['facebook_url'] ?? '', $kontak['instagram_url'] ?? '', $kontak['youtube_url'] ?? ''])),
], static fn($v) => $v !== null && $v !== []);

// Breadcrumb Google: Beranda > halaman (> judul artikel). Halaman noindex & kategori dilewati.
$breadcrumbSchema = null;
$namaHalaman = ['profil' => 'Profil', 'berita' => 'Berita & Artikel', 'ekstrakurikuler' => 'Ekstrakurikuler', 'alumni' => 'Alumni', 'pendaftaran' => 'Formulir PPDB', 'kebijakan-privasi' => 'Kebijakan Privasi'];
$segmen = explode('/', trim((string) parse_url($canonicalUrl, PHP_URL_PATH), '/'));
if (empty($noIndex) && isset($namaHalaman[$segmen[0]]) && ($segmen[1] ?? '') !== 'kategori') {
    $crumbs = [['Beranda', '/'], [$namaHalaman[$segmen[0]], '/' . $segmen[0]]];
    if (!empty($article['judul'])) {
        $crumbs[] = [$article['judul'], '/berita/' . $article['slug']];
    }
    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
    foreach ($crumbs as $i => [$nama, $url]) {
        $breadcrumbSchema['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $nama, 'item' => Helpers::fullUrl($url)];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>document.documentElement.className += ' js';</script>
  <meta name="theme-color" content="#10AEAD">
  <title><?= Helpers::e($pageTitle) ?></title>
  <meta name="description" content="<?= Helpers::e($metaDesc) ?>">
  <?php if (!empty($noIndex)): ?>
  <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <!-- Favicon & Ikon Tab Resmi -->
  <link rel="icon" type="image/png" sizes="64x64" href="/assets/img/favicon.png?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/img/favicon.png') ?: '1' ?>">
  <link rel="shortcut icon" href="/favicon.ico?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/img/favicon.png') ?: '1' ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/img/favicon.png') ?: '1' ?>">
  <link rel="canonical" href="<?= Helpers::e($canonicalUrl) ?>">

  <!-- Open Graph / WhatsApp Preview -->
  <meta property="og:locale" content="id_ID">
  <meta property="og:type" content="<?= Helpers::e($ogType) ?>">
  <meta property="og:site_name" content="<?= Helpers::e($namaLembaga) ?>">
  <meta property="og:title" content="<?= Helpers::e($pageTitle) ?>">
  <meta property="og:description" content="<?= Helpers::e($metaDesc) ?>">
  <meta property="og:url" content="<?= Helpers::e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= Helpers::e($ogImage) ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= Helpers::e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= Helpers::e($metaDesc) ?>">
  <meta name="twitter:image" content="<?= Helpers::e($ogImage) ?>">

  <!-- Preload Font Utama -->
  <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/css/site.css?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/css/site.css') ?: '1' ?>">

  <!-- JSON-LD EducationalOrganization -->
  <script type="application/ld+json"><?= json_encode($orgSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php if (!empty($faqSchema)): ?>
  <script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php endif; ?>
  <?php if ($breadcrumbSchema): ?>
  <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php endif; ?>
  <?php if (!empty($articleSchema)): ?>
  <script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php endif; ?>
</head>
<body>
  <a class="sr-only skip-link" href="#isi">Langsung ke isi halaman</a>
  <?php require dirname(__DIR__) . '/partials/header.php'; ?>
  <main id="isi">
    <?= $content ?>
  </main>
  <?php require dirname(__DIR__) . '/partials/footer.php'; ?>
  <?php if (empty($hideFloatingWa) && !empty($kontak['whatsapp_nomor'])): ?>
  <a class="wa-float no-print" href="<?= Helpers::e($kontak['whatsapp_link'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Chat WhatsApp SMA Swadaya"><?= Helpers::icon('wa', 28) ?></a>
  <?php endif; ?>
  <script src="/assets/js/app.js?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/js/app.js') ?: '2' ?>" defer></script>
</body>
</html>
