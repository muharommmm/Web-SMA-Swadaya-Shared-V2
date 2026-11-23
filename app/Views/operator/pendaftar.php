<?php
use App\Controllers\OperatorController;
use App\Controllers\PendaftaranController;
use App\Core\Helpers;

$semua = array_sum($counts);
$qs = static function (array $ubah) use ($filter): string {
    $q = array_filter(array_merge($filter, ['page' => null], $ubah), static fn($v) => $v !== '' && $v !== null && $v !== 'terbaru');
    return $q ? '?' . http_build_query($q) : '';
};
?>
<div class="page-head">
  <div>
    <h1>Pendaftar PPDB</h1>
    <p>Tahun ajaran <?= Helpers::e($tahunAjaran) ?> · <?= $semua ?> pendaftar<?= $kuota > 0 ? ' · kuota ' . $kuota . ' kursi' : '' ?></p>
  </div>
  <a class="btn btn--outline btn--lg" href="/operator/pendaftar/ekspor<?= Helpers::e($qs([])) ?>"><?= Helpers::icon('download') ?>Ekspor Excel</a>
</div>

<section class="box" style="gap: 20px">
  <nav class="tabs chips--scroll" aria-label="Filter status">
    <a class="chip" href="/operator/pendaftar<?= Helpers::e($qs(['status' => ''])) ?>"<?= $filter['status'] === '' ? ' aria-current="page"' : '' ?>>Semua <small><?= $semua ?></small></a>
    <?php foreach (OperatorController::STATUS as $key => [$label]): ?>
      <a class="chip" href="/operator/pendaftar<?= Helpers::e($qs(['status' => $key])) ?>"<?= $filter['status'] === $key ? ' aria-current="page"' : '' ?>><?= Helpers::e($label) ?> <small><?= $counts[$key] ?></small></a>
    <?php endforeach; ?>
  </nav>

  <form class="filters" method="get" action="/operator/pendaftar">
    <?php if ($filter['status'] !== ''): ?><input type="hidden" name="status" value="<?= Helpers::e($filter['status']) ?>"><?php endif; ?>
    <div class="f"><label for="cari">Cari nama, nomor pendaftaran, atau asal sekolah</label><input id="cari" class="field" type="search" name="q" value="<?= Helpers::e($filter['q']) ?>"></div>
    <div class="f"><label for="jalur">Jalur</label>
      <select id="jalur" class="field" name="jalur" data-autosubmit>
        <option value="">Semua jalur</option>
        <?php foreach (PendaftaranController::JALUR as $k => $l): ?><option value="<?= $k ?>"<?= $filter['jalur'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="f"><label for="urut">Urutkan</label>
      <select id="urut" class="field" name="urut" data-autosubmit>
        <?php foreach (['terbaru' => 'Terbaru dulu', 'terlama' => 'Terlama dulu', 'nama' => 'Nama (A–Z)'] as $k => $l): ?><option value="<?= $k ?>"<?= $filter['urut'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table table--cards">
        <thead><tr><th>Pendaftar</th><th>Asal sekolah</th><th>Jalur</th><th>Berkas</th><th>Masuk</th><th>Status</th><th style="text-align: right">Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <?php $wajib = $p['jalur'] === 'prestasi' ? 5 : 4; ?>
            <tr>
              <td><a class="who row-link" href="/operator/pendaftar/<?= (int) $p['id'] ?>"><span class="avatar"><?= Helpers::e(Helpers::inisial($p['nama_lengkap'])) ?></span><span><strong><?= Helpers::e($p['nama_lengkap']) ?></strong><small><?= Helpers::e($p['nomor_registrasi']) ?></small></span></a></td>
              <td data-hide-sm><?= Helpers::e($p['sekolah_asal']) ?></td>
              <td data-hide-sm><?= Helpers::e(PendaftaranController::JALUR[$p['jalur']] ?? '-') ?></td>
              <td data-hide-sm><?= (int) $p['jumlah_berkas'] ?> dari <?= $wajib ?></td>
              <td data-hide-sm><?= Helpers::e(OperatorController::waktuSingkat($p['created_at'])) ?></td>
              <td><span class="st st--<?= Helpers::e($p['status_pendaftaran']) ?>"><?= Helpers::e(OperatorController::STATUS[$p['status_pendaftaran']][0] ?? $p['status_pendaftaran']) ?></span></td>
              <td data-hide-sm style="text-align: right"><a class="btn btn--soft btn--sm" href="/operator/pendaftar/<?= (int) $p['id'] ?>">Periksa</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="pager-row">
      <span>Menampilkan <?= ($page - 1) * $perPage + 1 ?>–<?= min($total, $page * $perPage) ?> dari <?= $total ?> pendaftar</span>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Halaman">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
            <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 1): ?><a href="/operator/pendaftar<?= Helpers::e($qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php elseif (abs($i - $page) === 2): ?><span aria-hidden="true" style="background: none">…</span><?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Tidak ada pendaftar yang cocok</h3><p class="muted">Ubah filter atau kata kunci pencarian.</p><a class="btn btn--primary" href="/operator/pendaftar">Tampilkan semua</a></div>
  <?php endif; ?>
</section>
<script>
document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
</script>
