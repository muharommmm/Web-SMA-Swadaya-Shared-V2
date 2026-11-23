<?php
use App\Controllers\OperatorController;
use App\Controllers\PendaftaranController;
use App\Core\Helpers;

$menunggu = $counts['baru'];
$persen = $kuota > 0 ? min(100, (int) round($counts['diterima'] / $kuota * 100)) : 0;
$maxMinggu = max(1, max($minggu));
$warnaStatus = ['diterima' => '#16A34A', 'terverifikasi' => '#10AEAD', 'baru' => '#D97706', 'perlu_perbaikan' => '#F59E0B', 'tidak_diterima' => '#DC2626'];
$urutStatus = ['diterima', 'terverifikasi', 'baru', 'perlu_perbaikan', 'tidak_diterima'];
$labelStatus = ['baru' => 'Menunggu verifikasi'] + array_map(static fn($s) => $s[0], OperatorController::STATUS);
$hariLalu = $tertuaBaru ? (int) floor((time() - strtotime($tertuaBaru)) / 86400) : 0;
?>
<section class="greet">
  <div>
    <small><?= Helpers::e($tanggalHariIni) ?></small>
    <h1><?= Helpers::e($sapaan) ?>, <?= Helpers::e($operatorNama) ?></h1>
    <small>PPDB <?= Helpers::e($banner['tahun_ajaran'] ?? '') ?><?= !empty($banner['batas_tanggal']) ? ' · ditutup ' . Helpers::e($banner['batas_tanggal']) : '' ?></small>
  </div>
  <div class="btn-row">
    <a class="btn btn--white btn--lg btn-lift" href="/operator/pendaftar?status=baru">Verifikasi <?= $menunggu ?> pendaftar baru</a>
    <a class="btn btn--ghost-white btn--lg btn-lift" href="/operator/artikel/baru"><?= Helpers::icon('plus') ?>Tulis Berita</a>
  </div>
</section>

<section class="stats" aria-label="Ringkasan PPDB">
  <div class="stat"><span>Total pendaftar</span><strong><?= $total ?></strong><small style="color: #15803D; font-weight: 600">+<?= $mingguIni ?> minggu ini</small></div>
  <a class="stat<?= $menunggu > 0 ? ' stat--warn' : '' ?>" href="/operator/pendaftar?status=baru"><span>Menunggu verifikasi</span><strong><?= $menunggu ?></strong><small style="color: #9A3412; font-weight: 600"><?= $counts['perlu_perbaikan'] ?> lainnya perlu perbaikan berkas</small></a>
  <div class="stat"><span>Diterima</span><strong><?= $counts['diterima'] ?></strong><small><?= $kuota > 0 ? 'dari kuota ' . $kuota . ' kursi' : 'Kuota belum diisi' ?></small></div>
  <div class="stat"><span>Kursi terisi</span><strong><?= $persen ?>%</strong><div class="meter" role="img" aria-label="<?= $persen ?> persen kursi terisi"><span style="width: <?= $persen ?>%"></span></div></div>
</section>

<div class="cols cols--dash">
  <section class="box" aria-labelledby="h-grafik">
    <div class="box__head"><h2 id="h-grafik">Pendaftar per minggu</h2><span class="hint">8 minggu terakhir · total <?= array_sum($minggu) ?></span></div>
    <div class="bars" role="img" aria-label="Grafik pendaftar per minggu: <?= Helpers::e(implode(', ', $minggu)) ?>">
      <?php foreach ($minggu as $n): ?>
        <div><b><?= $n ?></b><i style="height: <?= max(2, round($n / $maxMinggu * 180)) ?>px"></i></div>
      <?php endforeach; ?>
    </div>
    <div class="bar-labels" aria-hidden="true">
      <?php foreach ($minggu as $i => $n): ?><span title="Mulai <?= Helpers::e(Helpers::formatTanggal(date('Y-m-d', $mingguAwal + $i * 7 * 86400), true)) ?>">Mg <?= $i + 1 ?></span><?php endforeach; ?>
    </div>
  </section>

  <section class="box" aria-labelledby="h-status">
    <h2 id="h-status">Status pendaftar</h2>
    <div class="status-bars">
      <?php foreach ($urutStatus as $s): ?>
        <div>
          <span><span class="dot" style="background: <?= $warnaStatus[$s] ?>"></span><?= Helpers::e($labelStatus[$s]) ?></span>
          <strong><?= $counts[$s] ?></strong>
          <div class="meter"><span style="width: <?= $total ? round($counts[$s] / $total * 100) : 0 ?>%; background: <?= $warnaStatus[$s] ?>"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<div class="cols cols--dash">
  <section class="box" aria-labelledby="h-terbaru">
    <div class="box__head"><h2 id="h-terbaru">Pendaftar terbaru</h2><a href="/operator/pendaftar" style="font-weight: 600">Lihat semua</a></div>
    <?php if ($terbaru): ?>
      <div class="table-wrap">
        <table class="table table--cards">
          <thead><tr><th>Nama</th><th>Jalur</th><th>Masuk</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($terbaru as $p): ?>
              <tr>
                <td><a class="row-link" href="/operator/pendaftar/<?= (int) $p['id'] ?>"><strong style="display: block"><?= Helpers::e($p['nama_lengkap']) ?></strong><small class="hint"><?= Helpers::e($p['nomor_registrasi']) ?> · <?= Helpers::e($p['sekolah_asal']) ?></small></a></td>
                <td data-hide-sm><?= Helpers::e(PendaftaranController::JALUR[$p['jalur']] ?? '-') ?></td>
                <td data-hide-sm><?= Helpers::e(OperatorController::waktuSingkat($p['created_at'])) ?></td>
                <td><span class="st st--<?= Helpers::e($p['status_pendaftaran']) ?>"><?= Helpers::e(OperatorController::STATUS[$p['status_pendaftaran']][0] ?? $p['status_pendaftaran']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="muted">Belum ada pendaftar. Formulir PPDB ada di <a href="/pendaftaran" target="_blank">/pendaftaran</a>.</p>
    <?php endif; ?>
  </section>

  <section class="box" aria-labelledby="h-tindakan" style="gap: 12px">
    <h2 id="h-tindakan">Perlu tindakan</h2>
    <?php if ($menunggu > 0): ?>
      <a class="todo todo--warn" href="/operator/pendaftar?status=baru"><span class="todo__icon"><?= Helpers::icon('file', 20) ?></span><span><strong><?= $menunggu ?> berkas menunggu verifikasi</strong><small>Tertua masuk <?= $hariLalu > 0 ? $hariLalu . ' hari lalu' : 'hari ini' ?></small></span></a>
    <?php endif; ?>
    <?php if ($emailGagal): ?>
      <a class="todo" href="/operator/pendaftar/<?= (int) $emailGagal[0]['id'] ?>"><span class="todo__icon"><?= Helpers::icon('mail', 20) ?></span><span><strong><?= count($emailGagal) ?> email notifikasi gagal</strong><small>Terbaru <?= Helpers::e($emailGagal[0]['nomor_registrasi']) ?> · periksa SMTP di .env</small></span></a>
    <?php endif; ?>
    <?php if ($alumniMenunggu > 0): ?>
      <a class="todo" href="/operator/alumni"><span class="todo__icon"><?= Helpers::icon('chat', 20) ?></span><span><strong><?= $alumniMenunggu ?> cerita alumni baru</strong><small>Menunggu persetujuan tayang</small></span></a>
    <?php endif; ?>
    <?php if ($draf > 0): ?>
      <a class="todo" href="/operator/artikel?status=draf"><span class="todo__icon"><?= Helpers::icon('edit', 20) ?></span><span><strong><?= $draf ?> draf berita</strong><small>Terakhir diubah <?= Helpers::e(strtolower(OperatorController::waktuSingkat($drafTerakhir))) ?></small></span></a>
    <?php endif; ?>
    <?php if (!$menunggu && !$emailGagal && !$alumniMenunggu && !$draf): ?>
      <p class="muted">Semua beres. Tidak ada yang perlu ditindaklanjuti.</p>
    <?php endif; ?>
  </section>
</div>
