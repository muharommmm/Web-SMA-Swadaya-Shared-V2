<?php
use App\Core\Helpers;

$sectionsByGroup = $sectionsByGroup ?? [];
$currentSection = $currentSection ?? [];
$activeKey = $currentSection['kunci'] ?? 'beranda_hero';
$sectionData = $currentSection['data'] ?? [];
$riwayatList = $currentSection['riwayat'] ?? [];

$previewHref = [
    'profil_tentang_legalitas' => '/profil', 'profil_visi_misi' => '/profil', 'data_tim_testimoni' => '/profil',
    'profil_fasilitas' => '/profil', 'data_ekskul' => '/ekstrakurikuler',
][$activeKey] ?? '/';

$labels = [
    'judul' => 'Judul utama', 'kata_berganti' => 'Kata yang berganti (pisahkan dengan koma)', 'subjudul' => 'Kalimat pendukung',
    'tombol_utama' => 'Teks tombol utama', 'tombol_kedua' => 'Teks tombol kedua', 'foto' => 'Foto', 'alt_foto' => 'Teks alt foto',
    'tahun_ajaran' => 'Tahun ajaran (contoh 2026/2027)', 'batas_tanggal' => 'Batas pendaftaran', 'kuota' => 'Kuota kursi', 'aktif' => 'Pendaftaran PPDB sedang dibuka',
    'items' => 'Daftar', 'label' => 'Label kecil', 'tautan' => 'Tautan video (opsional)', 'unggulan' => 'Tandai sebagai terfavorit',
    'nama_ketua' => 'Nama kepala sekolah', 'jabatan' => 'Jabatan', 'salam' => 'Kalimat salam (Beranda)', 'kutipan' => 'Kutipan (Profil)',
    'isi_sambutan' => 'Isi sambutan', 'isi_lanjutan' => 'Paragraf lanjutan (Profil)', 'foto_ketua' => 'Foto kepala sekolah',
    'hero_judul' => 'Judul halaman', 'hero_subjudul' => 'Kalimat pendukung halaman', 'akreditasi' => 'Akreditasi', 'kurikulum' => 'Kurikulum',
    'tahun_berdiri' => 'Berdiri sejak', 'tentang_judul' => 'Judul Tentang kami', 'tentang_paragraf_1' => 'Paragraf 1', 'tentang_paragraf_2' => 'Paragraf 2',
    'motto' => 'Motto', 'foto_gedung' => 'Foto gedung sekolah', 'visi' => 'Visi', 'misi' => 'Misi',
    'tampilkan_tim_tanpa_foto' => 'Tampilkan guru yang belum punya foto (halaman Profil)', 'tim' => 'Guru & tenaga pendidik',
    'nama' => 'Nama', 'peran' => 'Peran / mata pelajaran', 'ringkasan' => 'Ringkasan profil', 'tampil_beranda' => 'Tampilkan di Beranda',
    'judul_seksi' => 'Judul bagian', 'deskripsi' => 'Deskripsi', 'kategori' => 'Kategori', 'jadwal' => 'Jadwal', 'pembina' => 'Pembina', 'wajib' => 'Kegiatan wajib',
    'nama_lembaga' => 'Nama sekolah', 'tagline' => 'Tagline footer', 'npsn' => 'NPSN', 'alamat_lengkap' => 'Alamat lengkap', 'email' => 'Email resmi',
    'whatsapp_tampil' => 'Nomor WhatsApp (tampilan)', 'whatsapp_nomor' => 'Nomor WhatsApp tujuan (format 628...)', 'google_maps_url' => 'Tautan Google Maps',
    'google_maps_embed' => 'URL embed Google Maps', 'portal_siswa_url' => 'Tautan Portal CBT', 'instagram_url' => 'Instagram (opsional)',
    'youtube_url' => 'YouTube (opsional)', 'facebook_url' => 'Facebook (opsional)',
];
$label = static fn(string $k): string => $labels[$k] ?? (str_ends_with($k, '_title') ? 'Judul tab: ' . ucfirst(strtok($k, '_')) : (str_ends_with($k, '_desc') ? 'Deskripsi Google: ' . ucfirst(strtok($k, '_')) : ucfirst(str_replace('_', ' ', $k))));
$isPhoto = static fn(string $k): bool => $k === 'foto' || str_starts_with($k, 'foto_');
$isLong = static fn(string $k, string $v): bool => mb_strlen($v) > 90 || preg_match('/(desc|paragraf|subjudul|isi|deskripsi|ringkasan|visi|kutipan)/', $k) === 1;
$e = [Helpers::class, 'e'];

/** Kotak unggah foto; JS di bawah (js-photo-*) menangani pratinjau & hapus */
$photo = static function (string $title, string $value, string $fieldName, string $fileName, string $deleteName) use ($e): string {
    $preview = $value !== '' ? '<img src="' . $e($value) . '" alt="">' : Helpers::icon('image', 24) . '<span>[Belum ada foto]</span>';
    return '<div class="f js-photo-uploader" data-placeholder="[Belum ada foto]"><span class="label">' . $e($title) . '</span>'
        . '<div class="photo-field"><div class="js-photo-preview">' . $preview . '</div>'
        . '<div style="display: flex; flex-direction: column; gap: 8px">'
        . '<input type="hidden" name="' . $fieldName . '" value="' . $e($value) . '" class="js-photo-current">'
        . '<input type="hidden" name="' . $deleteName . '" value="0" class="js-photo-delete-flag">'
        . '<div class="btn-row" style="gap: 8px"><label class="btn btn--outline btn--sm upload__btn"><span class="js-photo-btn-label">' . ($value !== '' ? 'Ganti foto' : 'Pilih foto') . '</span>'
        . '<input class="sr-file js-photo-input" type="file" name="' . $fileName . '" accept=".jpg,.jpeg,.png,.webp" aria-label="Unggah ' . $e($title) . '"></label>'
        . '<button type="button" class="js-photo-remove btn btn--danger-outline btn--sm"' . ($value === '' ? ' style="display: none"' : '') . '>Hapus</button></div>'
        . '<span class="js-photo-status hint">JPG/PNG/WebP maks. 5 MB</span></div></div></div>';
};
?>
<div class="page-head">
  <div>
    <h1>Konten Situs</h1>
    <p>Ubah teks dan foto di setiap bagian halaman. Perubahan langsung tampil setelah disimpan.</p>
  </div>
  <a class="btn btn--outline btn--lg" href="<?= $e($previewHref) ?>" target="_blank">Lihat situs</a>
</div>

<div class="konten-grid">
  <nav class="sec-nav" aria-label="Bagian halaman">
    <?php foreach ($sectionsByGroup as $groupName => $items): ?>
      <span><?= $e($groupName) ?></span>
      <?php foreach ($items as $sec): ?>
        <a href="/operator/konten?bagian=<?= $e($sec['kunci']) ?>"<?= $sec['kunci'] === $activeKey ? ' aria-current="page"' : '' ?>><?= $e($sec['judul_bagian']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="stack-20">
    <form class="box form" action="/operator/konten" method="post" enctype="multipart/form-data" aria-labelledby="h-bagian" style="gap: 18px">
      <?= Helpers::csrfField() ?>
      <input type="hidden" name="kunci" value="<?= $e($activeKey) ?>">
      <div class="box__head" style="align-items: flex-start">
        <div><span class="hint" style="font-weight: 600">Halaman <?= $e($currentSection['kelompok'] ?? '') ?></span><h2 id="h-bagian" style="font-size: 22px"><?= $e($currentSection['judul_bagian'] ?? '') ?></h2></div>
        <span class="hint">Terakhir diubah <?= $e(strtolower(\App\Controllers\OperatorController::waktuSingkat($currentSection['updated_at'] ?? ''))) ?></span>
      </div>

      <?php foreach ($sectionData as $key => $val): $key = (string) $key; ?>
        <?php if (is_bool($val)): ?>
          <label class="check"><input type="hidden" name="fields[<?= $e($key) ?>]" value="0"><input type="checkbox" name="fields[<?= $e($key) ?>]" value="1"<?= $val ? ' checked' : '' ?>><?= $e($label($key)) ?></label>
        <?php elseif ((is_scalar($val) || $val === null) && $isPhoto($key)): ?>
          <?= $photo($label($key), trim((string) $val), "fields[{$key}]", "foto_files[{$key}]", "hapus_foto_files[{$key}]") ?>
        <?php elseif (is_scalar($val) || $val === null): ?>
          <div class="f"><label for="f-<?= $e($key) ?>"><?= $e($label($key)) ?></label>
            <?php if ($isLong($key, (string) $val)): ?>
              <textarea id="f-<?= $e($key) ?>" class="field" name="fields[<?= $e($key) ?>]" rows="3"><?= $e((string) $val) ?></textarea>
            <?php else: ?>
              <input id="f-<?= $e($key) ?>" class="field" name="fields[<?= $e($key) ?>]" type="text" value="<?= $e((string) $val) ?>">
            <?php endif; ?>
          </div>
        <?php elseif (is_array($val)): ?>
          <div class="f" style="gap: 12px">
            <span class="label"><?= $e($label($key)) ?></span>
            <?php foreach ($val as $i => $item): $i = (string) $i; ?>
              <?php if (is_scalar($item)): ?>
                <input class="field" type="text" name="fields[<?= $e($key) ?>][<?= $e($i) ?>]" value="<?= $e((string) $item) ?>" aria-label="<?= $e($label($key)) ?> <?= (int) $i + 1 ?>">
              <?php elseif (is_array($item)): ?>
                <div class="item-box">
                  <span>#<?= (int) $i + 1 ?><?= !empty($item['nama']) && is_string($item['nama']) ? ' · ' . $e($item['nama']) : (!empty($item['judul']) && is_string($item['judul']) ? ' · ' . $e($item['judul']) : '') ?></span>
                  <?php foreach ($item as $leaf => $leafVal): $leaf = (string) $leaf; $base = "[{$key}][{$i}][{$leaf}]"; ?>
                    <?php if (is_bool($leafVal)): ?>
                      <label class="check"><input type="hidden" name="fields<?= $e($base) ?>" value="0"><input type="checkbox" name="fields<?= $e($base) ?>" value="1"<?= $leafVal ? ' checked' : '' ?>><?= $e($label($leaf)) ?></label>
                    <?php elseif ((is_scalar($leafVal) || $leafVal === null) && $isPhoto($leaf)): ?>
                      <?= $photo($label($leaf), trim((string) $leafVal), 'fields' . $e($base), 'foto_nested' . $e($base), 'hapus_foto_nested' . $e($base)) ?>
                    <?php elseif (is_scalar($leafVal) || $leafVal === null): ?>
                      <div class="f"><label><?= $e($label($leaf)) ?>
                        <?php if ($isLong($leaf, (string) $leafVal)): ?>
                          <textarea class="field" name="fields<?= $e($base) ?>" rows="2" style="margin-top: 8px"><?= $e((string) $leafVal) ?></textarea>
                        <?php else: ?>
                          <input class="field" type="text" name="fields<?= $e($base) ?>" value="<?= $e((string) $leafVal) ?>" style="margin-top: 8px">
                        <?php endif; ?>
                      </label></div>
                    <?php elseif (is_array($leafVal)): ?>
                      <div class="f"><label><?= $e($label($leaf)) ?> <span class="opt">(satu baris per butir)</span>
                        <textarea class="field" name="fields_lines<?= $e($base) ?>" rows="4" style="margin-top: 8px"><?= $e(implode("\n", array_map(static fn($x) => is_scalar($x) ? (string) $x : '', $leafVal))) ?></textarea>
                      </label></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <div class="f"><label for="ringkasan-ubah">Catatan perubahan <span class="opt">(opsional, untuk riwayat versi)</span></label><input id="ringkasan-ubah" class="field" name="ringkasan_perubahan" type="text" placeholder="Contoh: Foto hero diganti"></div>
      <div class="btn-row" style="padding-top: 18px; border-top: 1px solid var(--line)">
        <button class="btn btn--primary btn--lg" type="submit">Simpan perubahan</button>
        <a class="btn btn--outline btn--lg" href="/operator/konten?bagian=<?= $e($activeKey) ?>">Batalkan perubahan</a>
      </div>
    </form>

    <?php if ($riwayatList): ?>
      <section class="box" aria-labelledby="h-riwayat">
        <div class="box__head"><h2 id="h-riwayat">Riwayat versi</h2><span class="hint">5 versi terakhir disimpan otomatis</span></div>
        <ol class="timeline">
          <?php foreach ($riwayatList as $idx => $ver): ?>
            <li style="grid-template-columns: 10px 1fr auto; align-items: center">
              <span><strong><?= $e($ver['ringkasan'] ?? ($ver['keterangan'] ?? 'Versi sebelumnya')) ?></strong><br><small><?= $e(\App\Controllers\OperatorController::waktuSingkat($ver['waktu'] ?? '')) ?></small></span>
              <form action="/operator/konten/pulihkan" method="post" onsubmit="return confirm('Kembalikan bagian ini ke versi tersebut?')">
                <?= Helpers::csrfField() ?><input type="hidden" name="kunci" value="<?= $e($activeKey) ?>"><input type="hidden" name="versi_index" value="<?= (int) $idx ?>">
                <button class="btn btn--soft btn--sm" type="submit">Kembalikan</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var placeholderSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="m21 16-5-5-8 9"></path></svg>';

  document.querySelectorAll('.js-photo-uploader').forEach(function (box) {
    var fileInput = box.querySelector('.js-photo-input');
    var preview = box.querySelector('.js-photo-preview');
    var statusEl = box.querySelector('.js-photo-status');
    var btnLabel = box.querySelector('.js-photo-btn-label');
    var removeBtn = box.querySelector('.js-photo-remove');
    var deleteFlag = box.querySelector('.js-photo-delete-flag');
    var currentVal = box.querySelector('.js-photo-current');
    var mirrorVal = box.querySelector('.js-photo-current-mirror');
    var placeholderText = box.getAttribute('data-placeholder') || '[Belum ada foto]';

    if (!fileInput || !preview) return;

    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      if (file.size > 5 * 1024 * 1024) {
        fileInput.value = '';
        if (statusEl) {
          statusEl.textContent = 'Ukuran foto melebihi 5 MB. Pilih foto yang lebih kecil.';
          statusEl.style.color = '#B91C1C';
          statusEl.style.fontWeight = '600';
        }
        return;
      }

      var reader = new FileReader();
      reader.onload = function (ev) {
        var dataUrl = ev && ev.target ? ev.target.result : '';
        if (!dataUrl) return;

        var img = new Image();
        img.alt = 'Pratinjau foto baru';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';

        img.onload = function () {
          preview.innerHTML = '';
          preview.appendChild(img);
        };
        img.onerror = function () {
          preview.innerHTML = placeholderSvg + '<span>' + placeholderText + '</span>';
        };
        img.src = dataUrl;
      };
      reader.readAsDataURL(file);

      if (deleteFlag) deleteFlag.value = '0';
      if (btnLabel) btnLabel.textContent = 'Ganti foto';
      if (removeBtn) removeBtn.style.display = 'inline-flex';
      if (statusEl) {
        statusEl.textContent = 'File dipilih: ' + file.name + ' — Klik "Simpan perubahan" untuk menayangkan.';
        statusEl.style.color = '#15803D';
        statusEl.style.fontWeight = '600';
      }
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        fileInput.value = '';
        if (deleteFlag) deleteFlag.value = '1';
        if (currentVal) currentVal.value = '';
        if (mirrorVal) mirrorVal.value = '';
        preview.innerHTML = placeholderSvg + '<span>' + placeholderText + '</span>';
        removeBtn.style.display = 'none';
        if (btnLabel) btnLabel.textContent = 'Pilih foto';
        if (statusEl) {
          statusEl.textContent = 'Foto akan dihapus setelah Anda klik "Simpan perubahan".';
          statusEl.style.color = '#B91C1C';
          statusEl.style.fontWeight = '600';
        }
      });
    }
  });
});
</script>

