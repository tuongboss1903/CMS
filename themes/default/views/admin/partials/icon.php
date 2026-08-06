<?php
/**
 * Icon dung chung toan Admin - render qua <use href="/assets/icons/sprite.svg#icon-{name}">
 * tham chieu file sprite TINH (khong phai include theo trang) - sinh 1 lan tu chinh
 * _icon_paths.php qua script, xem "php bin/build_icon_sprite.php".
 *
 * Performance fix (2 buoc): (1) truoc day file nay tu emit toan bo <path>/<circle> moi lan goi -
 * 1 trang co ~30-40 luot dung icon khien HTML nang them hang chuc KB do lap markup; (2) thu dau
 * tien dung <symbol> INLINE trong chinh trang (1 lan/trang) van lam TANG dung luong vi luon nhet
 * ca ~32 icon du trang chi dung vai icon. Chuyen sprite ra file .svg tinh duoi day giai quyet ca
 * 2: moi luot goi chi con ~110 byte co dinh (<svg><use>...), va ban than file sprite duoc trinh
 * duyet CACHE sau lan tai dau tien (Last-Modified/ETag qua static file serving) - cac trang sau
 * KHONG tai lai, khac han cach nhet <symbol> vao moi HTML response.
 *
 * Goi qua $this->include('admin.partials.icon', ['name' => 'dashboard', 'class' => 'icon']) -
 * $name khong hop le se khong render gi (khong throw, tranh vo trang neu ai do go sai ten).
 */
$icons = require __DIR__ . '/_icon_paths.php';

$name = $name ?? '';

if (!isset($icons[$name])) {
    return;
}

$class = $this->e((string) ($class ?? 'icon'));
$safeName = $this->e($name);
/* Cache-busting filemtime (dong bo ly do voi admin.css o layouts/main.php) - sprite.svg la static
   file sinh boi bin/build_icon_sprite.php, thieu version se khong len icon moi/sua path icon cu
   ngay tren trinh duyet da tung tai trang truoc do. */
$spriteVersion = @\filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/icons/sprite.svg') ?: \time();
?><svg class="<?= $class ?>" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="/assets/icons/sprite.svg?v=<?= $this->e((string) $spriteVersion) ?>#icon-<?= $safeName ?>"></use></svg>
