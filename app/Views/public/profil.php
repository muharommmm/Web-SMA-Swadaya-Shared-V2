<?php
use App\Core\Helpers;
use App\Core\View;

$heroCrumb = 'Profil';
$heroTag = 'Profil sekolah';
$heroTitle = $tentang['hero_judul'] ?? 'Profil SMA Swadaya';
$heroSub = $tentang['hero_subjudul'] ?? '';
require dirname(__DIR__) . '/partials/page-hero.php';

$identitas = [
    'NPSN' => $kontak['npsn'] ?? '',
    'Akreditasi' => $tentang['akreditasi'] ?? '',
    'Kurikulum' => $tentang['kurikulum'] ?? '',
    'Berdiri sejak' => $tentang['tahun_berdiri'] ?? '',
];
$misi = array_values(array_filter((array) ($visiMisi['misi'] ?? [])));
?>
<!-- IDENTITAS -->
<section class="wrap overlap" aria-label="Identitas sekolah">
  <dl class="panel identity rise" style="animation-delay: .4s; margin: 0">
    <?php foreach ($identitas as $label => $nilai): ?>
      <div><dt><?= Helpers::e($label) ?></dt><dd><?= Helpers::e($nilai ?: '-') ?></dd></div>
    <?php endforeach; ?>
  </dl>
</section>

<!-- TENTANG -->
<section class="wrap section--top split" aria-labelledby="judul-tentang">
  <?= View::partial('partials/foto', ['src' => $tentang['foto_gedung'] ?? '', 'alt' => 'Gedung SMA Swadaya', 'label' => '[Foto gedung sekolah]', 'class' => 'portrait portrait--wide', 'eager' => true]) ?>
  <div class="head" style="gap: 18px">
    <span class="eyebrow">Tentang kami</span>
    <h2 id="judul-tentang" class="h2"><?= Helpers::e($tentang['tentang_judul'] ?? '') ?></h2>
    <?php foreach (['tentang_paragraf_1', 'tentang_paragraf_2'] as $p): ?>
      <?php if (!empty($tentang[$p])): ?><p class="lead" style="line-height: 1.75"><?= Helpers::e($tentang[$p]) ?></p><?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($tentang['motto'])): ?><span class="motto">Motto: <?= Helpers::e($tentang['motto']) ?></span><?php endif; ?>
  </div>
</section>

<!-- VISI MISI -->
<section class="wrap section--top grid grid--2" aria-label="Visi dan misi">
  <div class="visi">
    <svg class="deco" aria-hidden="true" viewBox="0 0 600 400" preserveAspectRatio="none"><path d="M0 300 C 150 240, 300 360, 450 280 S 600 220, 600 250" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="2"/></svg>
    <span class="eyebrow" style="color: #FFFFFF; opacity: .85">Visi</span>
    <p><?= Helpers::e($visiMisi['visi'] ?? '') ?></p>
  </div>
  <div class="misi">
    <span class="eyebrow">Misi</span>
    <ol class="numbered">
      <?php foreach ($misi as $i => $m): ?>
        <li><span class="num"><?= $i + 1 ?></span><?= Helpers::e($m) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<!-- SAMBUTAN -->
<section class="bg-soft" style="margin-top: 96px" aria-labelledby="judul-sambutan">
  <div class="wrap section split" style="padding-top: 88px; padding-bottom: 88px">
    <?= View::partial('partials/foto', [
        'src' => $sambutan['foto_ketua'] ?? '',
        'alt' => $sambutan['nama_ketua'] ?? 'Kepala sekolah',
        'label' => '[Foto kepala sekolah]',
        'ikon' => 'user',
        'class' => 'portrait portrait--tall',
        'slot' => '<span class="name-chip">' . Helpers::e($sambutan['nama_ketua'] ?? '') . '</span>',
    ]) ?>
    <div class="head" style="gap: 18px">
      <span id="judul-sambutan" class="eyebrow">Sambutan kepala sekolah</span>
      <?php if (!empty($sambutan['kutipan'])): ?><p class="quote-lg"><?= Helpers::e($sambutan['kutipan']) ?></p><?php endif; ?>
      <?php foreach (['isi_sambutan', 'isi_lanjutan'] as $p): ?>
        <?php if (!empty($sambutan[$p])): ?><p class="lead" style="line-height: 1.75"><?= Helpers::e($sambutan[$p]) ?></p><?php endif; ?>
      <?php endforeach; ?>
      <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 6px">
        <strong style="font-size: 17px"><?= Helpers::e($sambutan['nama_ketua'] ?? '') ?></strong>
        <span style="font-size: 14px; color: var(--muted-2)"><?= Helpers::e($sambutan['jabatan'] ?? '') ?></span>
      </span>
    </div>
  </div>
</section>

<!-- GURU -->
<?php if ($tim): ?>
<section id="pendidik" class="wrap section--top stack" aria-labelledby="judul-guru">
  <div class="head-row">
    <div class="head">
      <span class="eyebrow">Guru &amp; tenaga pendidik</span>
      <h2 id="judul-guru" class="h2">Tumbuh bersama kami</h2>
    </div>
    <p class="muted" style="font-size: 16px">Tenaga pendidik profesional dan bersertifikasi</p>
  </div>
  <div class="grid grid--4">
    <?php foreach ($tim as $g): ?>
      <article class="card teacher-card card-lift" style="gap: 14px">
        <?= View::partial('partials/foto', ['src' => $g['foto'] ?? '', 'alt' => $g['nama'] ?? '', 'label' => '[Foto]', 'ikon' => '']) ?>
        <div class="card__body" style="padding: 0 8px; gap: 4px">
          <h3><?= Helpers::e($g['nama'] ?? '') ?></h3>
          <span><?= Helpers::e($g['peran'] ?? '') ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- FASILITAS -->
<?php if (!empty($fasilitas['items'])): ?>
<section class="wrap section--top stack" aria-labelledby="judul-fasilitas">
  <div class="head">
    <span class="eyebrow">Fasilitas</span>
    <h2 id="judul-fasilitas" class="h2"><?= Helpers::e($fasilitas['judul_seksi'] ?? 'Fasilitas sekolah') ?></h2>
  </div>
  <div class="grid grid--4">
    <?php foreach ($fasilitas['items'] as $f): ?>
      <article class="card card-lift">
        <?= View::partial('partials/foto', ['src' => $f['foto'] ?? '', 'alt' => $f['nama'] ?? '', 'label' => '[Foto fasilitas]', 'ikon' => '', 'class' => 'card__media']) ?>
        <div class="card__body">
          <h3 class="card__title"><?= Helpers::e($f['nama'] ?? '') ?></h3>
          <p class="card__text"><?= Helpers::e($f['deskripsi'] ?? '') ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- LOKASI -->
<section class="wrap section--top split" style="gap: 40px 56px" aria-labelledby="judul-lokasi">
  <div class="head" style="flex: 1 1 340px; gap: 16px">
    <span class="eyebrow">Lokasi</span>
    <h2 id="judul-lokasi" class="h2">Kunjungi sekolah kami</h2>
    <p class="lead"><?= Helpers::e($kontak['alamat_lengkap'] ?? '') ?></p>
    <?php if (!empty($kontak['google_maps_url'])): ?>
      <a class="btn btn--primary btn-lift" style="align-self: flex-start" href="<?= Helpers::e($kontak['google_maps_url']) ?>" target="_blank" rel="noopener">Buka di Google Maps</a>
    <?php endif; ?>
  </div>
  <div class="ph map" id="lazy-gmaps" data-src="<?= Helpers::e($kontak['google_maps_embed'] ?? '') ?>">
    <?= Helpers::icon('pin', 34) ?>
    [Peta Google Maps]
  </div>
</section>

<?php require dirname(__DIR__) . '/partials/cta.php'; ?>
