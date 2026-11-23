# Website SMA Swadaya

Situs sekolah + PPDB online + area admin. PHP native (tanpa framework) untuk shared hosting cPanel.

## Jalankan di lokal

1. Salin `.env.example` menjadi `.env`, isi `APP_ENV=local` dan koneksi MySQL (XAMPP) atau biarkan jatuh ke SQLite.
2. Isi database: `php database/seed-lokal.php` (ulang dari awal: `--reset`).
3. Jalankan: `php -S 127.0.0.1:8080 -t public_html public_html/index.php`
4. Situs: http://127.0.0.1:8080 · Admin: `/operator/login` (kata sandi bawaan `rahasia123`, **wajib diganti**).

## Deploy

Push ke `main` menjalankan `.github/workflows/main.yml` (FTP ke cPanel). Isi secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Di server buat `.env` sendiri (tidak ikut repo) dengan:

- Secret tambahan `FTP_WEB_DIR`: folder web subdomain relatif terhadap direktori akun FTP (mis. `sma.swadaya.sch.id`).
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sma.swadaya.sch.id`
- kredensial database, SMTP, dan kunci Cloudflare Turnstile asli

Database lama (sebelum redesain 21 Nov 2026): jalankan `database/migrations/2026-11-21-redesain-ppdb-alumni.sql` sekali.

## Struktur

`public_html/` (web) · `app/` (controller, view, service) · `database/` (skema, seed, migrasi) · `storage/` (berkas pendaftar, log; tidak ikut repo).
