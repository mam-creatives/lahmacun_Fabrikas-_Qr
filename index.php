<?php
/*
 * QR Sayaç — Yönlendirme
 * QR kod bu adrese gelir: https://alanadiniz.com/qr/?k=masa
 * Taramayı kaydeder, ardından kişiyi Instagram'a yönlendirir.
 */
declare(strict_types=1);

$c = require __DIR__ . '/config.php';
date_default_timezone_set($c['saat_dilimi']);

$k = strtolower(trim((string)($_GET['k'] ?? '')));
if (!isset($c['kaynaklar'][$k])) {
    $k = 'diger';
}

$ua     = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Botları ve link önizlemelerini (WhatsApp, Telegram vb.) sayma
$bot = $ua === ''
    || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|skype|curl|wget|python|headless|monitor/i', $ua);

if (!$bot && $method === 'GET') {
    if (preg_match('/iphone|ipad|ipod/i', $ua)) {
        $cihaz = 'iOS';
    } elseif (preg_match('/android/i', $ua)) {
        $cihaz = 'Android';
    } else {
        $cihaz = 'Diğer';
    }

    // IP adresi saklanmaz; sadece tekil kişi sayımı için geri çevrilemez bir özet tutulur
    $ip        = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ziyaretci = substr(hash_hmac('sha256', $ip . '|' . $ua, (string)$c['gizli_anahtar']), 0, 16);

    $dosya = __DIR__ . '/data/taramalar.csv';
    if ($fp = @fopen($dosya, 'ab')) {
        if (flock($fp, LOCK_EX)) {
            fputcsv($fp, [date('Y-m-d H:i:s'), $k, $cihaz, $ziyaretci], ',', '"', '');
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// Her taramanın sunucuya gelmesi için önbelleğe alınmasın
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('Location: ' . $c['hedef'], true, 302);
exit;
