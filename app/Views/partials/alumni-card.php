<?php
/** Kartu cerita alumni: $al (baris cerita_alumni), opsional $accent (kartu berwarna), $showType (label studi/karier) */
use App\Core\Helpers;

$karier = ($al['kegiatan'] ?? '') === 'karier';
?>
<figure class="alumni-card card-lift<?= !empty($accent) ? ' alumni-card--accent' : '' ?>" data-kegiatan="<?= Helpers::e($al['kegiatan'] ?? '') ?>">
  <span class="avatar">
    <?php if (!empty($al['foto'])): ?>
      <img src="<?= Helpers::e($al['foto']) ?>" alt="" loading="lazy">
    <?php else: ?>
      <?= Helpers::e(Helpers::inisial((string) $al['nama'])) ?>
    <?php endif; ?>
  </span>
  <figcaption>
    <strong><?= Helpers::e($al['nama']) ?></strong>
    <?php if (!empty($al['tempat'])): ?><span><?= Helpers::e($al['tempat']) ?></span><?php endif; ?>
  </figcaption>
  <blockquote>“<?= Helpers::e(trim((string) $al['cerita'], " \"“”")) ?>”</blockquote>
  <span class="pills">
    <span class="angkatan">Angkatan <?= Helpers::e(($al['tahun_lulus'] ?? '') ?: '[tahun]') ?></span>
    <?php if (!empty($showType)): ?>
      <span class="type-tag<?= $karier ? ' type-tag--karier' : '' ?>"><?= $karier ? 'Berkarier' : 'Melanjutkan studi' ?></span>
    <?php endif; ?>
  </span>
</figure>
