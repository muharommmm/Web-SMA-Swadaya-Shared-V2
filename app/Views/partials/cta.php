<?php
/** CTA "Siap Bergabung" di bawah halaman dalam */
use App\Core\Helpers;

$kontak = $kontak ?? ($konten['kontak'] ?? []);
$tahunAjaran = $konten['beranda_banner']['tahun_ajaran'] ?? '';
?>
<section class="wrap section no-print">
  <div class="cta-box">
    <div class="head" style="position: relative; max-width: 560px; gap: 14px">
      <h2>Bergabung bersama SMA Swadaya Sekarang!</h2>
      <p style="font-size: 16px; line-height: 1.65">PPDB <?= Helpers::e($tahunAjaran) ?> sudah dibuka. Konsultasikan pilihan terbaik untuk anak Anda.</p>
    </div>
    <div class="btn-row" style="position: relative">
      <?php if (!empty($kontak['whatsapp_link'])): ?>
        <a class="btn btn--ghost-white btn--lg btn-lift" href="<?= Helpers::e($kontak['whatsapp_link']) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa', 18) ?>Konsultasi WA</a>
      <?php endif; ?>
      <a class="btn btn--white btn--lg btn-lift" href="/pendaftaran">Isi Form Pendaftaran</a>
    </div>
  </div>
</section>
