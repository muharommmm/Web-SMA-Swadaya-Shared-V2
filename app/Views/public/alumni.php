<?php
use App\Core\Helpers;
use App\Core\View;

$heroCrumb = 'Alumni';
$heroTag = 'Testimoni & jejak alumni';
$heroTitle = 'Kisah Inspiratif & Prestasi Alumni';
$heroSub = 'Dengarkan pengalaman lulusan SMA Swadaya yang kini menempuh studi di perguruan tinggi unggulan, serta berkarier di berbagai industri.';
require dirname(__DIR__) . '/partials/page-hero.php';

$pilihan = null;
foreach ($alumni as $al) {
    if (!empty($al['unggulan'])) {
        $pilihan = $al;
        break;
    }
}
$old = $old ?? [];
$errors = $errors ?? [];
$err = static fn(string $k): string => isset($errors[$k])
    ? '<p class="field-error" id="err-' . $k . '">' . Helpers::e($errors[$k]) . '</p>'
    : '';
$aria = static fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
?>
<?php if ($pilihan): ?>
<!-- KISAH PILIHAN -->
<section class="wrap" style="padding-top: 48px" aria-label="Kisah alumni pilihan">
  <article class="featured-story rise" style="animation-delay: .4s">
    <?= View::partial('partials/foto', ['src' => $pilihan['foto'] ?? '', 'alt' => $pilihan['nama'], 'label' => '[Foto alumni]', 'ikon' => 'user', 'eager' => true]) ?>
    <div class="featured-story__text">
      <span class="tag">Kisah pilihan</span>
      <p class="quote-lg" style="font-size: clamp(18px, 1.6vw, 22px)">“<?= Helpers::e(trim((string) $pilihan['cerita'], " \"“”")) ?>”</p>
      <span style="display: flex; flex-direction: column; gap: 2px">
        <strong style="font-size: 18px"><?= Helpers::e($pilihan['nama']) ?></strong>
        <span class="muted" style="font-size: 14.5px"><?= Helpers::e(trim(($pilihan['tempat'] ?? '') . ' · Alumni angkatan ' . (($pilihan['tahun_lulus'] ?? '') ?: '[tahun]'), ' ·')) ?></span>
      </span>
    </div>
  </article>
</section>
<?php endif; ?>

<!-- JEJAK ALUMNI -->
<section class="wrap stack" style="padding-top: 80px; gap: 28px" aria-labelledby="judul-jejak">
  <div class="head-row">
    <div class="head">
      <span class="eyebrow">Jejak alumni</span>
      <h2 id="judul-jejak" class="h2">Ke mana lulusan kami melangkah</h2>
    </div>
    <div class="chips" role="group" aria-label="Filter alumni" data-filter-group="alumni">
      <button class="chip" type="button" data-filter="" aria-pressed="true">Semua</button>
      <button class="chip" type="button" data-filter="studi" aria-pressed="false">Melanjutkan studi</button>
      <button class="chip" type="button" data-filter="karier" aria-pressed="false">Berkarier</button>
    </div>
  </div>
  <?php if ($alumni): ?>
    <div class="grid grid--4" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))">
      <?php foreach ($alumni as $al): ?>
        <div data-filter-item="alumni" data-kategori="<?= Helpers::e($al['kegiatan']) ?>" style="display: flex">
          <?= View::partial('partials/alumni-card', ['al' => $al, 'showType' => true]) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Belum ada cerita yang ditampilkan</h3><p class="muted">Jadilah alumni pertama yang berbagi cerita lewat formulir di bawah.</p></div>
  <?php endif; ?>
</section>

<!-- FORM ALUMNI -->
<section id="cerita" class="bg-soft" style="margin-top: 96px" aria-labelledby="judul-cerita">
  <div class="wrap section split" style="align-items: flex-start; gap: 40px 64px; padding-top: 88px; padding-bottom: 88px">
    <div class="head" style="flex: 1 1 340px; gap: 16px">
      <span class="eyebrow">Untuk alumni</span>
      <h2 id="judul-cerita" class="h2">Bagikan ceritamu, inspirasi adik kelas</h2>
      <p class="lead">Isi data singkat di samping. Tim sekolah akan menghubungimu sebelum cerita ditampilkan di halaman ini.</p>
      <ul class="checks">
        <li><?= Helpers::icon('check') ?>Diundang ke acara reuni dan temu alumni</li>
        <li><?= Helpers::icon('check') ?>Kesempatan berbagi di kelas inspirasi</li>
        <li><?= Helpers::icon('check') ?>Cerita tampil hanya setelah kamu setujui</li>
      </ul>
    </div>

    <div class="form-card" style="flex: 1.4 1 520px">
      <?php if (!empty($terkirim)): ?>
        <div class="notice-ok" role="status">
          <span class="notice-ok__icon"><?= Helpers::icon('check', 30) ?></span>
          <h3 style="font-size: 22px; font-weight: 700">Terima kasih sudah berbagi</h3>
          <p class="muted" style="max-width: 420px; font-size: 15.5px; line-height: 1.6">Tim sekolah akan menghubungimu untuk konfirmasi sebelum cerita ditampilkan.</p>
          <a class="btn btn--outline" href="/alumni#cerita">Kirim cerita lain</a>
        </div>
      <?php else: ?>
        <form class="form" action="/alumni#cerita" method="post">
          <?= Helpers::csrfField() ?>
          <div class="sr-only" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <?php if (!empty($errors['form'])): ?><div class="alert" role="alert"><?= Helpers::e($errors['form']) ?></div><?php endif; ?>
          <div class="fields">
            <div class="f"><label for="al-nama">Nama lengkap</label><input id="al-nama" class="field" name="nama" type="text" autocomplete="name" required maxlength="100" value="<?= Helpers::e($old['nama'] ?? '') ?>"<?= $aria('nama') ?>><?= $err('nama') ?></div>
            <div class="f"><label for="al-tahun">Tahun lulus</label><input id="al-tahun" class="field" name="tahun_lulus" type="text" inputmode="numeric" maxlength="4" placeholder="Contoh: 2018" required value="<?= Helpers::e($old['tahun_lulus'] ?? '') ?>"<?= $aria('tahun_lulus') ?>><?= $err('tahun_lulus') ?></div>
            <div class="f"><label for="al-kegiatan">Kegiatan saat ini</label>
              <select id="al-kegiatan" class="field" name="kegiatan" required<?= $aria('kegiatan') ?>>
                <option value="studi"<?= ($old['kegiatan'] ?? '') === 'studi' ? ' selected' : '' ?>>Melanjutkan studi</option>
                <option value="karier"<?= ($old['kegiatan'] ?? '') === 'karier' ? ' selected' : '' ?>>Berkarier</option>
              </select><?= $err('kegiatan') ?></div>
            <div class="f"><label for="al-tempat">Sekolah, kampus, atau tempat kerja</label><input id="al-tempat" class="field" name="tempat" type="text" maxlength="150" value="<?= Helpers::e($old['tempat'] ?? '') ?>"<?= $aria('tempat') ?>><?= $err('tempat') ?></div>
          </div>
          <div class="f"><label for="al-kontak">Nomor WhatsApp atau email</label><input id="al-kontak" class="field" name="kontak" type="text" required maxlength="150" value="<?= Helpers::e($old['kontak'] ?? '') ?>" aria-describedby="hint-kontak<?= isset($errors['kontak']) ? ' err-kontak' : '' ?>"<?= isset($errors['kontak']) ? ' aria-invalid="true"' : '' ?>><span class="hint" id="hint-kontak">Hanya untuk dihubungi tim sekolah, tidak ditampilkan.</span><?= $err('kontak') ?></div>
          <div class="f"><label for="al-cerita">Cerita singkat</label><textarea id="al-cerita" class="field" name="cerita" rows="4" required maxlength="1500" placeholder="Apa yang paling berkesan dari SMA Swadaya?"<?= $aria('cerita') ?>><?= Helpers::e($old['cerita'] ?? '') ?></textarea><?= $err('cerita') ?></div>
          <label class="check"><input type="checkbox" name="setuju" value="1" required<?= !empty($old['setuju']) ? ' checked' : '' ?><?= $aria('setuju') ?>>Saya bersedia dihubungi dan ceritaku ditampilkan setelah saya setujui.</label>
          <?= $err('setuju') ?>
          <?= \App\Services\Turnstile::widget() ?>
          <button class="btn btn--primary btn--lg btn-lift" type="submit" style="align-self: flex-start">Kirim Cerita</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
