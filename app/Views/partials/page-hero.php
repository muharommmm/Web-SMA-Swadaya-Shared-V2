<?php
/** Hero halaman dalam: $heroCrumb, $heroTitle, $heroSub, opsional $heroTag (label pil putih), $heroBadge (pil badge) */
use App\Core\Helpers;
?>
<section class="hero">
  <div class="hero__circle hero__circle--fill" aria-hidden="true"></div>
  <div class="hero__circle hero__circle--line" aria-hidden="true"></div>
  <svg class="hero__star" aria-hidden="true" width="30" height="30" viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z" fill="#F5C518"/></svg>
  <div class="wrap hero__inner<?= !empty($heroShort) ? ' hero__inner--short' : '' ?>">
    <nav class="crumbs rise" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><span><?= Helpers::e($heroCrumb) ?></span></nav>
    <?php if (!empty($heroTag)): ?>
      <span class="hero-tag rise" style="animation-delay: .08s"><?= Helpers::e($heroTag) ?></span>
    <?php endif; ?>
    <?php if (!empty($heroBadge)): ?>
      <span class="ppdb-badge rise" style="animation-delay: .05s"><?= Helpers::e($heroBadge) ?></span>
    <?php endif; ?>
    <h1 class="rise" style="animation-delay: .1s"><?= Helpers::e($heroTitle) ?></h1>
    <?php if (!empty($heroSub)): ?>
      <p class="hero__sub rise" style="animation-delay: .25s"><?= Helpers::e($heroSub) ?></p>
    <?php endif; ?>
  </div>
</section>
