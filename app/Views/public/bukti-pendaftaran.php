<?php
use App\Controllers\PendaftaranController;
use App\Core\Helpers;
use App\Services\QrCodeGenerator;

$p = $pendaftar ?? [];
$berkasList = $berkasList ?? [];
$token = $p['token_bukti'] ?? '';
$buktiUrl = Helpers::fullUrl('/pendaftaran/bukti/' . $token);
$pdfUrl = '/pendaftaran/bukti/' . $token . '/pdf';
$fotoUrl = '/pendaftaran/bukti/' . $token . '/foto';
$jalurLabel = PendaftaranController::JALUR[$p['jalur'] ?? ''] ?? 'Reguler';
$ortu = static fn(string $nama, string $kerja): string => ($p[$nama] ?? '') !== '' && $p[$nama] !== null
    ? $p[$nama] . (!empty($p[$kerja]) ? ' (' . $p[$kerja] . ')' : '')
    : '-';

$uploaded = [];
foreach ($berkasList as $b) {
    $uploaded[$b['jenis_berkas']] = $b;
}
$docLabels = [
    'pas_foto' => 'Pas foto 3x4',
    'kk' => 'Kartu Keluarga',
    'akta' => 'Akta kelahiran',
    'rapor' => 'Rapor kelas 4–6 atau SKL',
];
if (($p['jalur'] ?? '') === 'prestasi') {
    $docLabels['sertifikat'] = 'Sertifikat prestasi';
}

$waNomor = preg_replace('/\D+/', '', (string) ($kontak['whatsapp_nomor'] ?? ''));
$waConfirmUrl = 'https://wa.me/' . $waNomor . '?text=' . rawurlencode(sprintf(
    "Halo Panitia PPDB SMA Swadaya, saya sudah mengisi formulir pendaftaran online.\nNomor pendaftaran: %s\nNama: %s\nJalur: %s\nMohon informasi verifikasi berkas selanjutnya. Terima kasih.",
    $p['no_registrasi'] ?? '-',
    $p['nama_lengkap'] ?? '-',
    $jalurLabel
));

$rows = [
    'Nama lengkap' => $p['nama_lengkap'] ?? '-',
    'Jalur pendaftaran' => 'Kelas 10, jalur ' . $jalurLabel,
    'NIK / NISN' => ($p['nik'] ?? '-') . ' / ' . (($p['nisn'] ?? '') ?: '-'),
    'Tempat, tanggal lahir' => ($p['tempat_lahir'] ?? '-') . ', ' . Helpers::tanggalPanjang($p['tanggal_lahir'] ?? ''),
    'Jenis kelamin / agama' => (($p['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki') . ' / ' . ($p['agama'] ?? '-'),
    'Asal sekolah' => (($p['sekolah_asal'] ?? '') ?: '-') . (!empty($p['tahun_lulus']) ? ' (lulus ' . $p['tahun_lulus'] . ')' : ''),
    'Alamat' => ($p['alamat'] ?? '') ?: '-',
    'Ayah' => $ortu('nama_ayah', 'pekerjaan_ayah'),
    'Ibu' => $ortu('nama_ibu', 'pekerjaan_ibu'),
    'WhatsApp / email' => ($p['no_wa'] ?? '-') . ' / ' . (($p['email'] ?? '') ?: '-'),
    'Minat ekskul' => ($p['minat_ekskul'] ?? '') ?: '-',
];
?>
<style>
.bukti { display: flex; flex-direction: column; gap: 26px; padding: clamp(22px, 3.4vw, 44px); }
.bukti-kop { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 20px; border-bottom: 2px solid var(--accent); }
.bukti-kop img { width: 56px; height: 56px; border-radius: 50%; }
.bukti-id { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 22px; border-radius: 18px; background: var(--tint); }
.bukti-id__foto { width: 96px; height: 128px; border-radius: 12px; overflow: hidden; background: var(--ph); flex: none; }
.bukti-id__foto img { width: 100%; height: 100%; object-fit: cover; }
.bukti-tabel { width: 100%; border-collapse: collapse; font-size: 14.5px; }
.bukti-tabel th, .bukti-tabel td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; overflow-wrap: anywhere; }
.bukti-tabel th { width: 34%; color: var(--muted-2); font-weight: 600; }
.bukti-berkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
.bukti-berkas div { display: flex; justify-content: space-between; gap: 12px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px; font-size: 14px; }
@media (max-width: 600px) {
  .bukti-tabel th, .bukti-tabel td { display: block; width: auto; }
  .bukti-tabel th { padding-bottom: 0; border-bottom: 0; font-size: 12.5px; }
}
@media print {
  @page { size: A4 portrait; margin: 10mm 12mm; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .hero, .bukti-aksi { display: none !important; }
  .overlap { margin: 0 !important; padding: 0 !important; max-width: none !important; }
  .bukti { box-shadow: none !important; padding: 0 !important; border: 0 !important; gap: 16px; }
}
</style>

<?php
$heroCrumb = 'Bukti pendaftaran';
$heroTitle = 'Bukti pendaftaran PPDB';
$heroSub = 'Cetak atau unduh lembar ini, lalu simpan nomor pendaftaran Anda.';
$heroShort = true;
require dirname(__DIR__) . '/partials/page-hero.php';
?>
<section class="wrap overlap narrow" style="max-width: 920px; padding-bottom: 96px; display: flex; flex-direction: column; gap: 20px">
  <div class="panel bukti-aksi btn-row" style="justify-content: space-between; align-items: center; padding: 18px 22px">
    <div class="btn-row" style="gap: 10px">
      <button class="btn btn--primary" type="button" onclick="window.print()"><?= Helpers::icon('print') ?>Cetak (A4)</button>
      <a class="btn btn--outline" href="<?= Helpers::e($pdfUrl) ?>"><?= Helpers::icon('download') ?>Unduh PDF</a>
    </div>
    <?php if ($waNomor !== ''): ?>
      <a class="btn btn--wa" href="<?= Helpers::e($waConfirmUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Helpers::icon('wa') ?>Konfirmasi ke panitia</a>
    <?php endif; ?>
  </div>

  <article class="panel bukti" aria-label="Lembar bukti pendaftaran">
    <div class="bukti-kop">
      <div style="display: flex; align-items: center; gap: 14px">
        <img src="/assets/img/logo.png" width="56" height="56" alt="Logo SMA Swadaya">
        <div style="display: flex; flex-direction: column; gap: 2px">
          <span class="eyebrow" style="font-size: 12px">Sekolah Menengah Atas</span>
          <strong style="font-size: 22px">SMA SWADAYA</strong>
          <span style="font-size: 13px; color: var(--muted)">NPSN <?= Helpers::e($kontak['npsn'] ?? '-') ?> · <?= Helpers::e($kontak['alamat_lengkap'] ?? '') ?></span>
        </div>
      </div>
      <div style="font-size: 13px; color: var(--muted); text-align: right">
        <div>Email: <?= Helpers::e($kontak['email'] ?? '') ?></div>
        <div>Tahun ajaran <?= Helpers::e($p['tahun_ajaran'] ?? '') ?></div>
      </div>
    </div>

    <div class="bukti-id">
      <div style="display: flex; flex-direction: column; gap: 6px">
        <span style="font-size: 13px; color: var(--muted-2)">Nomor pendaftaran</span>
        <strong style="font-size: clamp(24px, 3vw, 32px); letter-spacing: .02em; color: var(--accent-dark)"><?= Helpers::e($p['no_registrasi'] ?? '-') ?></strong>
        <span style="font-size: 13.5px; color: var(--muted)">Dikirim <?= Helpers::e(Helpers::tanggalPanjang($p['created_at'] ?? '')) ?> · Status: <strong>Menunggu verifikasi berkas</strong></span>
      </div>
      <div style="display: flex; gap: 14px; align-items: center">
        <?php if (isset($uploaded['pas_foto'])): ?>
          <span class="bukti-id__foto"><img src="<?= Helpers::e($fotoUrl) ?>" alt="Pas foto <?= Helpers::e($p['nama_lengkap'] ?? '') ?>"></span>
        <?php endif; ?>
        <span style="background: #FFFFFF; border-radius: 12px; padding: 6px; line-height: 0"><?= QrCodeGenerator::renderSvg($buktiUrl, 100, 1) ?></span>
      </div>
    </div>

    <div>
      <h2 class="eyebrow" style="margin-bottom: 10px">A. Data calon siswa</h2>
      <table class="bukti-tabel">
        <tbody>
          <?php foreach ($rows as $label => $nilai): ?>
            <tr><th scope="row"><?= Helpers::e($label) ?></th><td><?= Helpers::e((string) $nilai) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div>
      <h2 class="eyebrow" style="margin-bottom: 10px">B. Kelengkapan berkas</h2>
      <div class="bukti-berkas">
        <?php foreach ($docLabels as $key => $label): ?>
          <?php $b = $uploaded[$key] ?? null; ?>
          <div>
            <span style="display: flex; gap: 8px; align-items: center; font-weight: 600"><?= $b ? '<span style="color: #15803D; display: flex">' . Helpers::icon('check', 16) . '</span>' : '—' ?><?= Helpers::e($label) ?></span>
            <span style="color: var(--muted-2)"><?= $b ? (int) $b['ukuran_kb'] . ' KB' : 'Belum ada' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="padding: 16px 18px; border-radius: 14px; background: var(--soft); font-size: 14px; line-height: 1.6">
      <strong>Langkah selanjutnya</strong>
      <ol style="margin: 6px 0 0; padding-left: 20px">
        <li>Simpan nomor pendaftaran <strong><?= Helpers::e($p['no_registrasi'] ?? '-') ?></strong> atau cetak lembar ini.</li>
        <li>Panitia PPDB memverifikasi berkas dan menghubungi Anda lewat WhatsApp <strong><?= Helpers::e($p['no_wa'] ?? '-') ?></strong>.</li>
        <li>Bila ada dokumen asli yang perlu dibawa saat daftar ulang, panitia akan menginformasikan jadwalnya.</li>
      </ol>
    </div>

    <p style="font-size: 12.5px; color: var(--muted-2); overflow-wrap: anywhere">Bukti pendaftaran diterbitkan secara elektronik oleh sistem PPDB SMA Swadaya. Tautan verifikasi: <?= Helpers::e($buktiUrl) ?></p>
  </article>
</section>
