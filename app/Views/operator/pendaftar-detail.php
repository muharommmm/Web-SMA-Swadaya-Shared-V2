<?php
use App\Controllers\OperatorController;
use App\Controllers\PendaftaranController;
use App\Core\Helpers;

$statusLabel = OperatorController::STATUS[$p['status_pendaftaran']][0] ?? $p['status_pendaftaran'];
$jalur = PendaftaranController::JALUR[$p['jalur']] ?? '-';
$wajib = $p['jalur'] === 'prestasi' ? 5 : 4;
$diperiksa = count(array_filter($berkas, static fn($b) => !empty($b['status_periksa'])));
$fotoId = 0;
foreach ($berkas as $b) {
    if ($b['jenis_berkas'] === 'pas_foto') {
        $fotoId = (int) $b['id'];
    }
}
$ukuran = static function (int $byte): string {
    return $byte >= 1048576 ? number_format($byte / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($byte / 1024)) . ' KB';
};
$ortu = static fn(?string $nama, ?string $kerja): string => ($nama ?: '-') . ($kerja ? ' · ' . $kerja : '');
$waPesan = 'https://wa.me/' . $waOrtu . '?text=' . rawurlencode("Halo Bapak/Ibu, kami dari panitia PPDB SMA Swadaya terkait pendaftaran {$p['nama_lengkap']} ({$p['nomor_registrasi']}).");
?>
<nav class="crumb" aria-label="Breadcrumb"><?= Helpers::icon('chevron-left', 16) ?><a href="/operator/pendaftar">Pendaftar PPDB</a><span aria-hidden="true">/</span><span><?= Helpers::e($p['nomor_registrasi']) ?></span></nav>

<section class="box profile-head">
  <div class="ph profile-head__photo">
    <?php if ($fotoId): ?><img src="/operator/berkas/<?= $fotoId ?>" alt="Pas foto <?= Helpers::e($p['nama_lengkap']) ?>"><?php else: ?>[Pas foto]<?php endif; ?>
  </div>
  <div style="flex: 1; min-width: 240px">
    <h1><?= Helpers::e($p['nama_lengkap']) ?> <span class="st st--<?= Helpers::e($p['status_pendaftaran']) ?>"><?= Helpers::e($statusLabel) ?></span></h1>
    <p><?= Helpers::e($p['nomor_registrasi']) ?> · Jalur <?= Helpers::e($jalur) ?> · Masuk <?= Helpers::e(strtolower(OperatorController::waktuSingkat($p['created_at']))) ?><br>Asal sekolah <?= Helpers::e($p['sekolah_asal']) ?> · Lulus <?= Helpers::e($p['tahun_keluar']) ?></p>
  </div>
  <div class="btn-row">
    <?php if ($waOrtu !== ''): ?><a class="btn btn--green btn--lg" href="<?= Helpers::e($waPesan) ?>" target="_blank" rel="noopener"><?= Helpers::icon('wa') ?>WhatsApp orang tua</a><?php endif; ?>
    <a class="btn btn--outline btn--lg" href="/operator/pendaftar/<?= (int) $p['id'] ?>/cetak" target="_blank"><?= Helpers::icon('print') ?>Cetak</a>
  </div>
</section>

<?php if (empty($p['email_lembaga_terkirim'])): ?>
  <form class="flash flash--err" action="/operator/kirim-ulang-email" method="post">
    <?= Helpers::csrfField() ?><input type="hidden" name="referensi" value="<?= Helpers::e($p['nomor_registrasi']) ?>">
    <span>Email notifikasi pendaftaran ini belum terkirim ke sekolah.</span>
    <button class="btn btn--outline btn--sm" type="submit" style="margin-left: auto">Kirim ulang email</button>
  </form>
<?php endif; ?>

<div class="cols">
  <div class="stack-20">
    <section class="box" aria-labelledby="h-berkas">
      <div class="box__head"><h2 id="h-berkas">Berkas</h2><span class="hint"><?= $diperiksa ?> dari <?= count($berkas) ?> berkas diperiksa · wajib <?= $wajib ?></span></div>
      <?php if ($berkas): ?>
        <div class="files">
          <div class="file-list">
            <?php foreach ($berkas as $b): ?>
              <?php $aktif = $berkasAktif && (int) $berkasAktif['id'] === (int) $b['id']; ?>
              <div class="file<?= $aktif ? ' is-active' : '' ?>">
                <a class="file__name" href="/operator/pendaftar/<?= (int) $p['id'] ?>?berkas=<?= (int) $b['id'] ?>#h-berkas"<?= $aktif ? ' aria-current="true"' : '' ?>><?= Helpers::icon('file', 18) ?><span><strong><?= Helpers::e($b['label_berkas']) ?></strong><small><?= Helpers::e($b['nama_asli']) ?> · <?= $ukuran((int) $b['ukuran_byte']) ?></small></span></a>
                <form class="file__actions" action="/operator/pendaftar/<?= (int) $p['id'] ?>/berkas" method="post">
                  <?= Helpers::csrfField() ?><input type="hidden" name="berkas_id" value="<?= (int) $b['id'] ?>">
                  <button class="btn-ok" type="submit" name="hasil" value="sesuai" aria-pressed="<?= $b['status_periksa'] === 'sesuai' ? 'true' : 'false' ?>">Sesuai</button>
                  <button class="btn-fix" type="submit" name="hasil" value="perbaikan" aria-pressed="<?= $b['status_periksa'] === 'perbaikan' ? 'true' : 'false' ?>">Perlu perbaikan</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($berkasAktif): ?>
            <div class="viewer">
              <div class="viewer__bar"><span><?= Helpers::e($berkasAktif['label_berkas']) ?></span><a href="/operator/berkas/<?= (int) $berkasAktif['id'] ?>?unduh=1">Unduh</a></div>
              <div class="viewer__body">
                <?php if (str_starts_with((string) $berkasAktif['mime_type'], 'image/')): ?>
                  <img src="/operator/berkas/<?= (int) $berkasAktif['id'] ?>" alt="<?= Helpers::e($berkasAktif['label_berkas']) ?>">
                <?php else: ?>
                  <iframe src="/operator/berkas/<?= (int) $berkasAktif['id'] ?>" title="Pratinjau <?= Helpers::e($berkasAktif['label_berkas']) ?>"></iframe>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="muted">Tidak ada berkas tersimpan.</p>
      <?php endif; ?>
    </section>

    <section class="box" aria-labelledby="h-data">
      <h2 id="h-data">Data pendaftar</h2>
      <div class="kv">
        <dl>
          <dt>NISN</dt><dd><?= Helpers::e($p['nisn'] ?: '-') ?></dd>
          <dt>NIK</dt><dd><?= Helpers::e($p['nik']) ?></dd>
          <dt>TTL</dt><dd><?= Helpers::e($p['tempat_lahir'] . ', ' . Helpers::tanggalPanjang($p['tanggal_lahir'])) ?></dd>
          <dt>Jenis kelamin</dt><dd><?= $p['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></dd>
          <dt>Agama</dt><dd><?= Helpers::e($p['agama']) ?></dd>
          <dt>Alamat</dt><dd><?= Helpers::e($alamat) ?></dd>
        </dl>
        <dl>
          <dt>Ayah</dt><dd><?= Helpers::e($ortu($p['nama_ayah'], $p['pekerjaan_ayah'])) ?></dd>
          <dt>Ibu</dt><dd><?= Helpers::e($ortu($p['nama_ibu'], $p['pekerjaan_ibu'])) ?></dd>
          <dt>WhatsApp</dt><dd><?= Helpers::e($p['nomor_wa']) ?></dd>
          <dt>Email</dt><dd><?= Helpers::e($p['email'] ?: '-') ?></dd>
          <dt>Minat ekskul</dt><dd><?= Helpers::e($p['minat_ekskul'] ?: '-') ?></dd>
        </dl>
      </div>
    </section>
  </div>

  <div class="stack-20">
    <section class="box" aria-labelledby="h-status">
      <h2 id="h-status">Ubah status</h2>
      <form class="form" action="/operator/pendaftar/<?= (int) $p['id'] ?>/status" method="post" style="gap: 14px">
        <?= Helpers::csrfField() ?>
        <fieldset class="radio-cards" style="border: 0; margin: 0; padding: 0">
          <legend class="sr-only">Status pendaftaran</legend>
          <?php foreach (OperatorController::STATUS as $key => [$label, $desc]): ?>
            <label class="radio-card"><input type="radio" name="status" value="<?= $key ?>"<?= $p['status_pendaftaran'] === $key ? ' checked' : '' ?>><span><strong><?= Helpers::e($label) ?></strong><small><?= Helpers::e($desc) ?></small></span></label>
          <?php endforeach; ?>
        </fieldset>
        <div class="f"><label for="catatan">Catatan untuk orang tua <span class="opt">(opsional)</span></label><textarea id="catatan" class="field" name="catatan" rows="3" maxlength="500" placeholder="Tulis pesan singkat untuk orang tua"><?= Helpers::e($p['catatan_operator'] ?? '') ?></textarea></div>
        <?php if ($waOrtu !== ''): ?>
          <label class="check"><input type="checkbox" name="kirim_wa" value="1" checked>Siapkan pesan WhatsApp untuk orang tua setelah disimpan</label>
        <?php endif; ?>
        <button class="btn btn--primary btn--lg" type="submit" style="width: 100%">Simpan status</button>
      </form>
    </section>

    <section class="box" aria-labelledby="h-riwayat">
      <h2 id="h-riwayat">Riwayat</h2>
      <ol class="timeline">
        <?php foreach ($riwayat as $r): ?>
          <li><strong><?= Helpers::e($r['pesan']) ?></strong><small><?= Helpers::e(OperatorController::waktuSingkat($r['created_at'])) ?> · <?= Helpers::e($r['aktor']) ?></small></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <form class="box" action="/operator/pendaftar/hapus" method="post" onsubmit="return confirm('Hapus permanen data dan seluruh berkas <?= Helpers::e($p['nomor_registrasi']) ?>? Tindakan ini tidak bisa dibatalkan.');" style="gap: 10px">
      <?= Helpers::csrfField() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <h2>Hapus data</h2>
      <p class="hint">Data berstatus Tidak diterima terhapus otomatis setelah 30 hari (kebijakan privasi). Gunakan tombol ini bila orang tua meminta penghapusan.</p>
      <button class="btn btn--danger-outline" type="submit" style="align-self: flex-start">Hapus permanen</button>
    </form>
  </div>
</div>
