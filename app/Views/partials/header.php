<?php
use App\Core\Helpers;

$activeMenu = $activeMenu ?? '';
$kontak = $kontak ?? ($konten['kontak'] ?? []);
$cbtUrl = (string) ($kontak['portal_siswa_url'] ?? '');
$waLink = (string) ($kontak['whatsapp_link'] ?? '');
$menu = [
    'profil' => ['/profil', 'Profil'],
    'berita' => ['/berita', 'Berita & Artikel'],
    'ekskul' => ['/ekstrakurikuler', 'Ekstrakurikuler'],
    'alumni' => ['/alumni', 'Alumni'],
];
$current = static fn(string $key): string => $activeMenu === $key ? ' aria-current="page"' : '';
?>
<header class="site-header no-print">
  <div class="wrap site-header__bar">
    <a class="brand" href="/">
      <img src="/assets/img/logo-sma.png" width="34" height="30" alt="Logo SMA Swadaya">
      <span>SMA Swadaya</span>
    </a>

    <nav class="site-nav" aria-label="Menu utama">
      <?php foreach ($menu as $key => [$href, $label]): ?>
        <a class="nav-link" href="<?= $href ?>"<?= $current($key) ?>><?= Helpers::e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($cbtUrl !== ''): ?>
        <a class="nav-link nav-link--cbt" href="<?= Helpers::e($cbtUrl) ?>" target="_blank" rel="noopener">Portal CBT</a>
      <?php endif; ?>
    </nav>

    <?php if ($waLink !== ''): ?>
      <a class="btn btn--primary btn--cta btn-lift" href="<?= Helpers::e($waLink) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa') ?>Konsultasi</a>
    <?php endif; ?>

    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu-utama" aria-label="Buka menu">
      <span class="ic-open"><?= Helpers::icon('menu', 22) ?></span>
      <span class="ic-close"><?= Helpers::icon('close', 22) ?></span>
    </button>
  </div>

  <nav id="menu-utama" class="site-menu" aria-label="Menu utama">
    <a class="menu-link" href="/"<?= $current('beranda') ?>>Beranda</a>
    <?php foreach ($menu as $key => [$href, $label]): ?>
      <a class="menu-link" href="<?= $href ?>"<?= $current($key) ?>><?= Helpers::e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($cbtUrl !== ''): ?>
      <a class="menu-link" href="<?= Helpers::e($cbtUrl) ?>" target="_blank" rel="noopener">Portal CBT<?= Helpers::icon('external') ?></a>
    <?php endif; ?>
    <div class="site-menu__actions">
      <?php if ($activeMenu !== 'pendaftaran'): ?>
        <a class="btn btn--primary" href="/pendaftaran">Daftar PPDB</a>
      <?php endif; ?>
      <?php if ($waLink !== ''): ?>
        <a class="btn btn--outline" href="<?= Helpers::e($waLink) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa') ?>Konsultasi via WhatsApp</a>
      <?php endif; ?>
    </div>
  </nav>
</header>
