<?php
use App\Controllers\OperatorController;
use App\Core\Helpers;
use App\Core\View;

$tabs = ['' => ['Semua', $counts['all']], 'terbit' => ['Terbit', $counts['terbit']], 'terjadwal' => ['Terjadwal', $counts['terjadwal']], 'draf' => ['Draf', $counts['draf']]];
$statusLabel = ['terbit' => 'Terbit', 'terjadwal' => 'Terjadwal', 'draf' => 'Draf'];
$qs = static fn(array $ubah): string => http_build_query(array_filter(array_merge(['status' => $filterStatus, 'kategori' => $filterKategori, 'q' => $searchQuery], $ubah), static fn($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
  <div>
    <h1>Berita &amp; Artikel</h1>
    <p>Tulis dan terbitkan kabar sekolah tanpa bantuan teknisi.</p>
  </div>
  <a class="btn btn--primary btn--lg btn-lift" href="/operator/artikel/baru"><?= Helpers::icon('plus') ?>Tulis artikel</a>
</div>

<section class="box" style="gap: 18px">
  <div class="head-row" style="align-items: flex-end">
    <nav class="tabs" aria-label="Status artikel">
      <?php foreach ($tabs as $key => [$label, $n]): ?>
        <a class="chip" href="/operator/artikel?<?= Helpers::e($qs(['status' => $key, 'halaman' => null])) ?>"<?= $filterStatus === $key ? ' aria-current="page"' : '' ?>><?= $label ?> <small><?= $n ?></small></a>
      <?php endforeach; ?>
    </nav>
    <form method="get" action="/operator/artikel" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end">
      <?php if ($filterStatus !== ''): ?><input type="hidden" name="status" value="<?= Helpers::e($filterStatus) ?>"><?php endif; ?>
      <div class="f" style="min-width: 160px"><label for="kat">Kategori</label>
        <select id="kat" class="field" name="kategori" onchange="this.form.submit()">
          <option value="">Semua</option>
          <?php foreach ($categories as $c): ?><option value="<?= Helpers::e($c['slug']) ?>"<?= $filterKategori === $c['slug'] ? ' selected' : '' ?>><?= Helpers::e($c['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="f" style="min-width: 240px"><label for="cari">Cari judul</label><input id="cari" class="field" name="q" type="search" value="<?= Helpers::e($searchQuery) ?>"></div>
    </form>
  </div>

  <?php if ($articles): ?>
    <div>
      <?php foreach ($articles as $art): ?>
        <?php
          $st = $art['status'];
          $waktu = $st === 'terbit' ? 'Terbit ' . Helpers::formatTanggal($art['published_at'])
              : ($st === 'terjadwal' ? 'Tayang ' . Helpers::formatTanggal($art['published_at']) . ', ' . date('H.i', strtotime((string) $art['published_at']))
              : 'Disimpan ' . strtolower(OperatorController::waktuSingkat($art['updated_at'])));
        ?>
        <div class="post-row">
          <?= View::partial('partials/foto', ['src' => $art['gambar_sampul'] ?? '', 'alt' => '', 'label' => '[Foto]', 'ikon' => '', 'class' => 'post-row__thumb']) ?>
          <div class="post-row__body">
            <strong><a class="row-link" href="/operator/artikel/<?= (int) $art['id'] ?>"><?= Helpers::e($art['judul']) ?></a></strong>
            <small><span class="cat-tag"><?= Helpers::e($art['kategori_nama'] ?? '-') ?></span><?= Helpers::e($waktu) ?> · <?= Helpers::e($art['penulis'] ?: 'Admin') ?></small>
          </div>
          <div class="post-row__actions">
            <span class="st st--<?= Helpers::e($st) ?>"><?= $statusLabel[$st] ?? $st ?></span>
            <a class="btn btn--soft btn--sm" href="/operator/artikel/<?= (int) $art['id'] ?>">Ubah</a>
            <a class="btn btn--outline btn--sm" href="/berita/<?= Helpers::e($art['slug']) ?><?= $st !== 'terbit' ? '?preview=1' : '' ?>" target="_blank">Lihat</a>
            <form action="/operator/artikel/hapus" method="post" onsubmit="return confirm('Hapus artikel ini secara permanen?');">
              <?= Helpers::csrfField() ?><input type="hidden" name="id" value="<?= (int) $art['id'] ?>">
              <button class="btn btn--danger-outline btn--sm" type="submit" aria-label="Hapus <?= Helpers::e($art['judul']) ?>">Hapus</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
      <nav class="pager" aria-label="Halaman">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="/operator/artikel?<?= Helpers::e($qs(['halaman' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty"><h3>Belum ada artikel di sini</h3><p class="muted">Ubah filter, atau tulis artikel baru.</p><a class="btn btn--primary" href="/operator/artikel/baru">Tulis artikel</a></div>
  <?php endif; ?>
</section>
