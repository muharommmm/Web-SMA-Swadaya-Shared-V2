<?php
use App\Core\Helpers;

$heroCrumb = 'Formulir PPDB';
$heroTitle = 'Pendaftaran berhasil dikirim';
$heroSub = 'Terima kasih, ' . ($hasil['nama'] ?? '') . '. Data dan berkas sudah kami terima.';
require dirname(__DIR__) . '/partials/page-hero.php';
?>
<section class="wrap overlap" style="padding-bottom: 96px">
  <div class="panel notice-ok rise" role="status" style="max-width: 760px; margin: 0 auto; padding: clamp(28px, 4vw, 48px)">
    <span class="notice-ok__icon"><?= Helpers::icon('check', 30) ?></span>
    <h2 style="font-size: 24px; font-weight: 700">Simpan nomor pendaftaran Anda</h2>
    <p class="muted" style="max-width: 520px; font-size: 15.5px; line-height: 1.6">Panitia PPDB akan memverifikasi berkas dan menghubungi Anda lewat WhatsApp. Bukti pendaftaran juga dikirim ke email bila Anda mengisinya.</p>
    <div class="done-num"><span>Nomor pendaftaran</span><strong><?= Helpers::e($hasil['nomor'] ?? '') ?></strong></div>
    <div class="btn-row" style="justify-content: center">
      <a class="btn btn--primary btn--lg btn-lift" href="/pendaftaran/bukti/<?= Helpers::e($hasil['token'] ?? '') ?>"><?= Helpers::icon('print') ?>Cetak Bukti Pendaftaran</a>
      <a class="btn btn--outline btn--lg" href="/">Kembali ke Beranda</a>
    </div>
  </div>
</section>
