<?php
// Buat ikon PNG PLACEHOLDER untuk manifest PWA (butuh ekstensi GD).
//   php app/scripts/make_icons.php
// Ganti hasilnya dengan ikon dari logo resmi bila sudah ada.
if (PHP_SAPI !== 'cli') { exit; }
$out = __DIR__ . '/../../public_html/assets/img/';
$make = function (int $size, bool $maskable, string $file) use ($out) {
    $s = 4; // supersampling agar tepi halus
    $W = $size * $s;
    $im = imagecreatetruecolor($W, $W);
    $cream = imagecolorallocate($im, 0xFF, 0xF8, 0xEF);
    $teal = imagecolorallocate($im, 0x1E, 0x5B, 0x57);
    imagefill($im, 0, 0, $maskable ? $teal : $cream);
    if (!$maskable) {
        imagefilledellipse($im, $W / 2, $W / 2, (int) ($W * .96), (int) ($W * .96), $teal);
    }
    // Hati krem di tengah (zona aman maskable = 80%).
    $r = $W * ($maskable ? .16 : .19);
    $cx = $W / 2; $cy = $W / 2 + $r * .1;
    imagefilledellipse($im, (int) ($cx - $r * .62), (int) ($cy - $r * .35), (int) ($r * 1.45), (int) ($r * 1.45), $cream);
    imagefilledellipse($im, (int) ($cx + $r * .62), (int) ($cy - $r * .35), (int) ($r * 1.45), (int) ($r * 1.45), $cream);
    imagefilledpolygon($im, [
        (int) ($cx - $r * 1.33), (int) ($cy - $r * .15),
        (int) ($cx + $r * 1.33), (int) ($cy - $r * .15),
        (int) $cx, (int) ($cy + $r * 1.35),
    ], $cream);
    $dst = imagecreatetruecolor($size, $size);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $W, $W);
    imagepng($dst, $out . $file, 9);
    echo "$file\n";
};
$make(192, false, 'icon-192.png');
$make(512, false, 'icon-512.png');
$make(512, true, 'icon-maskable-512.png');
$make(180, true, 'apple-touch-icon.png');
