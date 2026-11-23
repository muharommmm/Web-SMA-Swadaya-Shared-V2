<?php
/**
 * Foto dari Konten Situs, atau kotak placeholder bila belum diunggah.
 * $src, $alt, $label ("[Foto kegiatan]"), $class (ukuran/rasio), opsional $ikon ('image'|'user'|''), $eager
 */
use App\Core\Helpers;

$src = (string) ($src ?? '');
?>
<div class="ph <?= Helpers::e($class ?? '') ?>">
  <?php if ($src !== ''): ?>
    <img src="<?= Helpers::e($src) ?>" alt="<?= Helpers::e($alt ?? '') ?>" loading="<?= !empty($eager) ? 'eager' : 'lazy' ?>">
  <?php else: ?>
    <?php if (($ikon ?? 'image') !== ''): ?><?= Helpers::icon($ikon ?? 'image', 34) ?><?php endif; ?>
    <?= Helpers::e($label ?? '[Foto]') ?>
  <?php endif; ?>
  <?= $slot ?? '' ?>
</div>
