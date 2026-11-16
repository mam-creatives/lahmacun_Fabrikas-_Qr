<?php
/*
 * QR Sayaç — Ayarlar
 * Bu dosyadaki 2 alanı MUTLAKA değiştirin: panel_sifre ve gizli_anahtar.
 */
return [
    // QR okutulunca gidilecek adres
    'hedef' => 'https://www.instagram.com/lahmacun.fabrikasi/',

    // İstatistik paneline giriş şifresi (değiştirin!)
    'panel_sifre' => 'BURAYI-DEGISTIRIN',

    // Ziyaretçileri anonim saymak için gizli metin. Rastgele, uzun bir şey yazın
    // ve sonra değiştirmeyin (değişirse tekil sayım sıfırdan başlar).
    'gizli_anahtar' => 'BURAYA-RASTGELE-UZUN-BIR-METIN-YAZIN',

    'saat_dilimi' => 'Europe/Istanbul',

    // QR noktaları: anahtar => panelde görünen ad
    // Anahtar sadece küçük harf, rakam ve tire içermeli. Yeni nokta eklemek için bir satır ekleyin.
    'kaynaklar' => [
        'masa'   => 'Masa',
        'paket'  => 'Paket servis',
        'vitrin' => 'Vitrin / kapı',
        'ilan'   => 'El ilanı',
    ],
];
