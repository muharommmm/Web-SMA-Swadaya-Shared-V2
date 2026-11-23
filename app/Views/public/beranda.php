<?php
use App\Core\Helpers;
use App\Core\View;

$tahunAjaran = $konten['beranda_banner']['tahun_ajaran'] ?? '';
$ppdbAktif = !isset($konten['beranda_banner']['aktif']) || !empty($konten['beranda_banner']['aktif']);
$kata = array_values(array_filter(array_map('trim', explode(',', (string) ($hero['kata_berganti'] ?? '')))));
$heroFoto = ($hero['foto'] ?? '') ?: '/assets/img/hero-siswa.png';
?>
<!-- HERO -->
<section class="home-hero">
  <div class="wrap home-hero__inner">
    <div class="home-hero__text">
      <?php if ($ppdbAktif && $tahunAjaran !== ''): ?>
        <span class="ppdb-badge rise" style="animation-delay: .1s">PPDB <?= Helpers::e($tahunAjaran) ?> Resmi Dibuka</span>
      <?php endif; ?>
      <h1 class="rise" style="animation-delay: .2s"><?= Helpers::e($hero['judul'] ?? '') ?>
        <?php if ($kata): ?>
          <span class="words" data-words>
            <?php foreach ($kata as $i => $k): ?>
              <span class="word<?= $i === 0 ? ' is-on' : '' ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>><?= Helpers::e($k) ?></span>
            <?php endforeach; ?>
          </span>
        <?php endif; ?>
      </h1>
      <p class="hero__sub rise" style="animation-delay: .35s"><?= Helpers::e($hero['subjudul'] ?? '') ?></p>
      <div class="btn-row rise" style="animation-delay: .5s; align-items: center; gap: 22px">
        <a class="btn btn--primary btn--lg btn-lift" href="/pendaftaran"><?= Helpers::e(($hero['tombol_utama'] ?? '') ?: 'Daftar Sekarang') ?></a>
        <a class="play-link" href="/profil"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span><?= Helpers::e(($hero['tombol_kedua'] ?? '') ?: 'Eksplor Profil') ?></a>
      </div>
    </div>
    <div class="home-hero__art pop" style="animation-delay: .3s">
      <span class="home-hero__disc" aria-hidden="true"></span>
      <svg class="home-hero__star" aria-hidden="true" width="34" height="34" viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z" fill="#F5C518"/></svg>
      <svg class="home-hero__leaf home-hero__leaf--l" aria-hidden="true" viewBox="0 0 100 100"><path d="M50 95C30 70 10 55 15 25c20 10 35 30 35 70z" fill="#10AEAD" opacity=".85"/><path d="M50 95C65 70 85 60 88 30 68 38 52 58 50 95z" fill="#3CC4C3" opacity=".85"/></svg>
      <svg class="home-hero__leaf home-hero__leaf--r" aria-hidden="true" viewBox="0 0 100 100"><path d="M30 95C40 65 60 45 92 40 85 65 65 85 30 95z" fill="#10AEAD" opacity=".85"/></svg>
      <img class="bob" src="<?= Helpers::e($heroFoto) ?>" width="420" height="419" alt="<?= Helpers::e($hero['alt_foto'] ?? '') ?>" fetchpriority="high">
    </div>
  </div>
</section>

<!-- GALERI -->
<?php if ($galeri): ?>
<section class="wrap section stack" aria-labelledby="judul-galeri" style="padding-top: 72px">
  <div class="head-row">
    <div class="head">
      <span class="eyebrow">Galeri dan kegiatan unggulan</span>
      <h2 id="judul-galeri" class="h2">Intip Kegiatan Siswa–Siswi SMA Swadaya</h2>
    </div>
    <div class="carousel-nav">
      <button type="button" data-carousel-prev aria-label="Kegiatan sebelumnya" disabled><?= Helpers::icon('chevron-left', 20) ?></button>
      <button type="button" data-carousel-next aria-label="Kegiatan berikutnya"><?= Helpers::icon('chevron-right', 20) ?></button>
    </div>
  </div>
  <div class="carousel" data-carousel tabindex="0" aria-label="Galeri kegiatan, geser untuk melihat lainnya">
    <div class="carousel__track">
      <?php foreach ($galeri as $g): ?>
        <article class="shade carousel__item card-lift<?= !empty($g['unggulan']) ? ' is-fav' : '' ?>">
          <?= View::partial('partials/foto', ['src' => $g['foto'] ?? '', 'alt' => $g['judul'] ?? '', 'label' => '[Foto kegiatan]']) ?>
          <?php if (!empty($g['unggulan'])): ?><span class="fav-label">TERFAVORIT</span><?php endif; ?>
          <div class="shade__cap">
            <div class="head" style="gap: 4px; min-width: 0">
              <h3><?= Helpers::e($g['judul'] ?? '') ?></h3>
              <span><?= Helpers::e($g['label'] ?? '') ?></span>
            </div>
            <?php if (!empty($g['tautan'])): ?>
              <a class="play" href="<?= Helpers::e($g['tautan']) ?>" target="_blank" rel="noopener" aria-label="Putar video <?= Helpers::e($g['judul'] ?? '') ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- SAMBUTAN -->
<section aria-labelledby="judul-sambutan">
  <div class="wrap section split" style="padding-top: 24px; padding-bottom: 104px; justify-content: space-between">
    <div class="head" style="gap: 20px; max-width: 560px">
      <span class="tag">Sambutan kepala sekolah</span>
      <h2 id="judul-sambutan" class="h2 h2--xl">Sepatah Kata Kepala Sekolah</h2>
      <?php if (!empty($sambutan['salam'])): ?><p class="salam"><?= Helpers::e($sambutan['salam']) ?></p><?php endif; ?>
      <p class="lead" style="line-height: 1.75"><?= Helpers::e($sambutan['isi_sambutan'] ?? '') ?></p>
      <a class="btn btn--primary btn-lift" style="align-self: flex-start" href="/profil#pendidik">Lihat Profil dan Tenaga Pendidik<?= Helpers::icon('arrow') ?></a>
    </div>
    <div class="sambutan-card card-lift">
      <div class="sambutan-card__top">
        <?= View::partial('partials/foto', [
            'src' => $sambutan['foto_ketua'] ?? '',
            'alt' => $sambutan['nama_ketua'] ?? 'Kepala sekolah',
            'label' => '[Foto]',
            'ikon' => 'user',
            'class' => 'teacher__photo',
        ]) ?>
        <div class="sambutan-card__info">
          <span class="sambutan-card__tag">Pimpinan sekolah</span>
          <strong><?= Helpers::e($sambutan['nama_ketua'] ?? '') ?></strong>
          <span><?= Helpers::e($sambutan['jabatan'] ?? 'Kepala Sekolah') ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TENAGA PENDIDIK -->
<?php if ($guruBeranda): ?>
<section id="pendidik" class="bg-soft" aria-labelledby="judul-pendidik">
  <div class="wrap section stack" style="align-items: center; gap: 40px">
    <div class="head head--center" style="gap: 12px">
      <span class="tag" style="align-self: center">Tenaga pendidik</span>
      <h2 id="judul-pendidik" class="h2 h2--xl">Tumbuh bersama kami</h2>
      <p class="lead">Tenaga pendidik profesional dan bersertifikasi</p>
    </div>
    <div class="grid grid--2" style="width: 100%">
      <?php foreach ($guruBeranda as $g): ?>
        <article class="teacher card-lift">
          <?= View::partial('partials/foto', ['src' => $g['foto'] ?? '', 'alt' => $g['nama'] ?? '', 'label' => '[Foto]', 'ikon' => '', 'class' => 'teacher__photo']) ?>
          <div class="teacher__body">
            <h3><?= Helpers::e($g['nama'] ?? '') ?></h3>
            <span class="teacher__role"><?= Helpers::e($g['peran'] ?? '') ?></span>
            <?php if (!empty($g['ringkasan'])): ?><p><?= Helpers::e($g['ringkasan']) ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <a class="btn btn--primary btn-lift" href="/profil#pendidik">Lihat Profil Pengajar</a>
  </div>
</section>
<?php endif; ?>

<!-- BERITA -->
<section class="bg-accent" aria-labelledby="judul-berita">
  <span class="bg-accent__c1" aria-hidden="true"></span><span class="bg-accent__c2" aria-hidden="true"></span>
  <div class="wrap section stack" style="position: relative; align-items: center; gap: 40px">
    <div class="head head--center">
      <span class="tag tag--glass" style="align-self: center">Artikel &amp; Kegiatan</span>
      <h2 id="judul-berita" class="h2 h2--xl">Berita &amp; Kegiatan Terkini</h2>
      <p style="font-size: 17px; line-height: 1.65">Ikuti ragam inovasi, prestasi siswa, dan dinamika pembelajaran aplikatif terbaru di lingkungan SMA Swadaya Bandung.</p>
    </div>
    <?php if ($latestArticles): ?>
      <div class="grid" style="width: 100%; color: var(--ink)">
        <?php foreach ($latestArticles as $a): ?>
          <?= View::partial('partials/berita-card', ['a' => $a, 'flat' => true]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <a class="btn btn--white btn-lift" href="/berita">Lihat Semua Berita</a>
  </div>
</section>

<!-- ALUMNI -->
<?php if ($alumni): ?>
<section aria-labelledby="judul-alumni">
  <div class="wrap section stack" style="align-items: center; gap: 40px">
    <div class="head head--center">
      <span class="tag" style="align-self: center">Testimoni &amp; Jejak Alumni</span>
      <h2 id="judul-alumni" class="h2 h2--xl">Kisah Inspiratif &amp; Prestasi Alumni</h2>
      <p class="lead">Dengarkan pengalaman lulusan SMA Swadaya yang kini telah menempuh studi di sekolah dan perguruan tinggi unggulan, serta berkarier di berbagai industri.</p>
    </div>
    <div class="grid" style="width: 100%">
      <?php foreach ($alumni as $i => $al): ?>
        <?= View::partial('partials/alumni-card', ['al' => $al]) ?>
      <?php endforeach; ?>
    </div>
    <a class="btn btn--outline btn-lift" href="/alumni">Lihat Apa Kata Mereka<?= Helpers::icon('arrow') ?></a>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<?php require dirname(__DIR__) . '/partials/cta.php'; ?>
