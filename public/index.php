<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Config;

// Smoke test tam thoi: xac nhan autoload + Config (instance-based) hoat dong dung.
// File nay se duoc thay the hoan toan boi bootstrap that (Container + Router + Middleware)
// o task CMS-011 - khong echo bat ky gia tri cau hinh nhay cam nao (DB host/password...) ra output.
$config = new Config(__DIR__ . '/../config');

header('Content-Type: text/plain; charset=utf-8');
echo $config->get('app.name') . ' (' . $config->get('app.env') . ')';