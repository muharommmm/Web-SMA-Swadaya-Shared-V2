<?php
use App\Core\Helpers;

$heroCrumb = 'Kebijakan Privasi';
$heroTitle = 'Kebijakan Privasi & Pelindungan Data Pribadi';
$heroSub = 'Kepatuhan terhadap Undang-Undang Pelindungan Data Pribadi (UU No. 27 Tahun 2022) di lingkungan SMA Swadaya.';
$heroShort = true;
require dirname(__DIR__) . '/partials/page-hero.php';
?>
<section class="wrap overlap narrow" style="padding-bottom: 96px">
  <div class="plain-card prose">
    <h2>1. Tujuan Pengumpulan Data</h2>
    <p>Data pribadi dan dokumen kependudukan/akademik (termasuk NIK, NISN, Kartu Keluarga, Akta Kelahiran, Rapor, SKL, dan sertifikat prestasi) yang Anda berikan melalui formulir pendaftaran daring hanya digunakan untuk keperluan administrasi PPDB dan pendataan Dapodik di SMA Swadaya.</p>

    <h2>2. Keamanan Penyimpanan</h2>
    <p>Seluruh berkas yang diunggah disimpan di luar direktori publik server (tidak dapat diakses atau diindeks melalui mesin pencari) dan hanya dapat dibuka oleh petugas operator resmi SMA Swadaya yang berwenang melakukan verifikasi pendaftaran.</p>

    <h2>3. Tidak Dibagikan ke Pihak Komersial</h2>
    <p>SMA Swadaya menjamin tidak pernah menjual, menyewakan, atau membagikan data pribadi pendaftar kepada pihak ketiga komersial mana pun.</p>

    <h2>4. Masa Retensi &amp; Penghapusan Data</h2>
    <p>Apabila calon siswa tidak diterima atau membatalkan pendaftaran, seluruh data pendaftaran beserta berkas dokumen yang telah diunggah akan dihapus secara permanen dari sistem paling lambat <strong>1 (satu) bulan</strong> setelah penutupan periode pendaftaran.</p>

    <h2>5. Hak Subjek Data &amp; Kontak Pengaduan</h2>
    <p>Anda berhak meminta koreksi atau penghapusan data pribadi Anda kapan saja dengan menghubungi tata usaha SMA Swadaya melalui email <a href="mailto:<?= Helpers::e($kontak['email'] ?? 'support@sma.swadaya.sch.id') ?>"><?= Helpers::e($kontak['email'] ?? 'support@sma.swadaya.sch.id') ?></a> atau layanan WhatsApp resmi lembaga.</p>
    <div style="padding-top: 24px; border-top: 1px solid var(--line)">
      <a class="btn btn--primary" href="/pendaftaran">Kembali ke Formulir PPDB</a>
    </div>
  </div>
</section>
