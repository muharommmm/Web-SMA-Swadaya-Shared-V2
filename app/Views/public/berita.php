<?php
use App\Core\Helpers;
use App\Core\View;

$heroCrumb = 'Berita & Artikel';
$heroTag = 'Artikel & Kegiatan';
$heroTitle = 'Berita & Kegiatan Terkini';
$heroSub = 'Ikuti ragam inovasi, prestasi siswa, dan dinamika pembelajaran aplikatif terbaru di lingkungan SMA Swadaya Bandung.';
require dirname(__DIR__) . '/partials/page-hero.php';

$items = $pagination['items'];
$basePath = $currentKategori ? '/berita/kategori/' . $currentKategori['slug'] : '/berita';
$pageUrl = static function (int $p) use ($basePath, $searchQuery): string {
    $q = array_filter(['q' => $searchQuery, 'page' => $p > 1 ? $p : null]);
    return $basePath . ($q ? '?' . http_build_query($q) : '');
};
$total = (int) $pagination['total'];
?>
<!-- CARI & FILTER -->
<section class="wrap" style="padding-top: 48px" aria-label="Cari dan filter">
  <div class="rise" style="animation-delay: .4s; display: flex; flex-wrap: wrap; align-items: center; gap: 20px 28px">
    <form class="f" action="<?= Helpers::e($basePath) ?>" method="get" role="search" style="flex: 1 1 300px; order: 2; max-width: 340px; margin-left: auto">
      <label class="sr-only" for="cari">Cari berita atau artikel</label>
      <div style="position: relative">
        <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted-2); display: flex"><?= Helpers::icon('search') ?></span>
        <input id="cari" class="field" name="q" type="search" value="<?= Helpers::e($searchQuery) ?>" placeholder="Cari berita atau artikel" style="padding-left: 46px; min-height: 46px; border-radius: 999px; font-size: 14px">
      </div>
    </form>
    <nav class="chips chips--scroll" aria-label="Kategori" style="flex: 2 1 480px; order: 1">
      <a class="chip" href="/berita"<?= $currentKategori === null ? ' aria-current="page"' : '' ?>>Semua</a>
      <?php foreach ($categories as $cat): ?>
        <a class="chip" href="/berita/kategori/<?= Helpers::e($cat['slug']) ?>"<?= ($currentKategori['id'] ?? null) === $cat['id'] ? ' aria-current="page"' : '' ?>><?= Helpers::e($cat['nama']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<!-- BERITA UTAMA -->
<?php if ($featured): ?>
<section class="wrap" style="padding-top: 40px" aria-label="Berita utama">
  <article class="split" style="gap: 32px 56px">
    <?= View::partial('partials/foto', [
        'src' => $featured['gambar_sampul'] ?? '',
        'alt' => ($featured['alt_gambar'] ?? '') ?: $featured['judul'],
        'label' => '[Foto berita utama]',
        'class' => 'portrait portrait--wide',
        'eager' => true,
        'slot' => '<span class="badge" style="top: 18px; left: 18px">Berita utama</span>',
    ]) ?>
    <div class="head" style="gap: 16px">
      <span class="card__date" style="font-size: 14px; flex-wrap: wrap; gap: 10px">
        <span class="tag" style="letter-spacing: 0; text-transform: none; font-size: 13px"><?= Helpers::e($featured['kategori_nama']) ?></span>
        <?= Helpers::e(Helpers::tanggalPanjang($featured['published_at'])) ?> · <?= (int) $featured['menit_baca'] ?> menit baca
      </span>
      <h2 class="h2" style="font-size: clamp(22px, 2vw, 28px); line-height: 1.3"><a href="/berita/<?= Helpers::e($featured['slug']) ?>" style="color: inherit; text-decoration: none"><?= Helpers::e($featured['judul']) ?></a></h2>
      <p class="lead"><?= Helpers::e($featured['ringkasan']) ?></p>
      <a class="btn btn--primary btn-lift" style="align-self: flex-start" href="/berita/<?= Helpers::e($featured['slug']) ?>">Baca Selengkapnya<?= Helpers::icon('arrow') ?></a>
    </div>
  </article>
</section>
<?php endif; ?>

<!-- DAFTAR -->
<section class="wrap stack" style="padding-top: 72px; gap: 28px" aria-labelledby="judul-daftar">
  <div class="head-row" style="align-items: center">
    <h2 id="judul-daftar" style="font-size: 24px; font-weight: 700"><?= $isFiltered ? ($currentKategori && $searchQuery === '' ? 'Kategori ' . Helpers::e($currentKategori['nama']) : 'Hasil penelusuran') : 'Tulisan terbaru' ?></h2>
    <span class="count" role="status"><?= $total ?> tulisan</span>
  </div>

  <?php if ($items): ?>
    <div class="grid" style="gap: 28px 24px">
      <?php foreach ($items as $a): ?>
        <?= View::partial('partials/berita-card', ['a' => $a]) ?>
      <?php endforeach; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
      <nav class="pager" aria-label="Halaman">
        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
          <?php if ($p === $pagination['page']): ?>
            <span aria-current="page"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= Helpers::e($pageUrl($p)) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
          <a href="<?= Helpers::e($pageUrl($pagination['page'] + 1)) ?>">Berikutnya<?= Helpers::icon('chevron-right', 16) ?></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php elseif (!$featured): ?>
    <div class="empty">
      <h3>Belum ada tulisan yang cocok</h3>
      <p class="muted" style="font-size: 15px">Coba kata kunci lain atau tampilkan semua kategori.</p>
      <a class="btn btn--primary" href="/berita">Tampilkan semua</a>
    </div>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/cta.php'; ?>
