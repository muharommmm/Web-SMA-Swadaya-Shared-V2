<?php
use App\Core\Helpers;
use App\Core\View;

$heroCrumb = 'Ekstrakurikuler';
$heroTag = 'Ekstrakurikuler';
$heroTitle = $ekskul['hero_judul'] ?? 'Ekstrakurikuler';
$heroSub = $ekskul['hero_subjudul'] ?? '';
$heroShort = true;
require dirname(__DIR__) . '/partials/page-hero.php';

$items = array_values(array_filter((array) ($ekskul['items'] ?? []), static fn($e) => !empty($e['nama'])));
$kategori = array_values(array_unique(array_filter(array_map(static fn($e) => trim((string) ($e['kategori'] ?? '')), $items))));
?>
<!-- FILTER -->
<section class="wrap" style="padding-top: 48px" aria-label="Filter kategori">
  <div class="rise" style="animation-delay: .4s; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px">
    <div class="chips chips--scroll" role="group" aria-label="Kategori ekstrakurikuler" data-filter-group="ekskul">
      <button class="chip" type="button" data-filter="" aria-pressed="true">Semua</button>
      <?php foreach ($kategori as $k): ?>
        <button class="chip" type="button" data-filter="<?= Helpers::e($k) ?>" aria-pressed="false"><?= Helpers::e($k) ?></button>
      <?php endforeach; ?>
    </div>
    <span class="count" role="status" data-filter-count="ekskul"><?= count($items) ?> kegiatan</span>
  </div>
</section>

<!-- DAFTAR EKSKUL -->
<section class="wrap" style="padding-top: 32px" aria-label="Daftar ekstrakurikuler">
  <div class="grid">
    <?php foreach ($items as $e): ?>
      <article class="card card-lift" data-filter-item="ekskul" data-kategori="<?= Helpers::e($e['kategori'] ?? '') ?>">
        <?= View::partial('partials/foto', [
            'src' => $e['foto'] ?? '',
            'alt' => $e['nama'],
            'label' => '[Foto kegiatan]',
            'ikon' => '',
            'class' => 'card__media',
            'slot' => '<span class="badge badge--white">' . Helpers::e($e['kategori'] ?? '') . '</span>'
                . (!empty($e['wajib']) ? '<span class="badge badge--sun">Wajib</span>' : ''),
        ]) ?>
        <div class="card__body">
          <h3 class="card__title" style="font-size: 19px; line-height: 1.35"><?= Helpers::e($e['nama']) ?></h3>
          <p class="card__text"><?= Helpers::e($e['deskripsi'] ?? '') ?></p>
          <dl class="meta-list">
            <dt>Jadwal</dt><dd><?= Helpers::e(($e['jadwal'] ?? '') ?: '-') ?></dd>
            <dt>Pembina</dt><dd><?= Helpers::e(($e['pembina'] ?? '') ?: '-') ?></dd>
          </dl>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- CARA BERGABUNG -->
<section class="bg-soft" style="margin-top: 96px" aria-labelledby="judul-cara">
  <div class="wrap section stack" style="padding-top: 88px; padding-bottom: 88px">
    <div class="head" style="max-width: 640px">
      <span class="eyebrow">Cara bergabung</span>
      <h2 id="judul-cara" class="h2">Tiga langkah memulai kegiatan</h2>
    </div>
    <ol class="steps-3">
      <li><span class="num">1</span><h3>Kenali pilihan saat MPLS</h3><p>Setiap pembina memperkenalkan kegiatannya kepada siswa baru di awal tahun ajaran.</p></li>
      <li><span class="num">2</span><h3>Daftar ke wali kelas</h3><p>Pilih kegiatan selain Pramuka yang wajib, lalu serahkan formulir pilihan ke wali kelas.</p></li>
      <li><span class="num">3</span><h3>Mulai berlatih</h3><p>Ikuti jadwal rutin dan tunjukkan karyamu di pameran, lomba, atau panen raya sekolah.</p></li>
    </ol>
  </div>
</section>

<?php require dirname(__DIR__) . '/partials/cta.php'; ?>
