<?php
use App\Core\Helpers;

$kontak = $kontak ?? ($konten['kontak'] ?? []);
$namaLembaga = $kontak['nama_lembaga'] ?? 'SMA Swadaya';
$cbtUrl = (string) ($kontak['portal_siswa_url'] ?? '');
$sosial = array_filter([
    'instagram' => $kontak['instagram_url'] ?? '',
    'youtube' => $kontak['youtube_url'] ?? '',
    'facebook' => $kontak['facebook_url'] ?? '',
]);
$program = ['Kurikulum Merdeka P5' => '/profil', 'Laboratorium Sains Terpadu' => '/profil'];
foreach (array_slice($konten['data_ekskul']['items'] ?? [], 0, 3) as $e) {
    $program[(string) ($e['nama'] ?? '')] = '/ekstrakurikuler';
}
?>
<footer class="site-footer no-print">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <span class="footer-brand__id">
          <span class="footer-brand__logo"><img src="/assets/img/logo-sma.png" width="32" height="30" alt=""></span>
          <span><strong><?= Helpers::e($namaLembaga) ?></strong><small><?= Helpers::e($kontak['tagline'] ?? '') ?></small></span>
        </span>
        <span class="footer-cta">
          <?php if (!empty($kontak['whatsapp_link'])): ?>
            <a class="btn btn--sun btn--sm btn-lift" href="<?= Helpers::e($kontak['whatsapp_link']) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa', 16) ?>Konsultasi PPDB</a>
          <?php endif; ?>
          <?php if ($cbtUrl !== ''): ?>
            <a class="btn btn--ghost-white btn--sm btn-lift" href="<?= Helpers::e($cbtUrl) ?>" target="_blank" rel="noopener">Portal CBT</a>
          <?php endif; ?>
        </span>
      </div>

      <nav class="footer-col" aria-label="Sekolah">
        <h2>Sekolah</h2>
        <a href="/">Beranda</a>
        <a href="/profil">Profil</a>
        <a href="/berita">Berita &amp; Artikel</a>
        <a href="/ekstrakurikuler">Ekstrakurikuler</a>
        <a href="/alumni">Alumni</a>
        <a href="/pendaftaran">Formulir PPDB</a>
      </nav>

      <nav class="footer-col" aria-label="Program">
        <h2>Program</h2>
        <?php foreach ($program as $nama => $href): ?>
          <?php if ($nama !== ''): ?><a href="<?= $href ?>"><?= Helpers::e($nama) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </nav>

      <div class="footer-col footer-col--wide">
        <h2>Lokasi &amp; Kontak</h2>
        <?php if (!empty($kontak['alamat_lengkap'])): ?>
          <span class="footer-contact"><?= Helpers::icon('pin') ?><?= Helpers::e($kontak['alamat_lengkap']) ?></span>
        <?php endif; ?>
        <?php if (!empty($kontak['whatsapp_tampil'])): ?>
          <a class="footer-contact" href="<?= Helpers::e($kontak['whatsapp_link'] ?? '#') ?>" target="_blank" rel="noopener"><?= Helpers::icon('phone') ?><?= Helpers::e($kontak['whatsapp_tampil']) ?></a>
        <?php endif; ?>
        <?php if (!empty($kontak['email'])): ?>
          <a class="footer-contact" href="mailto:<?= Helpers::e($kontak['email']) ?>"><?= Helpers::icon('mail') ?><?= Helpers::e($kontak['email']) ?></a>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= Helpers::e($namaLembaga) ?> Bandung. Hak cipta dilindungi. · <a href="/kebijakan-privasi">Kebijakan privasi</a></span>
      <?php if ($sosial): ?>
        <div class="socials">
          <?php foreach ($sosial as $ikon => $url): ?>
            <a href="<?= Helpers::e($url) ?>" target="_blank" rel="noopener" aria-label="<?= ucfirst($ikon) ?> <?= Helpers::e($namaLembaga) ?>"><?= Helpers::icon($ikon) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</footer>
