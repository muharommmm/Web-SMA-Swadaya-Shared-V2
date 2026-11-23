/**
 * SMA Swadaya · Interaktivitas situs publik (vanilla JS, tanpa dependensi)
 * Semua konten tetap tampil tanpa JS; skrip ini hanya menambah kenyamanan.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  // 1. Menu HP: sheet di bawah header, tutup dengan Escape atau saat tautan dipilih
  var menuBtn = $('.menu-btn');
  var menu = $('#menu-utama');
  if (menuBtn && menu) {
    var setMenu = function (open) {
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
      menu.classList.toggle('is-open', open);
    };
    menuBtn.addEventListener('click', function () { setMenu(menuBtn.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.classList.contains('is-open')) { setMenu(false); menuBtn.focus(); }
    });
    $$('a', menu).forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
  }

  // 2. Hero beranda: kata berganti tiap 2,6 detik
  var words = $$('[data-words] .word');
  if (words.length > 1 && !reduceMotion) {
    var w = 0;
    setInterval(function () {
      var prev = words[w];
      w = (w + 1) % words.length;
      words.forEach(function (el) { el.classList.remove('is-out'); });
      prev.classList.remove('is-on');
      prev.classList.add('is-out');
      prev.setAttribute('aria-hidden', 'true');
      words[w].classList.add('is-on');
      words[w].removeAttribute('aria-hidden');
    }, 2600);
  }

  // 3. Galeri: tombol geser (HP cukup diusap)
  var carousel = $('[data-carousel]');
  if (carousel) {
    var prevBtn = $('[data-carousel-prev]');
    var nextBtn = $('[data-carousel-next]');
    var step = function () {
      var item = $('.carousel__item', carousel);
      return item ? item.getBoundingClientRect().width + 24 : 360;
    };
    var syncNav = function () {
      if (!prevBtn || !nextBtn) return;
      prevBtn.disabled = carousel.scrollLeft <= 4;
      nextBtn.disabled = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 4;
    };
    if (prevBtn) prevBtn.addEventListener('click', function () { carousel.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
    if (nextBtn) nextBtn.addEventListener('click', function () { carousel.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
    carousel.addEventListener('scroll', syncNav, { passive: true });
    window.addEventListener('resize', syncNav);
    syncNav();
  }

  // 4. CTA beranda: tab Konsultasi / Isi Form Pendaftaran
  var tabWrap = $('[data-cta-tabs]');
  if (tabWrap) {
    var tabs = $$('[role="tab"]', tabWrap);
    var pick = function (tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { pick(t); });
      t.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
          pick(tabs[(i + (ev.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length], true);
        }
      });
    });
  }

  // 5. Filter chip (ekstrakurikuler & alumni) tanpa memuat ulang halaman
  $$('[data-filter-group]').forEach(function (group) {
    var name = group.dataset.filterGroup;
    var items = $$('[data-filter-item="' + name + '"]');
    var count = $('[data-filter-count="' + name + '"]');
    $$('[data-filter]', group).forEach(function (chip) {
      chip.addEventListener('click', function () {
        var val = chip.dataset.filter;
        var shown = 0;
        $$('[data-filter]', group).forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
        items.forEach(function (it) {
          var show = !val || it.dataset.kategori === val;
          it.hidden = !show;
          if (show) shown++;
        });
        if (count) count.textContent = shown + ' kegiatan';
      });
    });
  });

  // 6. Peta Profil dimuat saat hampir terlihat (halaman tetap cepat)
  var mapBox = document.getElementById('lazy-gmaps');
  if (mapBox && mapBox.dataset.src) {
    var loadMap = function () {
      if (mapBox.dataset.loaded) return;
      mapBox.dataset.loaded = '1';
      var iframe = document.createElement('iframe');
      iframe.src = mapBox.dataset.src;
      iframe.title = 'Lokasi SMA Swadaya di Google Maps';
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'no-referrer-when-downgrade';
      mapBox.appendChild(iframe);
    };
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        if (entries.some(function (e) { return e.isIntersecting; })) { loadMap(); io.disconnect(); }
      }, { rootMargin: '200px' });
      io.observe(mapBox);
    } else {
      window.addEventListener('load', loadMap);
    }
  }

  // ---------- Formulir PPDB ----------
  var form = $('form.pendaftaran-form');
  if (!form) return;

  // 7. Sertifikat prestasi tampil & wajib hanya untuk jalur Prestasi (aturan = UploadService::validateUploads)
  var syncJalur = function () {
    var jalur = (form.querySelector('input[name="jalur"]:checked') || {}).value;
    $$('[data-req-row="prestasi"]', form).forEach(function (row) {
      var on = jalur === 'prestasi';
      row.hidden = !on;
      var inp = $('input[type="file"]', row);
      if (inp) {
        inp.required = on;
        if (!on) inp.value = '';
      }
    });
  };
  $$('input[name="jalur"]', form).forEach(function (r) { r.addEventListener('change', syncJalur); });
  syncJalur();

  // 8. Baris unggah: ukuran dicek di browser, foto kamera besar dikompres jadi JPEG
  $$('.sr-file[data-upload-row]', form).forEach(function (input) {
    var row = document.getElementById(input.dataset.uploadRow);
    if (!row) return;
    var meta = $('.upload__meta', row);
    var metaDefault = meta ? meta.textContent : '';
    var btnText = $('.upload-btn-label', row);

    var markBad = function (msg) {
      row.classList.remove('is-ok');
      row.classList.add('is-bad');
      if (meta) meta.textContent = msg;
    };
    var markOk = function (file) {
      var kb = Math.max(1, Math.round(file.size / 1024));
      var size = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
      var maxMb = parseFloat(input.dataset.maxMb || '0');
      if (maxMb && file.size > maxMb * 1024 * 1024) {
        input.value = '';
        markBad('Terlalu besar (' + size + '). Maks. ' + maxMb + ' MB, pilih file lain.');
        return;
      }
      row.classList.remove('is-bad');
      row.classList.add('is-ok');
      if (meta) meta.textContent = file.name + ' · ' + size;
      if (btnText) btnText.textContent = 'Ganti';
    };

    input.addEventListener('invalid', function () { markBad('Berkas ini wajib diunggah.'); });
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) {
        row.classList.remove('is-ok', 'is-bad');
        if (meta) meta.textContent = metaDefault;
        return;
      }
      if (!/^image\/(jpeg|png)$/i.test(file.type) || file.size <= 900 * 1024 || typeof DataTransfer === 'undefined') {
        markOk(file);
        return;
      }
      var reader = new FileReader();
      reader.onload = function (ev) {
        var img = new Image();
        img.onload = function () {
          var max = 1600, w = img.width, h = img.height;
          if (w > max || h > max) {
            if (w > h) { h = Math.round(h * max / w); w = max; } else { w = Math.round(w * max / h); h = max; }
          }
          var canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          canvas.getContext('2d').drawImage(img, 0, 0, w, h);
          canvas.toBlob(function (blob) {
            if (blob && blob.size < file.size) {
              var small = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
              var dt = new DataTransfer();
              dt.items.add(small);
              input.files = dt.files;
              markOk(small);
            } else {
              markOk(file);
            }
          }, 'image/jpeg', 0.82);
        };
        img.src = ev.target.result;
      };
      reader.readAsDataURL(file);
    });
  });

  // 9. Alamat bertingkat: provinsi > kota > kecamatan > kelurahan, kode pos terisi otomatis
  var wil = {};
  ['provinsi', 'kota', 'kecamatan', 'kelurahan'].forEach(function (k) { wil[k] = $('[data-wilayah="' + k + '"]', form); });
  if (wil.provinsi) {
    var kodePos = document.getElementById('kode_pos');
    var kecData = [];
    var getJson = function (name) {
      return fetch('/assets/wilayah/' + name + '.json').then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.json();
      });
    };
    // Isi pilihan; nilai lama (setelah gagal validasi / simpanan otomatis) dipulihkan sekali saja
    var fill = function (sel, rows) {
      var first = sel.options[0];
      sel.innerHTML = '';
      sel.appendChild(first);
      rows.forEach(function (r) { sel.appendChild(new Option(r[1], r[0])); });
      sel.disabled = rows.length === 0;
      if (!rows.length) return;
      var want = sel.dataset.selected;
      sel.dataset.selected = '';
      if (want && rows.some(function (r) { return r[0] === want; })) sel.value = want;
    };
    var loadFail = function (sel) { sel.options[0].textContent = 'Gagal memuat data, pilih ulang di atas'; };
    var onKelurahan = function (fromUser) {
      var kec = kecData.filter(function (r) { return r[0] === wil.kecamatan.value; })[0];
      var kel = kec && kec[2].filter(function (r) { return r[0] === wil.kelurahan.value; })[0];
      if (kodePos && fromUser) kodePos.value = kel && kel[2] ? kel[2] : '';
      else if (kodePos && kel && kel[2] && !kodePos.value) kodePos.value = kel[2];
    };
    var onKecamatan = function (fromUser) {
      var kec = kecData.filter(function (r) { return r[0] === wil.kecamatan.value; })[0];
      fill(wil.kelurahan, kec ? kec[2] : []);
      onKelurahan(fromUser);
    };
    var onKota = function (fromUser) {
      var kota = wil.kota.value;
      kecData = [];
      fill(wil.kecamatan, []);
      fill(wil.kelurahan, []);
      onKelurahan(fromUser);
      if (!kota) return;
      getJson(kota).then(function (rows) {
        if (wil.kota.value !== kota) return;
        kecData = rows;
        fill(wil.kecamatan, rows);
        onKecamatan(fromUser);
      }).catch(function () { loadFail(wil.kecamatan); });
    };
    var onProvinsi = function (fromUser) {
      var prov = wil.provinsi.value;
      fill(wil.kota, []);
      onKota(fromUser);
      if (!prov) return;
      getJson(prov).then(function (rows) {
        if (wil.provinsi.value !== prov) return;
        fill(wil.kota, rows);
        onKota(fromUser);
      }).catch(function () { loadFail(wil.kota); });
    };
    wil.provinsi.addEventListener('change', function () { onProvinsi(true); });
    wil.kota.addEventListener('change', function () { onKota(true); });
    wil.kecamatan.addEventListener('change', function () { onKecamatan(true); });
    wil.kelurahan.addEventListener('change', function () { onKelurahan(true); });
    // Dipicu setelah simpanan otomatis dipulihkan, agar data-selected sudah terisi
    form.addEventListener('wilayah:restore', function () {
      getJson('provinsi').then(function (rows) {
        fill(wil.provinsi, rows);
        onProvinsi(false);
      }).catch(function () { loadFail(wil.provinsi); });
    });
  }

  // 10. Simpan otomatis di perangkat (kecuali NIK & berkas); dihapus setelah formulir terkirim
  var saveKey = form.dataset.autosave;
  var store = null;
  try { store = window.localStorage; } catch (e) { store = null; }
  var fieldsToSave = function () {
    return $$('input, select, textarea', form).filter(function (el) {
      return el.name && el.type !== 'file' && el.type !== 'hidden' && el.name !== 'website_url' && !el.hasAttribute('data-no-autosave');
    });
  };
  var hasServerData = form.dataset.errors && form.dataset.errors !== '[]' && form.dataset.errors !== '{}';
  if (store && saveKey) {
    var saved = null;
    try { saved = JSON.parse(store.getItem(saveKey) || 'null'); } catch (e) { saved = null; }
    if (saved && !hasServerData) {
      fieldsToSave().forEach(function (el) {
        if (!(el.name in saved)) return;
        var val = saved[el.name];
        if (el.type === 'checkbox') el.checked = Array.isArray(val) ? val.indexOf(el.value) !== -1 : !!val;
        else if (el.type === 'radio') el.checked = el.value === val;
        else if (el.dataset.wilayah) el.dataset.selected = val;
        else el.value = val;
      });
      syncJalur();
    }
    var save = function () {
      var data = {};
      fieldsToSave().forEach(function (el) {
        if (el.type === 'checkbox' && el.name.slice(-2) === '[]') {
          data[el.name] = data[el.name] || [];
          if (el.checked) data[el.name].push(el.value);
        } else if (el.type === 'checkbox') {
          data[el.name] = el.checked;
        } else if (el.type === 'radio') {
          if (el.checked) data[el.name] = el.value;
        } else {
          data[el.name] = el.value;
        }
      });
      try { store.setItem(saveKey, JSON.stringify(data)); } catch (e) { /* penyimpanan penuh/dimatikan */ }
    };
    form.addEventListener('input', save);
    form.addEventListener('change', save);
  } else {
    var note = $('[data-autosave-note]', form);
    if (note) note.hidden = true;
  }
  form.dispatchEvent(new CustomEvent('wilayah:restore'));

  // 11. Pesan kesalahan server tepat di bawah kolom yang bermasalah
  var fieldErrors = {};
  try { fieldErrors = JSON.parse(form.dataset.errors || '{}') || {}; } catch (e) { fieldErrors = {}; }
  Object.keys(fieldErrors).forEach(function (name) {
    var input = form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + name + '[]"]');
    if (!input) return;
    var anchor = input.dataset.uploadRow ? document.getElementById(input.dataset.uploadRow) : (input.closest('.choice-cards, .radios, .pills-check') || input);
    var msg = document.createElement('p');
    msg.className = 'field-error';
    msg.id = 'err-' + name;
    msg.textContent = fieldErrors[name];
    anchor.insertAdjacentElement('afterend', msg);
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', ((input.getAttribute('aria-describedby') || '') + ' ' + msg.id).trim());
    var clear = function () { msg.remove(); input.removeAttribute('aria-invalid'); };
    input.addEventListener('input', clear, { once: true });
    input.addEventListener('change', clear, { once: true });
  });

  // 12. Langkah 1–4: validasi per langkah, ringkasan di langkah terakhir
  var steps = $$('.step', form);
  var dots = $$('[data-step-dot]', form);
  var btnBack = $('[data-step-back]', form);
  var btnNext = $('[data-step-next]', form);
  var btnSubmit = $('[data-submit-once]', form);
  var current = 1;

  var stepOf = function (el) {
    var s = el.closest('.step');
    return s ? parseInt(s.dataset.step, 10) : 1;
  };
  var reviewRows = function () {
    var text = function (sel) {
      var el = form.querySelector(sel);
      if (!el) return '';
      if (el.tagName === 'SELECT') return el.value ? el.options[el.selectedIndex].text : '';
      return el.value.trim();
    };
    var jalur = (form.querySelector('input[name="jalur"]:checked') || {}).value === 'prestasi' ? 'Prestasi' : 'Reguler';
    var berkas = $$('input[type="file"]', form).filter(function (i) { return i.files && i.files.length; }).length;
    var ortu = [text('#ayah'), text('#ibu')].filter(Boolean).join(' · ');
    return {
      1: [['Jalur', jalur], ['Nama lengkap', text('#nama')], ['NISN', text('#nisn') || '-'], ['Asal sekolah', text('#sd')], ['Kelurahan', text('#kelurahan') || '-']],
      2: [['Orang tua', ortu || '-'], ['WhatsApp', text('#wa')], ['Berkas', berkas + ' berkas siap dikirim']]
    };
  };
  var renderReview = function () {
    var data = reviewRows();
    [1, 2].forEach(function (n) {
      var box = $('[data-review-box="' + n + '"]', form);
      if (!box) return;
      var dl = $('dl', box);
      dl.innerHTML = '';
      data[n].forEach(function (pair) {
        var dt = document.createElement('dt');
        var dd = document.createElement('dd');
        dt.textContent = pair[0];
        dd.textContent = pair[1];
        dl.appendChild(dt);
        dl.appendChild(dd);
      });
      box.hidden = false;
    });
  };
  // Turnstile digambar saat langkah terakhir dibuka (di area tersembunyi widget sering gagal tampil)
  var tsBox = $('.turnstile-lazy', form);
  var renderTurnstile = function () {
    if (!tsBox || tsBox.dataset.done) return;
    if (!window.turnstile) { setTimeout(renderTurnstile, 250); return; }
    tsBox.dataset.done = '1';
    window.turnstile.render(tsBox, { sitekey: tsBox.dataset.sitekey });
  };
  var show = function (n, focus) {
    current = n;
    steps.forEach(function (s) { s.classList.toggle('is-active', parseInt(s.dataset.step, 10) === n); });
    dots.forEach(function (d) {
      var k = parseInt(d.dataset.stepDot, 10);
      d.classList.toggle('is-done', k < n);
      if (k === n) d.setAttribute('aria-current', 'step'); else d.removeAttribute('aria-current');
    });
    if (btnBack) btnBack.hidden = n === 1;
    if (btnNext) btnNext.hidden = n === steps.length;
    if (btnSubmit) btnSubmit.hidden = n !== steps.length;
    if (n === steps.length) { renderReview(); renderTurnstile(); }
    if (focus) {
      var head = $('.step.is-active h2', form);
      form.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
      if (head) { head.tabIndex = -1; head.focus({ preventScroll: true }); }
    }
  };
  var stepValid = function (n) {
    var bad = $$('input, select, textarea', steps[n - 1]).filter(function (el) {
      return !el.disabled && !el.closest('[hidden]') && !el.checkValidity();
    });
    if (n === 2 && !($('#ayah', form).value.trim() || $('#ibu', form).value.trim())) {
      $('#ayah', form).setCustomValidity('Isi nama ayah atau nama ibu (minimal salah satu).');
      bad.unshift($('#ayah', form));
    }
    if (bad.length) { bad[0].reportValidity(); return false; }
    return true;
  };
  ['#ayah', '#ibu'].forEach(function (sel) {
    var el = $(sel, form);
    if (el) el.addEventListener('input', function () { $('#ayah', form).setCustomValidity(''); });
  });

  if (btnNext) btnNext.addEventListener('click', function () { if (stepValid(current)) show(current + 1, true); });
  if (btnBack) btnBack.addEventListener('click', function () { show(current - 1, true); });
  $$('[data-go-step]', form).forEach(function (b) {
    b.addEventListener('click', function () { show(parseInt(b.dataset.goStep, 10), true); });
  });
  // Kolom wajib yang kosong di langkah tersembunyi: buka langkahnya agar browser bisa menunjukkannya
  form.addEventListener('invalid', function (ev) {
    var n = stepOf(ev.target);
    if (n !== current) show(n, false);
  }, true);

  // Gagal validasi server: mulai dari langkah dengan kesalahan pertama
  var firstErr = Object.keys(fieldErrors).map(function (name) {
    return form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + name + '[]"]');
  }).filter(Boolean)[0];
  show(firstErr ? stepOf(firstErr) : 1, false);

  // 13. Tombol kirim tidak bisa diklik dua kali; simpanan otomatis dihapus saat formulir benar-benar dikirim
  if (btnSubmit) {
    var submitHtml = btnSubmit.innerHTML;
    form.addEventListener('submit', function () {
      btnSubmit.disabled = true;
      btnSubmit.setAttribute('aria-busy', 'true');
      btnSubmit.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> Mengirim…';
      if (store && saveKey) store.removeItem(saveKey);
    });
    window.addEventListener('pageshow', function () {
      btnSubmit.disabled = false;
      btnSubmit.removeAttribute('aria-busy');
      btnSubmit.innerHTML = submitHtml;
    });
  }
})();
