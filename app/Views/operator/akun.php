<?php
use App\Core\Helpers;
?>
<div class="page-head">
  <div>
    <h1>Pengaturan</h1>
    <p>Data akun admin dan kata sandi. Isi data sekolah (alamat, WhatsApp, kuota PPDB) diubah di <a href="/operator/konten?bagian=data_kontak">Konten Situs</a>.</p>
  </div>
</div>

<form class="box form" action="/operator/akun" method="post" style="max-width: 720px; gap: 18px">
  <?= Helpers::csrfField() ?>
  <h2>Akun admin</h2>
  <div class="fields">
    <div class="f"><label for="nama">Nama</label><input id="nama" class="field" name="nama" type="text" required maxlength="100" value="<?= Helpers::e($operator['nama'] ?? '') ?>"></div>
    <div class="f"><label for="email">Email masuk</label><input id="email" class="field" name="email" type="email" required autocomplete="username" value="<?= Helpers::e($operator['email'] ?? '') ?>"></div>
  </div>
  <h2 style="padding-top: 8px">Ganti kata sandi <span class="opt" style="font-size: 14px; font-weight: 400">(kosongkan bila tidak diganti)</span></h2>
  <div class="fields">
    <div class="f"><label for="pw-lama">Kata sandi saat ini</label><input id="pw-lama" class="field" name="password_lama" type="password" autocomplete="current-password"></div>
    <div class="f"><label for="pw-baru">Kata sandi baru</label><input id="pw-baru" class="field" name="password_baru" type="password" minlength="8" autocomplete="new-password" aria-describedby="pw-hint"><span class="hint" id="pw-hint">Minimal 8 karakter.</span></div>
    <div class="f"><label for="pw-ulang">Ulangi kata sandi baru</label><input id="pw-ulang" class="field" name="password_konfirmasi" type="password" autocomplete="new-password"></div>
  </div>
  <div class="note"><?= Helpers::icon('lock', 16) ?>Terakhir masuk <?= Helpers::e(\App\Controllers\OperatorController::waktuSingkat($operator['last_login_at'] ?? null)) ?>. Akun dikunci 15 menit setelah 5 kali gagal masuk.</div>
  <button class="btn btn--primary btn--lg" type="submit" style="align-self: flex-start">Simpan pengaturan</button>
</form>
