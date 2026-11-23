<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Helpers;

/**
 * Cloudflare Turnstile: widget di formulir publik + verifikasi token di server.
 * Widget tampil bila TURNSTILE_SITE_KEY terisi. Verifikasi hanya wajib di produksi (APP_ENV=production),
 * jadi pengembangan lokal tidak terhalang.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function siteKey(): string
    {
        return trim((string) Env::get('TURNSTILE_SITE_KEY', ''));
    }

    /**
     * Apakah server wajib memeriksa token (kunci rahasia ada, produksi, bukan CLI)
     */
    public static function required(): bool
    {
        return trim((string) Env::get('TURNSTILE_SECRET_KEY', '')) !== ''
            && PHP_SAPI !== 'cli'
            && Env::get('APP_ENV', 'production') === 'production';
    }

    /**
     * Kotak verifikasi. $lazy = true: dirender manual lewat app.js saat langkah terakhir dibuka
     * (widget di elemen tersembunyi sering gagal tampil).
     */
    public static function widget(bool $lazy = false): string
    {
        $key = self::siteKey();
        if ($key === '') {
            return '';
        }
        $src = 'https://challenges.cloudflare.com/turnstile/v0/api.js' . ($lazy ? '?render=explicit' : '');
        return '<div class="' . ($lazy ? 'turnstile-lazy' : 'cf-turnstile') . '" data-sitekey="' . Helpers::e($key) . '" data-theme="light" style="min-height: 65px"></div>'
            . '<script src="' . $src . '" async defer></script>';
    }

    /**
     * Periksa token dari formulir (field cf-turnstile-response). True bila tidak wajib atau token sah.
     */
    public static function verify(string $ip = ''): bool
    {
        if (!self::required()) {
            return true;
        }
        $token = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }
        $postData = http_build_query([
            'secret' => trim((string) Env::get('TURNSTILE_SECRET_KEY', '')),
            'response' => $token,
            'remoteip' => $ip,
        ]);
        $res = @file_get_contents(self::VERIFY_URL, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 8,
            ],
        ]));
        if ($res === false) {
            error_log('[Turnstile] Gagal menghubungi Cloudflare (periksa allow_url_fopen / koneksi keluar).');
            return false;
        }
        $json = json_decode($res, true);
        return !empty($json['success']);
    }
}
