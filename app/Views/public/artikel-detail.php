<?php
use App\Core\Helpers;
use App\Core\View;

$shareUrl = Helpers::fullUrl('/berita/' . $article['slug']);
$waShare = 'https://wa.me/?text=' . rawurlencode($article['judul'] . ' - ' . $shareUrl);
$fbShare = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
?>
<section class="hero">
  <div class="hero__circle hero__circle--fill" aria-hidden="true"></div>
  <div class="hero__circle hero__circle--line" aria-hidden="true"></div>
  <svg class="hero__star" aria-hidden="true" width="30" height="30" viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z" fill="#F5C518"/></svg>
  <div class="wrap hero__inner hero__inner--short">
    <nav class="crumbs rise" aria-label="Breadcrumb">
      <a href="/">Beranda</a><span aria-hidden="true">/</span>
      <a href="/berita">Berita &amp; Artikel</a><span aria-hidden="true">/</span>
      <a href="/berita/kategori/<?= Helpers::e($article['kategori_slug']) ?>"><?= Helpers::e($article['kategori_nama']) ?></a>
    </nav>
    <span class="crumbs rise" style="animation-delay: .05s; gap: 10px">
      <span class="hero-tag" style="letter-spacing: 0; text-transform: none; font-size: 13px"><?= Helpers::e($article['kategori_nama']) ?></span>
      <?= Helpers::e(Helpers::tanggalPanjang($article['published_at'] ?: $article['created_at'])) ?> · <?= (int) $article['menit_baca'] ?> menit baca
    </span>
    <h1 class="rise" style="animation-delay: .1s; max-width: 860px; font-size: clamp(28px, 2.8vw, 38px)"><?= Helpers::e($article['judul']) ?></h1>
  </div>
</section>

<article class="wrap overlap article stack" style="gap: 32px">
  <?= View::partial('partials/foto', [
      'src' => $article['gambar_sampul'] ?? '',
      'alt' => ($article['alt_gambar'] ?? '') ?: $article['judul'],
      'label' => '[Foto berita]',
      'class' => 'portrait',
      'eager' => true,
  ]) ?>

  <div class="prose">
    <?= Helpers::sanitizeRichHtml((string) ($article['isi'] ?? '')) ?>
  </div>

  <div class="head-row" style="align-items: center; padding: 24px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line)">
    <span style="font-size: 15px; font-weight: 700">Bagikan tulisan ini</span>
    <div class="btn-row" style="gap: 10px">
      <a class="btn btn--wa btn--sm" href="<?= Helpers::e($waShare) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa', 16) ?>WhatsApp</a>
      <a class="btn btn--outline btn--sm" href="<?= Helpers::e($fbShare) ?>" target="_blank" rel="noopener"><?= Helpers::icon('facebook', 16) ?>Facebook</a>
    </div>
  </div>
</article>

<?php if ($relatedArticles): ?>
<section class="wrap section--top stack" aria-labelledby="judul-terkait" style="gap: 28px">
  <div class="head-row">
    <div class="head">
      <span class="eyebrow">Baca juga</span>
      <h2 id="judul-terkait" class="h2">Tulisan terkait</h2>
    </div>
    <a class="btn btn--outline btn-lift" href="/berita">Semua berita</a>
  </div>
  <div class="grid">
    <?php foreach ($relatedArticles as $a): ?>
      <?= View::partial('partials/berita-card', ['a' => $a]) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/cta.php'; ?>
