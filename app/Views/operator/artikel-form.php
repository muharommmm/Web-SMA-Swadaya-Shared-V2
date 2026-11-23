<?php
use App\Core\Helpers;

$article = $article ?? [];
$categories = $categories ?? [];
$isEdit = !empty($article['id']);
// Draf dibuka dengan pilihan "Terbitkan sekarang"; menyimpan draf lewat tombol Simpan draf
$statusVal = ($article['status'] ?? '') === 'terjadwal' ? 'terjadwal' : 'terbit';
$publishedAtVal = !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $article['published_at'])) : date('Y-m-d\T08:00', strtotime('+1 day'));
$metaDescVal = $article['meta_description'] ?? ($article['ringkasan'] ?? '');
$isiAwal = $article['isi'] ?? '<p></p>';
?>
<nav class="crumb" aria-label="Breadcrumb"><?= Helpers::icon('chevron-left', 16) ?><a href="/operator/artikel">Berita &amp; Artikel</a><span aria-hidden="true">/</span><span><?= $isEdit ? 'Ubah artikel' : 'Tulis artikel' ?></span></nav>

<form id="form-artikel" class="cols" action="/operator/artikel/simpan" method="post" enctype="multipart/form-data">
  <?= Helpers::csrfField() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $article['id'] ?>"><?php endif; ?>

  <section class="box" aria-label="Isi artikel" style="gap: 20px">
    <div class="f">
      <label for="judul">Judul</label>
      <input id="judul" class="field" name="judul" type="text" required value="<?= Helpers::e($article['judul'] ?? '') ?>" placeholder="Ketik judul artikel..." style="min-height: 56px; font-size: 21px; font-weight: 700">
      <span class="hint">Alamat halaman: <?= Helpers::e(parse_url(Helpers::fullUrl('/'), PHP_URL_HOST)) ?>/berita/<input id="slug" name="slug" type="text" value="<?= Helpers::e($article['slug'] ?? '') ?>" aria-label="Alamat halaman (slug)" style="border: 0; border-bottom: 1px dashed var(--line-2); font: inherit; font-weight: 700; color: var(--accent-dark); min-width: 200px; width: 60%; padding: 2px 0; background: transparent"></span>
    </div>

    <div class="f">
      <span class="label">Foto sampul</span>
      <div class="photo-field" style="align-items: stretch">
        <div id="cover-preview-box" class="js-photo-preview" style="width: 100%; max-width: 420px; aspect-ratio: 16 / 9; border: 1.5px dashed #A9B0DA">
          <?php if (!empty($article['gambar_sampul'])): ?>
            <img src="<?= Helpers::e($article['gambar_sampul']) ?>" alt="<?= Helpers::e($article['alt_gambar'] ?? '') ?>">
          <?php else: ?>
            <?= Helpers::icon('image', 28) ?><span>Belum ada foto sampul</span>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px; flex: 1; min-width: 220px">
          <input type="hidden" id="hapus-gambar-sampul" name="hapus_gambar_sampul" value="0">
          <div class="btn-row" style="gap: 8px">
            <label class="btn btn--soft btn--sm upload__btn"><span id="cover-btn-label"><?= !empty($article['gambar_sampul']) ? 'Ganti foto' : 'Pilih foto' ?></span><input id="input-gambar-sampul" class="sr-file" type="file" name="gambar_sampul" accept=".jpg,.jpeg,.png,.webp" aria-label="Pilih foto sampul"></label>
            <button type="button" id="btn-remove-cover" class="btn btn--danger-outline btn--sm"<?= empty($article['gambar_sampul']) ? ' style="display: none"' : '' ?>>Hapus foto</button>
          </div>
          <span id="cover-status-text" class="hint">JPG, PNG, atau WebP, maks. 5 MB. Ukuran disesuaikan otomatis.</span>
          <div class="f"><label for="alt">Teks alt foto</label><input id="alt" class="field" name="alt_gambar" type="text" value="<?= Helpers::e($article['alt_gambar'] ?? '') ?>" placeholder="Gambarkan isi foto untuk pembaca layar"></div>
        </div>
      </div>
    </div>

    <div class="f">
      <span class="label" id="isi-label">Isi artikel</span>
      <div style="border: 1px solid var(--line-2); border-radius: 16px; overflow: hidden">
        <div role="toolbar" aria-label="Format teks" style="display: flex; flex-wrap: wrap; gap: 4px; padding: 10px 12px; background: #F6F7FC; border-bottom: 1px solid var(--line)">
          <button class="tool btn btn--sm" type="button" data-cmd="bold" title="Tebal" style="background: transparent; color: var(--accent-dark)"><b>B</b></button>
          <button class="tool btn btn--sm" type="button" data-cmd="italic" title="Miring" style="background: transparent; color: var(--accent-dark)"><i>I</i></button>
          <button class="tool btn btn--sm" type="button" data-cmd="formatBlock" data-val="H2" title="Subjudul" style="background: transparent; color: var(--accent-dark)">H2</button>
          <button class="tool btn btn--sm" type="button" data-cmd="formatBlock" data-val="H3" title="Subjudul kecil" style="background: transparent; color: var(--accent-dark)">H3</button>
          <button class="tool btn btn--sm" type="button" data-cmd="insertUnorderedList" title="Daftar berpoin" style="background: transparent; color: var(--accent-dark)">• List</button>
          <button class="tool btn btn--sm" type="button" data-cmd="insertOrderedList" title="Daftar bernomor" style="background: transparent; color: var(--accent-dark)">1. List</button>
          <button class="tool btn btn--sm" type="button" data-cmd="formatBlock" data-val="BLOCKQUOTE" title="Kutipan" style="background: transparent; color: var(--accent-dark)">“ ”</button>
          <button class="btn btn--sm" type="button" id="btn-insert-link" title="Sisipkan tautan" style="background: transparent; color: var(--accent-dark)">Link</button>
        </div>
        <div id="rich-editor" contenteditable="true" aria-labelledby="isi-label" aria-multiline="true" role="textbox" class="prose" style="min-height: 380px; padding: 20px 22px; outline: none"><?= Helpers::sanitizeRichHtml($isiAwal) ?></div>
        <textarea id="isi-hidden" name="isi" class="sr-only" tabindex="-1" aria-hidden="true"><?= Helpers::e($isiAwal) ?></textarea>
      </div>
    </div>
  </section>

  <div class="stack-20">
    <section class="box" aria-labelledby="h-terbit">
      <h2 id="h-terbit">Penerbitan</h2>
      <fieldset class="radio-cards" style="border: 0; margin: 0; padding: 0">
        <legend class="sr-only">Waktu tayang</legend>
        <label class="radio-card status-radio-card"><input type="radio" name="status" value="terbit"<?= $statusVal === 'terbit' ? ' checked' : '' ?>><span><strong>Terbitkan sekarang</strong><small>Langsung tampil di halaman Berita</small></span></label>
        <label class="radio-card status-radio-card"><input type="radio" name="status" value="terjadwal"<?= $statusVal === 'terjadwal' ? ' checked' : '' ?>><span><strong>Jadwalkan</strong><small>Tampil otomatis pada waktu yang dipilih</small></span></label>
      </fieldset>
      <div id="jadwal-picker-wrap" class="f" style="display: <?= $statusVal === 'terjadwal' ? 'flex' : 'none' ?>"><label for="tgl-terbit">Tanggal dan jam tayang</label><input id="tgl-terbit" class="field" name="published_at" type="datetime-local" value="<?= Helpers::e($publishedAtVal) ?>"></div>
      <button id="btn-submit-main" class="btn btn--primary btn--lg" type="submit" style="width: 100%"><?= $statusVal === 'terjadwal' ? 'Jadwalkan' : 'Terbitkan' ?></button>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
        <button class="btn btn--outline" type="submit" name="QuickAction" value="save_draft" formnovalidate>Simpan draf</button>
        <?php if ($isEdit && !empty($article['slug'])): ?>
          <a class="btn btn--outline" href="/berita/<?= Helpers::e($article['slug']) ?>?preview=1" target="_blank">Pratinjau</a>
        <?php else: ?>
          <span class="btn btn--outline" aria-disabled="true" title="Simpan draf dulu untuk pratinjau" style="opacity: .5">Pratinjau</span>
        <?php endif; ?>
      </div>
      <span id="autosave-indicator" class="hint" style="text-align: center"><?= $isEdit ? 'Terakhir disimpan ' . Helpers::e(strtolower(\App\Controllers\OperatorController::waktuSingkat($article['updated_at'] ?? ''))) : 'Belum disimpan' ?></span>
    </section>

    <section class="box" aria-labelledby="h-detail">
      <h2 id="h-detail">Detail</h2>
      <div class="f"><label for="kategori">Kategori</label>
        <select id="kategori" class="field" name="kategori_id">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>"<?= ((int) ($article['kategori_id'] ?? 0)) === (int) $cat['id'] ? ' selected' : '' ?>><?= Helpers::e($cat['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f">
        <label for="meta">Ringkasan untuk Google</label>
        <textarea id="meta" class="field" name="meta_description" rows="4" maxlength="200" aria-describedby="meta-count"><?= Helpers::e($metaDescVal) ?></textarea>
        <span id="meta-count" class="hint"><?= mb_strlen($metaDescVal) ?>/160 karakter</span>
      </div>
      <div style="border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; background: #F6F7FC; display: flex; flex-direction: column; gap: 4px">
        <span class="hint" style="font-weight: 600">Tampilan di hasil pencarian</span>
        <span style="font-size: 13px; color: var(--muted)"><?= Helpers::e(parse_url(Helpers::fullUrl('/'), PHP_URL_HOST)) ?> › berita</span>
        <span id="google-title-preview" style="font-size: 18px; color: #1A0DAB; line-height: 1.3"><?= Helpers::e(($article['judul'] ?? '') ?: 'Judul artikel Anda') ?></span>
        <span id="google-desc-preview" style="font-size: 13.5px; color: var(--muted); line-height: 1.5"><?= Helpers::e($metaDescVal ?: 'Ringkasan artikel akan tampil di hasil pencarian Google.') ?></span>
      </div>
    </section>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('form-artikel');
  var editor = document.getElementById('rich-editor');
  var hiddenIsi = document.getElementById('isi-hidden');
  var judulInput = document.getElementById('judul');
  var slugInput = document.getElementById('slug');
  var metaInput = document.getElementById('meta');
  var metaCount = document.getElementById('meta-count');
  var gTitle = document.getElementById('google-title-preview');
  var gDesc = document.getElementById('google-desc-preview');

  if (editor && hiddenIsi && form) {
    var syncEditor = function () {
      hiddenIsi.value = editor.innerHTML;
    };
    editor.addEventListener('input', syncEditor);
    form.addEventListener('submit', syncEditor);
  }

  document.querySelectorAll('.tool[data-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-cmd');
      var val = btn.getAttribute('data-val') || null;
      document.execCommand(cmd, false, val);
      if (editor) editor.focus();
    });
  });

  var linkBtn = document.getElementById('btn-insert-link');
  if (linkBtn) {
    linkBtn.addEventListener('click', function () {
      var url = prompt('Masukkan alamat tautan (contoh: https://... atau /pendaftaran):', 'https://');
      if (url) {
        document.execCommand('createLink', false, url);
      }
    });
  }

  if (judulInput && slugInput) {
    var slugManuallyEdited = slugInput.value !== '';
    slugInput.addEventListener('input', function () {
      slugManuallyEdited = slugInput.value !== '';
    });
    judulInput.addEventListener('input', function () {
      if (gTitle) gTitle.textContent = judulInput.value || 'Judul Artikel Anda';
      if (!slugManuallyEdited) {
        slugInput.value = judulInput.value
          .toLowerCase()
          .replace(/[^a-z0-9\s\-]/g, '')
          .trim()
          .replace(/\s+/g, '-')
          .replace(/\-+/g, '-');
      }
    });
  }

  if (metaInput) {
    metaInput.addEventListener('input', function () {
      var len = metaInput.value.length;
      if (metaCount) metaCount.textContent = len + '/160 karakter';
      if (gDesc) gDesc.textContent = metaInput.value || 'Ringkasan artikel akan tampil di hasil pencarian Google.';
    });
  }

  var statusRadios = document.querySelectorAll('input[name="status"]');
  var jadwalWrap = document.getElementById('jadwal-picker-wrap');
  var submitBtn = document.getElementById('btn-submit-main');

  statusRadios.forEach(function (r) {
    r.addEventListener('change', function () {
      statusRadios.forEach(function (item) {
        var card = item.closest('.status-radio-card');
        if (card) {
        }
      });
      if (jadwalWrap) {
        jadwalWrap.style.display = (r.value === 'terjadwal' && r.checked) ? 'flex' : 'none';
      }
      if (submitBtn && r.checked) {
        submitBtn.textContent = r.value === 'terjadwal' ? 'Jadwalkan' : (r.value === 'terbit' ? 'Terbitkan' : 'Simpan dan tutup');
      }
    });
  });

  var coverInput = document.getElementById('input-gambar-sampul');
  var coverPreview = document.getElementById('cover-preview-box');
  var coverBtnLabel = document.getElementById('cover-btn-label');
  var coverRemoveBtn = document.getElementById('btn-remove-cover');
  var coverDeleteFlag = document.getElementById('hapus-gambar-sampul');
  var coverStatus = document.getElementById('cover-status-text');

  if (coverInput && coverPreview) {
    coverInput.addEventListener('change', function () {
      var file = coverInput.files && coverInput.files[0];
      if (!file) return;

      if (file.size > 5 * 1024 * 1024) {
        coverInput.value = '';
        if (coverStatus) {
          coverStatus.textContent = 'Ukuran gambar melebihi 5 MB. Pilih gambar yang lebih kecil.';
          coverStatus.style.color = '#B91C1C';
          coverStatus.style.fontWeight = '600';
        }
        return;
      }

      var reader = new FileReader();
      reader.onload = function (ev) {
        var dataUrl = ev && ev.target ? ev.target.result : '';
        if (!dataUrl) return;

        var img = new Image();
        img.alt = 'Pratinjau gambar sampul';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';

        img.onload = function () {
          coverPreview.innerHTML = '';
          coverPreview.appendChild(img);
        };
        img.onerror = function () {
          coverPreview.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="m21 16-5-5-8 9"></path></svg><span>Pratinjau siap disimpan</span>';
        };
        img.src = dataUrl;
      };
      reader.readAsDataURL(file);

      if (coverDeleteFlag) coverDeleteFlag.value = '0';
      if (coverBtnLabel) coverBtnLabel.textContent = 'Ganti gambar';
      if (coverRemoveBtn) coverRemoveBtn.style.display = 'inline-flex';
      if (coverStatus) {
        coverStatus.textContent = 'File dipilih: ' + file.name + ' — Klik tombol Simpan/Terbitkan untuk menayangkan.';
        coverStatus.style.color = '#15803D';
        coverStatus.style.fontWeight = '600';
      }
    });
  }

  if (coverRemoveBtn && coverPreview) {
    coverRemoveBtn.addEventListener('click', function () {
      if (coverInput) coverInput.value = '';
      if (coverDeleteFlag) coverDeleteFlag.value = '1';
      coverPreview.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="m21 16-5-5-8 9"></path></svg><span>Belum ada gambar</span>';
      coverRemoveBtn.style.display = 'none';
      if (coverBtnLabel) coverBtnLabel.textContent = 'Pilih gambar';
      if (coverStatus) {
        coverStatus.textContent = 'Gambar sampul akan dihapus setelah artikel disimpan.';
        coverStatus.style.color = '#B91C1C';
        coverStatus.style.fontWeight = '600';
      }
    });
  }
});
</script>
