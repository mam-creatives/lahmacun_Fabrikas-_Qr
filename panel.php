<?php
/*
 * QR Sayaç — İstatistik paneli ve QR üretici
 * Adres: https://alanadiniz.com/qr/panel.php
 */
declare(strict_types=1);

$c = require __DIR__ . '/config.php';
date_default_timezone_set($c['saat_dilimi']);

session_name('qrpanel');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$varsayilanSifre = ($c['panel_sifre'] === 'BURAYI-DEGISTIRIN' || $c['panel_sifre'] === '');
$hata = '';

if (isset($_GET['cikis'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: panel.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sifre'])) {
    if ($varsayilanSifre) {
        $hata = 'Önce config.php içindeki panel_sifre alanını değiştirin.';
    } elseif (hash_equals((string)$c['panel_sifre'], (string)$_POST['sifre'])) {
        session_regenerate_id(true);
        $_SESSION['giris'] = true;
        header('Location: panel.php');
        exit;
    } else {
        sleep(1);
        $hata = 'Şifre yanlış.';
    }
}

$girisli = !empty($_SESSION['giris']) && !$varsayilanSifre;
$dosya   = __DIR__ . '/data/taramalar.csv';

// CSV indirme
if ($girisli && isset($_GET['indir'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="qr-taramalar-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF" . "tarih,kaynak,cihaz,ziyaretci\n";
    if (is_file($dosya)) readfile($dosya);
    exit;
}

if (!$girisli) {
    ?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>QR Panel — Giriş</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f3ee;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#2a2420}
form{background:#fff;padding:32px;border-radius:14px;box-shadow:0 2px 20px rgba(0,0,0,.06);width:min(320px,90vw)}
h1{font-size:20px;margin:0 0 18px}
input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:16px;margin-bottom:12px}
button{width:100%;padding:12px;border:0;border-radius:8px;background:#b5451b;color:#fff;font-size:16px;cursor:pointer}
.hata{color:#b00020;font-size:14px;margin-bottom:12px}
</style></head><body>
<form method="post">
  <h1>QR Sayaç Paneli</h1>
  <?php if ($varsayilanSifre): ?><div class="hata">Panel şifresi henüz ayarlanmamış. config.php dosyasında panel_sifre alanını değiştirin.</div><?php endif; ?>
  <?php if ($hata): ?><div class="hata"><?= h($hata) ?></div><?php endif; ?>
  <input type="password" name="sifre" placeholder="Şifre" autofocus required>
  <button type="submit">Giriş</button>
</form>
</body></html><?php
    exit;
}

// ---- Verileri oku ----
$etiketler = $c['kaynaklar'] + ['diger' => 'Tanımsız / diğer'];
$bugun     = date('Y-m-d');
$yediGun   = date('Y-m-d', strtotime('-6 days'));

$toplam = 0; $bugunSay = 0; $haftaSay = 0;
$tekil = []; $kaynak = []; $cihaz = []; $gunluk = []; $saatlik = array_fill(0, 24, 0);

foreach (array_keys($c['kaynaklar']) as $key) {
    $kaynak[$key] = ['toplam' => 0, 'tekil' => [], 'bugun' => 0, 'son' => ''];
}

if (is_file($dosya) && ($fp = fopen($dosya, 'rb'))) {
    while (($r = fgetcsv($fp, 0, ',', '"', '')) !== false) {
        if (count($r) < 4) continue;
        [$t, $k, $d, $z] = $r;
        $gun = substr($t, 0, 10);
        $toplam++;
        $tekil[$z] = true;
        if ($gun === $bugun) $bugunSay++;
        if ($gun >= $yediGun) $haftaSay++;
        if (!isset($kaynak[$k])) $kaynak[$k] = ['toplam' => 0, 'tekil' => [], 'bugun' => 0, 'son' => ''];
        $kaynak[$k]['toplam']++;
        $kaynak[$k]['tekil'][$z] = true;
        if ($gun === $bugun) $kaynak[$k]['bugun']++;
        if ($t > $kaynak[$k]['son']) $kaynak[$k]['son'] = $t;
        $cihaz[$d] = ($cihaz[$d] ?? 0) + 1;
        $gunluk[$gun] = ($gunluk[$gun] ?? 0) + 1;
        $saatlik[(int)substr($t, 11, 2)]++;
    }
    fclose($fp);
}

// Son 30 gün
$son30 = [];
for ($i = 29; $i >= 0; $i--) {
    $g = date('Y-m-d', strtotime("-$i days"));
    $son30[$g] = $gunluk[$g] ?? 0;
}
$max30   = max(1, max($son30));
$maxSaat = max(1, max($saatlik));
arsort($cihaz);

// QR adresleri
$https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host   = preg_replace('/[^a-z0-9.\-:]/i', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$klasor = rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/') . '/';
$taban  = ($https ? 'https' : 'http') . '://' . $host . $klasor;

$gunAdi = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>QR Sayaç Paneli</title>
<style>
:root{--bg:#f6f3ee;--kart:#fff;--yazi:#2a2420;--soluk:#7a706a;--ana:#b5451b;--cizgi:#ece6df}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--yazi)}
.kap{max-width:1040px;margin:0 auto;padding:24px 16px 60px}
header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
header h1{font-size:22px;margin:0}
header a{color:var(--soluk);font-size:14px;text-decoration:none;margin-left:14px}
header a:hover{color:var(--ana)}
.uyari{background:#fff4e5;border:1px solid #f3d3a5;padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:16px}
.ozet{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.kart{background:var(--kart);border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ozet .sayi{font-size:32px;font-weight:700;line-height:1.1}
.ozet .etk{color:var(--soluk);font-size:13px;margin-top:4px}
h2{font-size:16px;margin:0 0 14px}
.izgara{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:760px){.izgara{grid-template-columns:1fr}}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:9px 6px;border-bottom:1px solid var(--cizgi)}
th{color:var(--soluk);font-weight:500;font-size:12px}
td.s{text-align:right;font-variant-numeric:tabular-nums}th.s{text-align:right}
.grafik{display:flex;align-items:flex-end;gap:3px;height:140px;margin-top:6px}
.grafik .c{flex:1;background:var(--ana);border-radius:3px 3px 0 0;min-height:2px;position:relative;opacity:.85}
.grafik .c:hover{opacity:1}
.grafik .c[data-v="0"]{background:var(--cizgi)}
.ekseni{display:flex;justify-content:space-between;color:var(--soluk);font-size:11px;margin-top:6px}
.cihaz div{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--cizgi);font-size:14px}
.qrlar{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.qr{text-align:center}
.qr .resim{background:#fff;border:1px solid var(--cizgi);border-radius:10px;padding:10px;aspect-ratio:1}
.qr .resim svg{width:100%;height:100%;display:block}
.qr h3{font-size:15px;margin:12px 0 4px}
.qr code{font-size:11px;color:var(--soluk);word-break:break-all;display:block;margin-bottom:10px}
.qr button{border:1px solid var(--cizgi);background:#fff;border-radius:8px;padding:7px 10px;font-size:13px;cursor:pointer;margin:2px}
.qr button:hover{border-color:var(--ana);color:var(--ana)}
.not{color:var(--soluk);font-size:13px;margin-top:12px}
</style></head><body><div class="kap">

<header>
  <h1>QR Sayaç Paneli</h1>
  <div><a href="panel.php">Yenile</a><a href="?indir=1">CSV indir</a><a href="?cikis=1">Çıkış</a></div>
</header>

<?php if ($c['gizli_anahtar'] === 'BURAYA-RASTGELE-UZUN-BIR-METIN-YAZIN'): ?>
<div class="uyari">config.php içindeki <b>gizli_anahtar</b> alanı hâlâ varsayılan değerde. Rastgele uzun bir metinle değiştirin.</div>
<?php endif; ?>
<?php if (!is_writable(__DIR__ . '/data')): ?>
<div class="uyari"><b>data</b> klasörüne yazılamıyor; taramalar kaydedilmez. cPanel Dosya Yöneticisi'nde klasör iznini 755 yapın.</div>
<?php endif; ?>

<section class="ozet">
  <div class="kart"><div class="sayi"><?= number_format($toplam, 0, ',', '.') ?></div><div class="etk">Toplam tarama</div></div>
  <div class="kart"><div class="sayi"><?= number_format(count($tekil), 0, ',', '.') ?></div><div class="etk">Tekil kişi (yaklaşık)</div></div>
  <div class="kart"><div class="sayi"><?= $bugunSay ?></div><div class="etk">Bugün</div></div>
  <div class="kart"><div class="sayi"><?= $haftaSay ?></div><div class="etk">Son 7 gün</div></div>
</section>

<section class="kart" style="margin-bottom:16px">
  <h2>QR noktalarına göre</h2>
  <table>
    <tr><th>Nokta</th><th class="s">Tarama</th><th class="s">Tekil kişi</th><th class="s">Bugün</th><th class="s">Son tarama</th></tr>
    <?php foreach ($kaynak as $key => $v): if ($key === 'diger' && $v['toplam'] === 0) continue; ?>
    <tr>
      <td><?= h($etiketler[$key] ?? $key) ?></td>
      <td class="s"><?= $v['toplam'] ?></td>
      <td class="s"><?= count($v['tekil']) ?></td>
      <td class="s"><?= $v['bugun'] ?></td>
      <td class="s"><?= $v['son'] ? h(date('d.m.Y H:i', strtotime($v['son']))) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</section>

<div class="izgara">
  <section class="kart">
    <h2>Son 30 gün</h2>
    <div class="grafik">
      <?php foreach ($son30 as $g => $v): $ts = strtotime($g); ?>
      <div class="c" data-v="<?= $v ?>" style="height:<?= round($v / $max30 * 100) ?>%" title="<?= h(date('d.m', $ts) . ' ' . $gunAdi[(int)date('w', $ts)] . ': ' . $v . ' tarama') ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="ekseni"><span><?= h(date('d.m', strtotime(array_key_first($son30)))) ?></span><span>Bugün</span></div>
  </section>
  <section class="kart cihaz">
    <h2>Cihaz</h2>
    <?php if (!$cihaz): ?><p class="not">Henüz tarama yok.</p><?php endif; ?>
    <?php foreach ($cihaz as $d => $v): ?>
    <div><span><?= h($d) ?></span><b><?= $v ?> <small style="color:var(--soluk);font-weight:400">(%<?= $toplam ? round($v / $toplam * 100) : 0 ?>)</small></b></div>
    <?php endforeach; ?>
  </section>
</div>

<section class="kart" style="margin-bottom:16px">
  <h2>Saatlere göre (tüm zamanlar)</h2>
  <div class="grafik" style="height:100px">
    <?php foreach ($saatlik as $s => $v): ?>
    <div class="c" data-v="<?= $v ?>" style="height:<?= round($v / $maxSaat * 100) ?>%" title="<?= sprintf('%02d:00–%02d:59', $s, $s) ?>: <?= $v ?> tarama"></div>
    <?php endforeach; ?>
  </div>
  <div class="ekseni"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
</section>

<section class="kart">
  <h2>QR kodlar</h2>
  <div class="qrlar">
    <?php foreach ($c['kaynaklar'] as $key => $ad): $url = $taban . '?k=' . rawurlencode($key); ?>
    <div class="qr" data-url="<?= h($url) ?>" data-ad="<?= h($key) ?>">
      <div class="resim"></div>
      <h3><?= h($ad) ?></h3>
      <code><?= h($url) ?></code>
      <button data-tip="svg">SVG indir (baskı)</button>
      <button data-tip="png">PNG indir</button>
      <button data-tip="kopya">Linki kopyala</button>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="not">Matbaaya <b>SVG</b> dosyasını verin; her boyutta net basılır. Yeni nokta eklemek için config.php'deki <i>kaynaklar</i> listesine bir satır ekleyin. Kodu bastırmadan önce telefonla okutup bu panelde sayının arttığını kontrol edin.</p>
</section>

</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>window.qrcode || document.write('<script src="https://unpkg.com/qrcode-generator@1.4.4/qrcode.js"><\/script>');</script>
<script>
(function () {
  if (typeof qrcode !== 'function') {
    document.querySelectorAll('.qr .resim').forEach(function (el) {
      el.innerHTML = '<p style="font-size:13px;color:#b00020;padding:20px">QR kütüphanesi yüklenemedi. İnternet bağlantınızı kontrol edip sayfayı yenileyin.</p>';
    });
    return;
  }
  var KENAR = 4; // QR standardındaki sessiz alan (modül)

  function matris(url) {
    var q = qrcode(0, 'M');
    q.addData(url);
    q.make();
    return q;
  }
  function svgYap(q) {
    var n = q.getModuleCount(), boy = n + KENAR * 2, yol = '';
    for (var r = 0; r < n; r++)
      for (var c = 0; c < n; c++)
        if (q.isDark(r, c)) yol += 'M' + (c + KENAR) + ' ' + (r + KENAR) + 'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + boy + ' ' + boy + '" shape-rendering="crispEdges">' +
           '<rect width="100%" height="100%" fill="#fff"/><path d="' + yol + '" fill="#000"/></svg>';
  }
  function pngYap(q, cb) {
    var n = q.getModuleCount(), boy = n + KENAR * 2, olcek = Math.ceil(2000 / boy);
    var cv = document.createElement('canvas');
    cv.width = cv.height = boy * olcek;
    var x = cv.getContext('2d');
    x.fillStyle = '#fff'; x.fillRect(0, 0, cv.width, cv.height);
    x.fillStyle = '#000';
    for (var r = 0; r < n; r++)
      for (var c = 0; c < n; c++)
        if (q.isDark(r, c)) x.fillRect((c + KENAR) * olcek, (r + KENAR) * olcek, olcek, olcek);
    cv.toBlob(cb, 'image/png');
  }
  function indir(blob, ad) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = ad;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  }

  document.querySelectorAll('.qr').forEach(function (kutu) {
    var url = kutu.dataset.url, ad = 'qr-' + kutu.dataset.ad, q = matris(url), svg = svgYap(q);
    kutu.querySelector('.resim').innerHTML = svg;
    kutu.addEventListener('click', function (e) {
      var tip = e.target.dataset && e.target.dataset.tip;
      if (tip === 'svg') indir(new Blob([svg], { type: 'image/svg+xml' }), ad + '.svg');
      if (tip === 'png') pngYap(q, function (b) { indir(b, ad + '.png'); });
      if (tip === 'kopya') {
        var btn = e.target;
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
          btn.textContent = 'Kopyalandı ✓';
        }, function () { window.prompt('Linki kopyalayın:', url); });
        setTimeout(function () { btn.textContent = 'Linki kopyala'; }, 1500);
      }
    });
  });
})();
</script>
</body></html>
