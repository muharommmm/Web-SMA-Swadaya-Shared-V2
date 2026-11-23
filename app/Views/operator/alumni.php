<?php
use App\Controllers\OperatorController;
use App\Core\Helpers;

$tabLabel = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
$aksi = static function (int $id, string $aksi, string $label, string $kelas, string $tab, string $konfirmasi = ''): string {
    return '<form action="/operator/alumni/aksi" method="post"' . ($konfirmasi !== '' ? ' onsubmit="return confirm(\'' . Helpers::e($konfirmasi) . '\')"' : '') . '>'
        . Helpers::csrfField()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="tab" value="' . Helpers::e($tab) . '">'
        . '<button class="btn ' . $kelas . '" type="submit" name="aksi" value="' . $aksi . '" style="width: 100%">' . $label . '</button></form>';
};
?>
<div class="page-head">
  <div>
    <h1>Cerita Alumni</h1>
    <p>Cerita yang dikirim lewat halaman Alumni tampil di situs setelah disetujui.</p>
  </div>
  <a class="btn btn--outline btn--lg" href="/alumni" target="_blank">Lihat halaman Alumni</a>
</div>

<section class="box" style="gap: 20px">
  <nav class="tabs" aria-label="Status cerita">
    <?php foreach ($tabLabel as $key => $label): ?>
      <a class="chip" href="/operator/alumni?status=<?= $key ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $label ?> <small><?= $counts[$key] ?></small></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($rows): ?>
    <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px">
      <?php foreach ($rows as $r): ?>
        <article class="story">
          <div class="story__head">
            <span class="avatar"><?= !empty($r['foto']) ? '<img src="' . Helpers::e($r['foto']) . '" alt="">' : Helpers::e(Helpers::inisial($r['nama'])) ?></span>
            <span style="flex: 1; min-width: 0">
              <strong><?= Helpers::e($r['nama']) ?></strong>
              <small>Lulus <?= Helpers::e($r['tahun_lulus'] ?: '[tahun]') ?> · <?= $r['kegiatan'] === 'karier' ? 'Bekerja' : 'Studi' ?><?= $r['tempat'] ? ' di ' . Helpers::e($r['tempat']) : '' ?></small>
            </span>
            <span class="st st--<?= Helpers::e($r['status']) ?>"><?= !empty($r['unggulan']) ? 'Kisah pilihan' : $tabLabel[$r['status']] ?></span>
          </div>
          <p><?= nl2br(Helpers::e($r['cerita'])) ?></p>
          <div class="story__meta">Dikirim <?= Helpers::e(strtolower(OperatorController::waktuSingkat($r['created_at']))) ?> · Kontak: <?= Helpers::e($r['kontak']) ?></div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
            <?php if ($r['status'] !== 'disetujui'): ?>
              <?= $aksi((int) $r['id'], 'setujui', 'Setujui &amp; tampilkan', 'btn--green', $tab) ?>
            <?php elseif (empty($r['unggulan'])): ?>
              <?= $aksi((int) $r['id'], 'unggulan', 'Jadikan kisah pilihan', 'btn--soft', $tab) ?>
            <?php else: ?>
              <span></span>
            <?php endif; ?>
            <?php if ($r['status'] !== 'ditolak'): ?>
              <?= $aksi((int) $r['id'], 'tolak', $r['status'] === 'disetujui' ? 'Sembunyikan' : 'Tolak', 'btn--danger-outline', $tab) ?>
            <?php else: ?>
              <?= $aksi((int) $r['id'], 'hapus', 'Hapus permanen', 'btn--danger-outline', $tab, 'Hapus permanen cerita ' . $r['nama'] . '?') ?>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Belum ada cerita <?= strtolower($tabLabel[$tab]) ?></h3><p class="muted">Cerita baru dari alumni akan muncul di tab Menunggu.</p></div>
  <?php endif; ?>
</section>
