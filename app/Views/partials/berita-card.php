<?php
/** Kartu berita: $a (baris artikel + kategori_nama), opsional $flat (tanpa garis tepi, untuk latar berwarna) */
use App\Core\Helpers;
use App\Core\View;

$href = '/berita/' . $a['slug'];
?>
<article class="card card-lift<?= !empty($flat) ? ' card--flat' : '' ?>">
  <?= View::partial('partials/foto', [
      'src' => $a['gambar_sampul'] ?? '',
      'alt' => ($a['alt_gambar'] ?? '') ?: $a['judul'],
      'label' => '[Foto berita]',
      'ikon' => '',
      'class' => 'card__media',
      'slot' => '<span class="badge">' . Helpers::e($a['kategori_nama'] ?? 'Berita') . '</span>',
  ]) ?>
  <div class="card__body">
    <span class="card__date"><?= Helpers::icon('calendar', 15) ?><?= Helpers::e(Helpers::tanggalPanjang($a['published_at'] ?? '')) ?></span>
    <h3 class="card__title"><a href="<?= Helpers::e($href) ?>"><?= Helpers::e($a['judul']) ?></a></h3>
    <p class="card__text"><?= Helpers::e($a['ringkasan'] ?? '') ?></p>
    <a class="btn btn--soft btn--sm card__more" href="<?= Helpers::e($href) ?>" aria-label="Baca selengkapnya: <?= Helpers::e($a['judul']) ?>">Baca Selengkapnya<?= Helpers::icon('arrow', 15) ?></a>
  </div>
</article>
