<?php
use App\Core\Helpers;

$error = $error ?? null;
$asset = static fn(string $p): string => $p . '?v=' . (@filemtime(\App\Core\Helpers::webDir() . $p) ?: '1');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk Area Admin · SMA Swadaya</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#10AEAD">
  <link rel="icon" type="image/png" sizes="64x64" href="/assets/img/favicon.png?v=<?= @filemtime(\App\Core\Helpers::webDir() . '/assets/img/favicon.png') ?: '1' ?>">
  <link rel="stylesheet" href="<?= $asset('/assets/css/site.css') ?>">
  <link rel="stylesheet" href="<?= $asset('/assets/css/admin.css') ?>">
</head>
<body class="admin">
  <main class="login">
    <section class="login__art">
      <a class="brand" href="/" style="color: #FFFFFF">
        <span style="width: 48px; height: 48px; border-radius: 50%; background: #FFFFFF; display: flex; align-items: center; justify-content: center"><img src="/assets/img/logo-sma.png" width="30" height="27" alt=""></span>
        <span>SMA Swadaya</span>
      </a>
      <div class="head" style="gap: 18px; margin-top: auto; margin-bottom: auto">
        <span class="tag tag--glass" style="letter-spacing: 0; text-transform: none; font-size: 13.5px">Area admin</span>
        <h1>Kelola PPDB, berita, dan konten sekolah</h1>
        <p style="font-size: 17px; line-height: 1.65; max-width: 360px">Verifikasi pendaftar, terbitkan berita kegiatan, dan perbarui isi situs dari satu tempat.</p>
      </div>
      <img class="maskot" src="/assets/img/hero-siswa.png" alt="" width="300" height="299">
    </section>

    <section class="login__form">
      <form class="form" action="/operator/login" method="post" style="gap: 20px">
        <?= Helpers::csrfField() ?>
        <div class="head" style="gap: 8px">
          <h2 style="font-size: 30px; font-weight: 700; letter-spacing: -.01em">Masuk</h2>
          <p class="muted" style="font-size: 16px">Gunakan akun admin yang diberikan sekolah.</p>
        </div>
        <?php if ($error): ?><div class="alert" role="alert"><?= Helpers::e($error) ?></div><?php endif; ?>
        <div class="f"><label for="email">Email</label><input id="email" class="field" name="email" type="email" required autocomplete="username" value="<?= Helpers::e($oldEmail ?? '') ?>" placeholder="admin@sma.swadaya.sch.id"></div>
        <div class="f">
          <label for="sandi">Kata sandi</label>
          <div class="pw">
            <input id="sandi" class="field" name="password" type="password" required autocomplete="current-password">
            <button type="button" data-pw-toggle aria-controls="sandi" aria-pressed="false">Tampilkan</button>
          </div>
        </div>
        <?= \App\Services\Turnstile::widget() ?>
        <button class="btn btn--primary btn--lg" type="submit" style="width: 100%; min-height: 54px">Masuk</button>
        <div class="note"><?= Helpers::icon('lock', 16) ?>Akun dikunci sementara setelah 5 kali gagal. Sesi berakhir otomatis setelah 30 menit tidak aktif. Lupa kata sandi? Hubungi pengelola situs sekolah.</div>
        <a href="/" style="align-self: center; font-weight: 600">Kembali ke situs</a>
      </form>
    </section>
  </main>
  <script>
  (function () {
    var b = document.querySelector('[data-pw-toggle]');
    var i = document.getElementById('sandi');
    if (!b || !i) return;
    b.addEventListener('click', function () {
      var show = i.type === 'password';
      i.type = show ? 'text' : 'password';
      b.textContent = show ? 'Sembunyikan' : 'Tampilkan';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  })();
  </script>
</body>
</html>
