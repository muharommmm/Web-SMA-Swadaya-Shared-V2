<?php
use App\Core\Helpers;

$info = $konten['beranda_banner'] ?? [];
$tahunAjaran = $info['tahun_ajaran'] ?? '';
$aktif = !isset($info['aktif']) || !empty($info['aktif']);
$old = $old ?? [];
$errors = $errors ?? [];
$jalur = $old['jalur'] ?? 'reguler';
$minat = (array) ($old['minat_ekskul'] ?? []);
$waLink = (string) ($kontak['whatsapp_link'] ?? '');
$v = static fn(string $k): string => Helpers::e((string) ($old[$k] ?? ''));

// Baris unggah berkas. Aturan wajib sama dengan UploadService::validateUploads (app.js menyesuaikan saat jalur diganti)
$berkas = [
    ['pas_foto', 'Pas foto 3x4', 'JPG atau PNG · maks. 1 MB', 'image/jpeg,image/png,.jpg,.jpeg,.png', 1, 'selalu'],
    ['kk', 'Kartu Keluarga', 'PDF, JPG, PNG · maks. 2 MB', 'application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png', 2, 'selalu'],
    ['akta', 'Akta kelahiran', 'PDF, JPG, PNG · maks. 2 MB', 'application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png', 2, 'selalu'],
    ['rapor', 'Rapor kelas 4–6 atau SKL', 'Satu file PDF · maks. 5 MB', 'application/pdf,.pdf', 5, 'selalu'],
    ['sertifikat', 'Sertifikat prestasi', 'Wajib untuk jalur Prestasi · PDF, JPG, PNG · maks. 2 MB', 'application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png', 2, 'prestasi'],
];

$heroCrumb = 'Formulir PPDB';
$heroBadge = $tahunAjaran !== '' ? 'PPDB ' . $tahunAjaran . ($aktif ? ' Resmi Dibuka' : ' Belum Dibuka') : '';
$heroTitle = 'Formulir Pendaftaran Siswa Baru';
$heroSub = 'Empat langkah singkat, sekitar 10 menit. Nomor pendaftaran langsung Anda terima setelah formulir dikirim.';
require dirname(__DIR__) . '/partials/page-hero.php';
?>
<div class="wrap ppdb-layout">
  <div class="ppdb-main rise" style="animation-delay: .3s">
    <?php if (!$aktif): ?>
      <div class="alert" role="status" style="background: var(--tint); border-color: var(--line); color: var(--accent-dark)">Pendaftaran tahun ajaran <?= Helpers::e($tahunAjaran) ?> belum dibuka. Hubungi panitia lewat WhatsApp untuk informasi jadwal.</div>
    <?php else: ?>
    <form class="pendaftaran-form" action="/pendaftaran" method="post" enctype="multipart/form-data" data-errors="<?= Helpers::e(json_encode($errors, JSON_UNESCAPED_UNICODE)) ?>" data-autosave="ppdb-<?= Helpers::e(\App\Controllers\PendaftaranController::kodeTahunAjaran($tahunAjaran)) ?>">
      <?= Helpers::csrfField() ?>
      <div class="sr-only" aria-hidden="true"><label for="website_url">Jangan isi kolom ini</label><input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off" value=""></div>

      <ol class="stepper" aria-label="Langkah pendaftaran">
        <?php foreach (['Data siswa', 'Orang tua', 'Berkas', 'Kirim'] as $i => $label): ?>
          <li data-step-dot="<?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="step"' : '' ?>><span><b><?= $i + 1 ?></b><?= $label ?></span></li>
        <?php endforeach; ?>
      </ol>

      <?php if ($errors): ?>
        <div class="alert" role="alert" style="margin-bottom: 24px">
          <strong>Mohon periksa kembali isian berikut:</strong>
          <ul><?php foreach ($errors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <!-- LANGKAH 1 -->
      <fieldset class="step is-active" data-step="1" aria-labelledby="h-step-1">
        <div class="step__head"><h2 id="h-step-1">Data calon siswa</h2><p>Isi sesuai akta kelahiran dan Kartu Keluarga.</p></div>
        <div class="f">
          <span class="legend" id="legend-jalur">Jalur pendaftaran</span>
          <div class="choice-cards" role="radiogroup" aria-labelledby="legend-jalur">
            <label class="choice"><input type="radio" name="jalur" value="reguler" required<?= $jalur !== 'prestasi' ? ' checked' : '' ?>><span><strong>Reguler</strong><small>Untuk semua lulusan SMP/MTs</small></span></label>
            <label class="choice"><input type="radio" name="jalur" value="prestasi"<?= $jalur === 'prestasi' ? ' checked' : '' ?>><span><strong>Prestasi</strong><small>Lampirkan sertifikat prestasi</small></span></label>
          </div>
        </div>
        <div class="f"><label for="nama">Nama lengkap</label><input id="nama" class="field" name="nama_lengkap" type="text" autocomplete="name" required minlength="3" maxlength="100" value="<?= $v('nama_lengkap') ?>" data-review="Nama lengkap"></div>
        <div class="fields">
          <div class="f"><label for="nisn">NISN <span class="opt">(opsional)</span></label><input id="nisn" class="field" name="nisn" type="text" inputmode="numeric" pattern="\d{10}" maxlength="10" value="<?= $v('nisn') ?>" aria-describedby="hint-nisn" data-review="NISN"><span class="hint" id="hint-nisn">10 digit, tertera di rapor SD.</span></div>
          <div class="f"><label for="nik">NIK</label><input id="nik" class="field" name="nik" type="text" inputmode="numeric" pattern="\d{16}" maxlength="16" required value="<?= $v('nik') ?>" aria-describedby="hint-nik" data-no-autosave><span class="hint" id="hint-nik">16 digit, lihat Kartu Keluarga.</span></div>
          <div class="f"><label for="tempat">Tempat lahir</label><input id="tempat" class="field" name="tempat_lahir" type="text" required maxlength="50" value="<?= $v('tempat_lahir') ?>"></div>
          <div class="f"><label for="tgl">Tanggal lahir</label><input id="tgl" class="field" name="tanggal_lahir" type="date" required max="<?= date('Y-m-d') ?>" value="<?= $v('tanggal_lahir') ?>"></div>
          <div class="f"><span class="label" id="label-jk">Jenis kelamin</span>
            <div class="radios" role="radiogroup" aria-labelledby="label-jk">
              <label><input type="radio" name="jenis_kelamin" value="L" required<?= ($old['jenis_kelamin'] ?? '') === 'L' ? ' checked' : '' ?>>Laki-laki</label>
              <label><input type="radio" name="jenis_kelamin" value="P"<?= ($old['jenis_kelamin'] ?? '') === 'P' ? ' checked' : '' ?>>Perempuan</label>
            </div>
          </div>
          <div class="f"><label for="agama">Agama</label>
            <select id="agama" class="field" name="agama" required>
              <option value="">Pilih agama</option>
              <?php foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $ag): ?>
                <option value="<?= $ag ?>"<?= ($old['agama'] ?? '') === $ag ? ' selected' : '' ?>><?= $ag ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="f"><label for="sd">Asal sekolah (SMP/MTs)</label><input id="sd" class="field" name="sekolah_asal" type="text" required maxlength="100" value="<?= $v('sekolah_asal') ?>" data-review="Asal sekolah"></div>
          <div class="f"><label for="lulus">Tahun lulus</label><input id="lulus" class="field" name="tahun_lulus" type="text" inputmode="numeric" pattern="\d{4}" maxlength="4" required placeholder="Contoh: <?= date('Y') ?>" value="<?= $v('tahun_lulus') ?>"></div>
        </div>
        <div class="f"><label for="alamat">Alamat tempat tinggal</label><input id="alamat" class="field" name="alamat" type="text" required minlength="5" maxlength="255" autocomplete="address-line1" placeholder="Nama jalan, nomor rumah, RT/RW" value="<?= $v('alamat') ?>"></div>
        <noscript><p class="hint">Aktifkan JavaScript untuk memilih provinsi, kota, kecamatan, dan kelurahan.</p></noscript>
        <div class="fields">
          <?php foreach (['provinsi' => 'Provinsi', 'kota' => 'Kota/kabupaten', 'kecamatan' => 'Kecamatan', 'kelurahan' => 'Kelurahan/desa'] as $key => $label): ?>
            <div class="f"><label for="<?= $key ?>"><?= $label ?></label>
              <select id="<?= $key ?>" class="field" name="<?= $key ?>" required data-wilayah="<?= $key ?>" data-selected="<?= $v($key) ?>"><option value="">Pilih <?= strtolower($label) ?></option></select>
            </div>
          <?php endforeach; ?>
          <div class="f"><label for="kode_pos">Kode pos</label><input id="kode_pos" class="field" name="kode_pos" type="text" required inputmode="numeric" pattern="\d{5}" maxlength="5" autocomplete="postal-code" value="<?= $v('kode_pos') ?>" aria-describedby="hint-pos"><span class="hint" id="hint-pos">Terisi otomatis setelah memilih kelurahan.</span></div>
        </div>
      </fieldset>

      <!-- LANGKAH 2 -->
      <fieldset class="step" data-step="2" aria-labelledby="h-step-2">
        <div class="step__head"><h2 id="h-step-2">Data orang tua atau wali</h2><p>Panitia PPDB menghubungi Anda lewat nomor WhatsApp di bawah.</p></div>
        <div class="fields">
          <div class="f"><label for="ayah">Nama ayah</label><input id="ayah" class="field" name="nama_ayah" type="text" maxlength="100" value="<?= $v('nama_ayah') ?>" data-review="Nama ayah"></div>
          <div class="f"><label for="ibu">Nama ibu</label><input id="ibu" class="field" name="nama_ibu" type="text" maxlength="100" value="<?= $v('nama_ibu') ?>" data-review="Nama ibu"></div>
          <div class="f"><label for="kerja-ayah">Pekerjaan ayah</label><input id="kerja-ayah" class="field" name="pekerjaan_ayah" type="text" maxlength="100" value="<?= $v('pekerjaan_ayah') ?>"></div>
          <div class="f"><label for="kerja-ibu">Pekerjaan ibu</label><input id="kerja-ibu" class="field" name="pekerjaan_ibu" type="text" maxlength="100" value="<?= $v('pekerjaan_ibu') ?>"></div>
          <div class="f"><label for="wa">Nomor WhatsApp</label><input id="wa" class="field" name="no_wa" type="tel" autocomplete="tel" required pattern="[0-9+\s\-]{10,18}" placeholder="08xx xxxx xxxx" value="<?= $v('no_wa') ?>" data-review="WhatsApp"></div>
          <div class="f"><label for="email">Email <span class="opt">(opsional)</span></label><input id="email" class="field" name="email" type="email" autocomplete="email" maxlength="150" value="<?= $v('email') ?>"></div>
        </div>
        <p class="hint" style="margin-top: -8px">Isi minimal nama ayah atau nama ibu.</p>
        <?php if (!empty($pilihanEkskul)): ?>
          <div class="f">
            <span class="legend" id="legend-minat">Minat ekstrakurikuler <span class="opt">(boleh lebih dari satu)</span></span>
            <div class="pills-check" role="group" aria-labelledby="legend-minat">
              <?php foreach ($pilihanEkskul as $nama): ?>
                <label><input type="checkbox" name="minat_ekskul[]" value="<?= Helpers::e($nama) ?>"<?= in_array($nama, $minat, true) ? ' checked' : '' ?>><?= Helpers::e($nama) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </fieldset>

      <!-- LANGKAH 3 -->
      <fieldset class="step" data-step="3" aria-labelledby="h-step-3">
        <div class="step__head"><h2 id="h-step-3">Unggah berkas</h2><p>PDF, JPG, atau PNG. Di HP, Anda bisa langsung memotret dokumen; foto besar dikecilkan otomatis.</p></div>
        <?php foreach ($berkas as [$key, $label, $meta, $accept, $maxMb, $aturan]): ?>
          <?php $wajib = $aturan === 'selalu' || $jalur === 'prestasi'; ?>
          <div class="upload" id="row-<?= $key ?>" data-req-row="<?= $aturan ?>"<?= $wajib ? '' : ' hidden' ?>>
            <span class="upload__icon"><?= Helpers::icon('file', 22) ?></span>
            <span class="upload__text"><strong><?= Helpers::e($label) ?><span class="req">Wajib</span></strong><span class="upload__meta"><?= Helpers::e($meta) ?></span></span>
            <label class="btn btn--soft btn--sm upload__btn">
              <span class="upload-btn-label">Pilih file</span>
              <input class="sr-file" type="file" name="<?= $key ?>" accept="<?= $accept ?>" data-upload-row="row-<?= $key ?>" data-max-mb="<?= $maxMb ?>" data-req="<?= $aturan ?>"<?= $wajib ? ' required' : '' ?> aria-label="Unggah <?= Helpers::e($label) ?>">
            </label>
          </div>
        <?php endforeach; ?>
        <p class="hint">Total semua berkas maks. 12 MB. Berkas disimpan aman dan hanya dibuka panitia PPDB.</p>
      </fieldset>

      <!-- LANGKAH 4 -->
      <fieldset class="step" data-step="4" aria-labelledby="h-step-4">
        <div class="step__head"><h2 id="h-step-4">Tinjau dan kirim</h2><p>Periksa kembali data sebelum dikirim. Pilih "Ubah" untuk memperbaiki.</p></div>
        <div class="review" data-review-box="1" hidden>
          <div class="review__head">Data calon siswa<button class="btn btn--soft btn--sm" type="button" data-go-step="1">Ubah</button></div>
          <dl></dl>
        </div>
        <div class="review" data-review-box="2" hidden>
          <div class="review__head">Orang tua dan berkas<button class="btn btn--soft btn--sm" type="button" data-go-step="2">Ubah</button></div>
          <dl></dl>
        </div>
        <label class="check"><input type="checkbox" name="persetujuan_pdp" value="1" required<?= !empty($old['persetujuan_pdp']) ? ' checked' : '' ?>><span>Saya menyatakan data di atas benar dan menyetujui pemrosesan data pribadi sesuai <a href="/kebijakan-privasi" target="_blank">kebijakan privasi</a> SMA Swadaya.</span></label>
        <?= \App\Services\Turnstile::widget(true) ?>
      </fieldset>

      <div class="form-nav">
        <button class="btn btn--outline" type="button" data-step-back hidden><?= Helpers::icon('arrow-left') ?>Kembali</button>
        <span class="hint" data-autosave-note>Data tersimpan otomatis di perangkat ini, kecuali NIK dan berkas.</span>
        <button class="btn btn--primary btn--lg btn-lift" type="button" data-step-next hidden>Lanjut<?= Helpers::icon('arrow') ?></button>
        <button class="btn btn--primary btn--lg btn-lift" type="submit" data-submit-once>Kirim Pendaftaran<?= Helpers::icon('arrow') ?></button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <aside class="ppdb-aside">
    <div class="aside-box aside-box--soft">
      <h2>Siapkan berkas ini</h2>
      <ul class="checks" style="font-size: 15px">
        <li><?= Helpers::icon('check') ?>Pas foto 3x4</li>
        <li><?= Helpers::icon('check') ?>Kartu Keluarga dan akta kelahiran</li>
        <li><?= Helpers::icon('check') ?>Rapor kelas 4–6 atau SKL</li>
        <li><?= Helpers::icon('check') ?>Sertifikat prestasi (jalur Prestasi)</li>
      </ul>
    </div>
    <?php if ($waLink !== ''): ?>
      <div class="aside-box">
        <h2>Butuh bantuan?</h2>
        <p>Panitia PPDB siap membantu lewat WhatsApp di <?= Helpers::e($kontak['whatsapp_tampil'] ?? '') ?>.</p>
        <a class="btn btn--outline btn-lift" style="align-self: flex-start" href="<?= Helpers::e($waLink) ?>" target="_blank" rel="noopener">Chat Panitia</a>
      </div>
    <?php endif; ?>
  </aside>
</div>
