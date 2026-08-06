<?php

declare(strict_types=1);

/**
 * Sinh public/assets/icons/sprite.svg tu nguon duy nhat
 * themes/default/views/admin/partials/_icon_paths.php - chay lai script nay MOI KHI them/sua/xoa
 * icon trong _icon_paths.php (giong quy uoc "npm run build:admin" cho admin.css tu tailwind.css).
 * File sprite la static asset (khong sinh dong theo request) de trinh duyet CACHE duoc sau lan
 * tai dau tien - xem giai thich day du o themes/default/views/admin/partials/icon.php.
 */
$basePath = dirname(__DIR__);
$iconPathsFile = $basePath . '/themes/default/views/admin/partials/_icon_paths.php';
$outputFile = $basePath . '/public/assets/icons/sprite.svg';

$icons = require $iconPathsFile;

/**
 * Gradient vang kim dung chung (Design Audit Phase 25, Executive Panel) - cac icon KPI/trang
 * trong "kpi-*" va "star" tu tham chieu fill="url(#icon-gold-fill)" ngay trong path data cua
 * chung (xem _icon_paths.php) thay vi currentColor - luon vang kim co dinh du dat trong ngu canh
 * mau nao (khac 27 icon chuc nang con lai, van dung currentColor de doi mau theo trang thai
 * active/hover/danger). <defs> nam 1 lan duy nhat o dau sprite, moi <symbol> tham chieu chung id.
 */
$svg = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">'
    . '<defs><linearGradient id="icon-gold-fill" x1="0" y1="0" x2="1" y2="1">'
    . '<stop offset="0" stop-color="#f6e3a3"/>'
    . '<stop offset="0.5" stop-color="#d4af37"/>'
    . '<stop offset="1" stop-color="#8a6f2a"/>'
    . '</linearGradient></defs>';

foreach ($icons as $key => $paths) {
    $svg .= '<symbol id="icon-' . $key . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
}

$svg .= '</svg>';

$outputDir = dirname($outputFile);

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Khong the tao thu muc: {$outputDir}\n");
    exit(1);
}

file_put_contents($outputFile, $svg);

echo 'Da sinh ' . $outputFile . ' (' . strlen($svg) . ' bytes, ' . count($icons) . " icon)\n";
