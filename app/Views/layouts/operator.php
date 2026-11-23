<?php
use App\Core\Database;
use App\Core\Helpers;

$pageTitle = $pageTitle ?? 'Area Admin · SMA Swadaya';
$activeNav = $activeNav ?? ($activeMenu ?? 'dashboard');
$flash = $flash ?? null;

// Lencana jumlah di menu: pendaftar baru & cerita alumni menunggu persetujuan
$pdoNav = Database::connection();
$navBaru = (int) $pdoNav->query("SELECT COUNT(*) FROM pendaftar WHERE status_pendaftaran = 'baru'")->fetchColumn();
$navAlumni = (int) $pdoNav->query("SELECT COUNT(*) FROM cerita_alumni WHERE status = 'menunggu'")->fetchColumn();

$ic = [
    'dashboard' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    'pendaftar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c.8-3.8 3.6-6 7-6s6.2 2.2 7 6M19 8v6M16 11h6"/></svg>',
    'artikel' => Helpers::icon('file', 20),
    'konten' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h8"/></svg>',
    'alumni' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>',
    'akun' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.3Z"/></svg>',
    'situs' => Helpers::icon('external', 20),
    'keluar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/></svg>',
];
$menu = [
    'dashboard' => ['/operator', 'Dashboard', 0],
    'pendaftar' => ['/operator/pendaftar', 'Pendaftar PPDB', $navBaru],
    'artikel' => ['/operator/artikel', 'Berita & Artikel', 0],
    'konten' => ['/operator/konten', 'Konten Situs', 0],
    'alumni' => ['/operator/alumni', 'Cerita Alumni', $navAlumni],
    'akun' => ['/operator/akun', 'Pengaturan', 0],
];
$cur = static fn(string $k): string => $activeNav === $k ? ' aria-current="page"' : '';
$asset = static fn(string $p): string => $p . '?v=' . (@filemtime(\App\Core\Helpers::webDir() . $p) ?: '1');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::e($pageTitle) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#10AEAD">
  <link rel="icon" type="image/png" sizes="64x64" href="/assets/img/favicon.png?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/img/favicon.png') ?: '1' ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
  <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= $asset('/assets/css/site.css') ?>">
  <link rel="stylesheet" href="<?= $asset('/assets/css/admin.css') ?>">
</head>
<body class="admin">
  <header class="m-top">
    <img src="/assets/img/logo-sma.png" width="34" height="30" alt="Logo SMA Swadaya">
    <span><strong>SMA Swadaya</strong><small>Area admin</small></span>
    <a href="/" target="_blank" rel="noopener" aria-label="Lihat situs"><?= $ic['situs'] ?></a>
  </header>

  <div class="admin-shell">
    <aside class="side" aria-label="Menu admin">
      <a class="side__brand" href="/operator">
        <img src="/assets/img/logo-sma.png" width="36" height="32" alt="Logo SMA Swadaya">
        <span><strong>SMA Swadaya</strong><small>Area admin</small></span>
      </a>
      <nav>
        <?php foreach ($menu as $key => [$href, $label, $badge]): ?>
          <a class="side-link" href="<?= $href ?>"<?= $cur($key) ?>><?= $ic[$key] ?><span><?= Helpers::e($label) ?></span><?php if ($badge > 0): ?><b class="count-pill<?= $key === 'alumni' ? ' count-pill--soft' : '' ?>"><?= $badge ?></b><?php endif; ?></a>
        <?php endforeach; ?>
      </nav>
      <nav class="side__sep">
        <a class="side-link" href="/" target="_blank" rel="noopener"><?= $ic['situs'] ?><span>Lihat situs</span></a>
        <a class="side-link" href="/operator/logout"><?= $ic['keluar'] ?><span>Keluar</span></a>
      </nav>
    </aside>

    <main class="admin-main" id="isi">
      <?php if (!empty($flash['message'])): ?>
        <div class="flash flash--<?= ($flash['type'] ?? '') === 'error' ? 'err' : 'ok' ?>" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
          <?php if (($flash['type'] ?? '') !== 'error'): ?><?= Helpers::icon('check') ?><?php endif; ?>
          <span><?= Helpers::e($flash['message']) ?></span>
          <?php if (!empty($flash['link'])): ?><a class="btn btn--wa btn--sm" style="margin-left: auto" href="<?= Helpers::e($flash['link']) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa', 16) ?>Kirim lewat WhatsApp</a><?php endif; ?>
        </div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>

  <div class="m-sheet" id="menu-lainnya">
    <a href="/operator/alumni"><?= $ic['alumni'] ?><span>Cerita Alumni</span><?php if ($navAlumni): ?><b class="count-pill count-pill--soft"><?= $navAlumni ?></b><?php endif; ?></a>
    <a href="/operator/akun"><?= $ic['akun'] ?><span>Pengaturan</span></a>
    <a href="/" target="_blank" rel="noopener"><?= $ic['situs'] ?><span>Lihat situs</span></a>
    <a href="/operator/logout"><?= $ic['keluar'] ?><span>Keluar</span></a>
  </div>
  <nav class="tabbar" aria-label="Menu admin">
    <?php foreach (['dashboard' => 'Dashboard', 'pendaftar' => 'Pendaftar', 'artikel' => 'Berita', 'konten' => 'Konten'] as $key => $label): ?>
      <a href="<?= $menu[$key][0] ?>"<?= $cur($key) ?>><i><?= $ic[$key] ?><?php if ($menu[$key][2] > 0): ?><b><?= $menu[$key][2] ?></b><?php endif; ?></i><?= $label ?></a>
    <?php endforeach; ?>
    <button type="button" aria-expanded="false" aria-controls="menu-lainnya" data-sheet-toggle><i><?= Helpers::icon('menu', 20) ?><?php if ($navAlumni): ?><b><?= $navAlumni ?></b><?php endif; ?></i>Lainnya</button>
  </nav>

  <script>
  (function () {
    var btn = document.querySelector('[data-sheet-toggle]');
    var sheet = document.getElementById('menu-lainnya');
    if (!btn || !sheet) return;
    var set = function (open) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); sheet.classList.toggle('is-open', open); };
    btn.addEventListener('click', function () { set(btn.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  })();
  </script>
</body>
</html>
